<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Deletes settings options and the integration logs table.
 *
 * @package WooCommerceApiConnector
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
defined('WP_UNINSTALL_PLUGIN') || exit;

$wcac_autoload = __DIR__ . '/vendor/autoload.php';

if (is_readable($wcac_autoload)) {
	require_once $wcac_autoload;

	\WooCommerceApiConnector\Install\Schema::drop_tables();
	\WooCommerceApiConnector\Install\Activator::delete_options();

	return;
}

global $wpdb;

$wcac_table = $wpdb->prefix . 'wcac_integration_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to remove the plugin's custom table.
$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wcac_table));

delete_option('wcac_settings');
delete_option('wcac_db_version');
