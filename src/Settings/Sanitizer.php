<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Settings;

use WooCommerceApiConnector\Plugin;

defined('ABSPATH') || exit;

/**
 * Sanitizes settings from admin POST data.
 */
final class Sanitizer
{
	public const AUTH_BEARER  = 'bearer';
	public const AUTH_API_KEY = 'api_key';

	/**
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $existing
	 * @return array<string, mixed>
	 */
	public static function from_post(array $post, array $existing): array
	{
		$raw = array();

		if (isset($post['wcac_settings']) && is_array($post['wcac_settings'])) {
			$raw = $post['wcac_settings'];
		}

		$auth = self::sanitize_auth_method(isset($raw['auth_method']) ? (string) $raw['auth_method'] : '');

		$posted_token = isset($raw['token']) ? (string) $raw['token'] : '';
		$token        = $existing['token'] ?? '';

		if ('' !== trim($posted_token)) {
			$token = sanitize_text_field($posted_token);
		}

		$events_raw = isset($raw['events']) && is_array($raw['events']) ? $raw['events'] : array();

		$mapping = $existing['mapping'] ?? null;

		return array(
			'api_name'          => sanitize_text_field(isset($raw['api_name']) ? (string) $raw['api_name'] : ''),
			'base_url'          => self::sanitize_base_url(isset($raw['base_url']) ? (string) $raw['base_url'] : ''),
			'health_path'       => self::sanitize_relative_path(isset($raw['health_path']) ? (string) $raw['health_path'] : '', true),
			'auth_method'       => $auth,
			'token'             => $token,
			'api_key_header'    => self::sanitize_header_name(
				isset($raw['api_key_header']) ? (string) $raw['api_key_header'] : 'X-API-Key'
			),
			'order_endpoint'    => self::sanitize_relative_path(isset($raw['order_endpoint']) ? (string) $raw['order_endpoint'] : '/api/orders', false),
			'customer_endpoint' => self::sanitize_relative_path(isset($raw['customer_endpoint']) ? (string) $raw['customer_endpoint'] : '/api/customers', false),
			'events'            => array(
				'order_created'   => ! empty($events_raw['order_created']),
				'order_completed' => ! empty($events_raw['order_completed']),
			),
			'mapping'           => is_array($mapping) ? $mapping : Settings::default_mapping(),
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return list<string>
	 */
	public static function validate(array $settings): array
	{
		$errors = array();

		if ('' === ($settings['api_name'] ?? '')) {
			$errors[] = __('API Name is required.', Plugin::TEXT_DOMAIN);
		}

		if ('' === ($settings['base_url'] ?? '')) {
			$errors[] = __('Base URL is required.', Plugin::TEXT_DOMAIN);
		} elseif (false === wp_http_validate_url($settings['base_url'])) {
			$errors[] = __('Base URL must be a valid URL.', Plugin::TEXT_DOMAIN);
		}

		if (self::path_is_absolute((string) ($settings['health_path'] ?? ''))) {
			$errors[] = __('Health path must be a path, not an absolute URL.', Plugin::TEXT_DOMAIN);
		}

		if (self::path_is_absolute((string) ($settings['order_endpoint'] ?? ''))) {
			$errors[] = __('Order endpoint must be a path, not an absolute URL.', Plugin::TEXT_DOMAIN);
		}

		if (self::path_is_absolute((string) ($settings['customer_endpoint'] ?? ''))) {
			$errors[] = __('Customer endpoint must be a path, not an absolute URL.', Plugin::TEXT_DOMAIN);
		}

		if (self::AUTH_API_KEY === ($settings['auth_method'] ?? '') && '' === ($settings['api_key_header'] ?? '')) {
			$errors[] = __('API key header name is required when using API Key authentication.', Plugin::TEXT_DOMAIN);
		}

		return $errors;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return list<string>
	 */
	public static function validate_connection(array $settings): array
	{
		$errors = array();

		if ('' === ($settings['base_url'] ?? '')) {
			$errors[] = __('Base URL is required to test the connection.', Plugin::TEXT_DOMAIN);
		} elseif (false === wp_http_validate_url($settings['base_url'])) {
			$errors[] = __('Base URL must be a valid URL.', Plugin::TEXT_DOMAIN);
		}

		return $errors;
	}

	private static function sanitize_auth_method(string $method): string
	{
		return self::AUTH_API_KEY === $method ? self::AUTH_API_KEY : self::AUTH_BEARER;
	}

	private static function sanitize_base_url(string $url): string
	{
		$url = esc_url_raw(trim($url));

		return rtrim($url, '/');
	}

	private static function sanitize_relative_path(string $path, bool $allow_empty): string
	{
		$path = trim($path);

		if ('' === $path) {
			return $allow_empty ? '' : '/';
		}

		if (self::path_is_absolute($path)) {
			return $path;
		}

		$path = '/' . ltrim($path, '/');

		return sanitize_text_field($path);
	}

	private static function path_is_absolute(string $path): bool
	{
		return (bool) preg_match('#^https?://#i', $path);
	}

	private static function sanitize_header_name(string $name): string
	{
		$name = trim($name);

		if ('' === $name) {
			return 'X-API-Key';
		}

		$name = preg_replace('/[^A-Za-z0-9\-]/', '', $name);

		return is_string($name) && '' !== $name ? $name : 'X-API-Key';
	}
}
