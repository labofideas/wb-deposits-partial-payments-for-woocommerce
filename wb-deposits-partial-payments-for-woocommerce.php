<?php
/**
 * Plugin Name: WB Deposits & Partial Payments for WooCommerce
 * Plugin URI:  https://wbcomdesigns.com/
 * Description: Accept deposits, generate linked balance orders, and automate reminders for WooCommerce orders.
 * Version:     1.0.1
 * Author:      Wbcom Designs
 * Author URI:  https://wbcomdesigns.com/
 * Text Domain: wb-deposits-partial-payments-for-woocommerce
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 10.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WBDPP_FILE' ) ) {
	define( 'WBDPP_FILE', __FILE__ );
}

if ( ! defined( 'WBDPP_PATH' ) ) {
	define( 'WBDPP_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WBDPP_URL' ) ) {
	define( 'WBDPP_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WBDPP_VERSION' ) ) {
	define( 'WBDPP_VERSION', '1.0.1' );
}

require_once WBDPP_PATH . 'includes/class-plugin.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		\WBCOM\WBDPP\Plugin::init();
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( esc_html__( 'WB Deposits & Partial Payments for WooCommerce requires WooCommerce to be active.', 'wb-deposits-partial-payments-for-woocommerce' ) );
		}

		update_option( 'wbdpp_version', WBDPP_VERSION );
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'wbdpp_daily_maintenance' );
	}
);
