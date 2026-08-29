<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Admin;

use WooCommerceApiConnector\Jobs\SendIntegrationJob;
use WooCommerceApiConnector\Logging\IntegrationLogger;
use WooCommerceApiConnector\Mapping\MappingEngine;
use WooCommerceApiConnector\Mapping\MappingValidator;
use WooCommerceApiConnector\Plugin;
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
		if (! is_admin() || ! current_user_can('manage_woocommerce') || empty($_POST['wcac_mapping_action'])) { return; }
		check_admin_referer('wcac_mapping', 'wcac_mapping_nonce');
		$action = sanitize_key((string) $_POST['wcac_mapping_action']);
		if ('save' === $action) {
			$rows = self::rows_from_post(wp_unslash($_POST));
			$errors = MappingValidator::validate_rows($rows);
			if (array() !== $errors) { self::notice('error', implode(' ', $errors)); } else { $settings = Settings::get(); $settings['mapping'] = $rows; Settings::save($settings); self::notice('success', __('Mapping saved.', Plugin::TEXT_DOMAIN)); }
			self::redirect();
		}
		$order_id = absint($_POST['order_id'] ?? 0);
		$order = wc_get_order($order_id);
		if (! $order) { self::notice('error', __('Choose a valid order.', Plugin::TEXT_DOMAIN)); self::redirect(); }
		if ('send' === $action) { $log_id = IntegrationLogger::create('order.test', 'order', $order_id, true); SendIntegrationJob::enqueue($log_id, $order_id, 'order.test', true); self::notice('success', __('Test request queued. View its result in Logs.', Plugin::TEXT_DOMAIN)); self::redirect(); }
		set_transient('wcac_mapping_payload_' . get_current_user_id(), wp_json_encode((new MappingEngine())->map($order, Settings::get()['mapping']), JSON_PRETTY_PRINT), 60);
		self::redirect();
	}

	public static function render_content(): void
	{
		$settings = Settings::get(); $rows = $settings['mapping']; $payload = get_transient('wcac_mapping_payload_' . get_current_user_id()); delete_transient('wcac_mapping_payload_' . get_current_user_id());
		?>
		<?php self::render_notice(); ?>
		<form method="post"><?php wp_nonce_field('wcac_mapping', 'wcac_mapping_nonce'); ?><h2><?php esc_html_e('Order Mapping', Plugin::TEXT_DOMAIN); ?></h2><p><?php esc_html_e('Use dot paths for nested values and items[].field for order line items.', Plugin::TEXT_DOMAIN); ?></p><table class="widefat"><thead><tr><th><?php esc_html_e('WooCommerce field', Plugin::TEXT_DOMAIN); ?></th><th><?php esc_html_e('API field', Plugin::TEXT_DOMAIN); ?></th></tr></thead><tbody><?php foreach ($rows as $index => $row) : ?><tr><td><select name="wcac_mapping[<?php echo esc_attr((string) $index); ?>][source]"><?php self::source_options((string) $row['source']); ?></select></td><td><input class="regular-text" name="wcac_mapping[<?php echo esc_attr((string) $index); ?>][destination]" value="<?php echo esc_attr((string) $row['destination']); ?>" /></td></tr><?php endforeach; ?></tbody></table><p><button class="button button-primary" name="wcac_mapping_action" value="save"><?php esc_html_e('Save mapping', Plugin::TEXT_DOMAIN); ?></button></p></form>
		<form method="post"><hr /><h2><?php esc_html_e('Test Mapping', Plugin::TEXT_DOMAIN); ?></h2><?php wp_nonce_field('wcac_mapping', 'wcac_mapping_nonce'); ?><select name="order_id"><?php foreach (wc_get_orders(array('limit'=>50,'orderby'=>'ID','order'=>'DESC','return'=>'objects')) as $order) : ?><option value="<?php echo esc_attr((string) $order->get_id()); ?>">#<?php echo esc_html($order->get_order_number()); ?></option><?php endforeach; ?></select> <button class="button" name="wcac_mapping_action" value="preview"><?php esc_html_e('Test Mapping', Plugin::TEXT_DOMAIN); ?></button> <button class="button" name="wcac_mapping_action" value="send"><?php esc_html_e('Send Test Request', Plugin::TEXT_DOMAIN); ?></button></form><?php if (is_string($payload)) : ?><h2><?php esc_html_e('Generated Payload', Plugin::TEXT_DOMAIN); ?></h2><pre><?php echo esc_html($payload); ?></pre><?php endif; ?><?php
	}

	/** @return list<array{source: string, destination: string}> */
	private static function rows_from_post(array $post): array { $rows = array(); foreach (($post['wcac_mapping'] ?? array()) as $row) { if (is_array($row)) { $rows[] = array('source'=>sanitize_key((string)($row['source']??'')), 'destination'=>sanitize_text_field((string)($row['destination']??''))); } } return $rows; }
	private static function source_options(string $selected): void { foreach (array('order_id'=>'Order ID','order_number'=>'Order number','status'=>'Status','currency'=>'Currency','total'=>'Total','date_created'=>'Date created','billing_first_name'=>'Billing first name','billing_last_name'=>'Billing last name','billing_email'=>'Billing email','billing_phone'=>'Billing phone','item_product_id'=>'Item product ID','item_sku'=>'Item SKU','item_name'=>'Item name','item_quantity'=>'Item quantity','item_subtotal'=>'Item subtotal') as $value=>$label) { printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($selected,$value,false), esc_html__($label,Plugin::TEXT_DOMAIN)); } }
	private static function notice(string $type, string $message): void { set_transient('wcac_mapping_notice_' . get_current_user_id(), array($type,$message), 30); }
	private static function render_notice(): void { $notice=get_transient('wcac_mapping_notice_'.get_current_user_id()); delete_transient('wcac_mapping_notice_'.get_current_user_id()); if (is_array($notice)) { printf('<div class="notice notice-%s"><p>%s</p></div>', esc_attr('success'===$notice[0]?'success':'error'), esc_html($notice[1])); } }
	private static function redirect(): never { wp_safe_redirect(admin_url('admin.php?page=' . SettingsPage::MENU_SLUG . '&tab=' . SettingsPage::TAB_MAPPING)); exit; }
}
