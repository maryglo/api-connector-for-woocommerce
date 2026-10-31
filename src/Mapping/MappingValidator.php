<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Mapping;

defined('ABSPATH') || exit;

/**
 * Validates mapping destination paths (v0.1).
 */
final class MappingValidator
{
	/** @return list<string> */
	public static function validate_rows(array $rows): array
	{
		$errors = array();
		foreach ($rows as $index => $row) {
			$source = is_array($row) ? trim((string) ($row['source'] ?? '')) : '';
			$path = is_array($row) ? trim((string) ($row['destination'] ?? '')) : '';
			if ('' === $source) {
				/* translators: %d: mapping row number. */
				$errors[] = sprintf(__('Mapping row %d requires a source.', 'storedispatcher-outbound-integration-for-woocommerce'), $index + 1);
			}
			if (! self::is_valid_destination($path)) {
				/* translators: %d: mapping row number. */
				$errors[] = sprintf(__('Mapping row %d has an invalid destination path.', 'storedispatcher-outbound-integration-for-woocommerce'), $index + 1);
			}
		}
		return $errors;
	}

	public static function is_valid_destination(string $path): bool
	{
		if ((bool) preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*$/', $path)) {
			return true;
		}

		return (bool) preg_match('/^items\[\]\.[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*$/', $path);
	}
}
