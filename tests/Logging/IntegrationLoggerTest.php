<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Tests\Logging;

use PHPUnit\Framework\TestCase;
use WcacWpdb;
use WooCommerceApiConnector\Logging\IntegrationLogger;

final class IntegrationLoggerTest extends TestCase
{
	protected function setUp(): void
	{
		global $wpdb;

		$wpdb = new WcacWpdb();
	}

	public function test_update_persists_response_code(): void
	{
		global $wpdb;

		$result = IntegrationLogger::update(12, array(
			'status'        => 'success',
			'response_code' => 201,
			'response_body' => '{"id":"ORD-1"}',
			'error_message' => '',
		));

		self::assertSame(1, $result);
		self::assertSame('wp_wcac_integration_logs', $wpdb->last_update['table']);
		self::assertSame(12, $wpdb->last_update['where']['id']);
		self::assertSame(201, $wpdb->last_update['data']['response_code']);
		self::assertSame('success', $wpdb->last_update['data']['status']);
		self::assertArrayNotHasKey('response_status', $wpdb->last_update['data']);
		self::assertContains('%d', $wpdb->last_update['format']);
	}

	public function test_response_status_alias_is_stored_as_response_code(): void
	{
		global $wpdb;

		IntegrationLogger::update(3, array(
			'response_status' => 500,
		));

		self::assertSame(500, $wpdb->last_update['data']['response_code']);
		self::assertArrayNotHasKey('response_status', $wpdb->last_update['data']);
	}
}
