<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Logging;

defined('ABSPATH') || exit;

/**
 * Persists integration job attempts to the custom logs table.
 */
final class IntegrationLogger
{
	/**
	 * Maximum number of attempts retained in a single log record.
	 */
	private const MAX_ATTEMPTS = 100;

	/**
	 * Maximum length for event/resource identifiers.
	 */
	private const MAX_EVENT_LENGTH = 100;

	private const MAX_RESOURCE_TYPE_LENGTH = 100;

	/**
	 * Fields that should never be persisted in logs.
	 *
	 * @var list<string>
	 */
	private const SENSITIVE_KEYS = array(
		'authorization',
		'api_key',
		'apikey',
		'access_token',
		'auth_token',
		'bearer_token',
		'token',
		'password',
		'passwd',
		'secret',
		'client_secret',
		'refresh_token',
		'private_key',
		'cookie',
		'set_cookie',
	);

	/**
	 * Placeholder used when sensitive values are removed.
	 */
	private const REDACTED = '[REDACTED]';

	/**
	 * Create a pending integration log.
	 */
	public static function create(
		string $event,
		string $resource_type,
		int $resource_id,
		bool $is_test = false
	): int {
		global $wpdb;

		$table = \WooCommerceApiConnector\Install\Schema::table_name();

		$event = self::sanitize_identifier(
			$event,
			self::MAX_EVENT_LENGTH
		);

		$resource_type = self::sanitize_identifier(
			$resource_type,
			self::MAX_RESOURCE_TYPE_LENGTH
		);

		$now = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom log table is not registered with the WordPress object cache.
		$result = $wpdb->insert(
			$table,
			array(
				'event_type'    => $event,
				'resource_type' => $resource_type,
				'resource_id'   => max(0, $resource_id),
				'status'        => 'pending',
				'is_test'       => (int) $is_test,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array(
				'%s',
				'%s',
				'%d',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		);

		return false === $result
			? 0
			: (int) $wpdb->insert_id;
	}

	/**
	 * Mark a log as running and persist the sanitized request details.
	 */
	public static function running(
		int $id,
		string $method,
		string $url,
		string $body
	): int {
		if ($id <= 0) {
			return 0;
		}

		$log = self::find($id);

		if (! $log) {
			return 0;
		}

		$attempts = min(
			self::MAX_ATTEMPTS,
			(int) $log->attempts + 1
		);

		$updated = self::update(
			$id,
			array(
				'status'         => 'running',
				'attempts'       => $attempts,
				'request_method' => self::sanitize_method($method),
				'request_url'    => self::sanitize_url($url),
				'request_body'   => self::sanitize_body($body),
			)
		);

		return $updated > 0 ? $attempts : 0;
	}

	/**
	 * Update a log record.
	 *
	 * Only known log fields should be accepted.
	 *
	 * @param array<string, mixed> $data
	 */
	public static function update(int $id, array $data): int
	{
		global $wpdb;

		if ($id <= 0) {
			return 0;
		}

		$allowed_fields = array(
			'status',
			'attempts',
			'request_method',
			'request_url',
			'request_body',
			'response_status',
			'response_code',
			'response_body',
			'error_message',
		);

		$filtered = array();

		foreach ($allowed_fields as $field) {
			if (! array_key_exists($field, $data)) {
				continue;
			}

			$value = $data[$field];

			switch ($field) {
				case 'attempts':
					$filtered[$field] = min(
						self::MAX_ATTEMPTS,
						max(0, (int) $value)
					);
					break;

				case 'response_status':
				case 'response_code':
					$filtered['response_code'] = max(0, (int) $value);
					break;

				case 'request_method':
					$filtered[$field] = self::sanitize_method(
						(string) $value
					);
					break;

				case 'request_url':
					$filtered[$field] = self::sanitize_url(
						(string) $value
					);
					break;

				case 'request_body':
				case 'response_body':
					$filtered[$field] = self::sanitize_body(
						(string) $value
					);
					break;

				case 'error_message':
					$filtered[$field] = self::sanitize_error(
						(string) $value
					);
					break;

				case 'status':
					$filtered[$field] = self::sanitize_status(
						(string) $value
					);
					break;
			}
		}

		if (empty($filtered)) {
			return 0;
		}

		$filtered['updated_at'] = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom log table is not registered with the WordPress object cache.
		$result = $wpdb->update(
			\WooCommerceApiConnector\Install\Schema::table_name(),
			$filtered,
			array(
				'id' => $id,
			),
			self::formats_for($filtered),
			array('%d')
		);

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Return the number of attempts for a log.
	 */
	public static function attempts(int $id): int
	{
		if ($id <= 0) {
			return 0;
		}

		$log = self::find($id);

		return $log
			? min(self::MAX_ATTEMPTS, max(0, (int) $log->attempts))
			: 0;
	}

	/**
	 * Find a log record.
	 */
	public static function find(int $id): ?object
	{
		global $wpdb;

		if ($id <= 0) {
			return null;
		}

		$table = \WooCommerceApiConnector\Install\Schema::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom log table is not registered with the WordPress object cache.
		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$id
			)
		);

		return $result ?: null;
	}

	/**
	 * Return recent log records.
	 *
	 * @return list<object>
	 */
	public static function recent(int $limit = 100): array
	{
		global $wpdb;

		$limit = max(1, min($limit, 100));

		$table = \WooCommerceApiConnector\Install\Schema::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom log table is not registered with the WordPress object cache.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY id DESC LIMIT %d',
				$table,
				$limit
			)
		);

