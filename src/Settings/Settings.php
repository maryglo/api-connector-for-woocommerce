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
			'events'            => array(
				'order_created'   => true,
				'order_completed' => true,
			),
			'mapping'           => self::default_mapping(),
		);
	}

	/**
	 * @return list<array{source: string, destination: string}>
	 */
	public static function default_mapping(): array
	{
		return array(
			array(
				'source'      => 'order_id',
				'destination' => 'order_id',
			),
			array(
				'source'      => 'order_number',
				'destination' => 'external_reference',
			),
			array(
				'source'      => 'status',
				'destination' => 'status',
			),
			array(
				'source'      => 'currency',
				'destination' => 'currency',
			),
			array(
				'source'      => 'total',
				'destination' => 'total',
			),
			array(
				'source'      => 'date_created',
				'destination' => 'created_at',
			),
			array(
				'source'      => 'billing_first_name',
				'destination' => 'customer.first_name',
			),
			array(
				'source'      => 'billing_last_name',
				'destination' => 'customer.last_name',
			),
			array(
				'source'      => 'billing_email',
				'destination' => 'customer.email',
			),
			array(
				'source'      => 'item_product_id',
				'destination' => 'items[].product_id',
			),
			array(
				'source'      => 'item_sku',
				'destination' => 'items[].sku',
			),
			array(
				'source'      => 'item_name',
				'destination' => 'items[].name',
			),
			array(
				'source'      => 'item_quantity',
				'destination' => 'items[].quantity',
			),
			array(
				'source'      => 'item_subtotal',
				'destination' => 'items[].subtotal',
			),
		);
	}

	/**
	 * Get plugin settings.
	 *
	 * The stored credential remains encrypted. Decryption is performed only
	 * by get_auth_secret() when the credential is actually required.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array
	{
		$saved = get_option(Plugin::OPTION_SETTINGS, array());

		if (! is_array($saved)) {
			$saved = array();
		}

		$settings = array_replace_recursive(
			self::defaults(),
			$saved
		);

		$settings['events'] = array_merge(
			self::defaults()['events'],
			is_array($saved['events'] ?? null)
				? $saved['events']
				: array()
		);

		/*
		 * Mapping rows are an ordered list, not a keyed settings group.
		 *
		 * Recursive merging would restore default rows that the merchant
		 * intentionally removed.
		 */
		if (is_array($saved['mapping'] ?? null)) {
			$settings['mapping'] = $saved['mapping'];
		}

		return $settings;
	}

	/**
	 * Save plugin settings.
	 *
	 * An empty token means:
	 * "keep the existing credential".
	 *
	 * A non-empty token is encrypted before being stored in wp_options.
	 *
	 * @param array<string, mixed> $settings
	 */
	public static function save(array $settings): void
	{
		$existing = get_option(Plugin::OPTION_SETTINGS, array());

		if (! is_array($existing)) {
			$existing = array();
		}

		if (
			isset($settings['token'])
			&& is_string($settings['token'])
		) {
			$token = trim($settings['token']);

			if ('' === $token) {
				/*
				 * Empty admin field means:
				 * keep the existing credential.
				 */
				$settings['token'] = is_string($existing['token'] ?? null)
					? $existing['token']
					: '';
			} else {
				/*
				 * Never store credentials in plaintext.
				 */
				$settings['token'] = SecretManager::encrypt($token);
			}
		}

		update_option(
			Plugin::OPTION_SETTINGS,
			$settings,
			false
		);
	}

	/**
	 * Return the configured API credential.
	 *
	 * A wp-config.php constant takes precedence over the database value.
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

		/*
		 * Only decrypt credentials when they are actually needed.
		 */
		return SecretManager::decrypt($stored);
	}

	/**
	 * Get a string setting.
	 *
	 * The credential is intentionally never returned to callers through
	 * the generic settings accessor.
	 */
	public static function get_string(string $key): string
	{
		if ('token' === $key) {
			return '';
		}

		$value = self::get()[$key] ?? '';

		return is_string($value) ? $value : '';
	}

	/**
	 * Determine whether an event is enabled.
	 */
	public static function event_enabled(string $event): bool
	{
		$events = self::get()['events'] ?? array();

		return is_array($events)
			&& ! empty($events[$event]);
	}

	/**
	 * Determine whether an API credential is configured.
	 */
	public static function has_token(): bool
	{
		/*
		 * A server-managed credential takes precedence.
		 */
		if (
			defined(self::API_KEY_CONSTANT)
			&& is_string(constant(self::API_KEY_CONSTANT))
			&& '' !== constant(self::API_KEY_CONSTANT)
		) {
			return true;
		}

		$settings = self::get();
		$stored   = $settings['token'] ?? '';

		return is_string($stored) && '' !== $stored;
	}

	/**
	 * Resolve the credential for an API request.
	 *
	 * If the admin has entered a new token in the current form submission,
	 * use that unsaved token for connection testing.
	 *
	 * Otherwise use the configured credential.
	 *
	 * @param array<string, mixed> $settings
	 */
	public static function resolve_auth_secret(array $settings): string
	{
		/*
		 * If the admin just entered a new token and clicked
		 * "Test Connection", use the unsaved token.
		 */
		if (
			isset($settings['token'])
			&& is_string($settings['token'])
			&& '' !== trim($settings['token'])
		) {
			return trim($settings['token']);
		}

		/*
		 * Otherwise retrieve the configured credential:
		 *
		 * 1. WCAC_API_KEY from wp-config.php
		 * 2. Encrypted token from wp_options
		 */
		return self::get_auth_secret();
	}
}
