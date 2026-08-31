<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Settings;

use WooCommerceApiConnector\Plugin;

defined('ABSPATH') || exit;

/**
 * Connection, event, and endpoint options.
 */
final class Settings
{
	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array
	{
		return array(
			'api_name'          => '',
			'base_url'          => '',
			'health_path'       => '',
			'auth_method'       => 'bearer',
			'token'             => '',
			'api_key_header'    => 'X-API-Key',
			'order_endpoint'    => '/api/orders',
			'customer_endpoint' => '/api/customers',
			'events'            => array(
				'order_created'   => true,
				'order_completed' => true,
			),
			'mapping'           => self::default_mapping(),
		);
	}

	/** @return list<array{source: string, destination: string}> */
	public static function default_mapping(): array
	{
		return array(
			array('source' => 'order_id', 'destination' => 'order_id'),
			array('source' => 'order_number', 'destination' => 'external_reference'),
			array('source' => 'status', 'destination' => 'status'),
			array('source' => 'currency', 'destination' => 'currency'),
			array('source' => 'total', 'destination' => 'total'),
			array('source' => 'date_created', 'destination' => 'created_at'),
			array('source' => 'billing_first_name', 'destination' => 'customer.first_name'),
			array('source' => 'billing_last_name', 'destination' => 'customer.last_name'),
			array('source' => 'billing_email', 'destination' => 'customer.email'),
			array('source' => 'item_product_id', 'destination' => 'items[].product_id'),
			array('source' => 'item_sku', 'destination' => 'items[].sku'),
			array('source' => 'item_name', 'destination' => 'items[].name'),
			array('source' => 'item_quantity', 'destination' => 'items[].quantity'),
			array('source' => 'item_subtotal', 'destination' => 'items[].subtotal'),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array
	{
		$saved = get_option(Plugin::OPTION_SETTINGS, array());

		if (! is_array($saved)) {
			$saved = array();
		}

		$settings           = array_replace_recursive(self::defaults(), $saved);
		$settings['events'] = array_merge(
			self::defaults()['events'],
			is_array($saved['events'] ?? null) ? $saved['events'] : array()
		);

		// Mapping rows are an ordered list, not a keyed settings group. Merging it
		// recursively would restore default rows the merchant intentionally removed.
		if (is_array($saved['mapping'] ?? null)) {
			$settings['mapping'] = $saved['mapping'];
		}

		return $settings;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function save(array $settings): void
	{
		update_option(Plugin::OPTION_SETTINGS, $settings, false);
	}

	public static function get_string(string $key): string
	{
		$value = self::get()[ $key ] ?? '';

		return is_string($value) ? $value : '';
	}

	public static function event_enabled(string $event): bool
	{
		$events = self::get()['events'] ?? array();

		return is_array($events) && ! empty($events[ $event ]);
	}

	public static function has_token(): bool
	{
		return '' !== self::get_string('token');
	}
}
