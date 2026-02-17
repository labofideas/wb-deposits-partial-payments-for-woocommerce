<?php
namespace WBCOM\WBDPP;

use WC_Order;
use WC_Order_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Reports {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_report_page' ), 30 );
	}

	/**
	 * Add reports page under WooCommerce menu.
	 *
	 * @return void
	 */
	public function add_report_page(): void {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'WB Deposits Report', 'wb-deposits-partial-payments-for-woocommerce' ),
			esc_html__( 'WB Deposits Report', 'wb-deposits-partial-payments-for-woocommerce' ),
			'manage_woocommerce',
			'wbdpp-report',
			array( $this, 'render_report_page' )
		);
	}

	/**
	 * Render report page.
	 *
	 * @return void
	 */
	public function render_report_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$orders = $this->get_outstanding_balance_orders();
		$today  = strtotime( 'today midnight', time() );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WB Deposits Report', 'wb-deposits-partial-payments-for-woocommerce' ) . '</h1>';
		echo '<p>' . esc_html__( 'Outstanding and overdue balance orders.', 'wb-deposits-partial-payments-for-woocommerce' ) . '</p>';
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Balance Order', 'wb-deposits-partial-payments-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Parent Order', 'wb-deposits-partial-payments-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Customer', 'wb-deposits-partial-payments-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Remaining', 'wb-deposits-partial-payments-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Due Date', 'wb-deposits-partial-payments-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'wb-deposits-partial-payments-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $orders ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No outstanding balances found.', 'wb-deposits-partial-payments-for-woocommerce' ) . '</td></tr>';
		} else {
			foreach ( $orders as $order ) {
				$parent_id = (int) $order->get_meta( '_wb_deposit_parent_order_id', true );
				$due_ts    = (int) $order->get_meta( '_wb_balance_due_date', true );
				$is_over   = $due_ts > 0 && $due_ts < $today;
				$edit_link = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() );

				echo '<tr>';
				echo '<td><a href="' . esc_url( $edit_link ) . '">#' . esc_html( (string) $order->get_id() ) . '</a></td>';
				echo '<td>#' . esc_html( (string) $parent_id ) . '</td>';
				echo '<td>' . esc_html( trim( $order->get_formatted_billing_full_name() ) ?: $order->get_billing_email() ) . '</td>';
				echo '<td>' . wp_kses_post( wc_price( (float) $order->get_total() ) ) . '</td>';
				echo '<td>' . esc_html( $due_ts > 0 ? wp_date( get_option( 'date_format' ), $due_ts ) : '-' ) . '</td>';
				echo '<td>' . esc_html( $is_over ? __( 'Overdue', 'wb-deposits-partial-payments-for-woocommerce' ) : ucfirst( $order->get_status() ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Get outstanding balance orders.
	 *
	 * @return array<int, WC_Order>
	 */
	private function get_outstanding_balance_orders(): array {
		$query = new WC_Order_Query(
			array(
				'limit'      => 200,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'return'     => 'objects',
				'status'     => array( 'wc-pending', 'wc-on-hold', 'wc-failed' ),
				'meta_query' => array(
					array(
						'key'   => '_wbdpp_is_balance_order',
						'value' => 'yes',
					),
				),
			)
		);

		$orders = $query->get_orders();
		return is_array( $orders ) ? $orders : array();
	}
}
