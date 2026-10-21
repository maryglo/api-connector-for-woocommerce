<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Api;

use WooCommerceApiConnector\Api\Authentication\ApiKeyAuth;
use WooCommerceApiConnector\Api\Authentication\BearerAuth;
use WooCommerceApiConnector\Settings\Sanitizer;
use WooCommerceApiConnector\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Outbound HTTP client.
 */
final class ApiClient
{
	/**
	 * Maximum time allowed for an HTTP request.
	 */
	private const TIMEOUT = 15;

	/**
	 * Maximum number of redirects WordPress may follow.
	 */
	private const MAX_REDIRECTS = 3;

	/**
	 * Maximum response body retained by the client.
	 *
	 * This limits memory/log usage. It does not redact sensitive data.
	 */
	public const MAX_BODY_LENGTH = 65536;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct(
		private readonly array $settings
	) {}

	/**
	 * Send an authenticated request to the configured API.
	 *
	 * @param array<string, mixed>|null $json_body
	 */
	public function request(
		string $method,
		string $url,
		?array $json_body = null
	): Response {
		$method = strtoupper(trim($method));

		if (! $this->is_allowed_method($method)) {
			return new Response(
				0,
				'',
				array(),
				__('HTTP method is not allowed.', 'woocommerce-api-connector')
			);
		}

		$validated_url = $this->validate_url($url);

		if (null === $validated_url) {
			return new Response(
				0,
				'',
				array(),
				__('The API URL is invalid or not allowed.', 'woocommerce-api-connector')
			);
		}

		$args = array(
			'method'      => $method,
			'timeout'     => self::TIMEOUT,
			'redirection' => self::MAX_REDIRECTS,
			'sslverify'   => true,
			'headers'     => $this->headers(null !== $json_body),
		);

		if (null !== $json_body) {
			$encoded = wp_json_encode($json_body);

			if (false === $encoded) {
				return new Response(
					0,
					'',
					array(),
					__('Unable to encode the request body as JSON.', 'woocommerce-api-connector')
				);
			}

			$args['body'] = $encoded;
		}

		$response = wp_remote_request(
			$validated_url,
			$args
		);

		if (is_wp_error($response)) {
			/*
			 * Do not expose the raw WP_Error message.
			 *
			 * Transport errors can contain hostnames, URLs, proxy details,
			 * filesystem information, or other infrastructure details.
			 */
			return new Response(
				0,
				'',
				array(),
				__('Unable to connect to the configured API.', 'woocommerce-api-connector')
			);
		}

		$headers = wp_remote_retrieve_headers($response);

		/*
		 * Depending on the WordPress HTTP transport/version, headers may
		 * be returned as an array or a response header object.
		 */
		if (
			is_object($headers)
			&& method_exists($headers, 'getAll')
		) {
			$headers = $headers->getAll();
		}

		$headers = is_array($headers)
			? self::normalize_headers($headers)
			: array();

		$body = (string) wp_remote_retrieve_body($response);

		return new Response(
			(int) wp_remote_retrieve_response_code($response),
			self::truncate_logged_body($body),
			$headers
		);
	}

	/**
	 * Build an absolute endpoint URL from the configured base URL.
	 *
	 * Only relative endpoint paths are accepted.
	 */
	public function endpoint_url(string $path): string
	{
		$base_url = trim(
			(string) ($this->settings['base_url'] ?? '')
		);

		if ('' === $base_url) {
			return '';
		}

		$path = trim($path);

		if ('' === $path) {
			return rtrim($base_url, '/');
		}

		/*
		 * Endpoint paths must not replace the configured host.
		 *
		 * Reject:
		 *   https://example.com/...
		 *   http://example.com/...
		 *   //example.com/...
		 */
		if (
			str_starts_with($path, 'http://')
			|| str_starts_with($path, 'https://')
			|| str_starts_with($path, '//')
		) {
			return '';
		}

		return rtrim($base_url, '/')
			. '/'
			. ltrim($path, '/');
	}

