<?php
namespace WBCOM\WBDPP;

use WC_Order;
use WC_Order_Item_Fee;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Order_Manager {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'create_balance_order' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'create_balance_order_from_store_api' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_status_notifications' ), 20, 4 );
	}

	/**
	 * Create linked balance order for Checkout Block/Store API orders.
	 *
	 * @param WC_Order|int|null $order Store API checkout order object or id.
	 * @return void
	 */
	public function create_balance_order_from_store_api( $order ): void {
		if ( $order instanceof WC_Order ) {
			$this->create_balance_order( (int) $order->get_id() );
			return;
		}

		if ( is_numeric( $order ) ) {
			$this->create_balance_order( (int) $order );
		}
	}

	/**
	 * Create linked balance order if needed.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function create_balance_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( 'yes' === (string) $order->get_meta( '_wbdpp_is_balance_order', true ) ) {
			return;
		}

		$existing_balance_id = (int) $order->get_meta( '_wb_balance_order_id', true );
		if ( $existing_balance_id <= 0 ) {
			$existing_balance_id = (int) $order->get_meta( '_wbdpp_balance_order_id', true );
		}

		if ( $existing_balance_id > 0 ) {
			return;
		}

		$remaining_amount = $this->calculate_remaining_amount( $order );
		if ( $remaining_amount <= 0 ) {
			return;
		}

		$due_timestamp = $this->resolve_due_timestamp_for_order( $order );

		$balance_order = wc_create_order(
			array(
				'customer_id' => $order->get_customer_id(),
			)
		);

		if ( is_wp_error( $balance_order ) || ! $balance_order instanceof WC_Order ) {
			return;
		}

		$fee_item = new WC_Order_Item_Fee();
		$fee_item->set_name(
			sprintf(
				/* translators: %d: parent order id. */
				esc_html__( 'Remaining balance for order #%d', 'wb-deposits-partial-payments-for-woocommerce' ),
				$order->get_id()
			)
		);
		$fee_item->set_total( wc_format_decimal( $remaining_amount ) );
		$fee_item->set_amount( wc_format_decimal( $remaining_amount ) );
		$balance_order->add_item( $fee_item );

		$balance_order->set_currency( $order->get_currency() );
		$balance_order->set_address( $order->get_address( 'billing' ), 'billing' );
		$balance_order->set_address( $order->get_address( 'shipping' ), 'shipping' );
		$balance_order->set_customer_note( $order->get_customer_note() );

		$balance_order->update_meta_data( '_wbdpp_is_balance_order', 'yes' );
		$balance_order->update_meta_data( '_wb_deposit_parent_order_id', $order->get_id() );
		$balance_order->update_meta_data( '_wb_balance_due_date', $due_timestamp );
		$balance_order->update_meta_data( '_wb_remaining_amount', wc_format_decimal( $remaining_amount ) );
		$balance_order->calculate_totals( false );
		$balance_order->save();

		$order->update_meta_data( '_wbdpp_has_deposit', 'yes' );
		$order->update_meta_data( '_wb_balance_order_id', $balance_order->get_id() );
		$order->update_meta_data( '_wb_deposit_amount', wc_format_decimal( (float) $order->get_total() ) );
		$order->update_meta_data( '_wb_remaining_amount', wc_format_decimal( $remaining_amount ) );
		$order->update_meta_data( '_wb_balance_due_date', $due_timestamp );
		$order->save();

		$balance_order->add_order_note(
			sprintf(
				/* translators: %d: parent order id. */
				esc_html__( 'Linked to deposit order #%d.', 'wb-deposits-partial-payments-for-woocommerce' ),
				$order->get_id()
			)
		);

		$order->add_order_note(
			sprintf(
				/* translators: 1: balance order id, 2: due date */
				esc_html__( 'Balance order #%1$d created. Due on %2$s.', 'wb-deposits-partial-payments-for-woocommerce' ),
				$balance_order->get_id(),
				wp_date( get_option( 'date_format' ), $due_timestamp )
			)
		);

		Reminders::schedule_for_balance_order( $balance_order );
		Emails::send_deposit_paid_confirmation( $order, $balance_order );
	}

	/**
	 * Send status based notifications.
	 *
	 * @param int      $order_id Order id.
	 * @param string   $old_status Old status.
	 * @param string   $new_status New status.
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public function handle_status_notifications( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
		if ( in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
			if ( 'yes' === (string) $order->get_meta( '_wbdpp_is_balance_order', true ) ) {
				Emails::send_balance_payment_receipt( $order );
			}
		}

		if ( in_array( $new_status, array( 'cancelled', 'refunded' ), true ) ) {
			$this->handle_parent_cancellation_or_refund( $order, $new_status );
		}
	}

	/**
	 * Calculate remaining amount from line meta.
	 *
	 * @param WC_Order $order Parent order.
	 * @return float
	 */
	private function calculate_remaining_amount( WC_Order $order ): float {
		$remaining = 0.0;
		$base_remaining_total        = 0.0;
		$has_dep   = false;
		$base_deposit_total          = 0.0;
		$current_deposit_discount    = 0.0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$mode = (string) $item->get_meta( '_wbdpp_payment_mode', true );
			if ( 'deposit' !== $mode ) {
				continue;
			}

			$has_dep = true;
			$line_remaining = (float) $item->get_meta( '_wbdpp_remaining_line_total', true );
			$line_deposit   = (float) $item->get_meta( '_wbdpp_deposit_line_total', true );
			$item_total     = (float) $item->get_total();

			$remaining     += max( 0.0, $line_remaining );
			$base_remaining_total      += max( 0.0, $line_remaining );
			$base_deposit_total       += max( 0.0, $line_deposit );
			$current_deposit_discount += max( 0.0, $line_deposit - $item_total );
		}

		if ( ! $has_dep ) {
			return 0.0;
		}

		if ( 'balance' === Settings::get( 'wbdpp_shipping_charge_stage' ) ) {
			$remaining += (float) $order->get_shipping_total();
		}

		if ( 'split' === Settings::get( 'wbdpp_tax_mode' ) ) {
			$remaining += (float) $order->get_total_tax() * 0.5;
		}

		$coupon_mode = Settings::get( 'wbdpp_coupon_split_mode' );
		if ( $current_deposit_discount > 0 && 'deposit' !== $coupon_mode ) {
			if ( 'full' === $coupon_mode ) {
				$remaining -= $current_deposit_discount;
			} elseif ( 'proportional' === $coupon_mode ) {
				$denominator = $base_deposit_total + $base_remaining_total;
				$ratio       = $denominator > 0 ? ( $base_remaining_total / $denominator ) : 0.5;
				$remaining  -= ( $current_deposit_discount * max( 0.0, min( 1.0, $ratio ) ) );
			}
		}

		return max( 0.0, (float) wc_format_decimal( $remaining, wc_get_price_decimals() ) );
	}

	/**
	 * Resolve due timestamp from booking date or default rules.
	 *
	 * @param WC_Order $order Parent order.
	 * @return int
	 */
	private function resolve_due_timestamp_for_order( WC_Order $order ): int {
		$use_booking_due = 'yes' === Settings::get( 'wbdpp_enable_booking_due' );
		if ( $use_booking_due ) {
			$booking_start = $this->extract_booking_start_timestamp( $order );
			if ( $booking_start > 0 ) {
				$offset_days = max( 0, (int) Settings::get( 'wbdpp_booking_due_days_before' ) );
				$due_ts      = $booking_start - ( $offset_days * DAY_IN_SECONDS );
				if ( $due_ts > time() ) {
					return $due_ts;
				}
			}
		}

		return Rules::resolve_due_timestamp( time() );
	}

	/**
	 * Extract booking start timestamp from order item metadata.
	 *
	 * @param WC_Order $order Order.
	 * @return int
	 */
	private function extract_booking_start_timestamp( WC_Order $order ): int {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$candidates = array(
				$item->get_meta( '_booking_start', true ),
				$item->get_meta( 'Booking Date', true ),
				$item->get_meta( 'Start Date', true ),
			);

			foreach ( $candidates as $candidate ) {
				if ( is_numeric( $candidate ) ) {
					$timestamp = (int) $candidate;
					if ( $timestamp > time() ) {
						return $timestamp;
					}
				}

				if ( is_string( $candidate ) && '' !== $candidate ) {
					$parsed = strtotime( $candidate );
					if ( $parsed && $parsed > time() ) {
						return (int) $parsed;
					}
				}
			}
		}

		return 0;
	}

	/**
	 * Handle parent order cancellation/refund.
	 *
	 * @param WC_Order $order Parent order.
	 * @param string   $new_status New status.
	 * @return void
	 */
	private function handle_parent_cancellation_or_refund( WC_Order $order, string $new_status ): void {
		if ( 'yes' === (string) $order->get_meta( '_wbdpp_is_balance_order', true ) ) {
			return;
		}

		$balance_order_id = (int) $order->get_meta( '_wb_balance_order_id', true );
		if ( $balance_order_id <= 0 ) {
			return;
		}

		$balance_order = wc_get_order( $balance_order_id );
		if ( ! $balance_order instanceof WC_Order ) {
			return;
		}

		$should_cancel_balance = 'yes' === Settings::get( 'wbdpp_cancel_balance_on_parent_cancel' );
		$should_cancel_balance = (bool) apply_filters( 'wbdpp_cancel_balance_on_parent_cancel', $should_cancel_balance, $order, $balance_order, $new_status );

		if ( $should_cancel_balance ) {
			if ( ! in_array( $balance_order->get_status(), array( 'cancelled', 'completed', 'processing', 'refunded' ), true ) ) {
				$balance_order->update_status( 'cancelled', esc_html__( 'Cancelled because parent deposit order was cancelled/refunded.', 'wb-deposits-partial-payments-for-woocommerce' ) );
			}
		}

		if ( 'cancelled' === $new_status ) {
			$this->apply_deposit_refund_policy( $order );
		}
	}

	/**
	 * Apply configured deposit refund policy.
	 *
	 * @param WC_Order $order Parent order.
	 * @return void
	 */
	private function apply_deposit_refund_policy( WC_Order $order ): void {
		$policy_data = $this->resolve_refund_policy_for_order( $order );
		$policy      = $policy_data['policy'];
		$policy_data = apply_filters( 'wbdpp_resolved_refund_policy', $policy_data, $order );
		$policy      = isset( $policy_data['policy'] ) ? (string) $policy_data['policy'] : 'none';
		if ( 'none' === $policy ) {
			$order->add_order_note( esc_html__( 'Deposit retained based on refund policy.', 'wb-deposits-partial-payments-for-woocommerce' ) );
			return;
		}

		$deposit_total = (float) $order->get_total();
		$refundable    = 0.0;

		if ( 'full' === $policy ) {
			$refundable = $deposit_total;
		} elseif ( 'partial' === $policy ) {
			$percent    = min( 100.0, max( 0.0, (float) $policy_data['partial_percent'] ) );
			$refundable = $deposit_total * ( $percent / 100.0 );
		}

		$already_refunded = (float) $order->get_total_refunded();
		$remaining_amount = max( 0.0, $refundable - $already_refunded );
		$remaining_amount = (float) apply_filters( 'wbdpp_refund_amount_on_cancellation', $remaining_amount, $order, $policy_data );
		if ( $remaining_amount <= 0 ) {
			return;
		}

		$refund = wc_create_refund(
			array(
				'amount'         => wc_format_decimal( $remaining_amount ),
				'reason'         => esc_html__( 'Deposit refund based on cancellation policy.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'order_id'       => $order->get_id(),
				'refund_payment' => false,
				'restock_items'  => false,
			)
		);

		if ( is_wp_error( $refund ) ) {
			$order->add_order_note( esc_html__( 'Automatic deposit refund failed. Please review manually.', 'wb-deposits-partial-payments-for-woocommerce' ) );
			do_action( 'wbdpp_deposit_refund_failed', $order, $refund, $policy_data );
			return;
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: refunded amount, 2: policy source. */
				esc_html__( 'Automatic deposit refund created: %1$s (policy source: %2$s)', 'wb-deposits-partial-payments-for-woocommerce' ),
				wp_strip_all_tags( wc_price( $remaining_amount ) )
				,
				$policy_data['source']
			)
		);

		do_action( 'wbdpp_deposit_refund_created', $order, $refund, $policy_data, $remaining_amount );
	}

	/**
	 * Resolve refund policy with precedence product > category > global.
	 *
	 * @param WC_Order $order Parent order.
	 * @return array{policy:string,partial_percent:float,source:string}
	 */
	private function resolve_refund_policy_for_order( WC_Order $order ): array {
		$global_policy = Settings::get( 'wbdpp_deposit_refund_policy' );
		$global_pct    = (float) Settings::get( 'wbdpp_deposit_refund_partial_percent' );

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product_id = (int) $item->get_product_id();
			if ( $product_id <= 0 ) {
				continue;
			}

			$product_policy = (string) get_post_meta( $product_id, '_wbdpp_refund_policy', true );
			if ( in_array( $product_policy, array( 'none', 'full', 'partial' ), true ) ) {
				$product_pct = (float) get_post_meta( $product_id, '_wbdpp_refund_partial_percent', true );
				return array(
					'policy'          => $product_policy,
					'partial_percent' => $product_pct > 0 ? $product_pct : $global_pct,
					'source'          => 'product',
				);
			}

			$term_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
				continue;
			}

			foreach ( $term_ids as $term_id ) {
				$term_policy = (string) get_term_meta( (int) $term_id, '_wbdpp_refund_policy', true );
				if ( ! in_array( $term_policy, array( 'none', 'full', 'partial' ), true ) ) {
					continue;
				}

				$term_pct = (float) get_term_meta( (int) $term_id, '_wbdpp_refund_partial_percent', true );
				return array(
					'policy'          => $term_policy,
					'partial_percent' => $term_pct > 0 ? $term_pct : $global_pct,
					'source'          => 'category',
				);
			}
		}

		return array(
			'policy'          => in_array( $global_policy, array( 'none', 'full', 'partial' ), true ) ? $global_policy : 'none',
			'partial_percent' => $global_pct,
			'source'          => 'global',
		);
	}
}
