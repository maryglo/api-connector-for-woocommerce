<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Admin;

use WooCommerceApiConnector\Jobs\SendIntegrationJob;
use WooCommerceApiConnector\Logging\IntegrationLogger;
use WooCommerceApiConnector\Mapping\MappingEngine;
use WooCommerceApiConnector\Mapping\MappingValidator;
use WooCommerceApiConnector\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Field mapping UI (v0.1).
 */
final class MappingPage
{
	public static function register(): void
	{
		add_action('admin_init', array(self::class, 'handle_post'));
	}

	public static function handle_post(): void
	{
		if (! is_admin() || ! current_user_can('manage_woocommerce') || empty($_POST['wcac_mapping_action'])) {
			return;
		}

		check_admin_referer('wcac_mapping', 'wcac_mapping_nonce');
		$action = sanitize_key((string) $_POST['wcac_mapping_action']);
		$rows   = self::rows_from_post(wp_unslash($_POST));

		if ('add_row' === $action) {
			$rows[] = array('source' => 'order_id', 'destination' => '');
			self::store_draft($rows);
			self::redirect();
		}

		if ('save' === $action) {
			$errors = MappingValidator::validate_rows($rows);

			if (array() !== $errors) {
				self::store_draft($rows);
				self::notice('error', implode(' ', $errors));
			} else {
				$settings            = Settings::get();
				$settings['mapping'] = $rows;
				Settings::save($settings);
				self::notice('success', __('Mapping saved.', 'storedispatcher-outbound-integration-for-woocommerce'));
			}

			self::redirect();
		}

		$order_id = absint($_POST['order_id'] ?? 0);
		$order    = wc_get_order($order_id);

		if (! $order) {
			self::notice('error', __('Choose a valid order.', 'storedispatcher-outbound-integration-for-woocommerce'));
			self::redirect();
		}

		if ('send' === $action) {
			$log_id = IntegrationLogger::create('order.test', 'order', $order_id, true);
			SendIntegrationJob::enqueue($log_id, $order_id, 'order.test', true);
			self::notice('success', __('Test request queued. View its result in Logs.', 'storedispatcher-outbound-integration-for-woocommerce'));
			self::redirect();
		}

		set_transient(
			'wcac_mapping_payload_' . get_current_user_id(),
			wp_json_encode((new MappingEngine())->map($order, Settings::get()['mapping']), JSON_PRETTY_PRINT),
			60
		);
		self::redirect();
	}

