<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Install;

defined('ABSPATH') || exit;

/**
 * Database schema for integration logs.
 */
final class Schema
{
	public const DB_VERSION = '1.0.0';

	public const TABLE_SUFFIX = 'wcac_integration_logs';

	public static function table_name(): string
	{
		global $wpdb;

		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function create_tables(): void
	{
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(64) NOT NULL DEFAULT '',
			resource_type varchar(32) NOT NULL DEFAULT '',
			resource_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(16) NOT NULL DEFAULT 'pending',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			request_method varchar(8) NOT NULL DEFAULT '',
			request_url text NULL,
			request_body longtext NULL,
			response_code smallint(5) unsigned NULL,
			response_body longtext NULL,
			error_message text NULL,
			is_test tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at),
			KEY resource (resource_type,resource_id)
		) {$collate};";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- dbDelta is required to create or update the plugin's custom table.
		dbDelta($sql);
	}

	public static function drop_tables(): void
	{
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to remove the plugin's custom table.
		$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table));
	}
}
