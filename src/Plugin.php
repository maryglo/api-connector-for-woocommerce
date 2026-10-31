<?php

declare(strict_types=1);

namespace WooCommerceApiConnector;

use WooCommerceApiConnector\Admin\SettingsPage;
use WooCommerceApiConnector\Admin\MappingPage;
use WooCommerceApiConnector\Admin\LogsPage;
use WooCommerceApiConnector\Jobs\SendIntegrationJob;
use WooCommerceApiConnector\Logging\IntegrationLogger;
use WooCommerceApiConnector\Orders\OrderEventHandler;

defined('ABSPATH') || exit;

/**
 * Plugin bootstrap.
 */
final class Plugin
{
	public const VERSION = '0.1.1';

	public const SLUG = 'storedispatcher-outbound-integration-for-woocommerce';

	public const TEXT_DOMAIN = 'storedispatcher-outbound-integration-for-woocommerce';

	public const OPTION_SETTINGS = 'wcac_settings';

	public const OPTION_DB_VERSION = 'wcac_db_version';

	public const JOB_HOOK = 'wcac_send_integration';

	public const PRUNE_HOOK = 'wcac_prune_logs';

	private static ?self $instance = null;

	public static function instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct()
	{
	}

	public function boot(): void
	{
		add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
		add_action('plugins_loaded', array($this, 'on_plugins_loaded'), 20);
	}

	public function declare_hpos_compatibility(): void
	{
		if (! class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			WCAC_FILE,
			true
		);
	}

	public function on_plugins_loaded(): void
	{
		if (! self::is_woocommerce_active()) {
			add_action('admin_notices', array($this, 'woocommerce_missing_notice'));

			return;
		}

		$this->register_runtime();
	}

	/**
	 * Runtime hooks.
	 */
	private function register_runtime(): void
	{
		add_action('init', array(\WooCommerceApiConnector\Install\Activator::class, 'maybe_upgrade'));
		SettingsPage::register();
		MappingPage::register();
		LogsPage::register();
		OrderEventHandler::register();
		SendIntegrationJob::register();
		add_action(self::PRUNE_HOOK, array(IntegrationLogger::class, 'prune'));
		add_action('init', array($this, 'schedule_pruning'));
	}

	public function schedule_pruning(): void
	{
		if (function_exists('as_has_scheduled_action') && ! as_has_scheduled_action(self::PRUNE_HOOK)) {
			as_schedule_recurring_action(time() + DAY_IN_SECONDS, DAY_IN_SECONDS, self::PRUNE_HOOK, array(), self::SLUG);
		}
	}

	public function woocommerce_missing_notice(): void
	{
		if (! current_user_can('activate_plugins')) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__(
			'StoreDispatcher - Outbound Integration for WooCommerce requires WooCommerce to be installed and active.',
			'storedispatcher-outbound-integration-for-woocommerce'
		);
		echo '</p></div>';
	}

	public static function is_woocommerce_active(): bool
	{
		return class_exists('WooCommerce');
	}
}
