<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Api;

defined('ABSPATH') || exit;

/**
 * Normalized HTTP response from the external API.
 */
final class Response
{
	/**
	 * @param array<string, string> $headers
	 */
	public function __construct(
		public readonly int $status_code,
		public readonly string $body,
		public readonly array $headers = array(),
		public readonly string $error_message = ''
	) {
	}

	public function is_success(): bool
	{
		return $this->status_code >= 200 && $this->status_code < 300;
	}

	public function is_transport_error(): bool
	{
		return '' !== $this->error_message;
	}
}
