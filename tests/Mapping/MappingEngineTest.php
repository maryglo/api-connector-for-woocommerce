<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Tests\Mapping;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WooCommerceApiConnector\Mapping\MappingEngine;
use WooCommerceApiConnector\Mapping\MappingValidator;

final class MappingEngineTest extends TestCase
{
	public function test_maps_nested_order_values_and_one_object_per_line_item(): void
	{
		$order = new FakeOrder();
		$payload = (new MappingEngine())->map($order, array(
			array('source' => 'order_id', 'destination' => 'order_id'),
			array('source' => 'billing_email', 'destination' => 'customer.email'),
			array('source' => 'item_sku', 'destination' => 'items[].sku'),
			array('source' => 'item_quantity', 'destination' => 'items[].quantity'),
		));

		self::assertSame(array(
			'order_id' => 42,
			'customer' => array('email' => 'buyer@example.com'),
			'items' => array(
				array('sku' => 'SKU-1', 'quantity' => 2),
				array('sku' => 'SKU-2', 'quantity' => 1),
			),
		), $payload);
	}

	public function test_rejects_invalid_destination_paths(): void
	{
		self::assertFalse(MappingValidator::is_valid_destination('customer..email'));
		self::assertFalse(MappingValidator::is_valid_destination('items[].sku[]'));
		self::assertTrue(MappingValidator::is_valid_destination('customer.address.postcode'));
	}
}

final class FakeOrder
{
	public function get_id(): int { return 42; }
	public function get_order_number(): string { return 'WC-42'; }
	public function get_status(): string { return 'completed'; }
	public function get_currency(): string { return 'PHP'; }
	public function get_total(): string { return '4999.00'; }
	public function get_date_created(): DateTimeImmutable { return new DateTimeImmutable('2026-01-01T00:00:00+00:00'); }
	public function get_billing_first_name(): string { return 'John'; }
	public function get_billing_last_name(): string { return 'Doe'; }
	public function get_billing_email(): string { return 'buyer@example.com'; }
	public function get_billing_phone(): string { return ''; }
	public function get_items(string $type): array { return array(new FakeItem('SKU-1', 2), new FakeItem('SKU-2', 1)); }
}

final class FakeItem
{
	public function __construct(private readonly string $sku, private readonly int $quantity) {}
	public function get_product_id(): int { return 10; }
	public function get_product(): object { return new FakeProduct($this->sku); }
	public function get_name(): string { return 'Product'; }
	public function get_quantity(): int { return $this->quantity; }
	public function get_subtotal(): string { return '10.00'; }
}

final class FakeProduct
{
	public function __construct(private readonly string $sku) {}
	public function get_sku(): string { return $this->sku; }
}
