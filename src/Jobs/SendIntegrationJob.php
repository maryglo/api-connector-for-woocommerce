<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Jobs;

defined('ABSPATH') || exit;

/**
 * Action Scheduler callback: map resource and POST to the external API (v0.1).
 */
final class SendIntegrationJob
{
	private const MAX_ATTEMPTS = 3;
	private const RETRY_DELAYS = array(60, 300);

	public static function register(): void { add_action(\WooCommerceApiConnector\Plugin::JOB_HOOK, array(self::class, 'run'), 10, 4); }
	public static function enqueue(int $log_id, int $order_id, string $event, bool $is_test): void
	{
		as_enqueue_async_action(\WooCommerceApiConnector\Plugin::JOB_HOOK, array($log_id, $order_id, $event, $is_test), \WooCommerceApiConnector\Plugin::SLUG);
	}

	public static function run(int|string $log_id, int|string $order_id, string $event, int|bool|string $is_test = false): void
	{
		$log_id  = (int) $log_id;
		$order_id = (int) $order_id;
		$is_test = (bool) $is_test;

		$order = wc_get_order($order_id);
		if (! $order) { self::fail($log_id, 'Order no longer exists.', false); return; }
		try { $payload = (new \WooCommerceApiConnector\Mapping\MappingEngine())->map($order, \WooCommerceApiConnector\Settings\Settings::get()['mapping'] ?? array()); }
		catch (\Throwable $error) { self::fail($log_id, $error->getMessage(), false); return; }
		$settings = \WooCommerceApiConnector\Settings\Settings::get();
		$url = (new \WooCommerceApiConnector\Api\ApiClient($settings))->endpoint_url((string) $settings['order_endpoint']);
		$body = (string) wp_json_encode($payload);
		$attempt = \WooCommerceApiConnector\Logging\IntegrationLogger::running($log_id, 'POST', $url, $body);
		if ($attempt <= 0) {
			return;
		}
		$response = (new \WooCommerceApiConnector\Api\ApiClient($settings))->request('POST', $url, $payload);
		if ($response->is_success()) { \WooCommerceApiConnector\Logging\IntegrationLogger::update($log_id, array('status'=>'success','response_code'=>$response->status_code,'response_body'=>$response->body,'error_message'=>'')); return; }
		$retryable = $response->is_transport_error() || 429 === $response->status_code || $response->status_code >= 500;
		self::fail($log_id, $response->is_transport_error() ? $response->error_message : 'HTTP ' . $response->status_code, $retryable, $response->status_code, $response->body, $order_id, $event, $is_test, $attempt);
	}

	private static function fail(int $log_id, string $message, bool $retryable, int $code = 0, string $body = '', int $order_id = 0, string $event = '', bool $is_test = false, int $attempt = 0): void
	{
		$delay = $retryable ? self::retry_delay_for_attempt($attempt) : null;
		$will_retry = null !== $delay;
		\WooCommerceApiConnector\Logging\IntegrationLogger::update($log_id, array('status' => $will_retry ? 'pending' : 'failed', 'response_code' => $code ?: null, 'response_body' => $body, 'error_message' => $message));
		if ($will_retry) {
			as_schedule_single_action(time() + $delay, \WooCommerceApiConnector\Plugin::JOB_HOOK, array($log_id, $order_id, $event, $is_test), \WooCommerceApiConnector\Plugin::SLUG);
		}
	}

	public static function retry_delay_for_attempt(int $attempt): ?int
	{
		if ($attempt <= 0 || $attempt >= self::MAX_ATTEMPTS) {
			return null;
		}

		return self::RETRY_DELAYS[$attempt - 1] ?? null;
	}
}
