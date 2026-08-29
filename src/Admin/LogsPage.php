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
	public static function register(): void {}
	public static function render_content(): void
	{
		if (! current_user_can('manage_woocommerce')) { return; }
		$log_id = absint($_GET['log_id'] ?? 0);
		if ($log_id) { self::detail($log_id); return; }
		?>
		<table class="widefat striped"><thead><tr><th>ID</th><th><?php esc_html_e('Event', Plugin::TEXT_DOMAIN); ?></th><th><?php esc_html_e('Resource', Plugin::TEXT_DOMAIN); ?></th><th><?php esc_html_e('Status', Plugin::TEXT_DOMAIN); ?></th><th><?php esc_html_e('Attempts', Plugin::TEXT_DOMAIN); ?></th><th><?php esc_html_e('Updated', Plugin::TEXT_DOMAIN); ?></th></tr></thead><tbody><?php foreach (IntegrationLogger::recent() as $log) : ?><tr><td><a href="<?php echo esc_url(admin_url('admin.php?page='.SettingsPage::MENU_SLUG.'&tab='.SettingsPage::TAB_LOGS.'&log_id='.(int)$log->id)); ?>"><?php echo esc_html((string)$log->id); ?></a></td><td><?php echo esc_html($log->event_type); ?></td><td><?php echo esc_html($log->resource_type.' #'.$log->resource_id); ?></td><td><?php echo esc_html($log->status); ?></td><td><?php echo esc_html((string)$log->attempts); ?></td><td><?php echo esc_html($log->updated_at); ?></td></tr><?php endforeach; ?></tbody></table><?php
	}
	private static function detail(int $id): void
	{
		$log = IntegrationLogger::find($id); if (! $log) { wp_die(esc_html__('Log entry not found.', Plugin::TEXT_DOMAIN)); }
		?>
		<h2><?php esc_html_e('Integration Log', Plugin::TEXT_DOMAIN); ?> #<?php echo esc_html((string)$log->id); ?></h2><p><a href="<?php echo esc_url(admin_url('admin.php?page='.SettingsPage::MENU_SLUG.'&tab='.SettingsPage::TAB_LOGS)); ?>">&larr; <?php esc_html_e('Back to logs', Plugin::TEXT_DOMAIN); ?></a></p><table class="widefat"><tbody><tr><th><?php esc_html_e('Event',Plugin::TEXT_DOMAIN); ?></th><td><?php echo esc_html($log->event_type); ?></td></tr><tr><th><?php esc_html_e('Status',Plugin::TEXT_DOMAIN); ?></th><td><?php echo esc_html($log->status); ?> (<?php echo esc_html((string)$log->attempts); ?> attempts)</td></tr><tr><th><?php esc_html_e('Request',Plugin::TEXT_DOMAIN); ?></th><td><?php echo esc_html($log->request_method.' '.$log->request_url); ?><pre><?php echo esc_html((string)$log->request_body); ?></pre></td></tr><tr><th><?php esc_html_e('Response',Plugin::TEXT_DOMAIN); ?></th><td><?php echo esc_html((string)$log->response_code); ?><pre><?php echo esc_html((string)$log->response_body); ?></pre><?php echo esc_html((string)$log->error_message); ?></td></tr></tbody></table><?php
	}
}
