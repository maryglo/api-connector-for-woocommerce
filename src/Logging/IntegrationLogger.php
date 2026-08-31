<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Logging;

defined('ABSPATH') || exit;

/**
 * Persists integration job attempts to the custom logs table (v0.1).
 */
final class IntegrationLogger
{
	public static function create(string $event, string $resource_type, int $resource_id, bool $is_test = false): int
	{
		global $wpdb;
		$table = \WooCommerceApiConnector\Install\Schema::table_name();
		$now = current_time('mysql', true);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert($table, array('event_type' => $event, 'resource_type' => $resource_type, 'resource_id' => $resource_id, 'status' => 'pending', 'is_test' => (int) $is_test, 'created_at' => $now, 'updated_at' => $now), array('%s','%s','%d','%s','%d','%s','%s'));
		return (int) $wpdb->insert_id;
	}

	public static function running(int $id, string $method, string $url, string $body): int
	{
		$log = self::find($id);

		if (! $log) {
			return 0;
		}

		$attempts = (int) $log->attempts + 1;
		$updated  = self::update($id, array(
			'status'         => 'running',
			'attempts'       => $attempts,
			'request_method' => $method,
			'request_url'    => $url,
			'request_body'   => \WooCommerceApiConnector\Api\ApiClient::truncate_logged_body($body),
		));

		return $updated > 0 ? $attempts : 0;
	}

	/** @param array<string, mixed> $data */
	public static function update(int $id, array $data): int
	{
		global $wpdb;
		$data['updated_at'] = current_time('mysql', true);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->update(\WooCommerceApiConnector\Install\Schema::table_name(), $data, array('id' => $id));
	}

	public static function attempts(int $id): int
	{
		$log = self::find($id);
		return $log ? (int) $log->attempts : 0;
	}

	public static function find(int $id): ?object
	{
		global $wpdb;
		$table = \WooCommerceApiConnector\Install\Schema::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
	}

	/** @return list<object> */
	public static function recent(int $limit = 100): array
	{
		global $wpdb;
		$table = \WooCommerceApiConnector\Install\Schema::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit)) ?: array();
	}

	public static function prune(): void
	{
		global $wpdb;
		$table = \WooCommerceApiConnector\Install\Schema::table_name();
		$before = gmdate('Y-m-d H:i:s', time() - (90 * DAY_IN_SECONDS));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", $before));
	}
}
