<?php
namespace WBCOM\WBDPP;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Order {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_shop_order', array( $this, 'register_meta_box' ) );
		add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( $this, 'register_meta_box' ) );
		add_action( 'admin_post_wbdpp_manage_balance', array( $this, 'handle_admin_action' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_bulk_actions' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'render_bulk_action_notice' ) );
	}

	/**
	 * Register order meta box.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'wbdpp-order-meta',
			esc_html__( 'WB Deposits', 'wb-deposits-partial-payments-for-woocommerce' ),
			array( $this, 'render_meta_box' ),
			null,
			'side',
			'default'
		);
	}

	/**
	 * Render order meta box.
	 *
	 * @param mixed $post_or_order_object Current object.
	 * @return void
	 */
	public function render_meta_box( $post_or_order_object ): void {
		$order = $this->resolve_order_from_screen_object( $post_or_order_object );
		if ( ! $order instanceof WC_Order ) {
			echo esc_html__( 'Order unavailable.', 'wb-deposits-partial-payments-for-woocommerce' );
			return;
		}

		$balance_order = $this->resolve_balance_order( $order );
		$parent_order  = $this->resolve_parent_order( $order );

		if ( ! $balance_order instanceof WC_Order && ! $parent_order instanceof WC_Order ) {
			echo esc_html__( 'No deposit relationship found for this order.', 'wb-deposits-partial-payments-for-woocommerce' );
			return;
		}

		if ( $balance_order instanceof WC_Order ) {
			echo '<p><strong>' . esc_html__( 'Balance Order:', 'wb-deposits-partial-payments-for-woocommerce' ) . '</strong> #' . esc_html( (string) $balance_order->get_id() ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Remaining:', 'wb-deposits-partial-payments-for-woocommerce' ) . '</strong> ' . wp_kses_post( wc_price( (float) $balance_order->get_total() ) ) . '</p>';
		}

		if ( $parent_order instanceof WC_Order ) {
			echo '<p><strong>' . esc_html__( 'Parent Deposit Order:', 'wb-deposits-partial-payments-for-woocommerce' ) . '</strong> #' . esc_html( (string) $parent_order->get_id() ) . '</p>';
		}

		$due_date  = $balance_order instanceof WC_Order ? (int) $balance_order->get_meta( '_wb_balance_due_date', true ) : 0;
		$due_value = $due_date > 0 ? wp_date( 'Y-m-d', $due_date ) : '';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wbdpp_manage_balance', 'wbdpp_nonce' );
		echo '<input type="hidden" name="action" value="wbdpp_manage_balance" />';
		echo '<input type="hidden" name="order_id" value="' . esc_attr( (string) $order->get_id() ) . '" />';
		if ( $balance_order instanceof WC_Order ) {
			echo '<input type="hidden" name="balance_order_id" value="' . esc_attr( (string) $balance_order->get_id() ) . '" />';
		}
		echo '<p><label for="wbdpp_due_date"><strong>' . esc_html__( 'Due date', 'wb-deposits-partial-payments-for-woocommerce' ) . '</strong></label><br />';
		echo '<input type="date" id="wbdpp_due_date" name="due_date" value="' . esc_attr( $due_value ) . '" /></p>';
		echo '<p><button type="submit" class="button" name="wbdpp_task" value="save_due_date">' . esc_html__( 'Save due date', 'wb-deposits-partial-payments-for-woocommerce' ) . '</button></p>';
		echo '<p><button type="submit" class="button" name="wbdpp_task" value="send_reminder">' . esc_html__( 'Send reminder now', 'wb-deposits-partial-payments-for-woocommerce' ) . '</button></p>';
		echo '<p><button type="submit" class="button button-primary" name="wbdpp_task" value="mark_paid">' . esc_html__( 'Mark balance as paid', 'wb-deposits-partial-payments-for-woocommerce' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Handle admin actions from meta box.
	 *
	 * @return void
	 */
	public function handle_admin_action(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wb-deposits-partial-payments-for-woocommerce' ) );
		}

		check_admin_referer( 'wbdpp_manage_balance', 'wbdpp_nonce' );

		$order_id         = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$balance_order_id = isset( $_POST['balance_order_id'] ) ? absint( wp_unslash( $_POST['balance_order_id'] ) ) : 0;
		$task             = isset( $_POST['wbdpp_task'] ) ? sanitize_text_field( wp_unslash( $_POST['wbdpp_task'] ) ) : '';

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		if ( $balance_order_id <= 0 ) {
			$balance_order_id = (int) $order->get_meta( '_wb_balance_order_id', true );
		}

		$balance_order = wc_get_order( $balance_order_id );
		if ( ! $balance_order instanceof WC_Order ) {
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		if ( 'save_due_date' === $task ) {
			$this->save_due_date( $order, $balance_order );
		}

		if ( 'send_reminder' === $task ) {
			Emails::send_balance_reminder( $balance_order, 0 );
			$balance_order->add_order_note( esc_html__( 'Manual balance reminder sent by admin.', 'wb-deposits-partial-payments-for-woocommerce' ) );
		}

		if ( 'mark_paid' === $task ) {
			$balance_order->payment_complete();
			$balance_order->add_order_note( esc_html__( 'Marked as paid manually by admin.', 'wb-deposits-partial-payments-for-woocommerce' ) );
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	/**
	 * Save due date change.
	 *
	 * @param WC_Order $parent_order Parent order.
	 * @param WC_Order $balance_order Balance order.
	 * @return void
	 */
	private function save_due_date( WC_Order $parent_order, WC_Order $balance_order ): void {
		$due_raw = isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '';
		if ( '' === $due_raw ) {
			return;
		}

		$dt = date_create_immutable( $due_raw . ' 23:59:59', wp_timezone() );
		if ( ! $dt instanceof \DateTimeImmutable ) {
			return;
		}

		$ts = (int) $dt->format( 'U' );

		$balance_order->update_meta_data( '_wb_balance_due_date', $ts );
		$balance_order->save();

		$parent_order->update_meta_data( '_wb_balance_due_date', $ts );
		$parent_order->save();

		$balance_order->add_order_note(
			sprintf(
				/* translators: %s: new due date. */
				esc_html__( 'Due date updated to %s by admin.', 'wb-deposits-partial-payments-for-woocommerce' ),
				wp_date( get_option( 'date_format' ), $ts )
			)
		);
	}

	/**
	 * Resolve WC_Order from screen object.
	 *
	 * @param mixed $post_or_order_object Screen object.
	 * @return WC_Order|null
	 */
	private function resolve_order_from_screen_object( $post_or_order_object ): ?WC_Order {
		if ( $post_or_order_object instanceof WC_Order ) {
			return $post_or_order_object;
		}

		if ( is_object( $post_or_order_object ) && isset( $post_or_order_object->ID ) ) {
			return wc_get_order( (int) $post_or_order_object->ID );
		}

		$order_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		if ( $order_id > 0 ) {
			return wc_get_order( $order_id );
		}

		return null;
	}

	/**
	 * Resolve balance order for order.
	 *
	 * @param WC_Order $order Order.
	 * @return WC_Order|null
	 */
	private function resolve_balance_order( WC_Order $order ): ?WC_Order {
		if ( 'yes' === (string) $order->get_meta( '_wbdpp_is_balance_order', true ) ) {
			return $order;
		}

		$balance_id = (int) $order->get_meta( '_wb_balance_order_id', true );
		if ( $balance_id <= 0 ) {
			return null;
		}

		$balance_order = wc_get_order( $balance_id );
		return $balance_order instanceof WC_Order ? $balance_order : null;
	}

	/**
	 * Resolve parent deposit order for order.
	 *
	 * @param WC_Order $order Order.
	 * @return WC_Order|null
	 */
	private function resolve_parent_order( WC_Order $order ): ?WC_Order {
		if ( 'yes' !== (string) $order->get_meta( '_wbdpp_is_balance_order', true ) ) {
			return $order;
		}

		$parent_id = (int) $order->get_meta( '_wb_deposit_parent_order_id', true );
		if ( $parent_id <= 0 ) {
			return null;
		}

		$parent_order = wc_get_order( $parent_id );
		return $parent_order instanceof WC_Order ? $parent_order : null;
	}

	/**
	 * Register custom bulk actions.
	 *
	 * @param array<string, string> $actions Existing actions.
	 * @return array<string, string>
	 */
	public function register_bulk_actions( array $actions ): array {
		$actions['wbdpp_cancel_overdue_now'] = esc_html__( 'WB: Cancel overdue balance orders now', 'wb-deposits-partial-payments-for-woocommerce' );
		return $actions;
	}

	/**
	 * Handle custom bulk actions.
	 *
	 * @param string     $redirect_to Redirect URL.
	 * @param string     $action Action key.
	 * @param array<int> $post_ids Selected order IDs.
	 * @return string
	 */
	public function handle_bulk_actions( string $redirect_to, string $action, array $post_ids ): string {
		unset( $post_ids );

		if ( 'wbdpp_cancel_overdue_now' !== $action ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect_to;
		}

		$cancelled = $this->cancel_overdue_balance_orders_now();

		return add_query_arg(
			array(
				'wbdpp_bulk_action' => 'cancel_overdue',
				'wbdpp_cancelled'   => $cancelled,
			),
			$redirect_to
		);
	}

	/**
	 * Show admin notice after bulk action.
	 *
	 * @return void
	 */
	public function render_bulk_action_notice(): void {
		if ( ! isset( $_REQUEST['wbdpp_bulk_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_REQUEST['wbdpp_bulk_action'] ) );
		if ( 'cancel_overdue' !== $action ) {
			return;
		}

		$count = isset( $_REQUEST['wbdpp_cancelled'] ) ? absint( wp_unslash( $_REQUEST['wbdpp_cancelled'] ) ) : 0;

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of orders. */
				__( 'WB Deposits: cancelled %d overdue balance order(s).', 'wb-deposits-partial-payments-for-woocommerce' ),
				$count
			)
		);
		echo '</p></div>';
	}

	/**
	 * Cancel overdue pending balance orders immediately.
	 *
	 * @return int
	 */
	private function cancel_overdue_balance_orders_now(): int {
		$result = Balance_Ops::cancel_overdue_balance_orders(
			array(
				'overdue_days'      => 0,
				'limit'             => 250,
				'source'            => 'bulk-admin',
				'reason'            => esc_html__( 'Cancelled manually via WB overdue balance bulk action.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'skip_setting_gate' => true,
			)
		);

		return (int) $result['count'];
	}
}
