<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Tests\Settings;

use PHPUnit\Framework\TestCase;
use WcacOptionStore;
use WooCommerceApiConnector\Plugin;
use WooCommerceApiConnector\Settings\Sanitizer;
use WooCommerceApiConnector\Settings\Settings;

final class SettingsTest extends TestCase
{
	protected function setUp(): void
	{
		WcacOptionStore::$options = array();
	}

	public function test_shortened_mapping_is_not_reexpanded_by_defaults(): void
	{
		$mapping = array(
			array('source' => 'order_id', 'destination' => 'external_id'),
			array('source' => 'billing_email', 'destination' => 'customer.email'),
		);
		WcacOptionStore::$options[Plugin::OPTION_SETTINGS] = array('mapping' => $mapping);

		self::assertSame($mapping, Settings::get()['mapping']);
	}

	public function test_connection_save_preserves_a_short_custom_mapping(): void
	{
		$mapping = array(array('source' => 'order_id', 'destination' => 'external_id'));
		WcacOptionStore::$options[Plugin::OPTION_SETTINGS] = array('mapping' => $mapping);

		$sanitized = Sanitizer::from_post(array(
			'wcac_settings' => array(
				'api_name' => 'Example API',
				'base_url' => 'https://api.example.com',
				'auth_method' => Sanitizer::AUTH_BEARER,
			),
		), Settings::get());

		self::assertSame($mapping, $sanitized['mapping']);
	}
}
