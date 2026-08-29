<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Orders;

defined('ABSPATH') || exit;

/**
 * Enqueues integration jobs from order lifecycle events (v0.1).
 */
final class OrderEventHandler
{
	public static function register(): void
	{
		add_action('woocommerce_new_order', array(self::class, 'created'));
		add_action('woocommerce_order_status_completed', array(self::class, 'completed'));
	}

	public static function created(int $order_id): void { self::enqueue_if_enabled($order_id, 'order_created', 'order.created'); }
	public static function completed(int $order_id): void { self::enqueue_if_enabled($order_id, 'order_completed', 'order.completed'); }

	private static function enqueue_if_enabled(int $order_id, string $setting, string $event): void
	{
		if ($order_id <= 0 || ! \WooCommerceApiConnector\Settings\Settings::event_enabled($setting)) { return; }
		$log_id = \WooCommerceApiConnector\Logging\IntegrationLogger::create($event, 'order', $order_id);
		\WooCommerceApiConnector\Jobs\SendIntegrationJob::enqueue($log_id, $order_id, $event, false);
	}
}
