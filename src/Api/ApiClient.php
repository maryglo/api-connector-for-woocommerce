<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Api;

use WooCommerceApiConnector\Api\Authentication\ApiKeyAuth;
use WooCommerceApiConnector\Api\Authentication\BearerAuth;
use WooCommerceApiConnector\Settings\Sanitizer;

defined('ABSPATH') || exit;

/**
 * Outbound HTTP client (v0.1).
 */
final class ApiClient
{
	private const TIMEOUT = 15;

	private const MAX_RESPONSE_BODY_LENGTH = 65536;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct(private readonly array $settings)
	{
	}

	/**
	 * Sends an authenticated request to an absolute API URL.
	 *
	 * @param array<string, mixed>|null $json_body
	 */
	public function request(string $method, string $url, ?array $json_body = null): Response
	{
		$args = array(
			'method'      => strtoupper($method),
			'timeout'     => self::TIMEOUT,
			'redirection' => 3,
			'sslverify'   => true,
			'headers'     => $this->headers(null !== $json_body),
		);

		if (null !== $json_body) {
			$encoded = wp_json_encode($json_body);

			if (false === $encoded) {
				return new Response(0, '', array(), __('Unable to encode the request body as JSON.', 'woocommerce-api-connector'));
			}

			$args['body'] = $encoded;
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			return new Response(0, '', array(), $response->get_error_message());
		}

		$headers = wp_remote_retrieve_headers($response);
		$headers = is_array($headers) ? $headers : array();

		return new Response(
			(int) wp_remote_retrieve_response_code($response),
			self::truncate_body((string) wp_remote_retrieve_body($response)),
			$headers
		);
	}

	/**
	 * Builds an absolute endpoint URL from the configured base URL and a relative path.
	 */
	public function endpoint_url(string $path): string
	{
		return rtrim((string) ($this->settings['base_url'] ?? ''), '/') . '/' . ltrim($path, '/');
	}

	/**
	 * @return array<string, string>
	 */
	private function headers(bool $has_json_body): array
	{
		$headers = array(
			'Accept' => 'application/json',
		);

		if ($has_json_body) {
			$headers['Content-Type'] = 'application/json';
		}

		$token  = (string) ($this->settings['token'] ?? '');
		$method = (string) ($this->settings['auth_method'] ?? Sanitizer::AUTH_BEARER);

		if (Sanitizer::AUTH_API_KEY === $method) {
			return array_merge($headers, ApiKeyAuth::headers($token, (string) ($this->settings['api_key_header'] ?? 'X-API-Key')));
		}

		return array_merge($headers, BearerAuth::headers($token));
	}

	private static function truncate_body(string $body): string
	{
		if (strlen($body) <= self::MAX_RESPONSE_BODY_LENGTH) {
			return $body;
		}

		return substr($body, 0, self::MAX_RESPONSE_BODY_LENGTH) . '…';
	}
}