	public static function render_content(): void
	{
		$settings = Settings::get();
		$draft    = get_transient(self::draft_key());
		delete_transient(self::draft_key());
		$rows = is_array($draft)
			? $draft
			: (is_array($settings['mapping'] ?? null) ? $settings['mapping'] : Settings::default_mapping());

		if (array() === $rows) {
			$rows = array(array('source' => 'order_id', 'destination' => ''));
		}

		$payload = get_transient('wcac_mapping_payload_' . get_current_user_id());
		delete_transient('wcac_mapping_payload_' . get_current_user_id());
		?>
		<?php self::render_notice(); ?>
		<form method="post">
			<?php wp_nonce_field('wcac_mapping', 'wcac_mapping_nonce'); ?>
			<h2><?php esc_html_e('Order Mapping', 'storedispatcher-outbound-integration-for-woocommerce'); ?></h2>
			<p><?php esc_html_e('Use dot paths for nested values and items[].field for order line items.', 'storedispatcher-outbound-integration-for-woocommerce'); ?></p>
			<table class="widefat wcac-mapping-table">
				<thead>
					<tr>
						<th><?php esc_html_e('WooCommerce field', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
						<th><?php esc_html_e('API field', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
						<th><?php esc_html_e('Actions', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					</tr>
				</thead>
				<tbody id="wcac-mapping-rows">
					<?php foreach ($rows as $index => $row) : ?>
						<?php self::render_row((int) $index, (string) ($row['source'] ?? ''), (string) ($row['destination'] ?? '')); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="wcac-add-mapping-row"><?php esc_html_e('Add row', 'storedispatcher-outbound-integration-for-woocommerce'); ?></button>
				<noscript><button class="button" name="wcac_mapping_action" value="add_row"><?php esc_html_e('Add row', 'storedispatcher-outbound-integration-for-woocommerce'); ?></button></noscript>
				<button class="button button-primary" name="wcac_mapping_action" value="save"><?php esc_html_e('Save mapping', 'storedispatcher-outbound-integration-for-woocommerce'); ?></button>
			</p>
		</form>
		<template id="wcac-mapping-row-template">
			<?php self::render_row('__INDEX__', 'order_id', ''); ?>
		</template>
		<form method="post">
			<hr />
			<h2><?php esc_html_e('Test Mapping', 'storedispatcher-outbound-integration-for-woocommerce'); ?></h2>
			<p class="description"><?php esc_html_e('Preview and test send use the saved mapping, not unsaved table edits.', 'storedispatcher-outbound-integration-for-woocommerce'); ?></p>
			<?php wp_nonce_field('wcac_mapping', 'wcac_mapping_nonce'); ?>
			<select name="order_id">
				<?php foreach (wc_get_orders(array('limit' => 50, 'orderby' => 'ID', 'order' => 'DESC', 'return' => 'objects')) as $order) : ?>
					<option value="<?php echo esc_attr((string) $order->get_id()); ?>">#<?php echo esc_html($order->get_order_number()); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="button" name="wcac_mapping_action" value="preview"><?php esc_html_e('Test Mapping', 'storedispatcher-outbound-integration-for-woocommerce'); ?></button>
			<button class="button" name="wcac_mapping_action" value="send"><?php esc_html_e('Send Test Request', 'storedispatcher-outbound-integration-for-woocommerce'); ?></button>
		</form>
		<?php if (is_string($payload)) : ?>
			<h2><?php esc_html_e('Generated Payload', 'storedispatcher-outbound-integration-for-woocommerce'); ?></h2>
			<pre style="background-color: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code><?php echo esc_html($payload); ?></code></pre>
		<?php endif; ?>
		<?php
	}

	private static function render_row(int|string $index, string $source, string $destination): void
	{
		?>
		<tr class="wcac-mapping-row">
			<td>
				<select name="wcac_mapping[<?php echo esc_attr((string) $index); ?>][source]">
					<?php self::source_options($source); ?>
				</select>
			</td>
			<td>
				<input
					type="text"
					class="regular-text"
					name="wcac_mapping[<?php echo esc_attr((string) $index); ?>][destination]"
					value="<?php echo esc_attr($destination); ?>"
					placeholder="customer.email"
				/>
			</td>
			<td>
				<button type="button" class="button wcac-remove-mapping-row"><?php esc_html_e('Remove', 'storedispatcher-outbound-integration-for-woocommerce'); ?></button>
			</td>
		</tr>
		<?php
	}

	/**
	 * @return list<array{source: string, destination: string}>
	 */
	private static function rows_from_post(array $post): array
	{
		$rows = array();

		foreach (($post['wcac_mapping'] ?? array()) as $row) {
			if (! is_array($row)) {
				continue;
			}

			$source      = sanitize_key((string) ($row['source'] ?? ''));
			$destination = sanitize_text_field((string) ($row['destination'] ?? ''));

			if ('' === $source && '' === $destination) {
				continue;
			}

			$rows[] = array(
				'source'      => $source,
				'destination' => $destination,
			);
		}

		return $rows;
	}

	private static function source_options(string $selected): void
	{
		foreach (array(
			'order_id'            => __('Order ID', 'storedispatcher-outbound-integration-for-woocommerce'),
			'order_number'        => __('Order number', 'storedispatcher-outbound-integration-for-woocommerce'),
			'status'              => __('Status', 'storedispatcher-outbound-integration-for-woocommerce'),
			'currency'            => __('Currency', 'storedispatcher-outbound-integration-for-woocommerce'),
			'total'               => __('Total', 'storedispatcher-outbound-integration-for-woocommerce'),
			'date_created'        => __('Date created', 'storedispatcher-outbound-integration-for-woocommerce'),
			'billing_first_name'  => __('Billing first name', 'storedispatcher-outbound-integration-for-woocommerce'),
			'billing_last_name'   => __('Billing last name', 'storedispatcher-outbound-integration-for-woocommerce'),
			'billing_email'       => __('Billing email', 'storedispatcher-outbound-integration-for-woocommerce'),
			'billing_phone'       => __('Billing phone', 'storedispatcher-outbound-integration-for-woocommerce'),
			'item_product_id'     => __('Item product ID', 'storedispatcher-outbound-integration-for-woocommerce'),
			'item_sku'             => __('Item SKU', 'storedispatcher-outbound-integration-for-woocommerce'),
			'item_name'            => __('Item name', 'storedispatcher-outbound-integration-for-woocommerce'),
			'item_quantity'        => __('Item quantity', 'storedispatcher-outbound-integration-for-woocommerce'),
			'item_subtotal'        => __('Item subtotal', 'storedispatcher-outbound-integration-for-woocommerce'),
		) as $value => $label) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr($value),
				selected($selected, $value, false),
				esc_html($label)
			);
		}
	}

	private static function notice(string $type, string $message): void
	{
		set_transient('wcac_mapping_notice_' . get_current_user_id(), array($type, $message), 30);
	}

	/** @param list<array{source: string, destination: string}> $rows */
	private static function store_draft(array $rows): void
	{
		set_transient(self::draft_key(), $rows, 5 * MINUTE_IN_SECONDS);
	}

	private static function draft_key(): string
	{
		return 'wcac_mapping_draft_' . get_current_user_id();
	}

	private static function render_notice(): void
	{
		$notice = get_transient('wcac_mapping_notice_' . get_current_user_id());
		delete_transient('wcac_mapping_notice_' . get_current_user_id());

		if (is_array($notice)) {
			printf(
				'<div class="notice notice-%s"><p>%s</p></div>',
				esc_attr('success' === $notice[0] ? 'success' : 'error'),
				esc_html($notice[1])
			);
		}
	}

	private static function redirect(): never
	{
		wp_safe_redirect(admin_url('admin.php?page=' . SettingsPage::MENU_SLUG . '&tab=' . SettingsPage::TAB_MAPPING));
		exit;
	}
}
