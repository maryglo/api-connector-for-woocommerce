<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Admin;

use WooCommerceApiConnector\Api\ConnectionTester;
use WooCommerceApiConnector\Plugin;
use WooCommerceApiConnector\Settings\Sanitizer;
use WooCommerceApiConnector\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * WooCommerce → API Connector settings.
 */
final class SettingsPage
{
	public const MENU_SLUG = 'wcac-settings';
	public const TAB_CONNECTION = 'connection';
	public const TAB_MAPPING = 'mapping';
	public const TAB_LOGS = 'logs';

	private const NOTICE_TRANSIENT = 'wcac_admin_notice_';

	public static function register(): void
	{
		add_action('admin_menu', array(self::class, 'add_menu'), 60);
		add_action('admin_init', array(self::class, 'handle_post'));
		add_action('admin_enqueue_scripts', array(self::class, 'enqueue_assets'));
	}

	public static function add_menu(): void
	{
		add_submenu_page(
			'woocommerce',
			__('API Connector', Plugin::TEXT_DOMAIN),
			__('API Connector', Plugin::TEXT_DOMAIN),
			'manage_woocommerce',
			self::MENU_SLUG,
			array(self::class, 'render')
		);
	}

	public static function enqueue_assets(string $hook): void
	{
		if ('woocommerce_page_' . self::MENU_SLUG !== $hook) {
			return;
		}

		$style_path     = WCAC_PATH . 'assets/css/admin-settings.css';
		$script_path    = WCAC_PATH . 'assets/js/admin-settings.js';
		$style_version  = is_readable($style_path) ? (string) filemtime($style_path) : WCAC_VERSION;
		$script_version = is_readable($script_path) ? (string) filemtime($script_path) : WCAC_VERSION;

		wp_enqueue_style(
			'wcac-admin-settings',
			WCAC_URL . 'assets/css/admin-settings.css',
			array(),
			$style_version
		);

		wp_enqueue_script(
			'wcac-admin-settings',
			WCAC_URL . 'assets/js/admin-settings.js',
			array(),
			$script_version,
			true
		);

		wp_localize_script(
			'wcac-admin-settings',
			'wcacAdminSettings',
			array(
				'confirmRemoveMappingRow' => __('Remove this mapping row?', Plugin::TEXT_DOMAIN),
			)
		);
	}

	public static function handle_post(): void
	{
		if (! is_admin() || ! current_user_can('manage_woocommerce')) {
			return;
		}

		$saving = isset($_POST['wcac_save_settings']);
		$testing = isset($_POST['wcac_test_connection']);

		if (! $saving && ! $testing) {
			return;
		}

		check_admin_referer('wcac_settings_save', 'wcac_settings_nonce');

		$settings = Sanitizer::from_post(wp_unslash($_POST), Settings::get());

		if ($saving) {
			self::save_settings($settings);
			return;
		}

		self::test_connection($settings);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function save_settings(array $settings): void
	{
		$errors = Sanitizer::validate($settings);

		if (array() !== $errors) {
			self::store_notice('error', implode(' ', $errors));
			self::redirect();
		}

		Settings::save($settings);
		self::store_notice('success', __('Settings saved.', Plugin::TEXT_DOMAIN));
		self::redirect();
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function test_connection(array $settings): void
	{
		$errors = Sanitizer::validate_connection($settings);

		if (array() !== $errors) {
			self::store_notice('error', implode(' ', $errors));
			self::redirect();
		}

		try {
			$result = ConnectionTester::test($settings);

			self::store_notice(
				$result['success'] ? 'success' : 'error',
				$result['message']
			);
		} catch (\RuntimeException $exception) {
			self::store_notice(
				'error',
				__('Unable to test the connection because the API credential configuration is incomplete.', Plugin::TEXT_DOMAIN)
			);


			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log(
					'WCAC connection test failed: ' . $exception->getMessage()
				);
			}
		}
		self::redirect();
	}

	public static function render(): void
	{
		if (! current_user_can('manage_woocommerce')) {
			return;
		}

		$tab = self::active_tab();
		$settings = Settings::get();
		self::render_notice();
?>
		<div class="wrap woocommerce wcac-settings">
			<h1><?php echo esc_html__('API Connector', Plugin::TEXT_DOMAIN); ?></h1>
			<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
				<?php self::tab_link(self::TAB_CONNECTION, __('Connection', Plugin::TEXT_DOMAIN), $tab); ?>
				<?php self::tab_link(self::TAB_MAPPING, __('Mapping', Plugin::TEXT_DOMAIN), $tab); ?>
				<?php self::tab_link(self::TAB_LOGS, __('Logs', Plugin::TEXT_DOMAIN), $tab); ?>
			</nav>
			<?php
			if (self::TAB_MAPPING === $tab) {
				MappingPage::render_content();
				echo '</div>';
				return;
			}

			if (self::TAB_LOGS === $tab) {
				LogsPage::render_content();
				echo '</div>';
				return;
			}

			$order_created   = ! empty($settings['events']['order_created']);
			$order_completed = ! empty($settings['events']['order_completed']);
			$is_api_key      = Sanitizer::AUTH_API_KEY === ($settings['auth_method'] ?? '');
			?>
			<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)); ?>" autocomplete="off">
				<?php wp_nonce_field('wcac_settings_save', 'wcac_settings_nonce'); ?>

