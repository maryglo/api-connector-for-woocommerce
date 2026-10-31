<?php
/**
 * Plugin Name:       StoreDispatcher - Outbound Integration for WooCommerce
 * Plugin URI:        https://github.com/maryglo/api-connector-for-woocommerce
 * Description:       Send WooCommerce order data to external REST APIs with configurable authentication, field mapping, retries, and request logs.
 * Version:           0.1.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Mary Glo De La Pedra
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       storedispatcher-outbound-integration-for-woocommerce
 * Domain Path:       /languages
 *
 * @package WooCommerceApiConnector
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('WCAC_VERSION', '0.1.1');
define('WCAC_FILE', __FILE__);
define('WCAC_PATH', plugin_dir_path(__FILE__));
define('WCAC_URL', plugin_dir_url(__FILE__));

$wcac_autoload = WCAC_PATH . 'vendor/autoload.php';

if (! is_readable($wcac_autoload)) {
	add_action(
		'admin_notices',
		static function (): void {
			if (! current_user_can('activate_plugins')) {
				return;
			}

			echo '<div class="notice notice-error"><p>';
			echo esc_html__(
				'StoreDispatcher - Outbound Integration for WooCommerce cannot load because its Composer autoload file is missing. Install the plugin using the release package.',
				'storedispatcher-outbound-integration-for-woocommerce'
			);
			echo '</p></div>';
		}
	);

	return;
}

require_once $wcac_autoload;

use WooCommerceApiConnector\Install\Activator;
use WooCommerceApiConnector\Install\Deactivator;
use WooCommerceApiConnector\Plugin;

register_activation_hook(WCAC_FILE, static fn () => Activator::activate());
register_deactivation_hook(WCAC_FILE, static fn () => Deactivator::deactivate());

Plugin::instance()->boot();
