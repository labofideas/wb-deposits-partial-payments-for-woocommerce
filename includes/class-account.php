<?php
namespace WBCOM\WBDPP;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Account {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_pay_balance_action' ), 20, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_balance_summary' ), 20 );
	}

	/**
	 * Add pay balance action on parent deposit order.
	 *
	 * @param array<string, array<string, string>> $actions Actions.
	 * @param WC_Order                              $order Order.
	 * @return array<string, array<string, string>>
	 */
	public function add_pay_balance_action( array $actions, WC_Order $order ): array {
		$balance_id = (int) $order->get_meta( '_wb_balance_order_id', true );
		if ( $balance_id <= 0 ) {
			return $actions;
		}

		$balance_order = wc_get_order( $balance_id );
		if ( ! $balance_order instanceof WC_Order ) {
			return $actions;
		}

		if ( ! in_array( $balance_order->get_status(), array( 'pending', 'on-hold', 'failed' ), true ) ) {
			return $actions;
		}

		$actions['wbdpp_pay_balance'] = array(
			'url'  => $balance_order->get_checkout_payment_url(),
			'name' => esc_html__( 'Pay balance', 'wb-deposits-partial-payments-for-woocommerce' ),
		);

		return $actions;
	}

	/**
	 * Show deposit/balance summary on order details.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public function render_balance_summary( WC_Order $order ): void {
		$balance_id = (int) $order->get_meta( '_wb_balance_order_id', true );

		if ( $balance_id <= 0 && 'yes' === (string) $order->get_meta( '_wbdpp_is_balance_order', true ) ) {
			$parent_id = (int) $order->get_meta( '_wb_deposit_parent_order_id', true );
			if ( $parent_id > 0 ) {
				$parent = wc_get_order( $parent_id );
				if ( $parent instanceof WC_Order ) {
					$balance_id = $order->get_id();
					$order      = $parent;
				}
			}
		}

		if ( $balance_id <= 0 ) {
			return;
		}

		$balance_order = wc_get_order( $balance_id );
		if ( ! $balance_order instanceof WC_Order ) {
			return;
		}

		$due_timestamp = (int) $order->get_meta( '_wb_balance_due_date', true );
		if ( $due_timestamp <= 0 ) {
			$due_timestamp = (int) $balance_order->get_meta( '_wb_balance_due_date', true );
		}

		echo '<section class="woocommerce-order-details" style="margin-top:16px;">';
		echo '<h2 class="woocommerce-order-details__title">' . esc_html__( 'Deposit Summary', 'wb-deposits-partial-payments-for-woocommerce' ) . '</h2>';
		echo '<table class="woocommerce-table shop_table">';
		echo '<tr><th>' . esc_html__( 'Paid deposit', 'wb-deposits-partial-payments-for-woocommerce' ) . '</th><td>' . wp_kses_post( wc_price( (float) $order->get_total() ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Remaining balance', 'wb-deposits-partial-payments-for-woocommerce' ) . '</th><td>' . wp_kses_post( wc_price( (float) $balance_order->get_total() ) ) . '</td></tr>';
		if ( $due_timestamp > 0 ) {
			echo '<tr><th>' . esc_html__( 'Due date', 'wb-deposits-partial-payments-for-woocommerce' ) . '</th><td>' . esc_html( wp_date( get_option( 'date_format' ), $due_timestamp ) ) . '</td></tr>';
		}
		echo '<tr><th>' . esc_html__( 'Balance order', 'wb-deposits-partial-payments-for-woocommerce' ) . '</th><td>#' . esc_html( (string) $balance_order->get_id() ) . '</td></tr>';
		echo '</table>';

		if ( in_array( $balance_order->get_status(), array( 'pending', 'on-hold', 'failed' ), true ) ) {
			echo '<p><a class="button" href="' . esc_url( $balance_order->get_checkout_payment_url() ) . '">' . esc_html__( 'Pay balance', 'wb-deposits-partial-payments-for-woocommerce' ) . '</a></p>';
		}
		echo '</section>';
	}
}
