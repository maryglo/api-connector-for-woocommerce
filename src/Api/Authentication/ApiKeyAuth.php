<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Api\Authentication;

defined('ABSPATH') || exit;

/**
 * API key header authentication (v0.1).
 */
final class ApiKeyAuth
{
	/**
	 * @return array<string, string>
	 */
	public static function headers(string $key, string $header = 'X-API-Key'): array
	{
		$key    = trim($key);
		$header = trim($header);

		if ('' === $key || '' === $header) {
			return array();
		}

		return array(
			$header => $key,
		);
	}
}
