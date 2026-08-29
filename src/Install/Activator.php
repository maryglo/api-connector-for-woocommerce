<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Install;

use WooCommerceApiConnector\Plugin;
use WooCommerceApiConnector\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Runs on plugin activation.
 */
final class Activator
{
	public static function activate(): void
	{
		if (! function_exists('deactivate_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if (! Plugin::is_woocommerce_active()) {
			deactivate_plugins(plugin_basename(WCAC_FILE));
			wp_die(
				esc_html__(
					'WooCommerce Integration Connector requires WooCommerce to be installed and active.',
					Plugin::TEXT_DOMAIN
				),
				esc_html__('Plugin activation failed', Plugin::TEXT_DOMAIN),
				array('back_link' => true)
			);
		}

		self::seed_options();
		Schema::create_tables();
		update_option(Plugin::OPTION_DB_VERSION, Schema::DB_VERSION);
	}

	public static function maybe_upgrade(): void
	{
		if (get_option(Plugin::OPTION_DB_VERSION) === Schema::DB_VERSION) {
			return;
		}

		Schema::create_tables();
		update_option(Plugin::OPTION_DB_VERSION, Schema::DB_VERSION, false);
	}

	public static function seed_options(): void
	{
		if (false !== get_option(Plugin::OPTION_SETTINGS, false)) {
			return;
		}

		add_option(
			Plugin::OPTION_SETTINGS,
			self::default_settings(),
			'',
			false
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array
	{
		return Settings::defaults();
	}

	public static function delete_options(): void
	{
		delete_option(Plugin::OPTION_SETTINGS);
		delete_option(Plugin::OPTION_DB_VERSION);
	}
}
