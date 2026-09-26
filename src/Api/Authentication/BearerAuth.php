<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Api\Authentication;

defined('ABSPATH') || exit;

/**
 * Bearer token authentication.
 */
final class BearerAuth
{
	/**
	 * Build Bearer authentication headers.
	 *
	 * @return array<string, string>
	 */
	public static function headers(string $token): array
	{
		$token = trim($token);

		if ('' === $token) {
			return array();
		}

		/*
		 * Prevent HTTP header injection.
		 *
		 * Bearer tokens must never contain CR/LF characters because those
		 * characters can be interpreted as HTTP header delimiters.
		 */
		if (
			false !== strpos($token, "\r")
			|| false !== strpos($token, "\n")
		) {
			return array();
		}

		return array(
			'Authorization' => 'Bearer ' . $token,
		);
	}
}
