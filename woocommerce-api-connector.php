<?php
/**
 * Plugin Name:       WooCommerce Integration Connector
 * Plugin URI:        https://github.com/woocommerce-api-connector/woocommerce-api-connector
 * Description:       Send WooCommerce orders, customers, and products to external REST APIs using configurable field mappings.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Mary Glo De La Pedra
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woocommerce-api-connector
 * Domain Path:       /languages
 *
 * @package WooCommerceApiConnector
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('WCAC_VERSION', '0.1.0');
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
				'WooCommerce Integration Connector cannot load because Composer autoload is missing. Run composer install in the plugin directory.',
				'woocommerce-api-connector'
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
