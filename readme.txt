=== WooCommerce Integration Connector ===
Contributors: woocommerce-api-connector
Tags: woocommerce, rest-api, integration, orders
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send WooCommerce orders, customers, and products to external REST APIs using configurable field mappings.

== Description ==

Configurable WooCommerce plugin that maps store data to custom JSON and posts it to an external REST API. HTTP calls run through Action Scheduler with retries and integration logs.

See docs/PRD.md for product requirements.

== Installation ==

1. Run `composer install` in the plugin directory if you cloned from source.
2. Upload the plugin to `/wp-content/plugins/woocommerce-api-connector`.
3. Activate through the Plugins screen. WooCommerce must already be active.

== Changelog ==

= 0.1.0 =
* Plugin skeleton, Composer PSR-4 autoload, activation and deactivation.
* Settings Page
* API URL
* Bearer/API-key authentication
* Order event
* Order field mapping
* Customer mapping
* Nested JSON
* HTTP client
* Logging
* Test connection
* Test mapping
* Action Scheduler
* Retry
