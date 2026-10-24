<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Mapping;

defined('ABSPATH') || exit;

/**
 * Builds outbound JSON from mapping rows (v0.1).
 */
final class MappingEngine
{
	public function __construct(private readonly FieldResolver $resolver = new FieldResolver())
	{
	}

	/** @param list<array{source: string, destination: string}> $rows */
	public function map(object $order, array $rows): array
	{
		$errors = MappingValidator::validate_rows($rows);
		if (array() !== $errors) {
			throw new \InvalidArgumentException(esc_html(implode(' ', $errors)));
		}
		$payload = array();
		$item_rows = array();
		foreach ($rows as $row) {
			$path = $row['destination'];
			if (str_starts_with($path, 'items[].')) {
				$item_rows[] = $row;
				continue;
			}
			$this->set($payload, $path, $this->resolver->resolve($order, $row['source']));
		}
		if (array() !== $item_rows) {
			foreach ($order->get_items('line_item') as $item) {
				$mapped_item = array();
				foreach ($item_rows as $row) {
					$this->set($mapped_item, substr($row['destination'], 8), $this->resolver->resolve($order, $row['source'], $item));
				}
				$payload['items'][] = $mapped_item;
			}
		}
		return $payload;
	}

	private function set(array &$payload, string $path, mixed $value): void
	{
		$segments = explode('.', $path);
		$cursor =& $payload;
		foreach ($segments as $position => $segment) {
			$is_array = str_ends_with($segment, '[]');
			$key = $is_array ? substr($segment, 0, -2) : $segment;
			if ($is_array) {
				$cursor[$key] ??= array();
				$cursor[$key][] = array();
				$cursor =& $cursor[$key][array_key_last($cursor[$key])];
				continue;
			}
			if ($position === count($segments) - 1) {
				$cursor[$key] = $value;
				return;
			}
			$cursor[$key] ??= array();
			$cursor =& $cursor[$key];
		}
	}
}