	/**
	 * Build authentication and content headers.
	 *
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

		$token = Settings::resolve_auth_secret($this->settings);

		$method = (string) (
			$this->settings['auth_method']
			?? Sanitizer::AUTH_BEARER
		);

		if (Sanitizer::AUTH_API_KEY === $method) {
			return array_merge(
				$headers,
				ApiKeyAuth::headers(
					$token,
					(string) (
						$this->settings['api_key_header']
						?? 'X-API-Key'
					)
				)
			);
		}

		return array_merge(
			$headers,
			BearerAuth::headers($token)
		);
	}

	/**
	 * Validate the requested HTTP method.
	 */
	private function is_allowed_method(string $method): bool
	{
		return in_array(
			$method,
			array(
				'HEAD',
				'GET',
				'POST',
				'PUT',
				'PATCH',
				'DELETE',
			),
			true
		);
	}

	/**
	 * Validate that the request URL belongs to the configured API.
	 *
	 * The configured API must use HTTPS.
	 */
	private function validate_url(string $url): ?string
	{
		$url = trim($url);

		if ('' === $url) {
			return null;
		}

		$base_url = trim(
			(string) ($this->settings['base_url'] ?? '')
		);

		if ('' === $base_url) {
			return null;
		}

		$base_parts = wp_parse_url($base_url);
		$url_parts  = wp_parse_url($url);

		if (
			! is_array($base_parts)
			|| ! is_array($url_parts)
		) {
			return null;
		}

		$base_scheme = strtolower(
			(string) ($base_parts['scheme'] ?? '')
		);

		$url_scheme = strtolower(
			(string) ($url_parts['scheme'] ?? '')
		);

		if (
			$base_scheme !== $url_scheme
			|| ! in_array($base_scheme, array('http', 'https'), true)
		) {
			return null;
		}

		$base_host = strtolower(
			(string) ($base_parts['host'] ?? '')
		);

		$url_host = strtolower(
			(string) ($url_parts['host'] ?? '')
		);

		if (
			'' === $base_host
			|| '' === $url_host
			|| $base_host !== $url_host
		) {
			return null;
		}

		/*
		 * Do not allow HTTP basic-auth style credentials embedded
		 * in the URL. Authentication is handled through headers.
		 */
		if (
			isset($url_parts['user'])
			|| isset($url_parts['pass'])
		) {
			return null;
		}

		/*
		 * Credentials must not be sent over public HTTP.
		 * Loopback, .local, and RFC1918 hosts are allowed for local Docker.
		 */
		if (
			'http' === $base_scheme
			&& ! self::is_local_or_private_host($base_host)
		) {
			return null;
		}

		$default_port = 'https' === $base_scheme ? 443 : 80;

		$base_port = isset($base_parts['port'])
			? (int) $base_parts['port']
			: $default_port;

		$url_port = isset($url_parts['port'])
			? (int) $url_parts['port']
			: $default_port;

		if ($base_port !== $url_port) {
			return null;
		}

		return $url;
	}

	/**
	 * Whether a host may use HTTP (local Docker / private networks).
	 */
	private static function is_local_or_private_host(string $host): bool
	{
		if (
			'localhost' === $host
			|| str_ends_with($host, '.localhost')
			|| str_ends_with($host, '.local')
		) {
			return true;
		}

		if (
			str_starts_with($host, '[')
			&& str_ends_with($host, ']')
		) {
			$host = substr($host, 1, -1);
		}

		if ('::1' === $host) {
			return true;
		}

		if (false === filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			return false;
		}

		$octets = array_map('intval', explode('.', $host));

		if (4 !== count($octets)) {
			return false;
		}

		return 127 === $octets[0]
			|| 10 === $octets[0]
			|| (192 === $octets[0] && 168 === $octets[1])
			|| (172 === $octets[0] && $octets[1] >= 16 && $octets[1] <= 31);
	}

	/**
	 * Normalize response headers to a string-key/string-value array.
	 *
	 * @param array<mixed> $headers
	 * @return array<string, string>
	 */
	private static function normalize_headers(array $headers): array
	{
		$normalized = array();

		foreach ($headers as $name => $value) {
			if (! is_string($name)) {
				continue;
			}

			if (is_array($value)) {
				$value = implode(', ', $value);
			}

			if (! is_scalar($value)) {
				continue;
			}

			$normalized[$name] = (string) $value;
		}

		return $normalized;
	}

	/**
	 * Truncate a response body before it is retained/logged.
	 *
	 * This method does not redact credentials or personal information.
	 */
	public static function truncate_logged_body(string $body): string
	{
		if (strlen($body) <= self::MAX_BODY_LENGTH) {
			return $body;
		}

		return substr(
			$body,
			0,
			self::MAX_BODY_LENGTH
		) . '…';
	}
}
