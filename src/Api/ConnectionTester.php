<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Api;

use WooCommerceApiConnector\Plugin;
use WooCommerceApiConnector\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Probes the configured API base URL (or health path).
 */
final class ConnectionTester
{
	private const SNIPPET_LENGTH = 200;

	/**
	 * @param array<string, mixed> $settings
	 * @return array{success: bool, code: int, message: string}
	 */
	public static function test(array $settings): array
	{
		$client   = new ApiClient($settings);
		$url      = self::build_url($settings);
		$response = $client->request('HEAD', $url);

		if (self::should_retry_with_get($response)) {
			$response = $client->request('GET', $url);
		}

		if ($response->is_transport_error()) {
			return array(
				'success' => false,
				'code'    => 0,
				'message' => sprintf(
					/* translators: %s: error message from the HTTP API */
					__('Connection failed: %s', 'api-connector-for-woocommerce'),
				$response->error_message
				),
			);
		}

		$code    = $response->status_code;
		$success = $response->is_success();
		$snippet = self::snippet($response->body);

		if ($success) {
			$message = sprintf(
				/* translators: %d: HTTP status code */
				__('Connection succeeded (HTTP %d).', 'api-connector-for-woocommerce'),
				$code
			);
		} else {
			$message = sprintf(
				/* translators: %d: HTTP status code */
				__('Connection failed (HTTP %d).', 'api-connector-for-woocommerce'),
				$code
			);
		}

		if (! $success && '' !== $snippet) {
			$message .= ' ' . sprintf(
				/* translators: %s: truncated response body */
				__('Response: %s', 'api-connector-for-woocommerce'),
				$snippet
			);
		}

		return array(
			'success' => $success,
			'code'    => $code,
			'message' => $message,
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function build_url(array $settings): string
	{
		$base = rtrim((string) ($settings['base_url'] ?? ''), '/');
		$path = (string) ($settings['health_path'] ?? '');

		if ('' === $path) {
			return $base;
		}

		return $base . '/' . ltrim($path, '/');
	}

	/**
	 * @param Response $response
	 */
	private static function should_retry_with_get(Response $response): bool
	{
		if ($response->is_transport_error()) {
			return true;
		}

		$code = $response->status_code;

		if (in_array($code, array(401, 403), true)) {
			return false;
		}

		return $code < 200 || $code >= 300;
	}

	private static function snippet(string $body): string
	{
		$body = trim(wp_strip_all_tags($body));

		if ('' === $body) {
			return '';
		}

		if (strlen($body) > self::SNIPPET_LENGTH) {
			$body = substr($body, 0, self::SNIPPET_LENGTH) . '…';
		}

		return $body;
	}
}
