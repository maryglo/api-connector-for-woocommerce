<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Mapping;

defined('ABSPATH') || exit;

/**
 * Resolves WooCommerce field values (v0.1).
 */
final class FieldResolver
{
	public function resolve(object $order, string $source, ?object $item = null): mixed
	{
		if (str_starts_with($source, 'item_')) {
			return $this->resolve_item($item, $source);
		}
		return match ($source) {
			'order_id' => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'status' => $order->get_status(),
			'currency' => $order->get_currency(),
			'total' => $order->get_total(),
			'date_created' => $order->get_date_created()?->date('c'),
			'billing_first_name' => $order->get_billing_first_name(),
			'billing_last_name' => $order->get_billing_last_name(),
			'billing_email' => $order->get_billing_email(),
			'billing_phone' => $order->get_billing_phone(),
			default => null,
		};
	}

	private function resolve_item(?object $item, string $source): mixed
	{
		if (null === $item) {
			return null;
		}
		return match ($source) {
			'item_product_id' => $item->get_product_id(),
			'item_sku' => (($product = $item->get_product()) && method_exists($product, 'get_sku')) ? $product->get_sku() : '',
			'item_name' => $item->get_name(),
			'item_quantity' => $item->get_quantity(),
			'item_subtotal' => $item->get_subtotal(),
			default => null,
		};
	}
}
