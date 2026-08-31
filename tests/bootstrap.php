<?php

declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

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

if (! function_exists('__')) {
	function __(string $text, string $domain = 'default'): string
	{
		return $text;
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
		return trim(strip_tags($value));
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