				<h2><?php echo esc_html__('Connection', Plugin::TEXT_DOMAIN); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wcac_api_name"><?php echo esc_html__('API Name', Plugin::TEXT_DOMAIN); ?></label>
						</th>
						<td>
							<input
								name="wcac_settings[api_name]"
								id="wcac_api_name"
								type="text"
								class="regular-text"
								value="<?php echo esc_attr((string) $settings['api_name']); ?>"
								required />
							<p class="description"><?php echo esc_html__('Label used in the admin UI and logs.', Plugin::TEXT_DOMAIN); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wcac_base_url"><?php echo esc_html__('Base URL', Plugin::TEXT_DOMAIN); ?></label>
						</th>
						<td>
							<input
								name="wcac_settings[base_url]"
								id="wcac_base_url"
								type="url"
								class="regular-text"
								placeholder="https://api.example.com"
								value="<?php echo esc_attr((string) $settings['base_url']); ?>"
								required />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wcac_health_path"><?php echo esc_html__('Health path', Plugin::TEXT_DOMAIN); ?></label>
						</th>
						<td>
							<input
								name="wcac_settings[health_path]"
								id="wcac_health_path"
								type="text"
								class="regular-text"
								placeholder="<?php echo esc_attr__('/ (base URL)', Plugin::TEXT_DOMAIN); ?>"
								value="<?php echo esc_attr((string) $settings['health_path']); ?>" />
							<p class="description"><?php echo esc_html__('Optional path for Test Connection. Leave empty to request the base URL.', Plugin::TEXT_DOMAIN); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wcac_auth_method"><?php echo esc_html__('Authentication', Plugin::TEXT_DOMAIN); ?></label>
						</th>
						<td>
							<select name="wcac_settings[auth_method]" id="wcac_auth_method">
								<option value="<?php echo esc_attr(Sanitizer::AUTH_BEARER); ?>" <?php selected($settings['auth_method'], Sanitizer::AUTH_BEARER); ?>>
									<?php echo esc_html__('Bearer Token', Plugin::TEXT_DOMAIN); ?>
								</option>
								<option value="<?php echo esc_attr(Sanitizer::AUTH_API_KEY); ?>" <?php selected($settings['auth_method'], Sanitizer::AUTH_API_KEY); ?>>
									<?php echo esc_html__('API Key', Plugin::TEXT_DOMAIN); ?>
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wcac_token"><?php echo esc_html__('API Key / Token', Plugin::TEXT_DOMAIN); ?></label>
						</th>
						<td>
							<input
								name="wcac_settings[token]"
								id="wcac_token"
								type="password"
								class="regular-text"
								value=""
								autocomplete="new-password"
								placeholder="<?php echo Settings::has_token() ? esc_attr__('Saved - leave blank to keep', Plugin::TEXT_DOMAIN) : ''; ?>" />
							<?php if (Settings::has_token()) : ?>
								<p class="description"><?php echo esc_html__('A token is saved. Leave this field blank to keep it.', Plugin::TEXT_DOMAIN); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr class="wcac-api-key-header" <?php echo $is_api_key ? '' : 'hidden'; ?>>
						<th scope="row">
							<label for="wcac_api_key_header"><?php echo esc_html__('API key header', Plugin::TEXT_DOMAIN); ?></label>
						</th>
						<td>
							<input
								name="wcac_settings[api_key_header]"
								id="wcac_api_key_header"
								type="text"
								class="regular-text"
								value="<?php echo esc_attr((string) $settings['api_key_header']); ?>" />
							<p class="description"><?php echo esc_html__('HTTP header that carries the API key. Default: X-API-Key.', Plugin::TEXT_DOMAIN); ?></p>
						</td>
					</tr>
				</table>

