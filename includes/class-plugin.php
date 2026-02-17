<?php
namespace WBCOM\WBDPP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WBDPP_PATH . 'includes/class-settings.php';
require_once WBDPP_PATH . 'includes/class-rules.php';
require_once WBDPP_PATH . 'includes/class-balance-ops.php';
require_once WBDPP_PATH . 'includes/class-product-settings.php';
require_once WBDPP_PATH . 'includes/class-cart.php';
require_once WBDPP_PATH . 'includes/class-order-manager.php';
require_once WBDPP_PATH . 'includes/class-reminders.php';
require_once WBDPP_PATH . 'includes/class-emails.php';
require_once WBDPP_PATH . 'includes/class-account.php';
require_once WBDPP_PATH . 'includes/class-admin-order.php';
require_once WBDPP_PATH . 'includes/class-admin-reports.php';
require_once WBDPP_PATH . 'includes/class-admin-ui.php';
require_once WBDPP_PATH . 'includes/class-cli.php';

final class Plugin {

	/**
	 * Settings page instance.
	 *
	 * @var \WC_Settings_Page|null
	 */
	private static $settings_page = null;

	/**
	 * Boot plugin.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'woocommerce_missing_notice' ) );
			return;
		}

		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'register_settings_page' ) );
		}

		( new Product_Settings() )->register();
		( new Cart() )->register();
		( new Order_Manager() )->register();
		( new Account() )->register();
		( new Admin_Order() )->register();
		( new Admin_Reports() )->register();
		( new Admin_UI() )->register();
		Reminders::register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			CLI::register();
		}
	}

	/**
	 * Display WooCommerce missing notice.
	 *
	 * @return void
	 */
	public static function woocommerce_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'WB Deposits & Partial Payments for WooCommerce requires WooCommerce to be active.', 'wb-deposits-partial-payments-for-woocommerce' );
		echo '</p></div>';
	}

	/**
	 * Register WooCommerce settings page.
	 *
	 * @param array<int, mixed> $pages Existing pages.
	 * @return array<int, mixed>
	 */
	public static function register_settings_page( array $pages ): array {
		if ( ! class_exists( 'WC_Settings_Page' ) ) {
			return $pages;
		}

		foreach ( $pages as $page ) {
			if ( is_object( $page ) && isset( $page->id ) && 'wbdpp' === (string) $page->id ) {
				return $pages;
			}
		}

		$settings_file = WBDPP_PATH . 'includes/class-settings-page.php';
		if ( ! is_readable( $settings_file ) ) {
			return $pages;
		}

		require_once $settings_file;

		$settings_class = __NAMESPACE__ . '\\Settings_Page';
		if ( ! class_exists( $settings_class ) ) {
			return $pages;
		}

		if ( ! self::$settings_page ) {
			self::$settings_page = new $settings_class();
		}

		if ( self::$settings_page instanceof \WC_Settings_Page ) {
			$pages[] = self::$settings_page;
		}

		return $pages;
	}
}