		return is_array($results) ? $results : array();
	}

	/**
	 * Remove logs older than 90 days.
	 */
	public static function prune(): void
	{
		global $wpdb;

		$table = \WooCommerceApiConnector\Install\Schema::table_name();

		$before = gmdate(
			'Y-m-d H:i:s',
			time() - (90 * DAY_IN_SECONDS)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom log table is not registered with the WordPress object cache.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s',
				$table,
				$before
			)
		);
	}

	/**
	 * Sanitize an HTTP method.
	 */
	private static function sanitize_method(string $method): string
	{
		$method = strtoupper(trim($method));

		if (
			! preg_match('/^[A-Z]{1,16}$/', $method)
		) {
			return 'UNKNOWN';
		}

		return $method;
	}

	/**
	 * Sanitize a URL before persisting it.
	 *
	 * Credentials embedded in URLs are removed.
	 */
	private static function sanitize_url(string $url): string
	{
		$url = trim($url);

		if ('' === $url) {
			return '';
		}

		$parts = wp_parse_url($url);

		if (! is_array($parts)) {
			return '[INVALID URL]';
		}

		unset($parts['user'], $parts['pass']);

		/*
		 * Query strings can contain API keys, tokens, signatures, etc.
		 * Remove them rather than attempting to guess which parameters
		 * are safe.
		 */
		unset($parts['query']);

		$scheme = isset($parts['scheme'])
			? strtolower((string) $parts['scheme']) . '://'
			: '';

		$host = isset($parts['host'])
			? (string) $parts['host']
			: '';

		$port = isset($parts['port'])
			? ':' . (int) $parts['port']
			: '';

		$path = isset($parts['path'])
			? (string) $parts['path']
			: '';

		return $scheme . $host . $port . $path;
	}

	/**
	 * Sanitize request/response body before persistence.
	 */
	private static function sanitize_body(string $body): string
	{
		if ('' === $body) {
			return '';
		}

		$decoded = json_decode($body, true);

		if (is_array($decoded)) {
			$redacted = self::redact_array($decoded);

			$encoded = wp_json_encode($redacted);

			if (false !== $encoded) {
				return \WooCommerceApiConnector\Api\ApiClient::truncate_logged_body(
					$encoded
				);
			}
		}

		/*
		 * Non-JSON content is still truncated.
		 *
		 * We cannot safely identify arbitrary secrets in an arbitrary
		 * non-JSON payload, so callers should avoid logging such payloads.
		 */
		return \WooCommerceApiConnector\Api\ApiClient::truncate_logged_body(
			$body
		);
	}

	/**
	 * Recursively redact sensitive JSON fields.
	 *
	 * @param array<mixed> $data
	 * @return array<mixed>
	 */
	private static function redact_array(array $data): array
	{
		foreach ($data as $key => $value) {
			if (is_string($key) && self::is_sensitive_key($key)) {
				$data[$key] = self::REDACTED;
				continue;
			}

			if (is_array($value)) {
				$data[$key] = self::redact_array($value);
			}
		}

		return $data;
	}

	/**
	 * Determine whether a JSON field contains a sensitive value.
	 */
	private static function is_sensitive_key(string $key): bool
	{
		$key = strtolower(trim($key));

		if (in_array($key, self::SENSITIVE_KEYS, true)) {
			return true;
		}

		foreach (self::SENSITIVE_KEYS as $sensitive_key) {
			if (
				false !== strpos($key, $sensitive_key)
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitize transport/API error messages.
	 */
	private static function sanitize_error(string $message): string
	{
		$message = trim($message);

		if ('' === $message) {
			return '';
		}

		/*
		 * Remove obvious credential-bearing URL components if a transport
		 * library includes them in an error message.
		 */
		$message = preg_replace(
			'/(https?:\/\/)([^\/\s:@]+):([^\/\s@]+)@/i',
			'$1[REDACTED]@[REDACTED]',
			$message
		);

		return \WooCommerceApiConnector\Api\ApiClient::truncate_logged_body(
			(string) $message
		);
	}

	/**
	 * Restrict status values stored by the logger.
	 */
	private static function sanitize_status(string $status): string
	{
		$status = strtolower(trim($status));

		$allowed = array(
			'pending',
			'running',
			'success',
			'failed',
			'error',
		);

		return in_array($status, $allowed, true)
			? $status
			: 'error';
	}

	/**
	 * Sanitize an event/resource identifier.
	 */
	private static function sanitize_identifier(
		string $value,
		int $max_length
	): string {
		$value = trim($value);

		/*
		 * Event names and resource types are identifiers, not free-form
		 * content. Keep the allowed character set deliberately small.
		 */
		$value = preg_replace(
			'/[^A-Za-z0-9_.:-]/',
			'_',
			$value
		);

		return substr((string) $value, 0, $max_length);
	}

	/**
	 * Build wpdb formats for the filtered update data.
	 *
	 * @param array<string, mixed> $data
	 * @return list<string>
	 */
	private static function formats_for(array $data): array
	{
		$formats = array();

		foreach ($data as $key => $value) {
			if ('attempts' === $key || 'response_code' === $key) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		return $formats;
	}
}
