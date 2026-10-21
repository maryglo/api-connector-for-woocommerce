<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Api\Authentication;

defined('ABSPATH') || exit;

/**
 * API key header authentication.
 */
final class ApiKeyAuth
{
	/**
	 * Default API key header.
	 */
	private const DEFAULT_HEADER = 'X-API-Key';

	/**
	 * Headers that must not be overridden by API key configuration.
	 *
	 * @var list<string>
	 */
	private const FORBIDDEN_HEADERS = array(
		'authorization',
		'cookie',
		'host',
		'content-length',
		'transfer-encoding',
		'connection',
		'proxy-authorization',
		'proxy-authenticate',
		'www-authenticate',
	);

	/**
	 * Build API key authentication headers.
	 *
	 * @return array<string, string>
	 */
	public static function headers(
		string $key,
		string $header = self::DEFAULT_HEADER
	): array {
		$key    = trim($key);
		$header = trim($header);

		if ('' === $key || '' === $header) {
			return array();
		}

		if (! self::is_valid_header_name($header)) {
			return array();
		}

		if (self::is_forbidden_header($header)) {
			return array();
		}

		/*
		 * Prevent header injection through newline characters.
		 *
		 * is_valid_header_name() also rejects these characters, but this
		 * explicit check keeps the security requirement obvious.
		 */
		if (
			false !== strpos($key, "\r")
			|| false !== strpos($key, "\n")
		) {
			return array();
		}

		return array(
			$header => $key,
		);
	}

	/**
	 * Validate an HTTP header field name.
	 *
	 * HTTP token characters are allowed:
	 * ! # $ % & ' * + - . ^ _ ` | ~
	 * and alphanumeric characters.
	 */
	private static function is_valid_header_name(string $header): bool
	{
		return 1 === preg_match(
			'/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/',
			$header
		);
	}

	/**
	 * Prevent API-key configuration from overriding security-sensitive
	 * transport/authentication headers.
	 */
	private static function is_forbidden_header(string $header): bool
	{
		return in_array(
			strtolower($header),
			self::FORBIDDEN_HEADERS,
			true
		);
	}
}