				<p>
					<button type="submit" name="wcac_test_connection" class="button" value="1">
						<?php echo esc_html__('Test Connection', Plugin::TEXT_DOMAIN); ?>
					</button>
				</p>

				<h2><?php echo esc_html__('Events', Plugin::TEXT_DOMAIN); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__('Orders', Plugin::TEXT_DOMAIN); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="wcac_settings[events][order_created]" value="1" <?php checked($order_created); ?> />
									<?php echo esc_html__('Send order when created', Plugin::TEXT_DOMAIN); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" name="wcac_settings[events][order_completed]" value="1" <?php checked($order_completed); ?> />
									<?php echo esc_html__('Send order when completed', Plugin::TEXT_DOMAIN); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Customers', Plugin::TEXT_DOMAIN); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" disabled />
									<?php echo esc_html__('Send customer when created', Plugin::TEXT_DOMAIN); ?>
								</label>
								<p class="description"><?php echo esc_html__('Coming in v0.2.', Plugin::TEXT_DOMAIN); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Products', Plugin::TEXT_DOMAIN); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" disabled />
									<?php echo esc_html__('Send product when created', Plugin::TEXT_DOMAIN); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" disabled />
									<?php echo esc_html__('Send product when updated', Plugin::TEXT_DOMAIN); ?>
								</label>
								<p class="description"><?php echo esc_html__('Coming in v0.2.', Plugin::TEXT_DOMAIN); ?></p>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__('Endpoints', Plugin::TEXT_DOMAIN); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wcac_order_endpoint"><?php echo esc_html__('Order Endpoint', Plugin::TEXT_DOMAIN); ?></label>
						</th>
						<td>
							<code class="wcac-http-method">POST</code>
							<input
								name="wcac_settings[order_endpoint]"
								id="wcac_order_endpoint"
								type="text"
								class="regular-text"
								value="<?php echo esc_attr((string) $settings['order_endpoint']); ?>" />
							<p class="description"><?php echo esc_html__('Path relative to the base URL.', Plugin::TEXT_DOMAIN); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wcac_customer_endpoint"><?php echo esc_html__('Customer Endpoint', Plugin::TEXT_DOMAIN); ?></label>
						</th>
						<td>
							<code class="wcac-http-method">POST</code>
							<input
								name="wcac_settings[customer_endpoint]"
								id="wcac_customer_endpoint"
								type="text"
								class="regular-text"
								value="<?php echo esc_attr((string) $settings['customer_endpoint']); ?>" />
							<p class="description"><?php echo esc_html__('Stored for v0.2 customer events. Path relative to the base URL.', Plugin::TEXT_DOMAIN); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="wcac_save_settings" class="button-primary" value="1">
						<?php echo esc_html__('Save changes', Plugin::TEXT_DOMAIN); ?>
					</button>
				</p>
			</form>
		</div>
<?php
	}

	private static function store_notice(string $type, string $message): void
	{
		set_transient(self::NOTICE_TRANSIENT . get_current_user_id(), array(
			'type'    => $type,
			'message' => $message,
		), 30);
	}

	private static function render_notice(): void
	{
		$notice = get_transient(self::NOTICE_TRANSIENT . get_current_user_id());
		delete_transient(self::NOTICE_TRANSIENT . get_current_user_id());

		if (! is_array($notice) || empty($notice['message'])) {
			return;
		}

		$class = 'success' === ($notice['type'] ?? '') ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>';
		echo esc_html((string) $notice['message']);
		echo '</p></div>';
	}

	private static function redirect(): void
	{
		wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG));
		exit;
	}

	private static function active_tab(): string
	{
		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : self::TAB_CONNECTION;

		return in_array($tab, array(self::TAB_CONNECTION, self::TAB_MAPPING, self::TAB_LOGS), true)
			? $tab
			: self::TAB_CONNECTION;
	}

	private static function tab_link(string $tab, string $label, string $active_tab): void
	{
		$url = add_query_arg(array('page' => self::MENU_SLUG, 'tab' => $tab), admin_url('admin.php'));
		$class = self::TAB_CONNECTION === $tab ? 'nav-tab' : 'nav-tab';
		$class .= $tab === $active_tab ? ' nav-tab-active' : '';
		printf('<a href="%s" class="%s">%s</a>', esc_url($url), esc_attr($class), esc_html($label));
	}
}
