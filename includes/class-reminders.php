<?php
namespace WBCOM\WBDPP;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reminders {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wbdpp_send_balance_reminder', array( __CLASS__, 'handle_reminder_action' ), 10, 2 );
		add_action( 'wbdpp_daily_maintenance', array( __CLASS__, 'run_daily_maintenance' ) );
		add_action( 'init', array( __CLASS__, 'ensure_daily_schedule' ) );
	}

	/**
	 * Schedule reminders.
	 *
	 * @param WC_Order $balance_order Balance order.
	 * @return void
	 */
	public static function schedule_for_balance_order( WC_Order $balance_order ): void {
		$due_date = (int) $balance_order->get_meta( '_wb_balance_due_date', true );
		if ( $due_date <= 0 ) {
			return;
		}

		foreach ( Settings::reminder_offsets() as $offset_days ) {
			$timestamp = $due_date - ( $offset_days * DAY_IN_SECONDS );
			if ( $timestamp <= time() ) {
				continue;
			}

			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( $timestamp, 'wbdpp_send_balance_reminder', array( $balance_order->get_id(), $offset_days ), 'wbdpp' );
			} else {
				wp_schedule_single_event( $timestamp, 'wbdpp_send_balance_reminder', array( $balance_order->get_id(), $offset_days ) );
			}
		}
	}

	/**
	 * Send reminder for scheduled event.
	 *
	 * @param int $balance_order_id Balance order id.
	 * @param int $offset_days Offset.
	 * @return void
	 */
	public static function handle_reminder_action( int $balance_order_id, int $offset_days ): void {
		$order = wc_get_order( $balance_order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( 'yes' !== (string) $order->get_meta( '_wbdpp_is_balance_order', true ) ) {
			return;
		}

		if ( ! in_array( $order->get_status(), array( 'pending', 'on-hold', 'failed' ), true ) ) {
			return;
		}

		Emails::send_balance_reminder( $order, $offset_days );
	}

	/**
	 * Ensure recurring maintenance event exists.
	 *
	 * @return void
	 */
	public static function ensure_daily_schedule(): void {
		if ( wp_next_scheduled( 'wbdpp_daily_maintenance' ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wbdpp_daily_maintenance' );
	}

	/**
	 * Run daily jobs (auto-cancel overdue balances).
	 *
	 * @return void
	 */
	public static function run_daily_maintenance(): void {
		Balance_Ops::cancel_overdue_balance_orders(
			array(
				'source' => 'daily-cron',
				'reason' => esc_html__( 'Auto-cancelled: overdue balance payment window expired.', 'wb-deposits-partial-payments-for-woocommerce' ),
			)
		);
	}
}
