<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Api\Authentication;

defined('ABSPATH') || exit;

/**
 * Bearer token authentication (v0.1).
 */
final class BearerAuth
{
	/**
	 * @return array<string, string>
	 */
	public static function headers(string $token): array
	{
		$token = trim($token);

		if ('' === $token) {
			return array();
		}

		return array(
			'Authorization' => 'Bearer ' . $token,
		);
	}
}
