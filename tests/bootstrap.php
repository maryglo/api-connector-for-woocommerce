<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
	if ('cli' !== PHP_SAPI) {
		exit;
	}

	define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

final class WcacOptionStore
{
	/** @var array<string, mixed> */
	public static array $options = array();
}

final class WcacHttpTestTransport
{
	/** @var array<string, mixed> */
	public static array $last_request = array();

	/** @var array<string, mixed>|WP_Error */
	public static array|WP_Error $next_response = array(
		'code'    => 200,
		'body'    => '',
		'headers' => array(),
	);
}

final class WcacWpdb
{
	public string $prefix = 'wp_';

	/** @var array<string, mixed> */
	public array $last_update = array();

	public int $update_result = 1;

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $where
	 * @param list<string>|null    $format
	 * @param list<string>|null    $where_format
	 */
	public function update(
		string $table,
		array $data,
		array $where,
		?array $format = null,
		?array $where_format = null
	): int {
		$this->last_update = array(
			'table'        => $table,
			'data'         => $data,
			'where'        => $where,
			'format'       => $format,
			'where_format' => $where_format,
		);

		return $this->update_result;
	}
}

if (! class_exists('WP_Error')) {
	final class WP_Error
	{
		public function __construct(private readonly string $message)
		{
		}

		public function get_error_message(): string
		{
			return $this->message;
		}
	}
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- These stubs mirror WordPress globals for isolated tests.
if (! function_exists('__')) {
	function __(string $text, string $domain = 'default'): string
	{
		return $text;
	}
}

if (! function_exists('esc_html')) {
	function esc_html(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (! function_exists('wp_json_encode')) {
	function wp_json_encode(mixed $value): string|false
	{
		return json_encode($value);
	}
}

if (! function_exists('get_option')) {
	function get_option(string $option, mixed $default = false): mixed
	{
		return WcacOptionStore::$options[$option] ?? $default;
	}
}

if (! function_exists('update_option')) {
	function update_option(string $option, mixed $value, mixed $autoload = null): bool
	{
		WcacOptionStore::$options[$option] = $value;

		return true;
	}
}

if (! function_exists('sanitize_text_field')) {
	function sanitize_text_field(string $value): string
	{
		return trim(wp_strip_all_tags($value));
	}
}

if (! function_exists('wp_strip_all_tags')) {
	function wp_strip_all_tags(string $text, bool $remove_breaks = false): string
	{
		$text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text) ?? $text;
		$text = preg_replace('/<[^>]*>/', '', $text) ?? $text;

		if ($remove_breaks) {
			$text = preg_replace('/[\\r\\n\\t ]+/', ' ', $text) ?? $text;
		}

		return $text;
	}
}

if (! function_exists('esc_url_raw')) {
	function esc_url_raw(string $url): string
	{
		return $url;
	}
}

if (! function_exists('wp_remote_request')) {
	/** @param array<string, mixed> $args */
	function wp_remote_request(string $url, array $args): array|WP_Error
	{
		WcacHttpTestTransport::$last_request = array(
			'url'  => $url,
			'args' => $args,
		);

		return WcacHttpTestTransport::$next_response;
	}
}

if (! function_exists('is_wp_error')) {
	function is_wp_error(mixed $thing): bool
	{
		return $thing instanceof WP_Error;
	}
}

if (! function_exists('wp_remote_retrieve_headers')) {
	/** @param array<string, mixed> $response */
	function wp_remote_retrieve_headers(array $response): array
	{
		return $response['headers'] ?? array();
	}
}

if (! function_exists('wp_remote_retrieve_response_code')) {
	/** @param array<string, mixed> $response */
	function wp_remote_retrieve_response_code(array $response): int
	{
		return (int) ($response['code'] ?? 0);
	}
}

if (! function_exists('wp_remote_retrieve_body')) {
	/** @param array<string, mixed> $response */
	function wp_remote_retrieve_body(array $response): string
	{
		return (string) ($response['body'] ?? '');
	}
}

if (! function_exists('wp_parse_url')) {
	function wp_parse_url(string $url, int $component = -1): mixed
	{
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Native fallback for standalone tests without WordPress loaded.
		return parse_url($url, $component);
	}
}

if (! function_exists('current_time')) {
	function current_time(string $type, int|bool $gmt = 0): string
	{
		return '2026-01-01 00:00:00';
	}
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
