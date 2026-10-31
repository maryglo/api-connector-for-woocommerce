<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Admin;

use WooCommerceApiConnector\Logging\IntegrationLogger;
use WooCommerceApiConnector\Plugin;

defined('ABSPATH') || exit;

/**
 * Integration logs list and detail (v0.1).
 */
final class LogsPage
{
	public static function register(): void
	{
	}

	public static function render_content(): void
	{
		if (! current_user_can('manage_woocommerce')) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Viewing a log detail is read-only.
		$log_id = absint(wp_unslash($_GET['log_id'] ?? 0));

		if ($log_id) {
			self::detail($log_id);
			return;
		}

		$logs_url = admin_url('admin.php?page=' . SettingsPage::MENU_SLUG . '&tab=' . SettingsPage::TAB_LOGS);
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__('ID', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Event', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Resource', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Status', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Attempts', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Updated', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$logs = IntegrationLogger::recent();
				if (array() === $logs) :
					?>
					<tr>
						<td colspan="6"><?php esc_html_e('No integration logs yet.', 'storedispatcher-outbound-integration-for-woocommerce'); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ($logs as $log) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url(add_query_arg('log_id', (int) $log->id, $logs_url)); ?>">
									<?php echo esc_html((string) $log->id); ?>
								</a>
							</td>
							<td><?php echo esc_html($log->event_type); ?></td>
							<td><?php echo esc_html($log->resource_type . ' #' . $log->resource_id); ?></td>
							<td><?php echo esc_html($log->status); ?></td>
							<td><?php echo esc_html((string) $log->attempts); ?></td>
							<td><?php echo esc_html($log->updated_at); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	private static function detail(int $id): void
	{
		$log = IntegrationLogger::find($id);

		if (! $log) {
			wp_die(esc_html__('Log entry not found.', 'storedispatcher-outbound-integration-for-woocommerce'));
		}

		$back_url     = admin_url('admin.php?page=' . SettingsPage::MENU_SLUG . '&tab=' . SettingsPage::TAB_LOGS);
		$resource_url = self::resource_admin_url((string) $log->resource_type, (int) $log->resource_id);
		$is_test      = ! empty($log->is_test);
		?>
		<h2><?php esc_html_e('Integration Log', 'storedispatcher-outbound-integration-for-woocommerce'); ?> #<?php echo esc_html((string) $log->id); ?></h2>
		<p>
			<a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Back to logs', 'storedispatcher-outbound-integration-for-woocommerce'); ?></a>
		</p>
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e('Event', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<td><?php echo esc_html($log->event_type); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e('Resource', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<td>
						<?php if ($resource_url) : ?>
							<a href="<?php echo esc_url($resource_url); ?>">
								<?php echo esc_html($log->resource_type . ' #' . $log->resource_id); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html($log->resource_type . ' #' . $log->resource_id); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e('Status', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<td><?php echo esc_html($log->status); ?> (<?php echo esc_html((string) $log->attempts); ?> <?php esc_html_e('attempts', 'storedispatcher-outbound-integration-for-woocommerce'); ?>)</td>
				</tr>
				<tr>
					<th><?php esc_html_e('Test request', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<td><?php echo esc_html($is_test ? __('Yes', 'storedispatcher-outbound-integration-for-woocommerce') : __('No', 'storedispatcher-outbound-integration-for-woocommerce')); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e('Created', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<td><?php echo esc_html((string) $log->created_at); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e('Updated', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<td><?php echo esc_html((string) $log->updated_at); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e('Request', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<td>
						<?php echo esc_html($log->request_method . ' ' . $log->request_url); ?>
						<pre><?php echo esc_html((string) $log->request_body); ?></pre>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e('Response', 'storedispatcher-outbound-integration-for-woocommerce'); ?></th>
					<td>
						<?php echo esc_html((string) $log->response_code); ?>
						<pre><?php echo esc_html((string) $log->response_body); ?></pre>
						<?php echo esc_html((string) $log->error_message); ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	private static function resource_admin_url(string $resource_type, int $resource_id): string
	{
		if ('order' !== $resource_type || $resource_id <= 0 || ! function_exists('wc_get_order')) {
			return '';
		}

		$order = wc_get_order($resource_id);

		if (! $order || ! is_object($order) || ! method_exists($order, 'get_edit_order_url')) {
			return '';
		}

		$url = $order->get_edit_order_url();

		return is_string($url) ? $url : '';
	}
}
