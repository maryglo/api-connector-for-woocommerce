<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Settings;

use WooCommerceApiConnector\Plugin;
use WooCommerceApiConnector\Security\SecretManager;

defined('ABSPATH') || exit;

/**
 * Connection, event, and endpoint options.
 */
final class Settings
{
	public const API_KEY_CONSTANT = 'WCAC_API_KEY';
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
		$existing = get_option(Plugin::OPTION_SETTINGS, array());

		if (! is_array($existing)) {
			$existing = array();
		}

		  /*
         * An empty token means:
         * "keep the existing credential".
         */
		  if (
            isset($settings['token'])
            && is_string($settings['token'])
        ) {
            if ('' === $settings['token']) {
                $settings['token'] = $existing['token'] ?? '';
            } else {
                $settings['token'] = SecretManager::encrypt(
                    $settings['token']
                );
            }
        }

        update_option(
            Plugin::OPTION_SETTINGS,
            $settings,
            false
        );
	}

	/**
	 * Return the API credential for an API request.
	 */
	public static function get_auth_secret(): string
	{
		/*
		  * Server-managed configuration takes precedence.
		  */
		if (
			defined(self::API_KEY_CONSTANT)
			&& is_string(constant(self::API_KEY_CONSTANT))
			&& '' !== constant(self::API_KEY_CONSTANT)
		) {
			return constant(self::API_KEY_CONSTANT);
		}

		$settings = self::get();

		$stored = $settings['token'] ?? '';

		if (! is_string($stored) || '' === $stored) {
			return '';
		}

		return SecretManager::decrypt($stored);
	}

	public static function get_string(string $key): string
	{
		if ('token' === $key) {
			return '';
		}

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
		if (
			defined(self::API_KEY_CONSTANT)
			&& is_string(constant(self::API_KEY_CONSTANT))
			&& '' !== constant(self::API_KEY_CONSTANT)
		) {
			return true;
		}

		$settings = self::get();

		return is_string($settings['token'] ?? '')
			&& '' !== $settings['token'];
	}

	public static function resolve_auth_secret(array $settings): string
	{
		/*
    	  * If the admin just entered a new token and clicked
          * "Test Connection", use that unsaved token.
          */
		if (
			isset($settings['token'])
			&& is_string($settings['token'])
			&& '' !== trim($settings['token'])
		) {
			return $settings['token'];
		}

		/*
          * Otherwise retrieve the configured credential:
          * - wp-config.php constant, if configured
          * - encrypted token from wp_options
          */
		return self::get_auth_secret();
	}
}
