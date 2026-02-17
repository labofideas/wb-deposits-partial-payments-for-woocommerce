<?php
namespace WBCOM\WBDPP;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Emails {

	/**
	 * Send deposit confirmation email.
	 *
	 * @param WC_Order $deposit_order Deposit order.
	 * @param WC_Order $balance_order Balance order.
	 * @return void
	 */
	public static function send_deposit_paid_confirmation( WC_Order $deposit_order, WC_Order $balance_order ): void {
		$to = $deposit_order->get_billing_email();
		if ( '' === $to ) {
			return;
		}

		$due_date = (int) $balance_order->get_meta( '_wb_balance_due_date', true );
		$due_text = $due_date > 0 ? wp_date( get_option( 'date_format' ), $due_date ) : esc_html__( 'N/A', 'wb-deposits-partial-payments-for-woocommerce' );

		$subject = sprintf(
			/* translators: %d: order id. */
			esc_html__( 'Deposit received for order #%d', 'wb-deposits-partial-payments-for-woocommerce' ),
			$deposit_order->get_id()
		);

		$body = sprintf(
			/* translators: 1: customer name, 2: deposit amount, 3: remaining amount, 4: due date, 5: pay url */
			esc_html__( "Hi %1\$s,\n\nYour deposit payment of %2\$s was received.\nRemaining balance: %3\$s\nDue date: %4\$s\n\nPay remaining balance: %5\$s\n", 'wb-deposits-partial-payments-for-woocommerce' ),
			$deposit_order->get_billing_first_name(),
			wp_strip_all_tags( wc_price( (float) $deposit_order->get_total() ) ),
			wp_strip_all_tags( wc_price( (float) $balance_order->get_total() ) ),
			$due_text,
			$balance_order->get_checkout_payment_url()
		);

		wc_mail( $to, $subject, nl2br( esc_html( $body ) ) );
	}

	/**
	 * Send balance reminder.
	 *
	 * @param WC_Order $balance_order Balance order.
	 * @param int      $days_before Days before due date.
	 * @return void
	 */
	public static function send_balance_reminder( WC_Order $balance_order, int $days_before ): void {
		$to = $balance_order->get_billing_email();
		if ( '' === $to ) {
			return;
		}

		$due_date = (int) $balance_order->get_meta( '_wb_balance_due_date', true );
		$due_text = $due_date > 0 ? wp_date( get_option( 'date_format' ), $due_date ) : esc_html__( 'N/A', 'wb-deposits-partial-payments-for-woocommerce' );

		$subject = ( 0 === $days_before )
			? esc_html__( 'Balance payment due today', 'wb-deposits-partial-payments-for-woocommerce' )
			: sprintf(
				/* translators: %d: days before due date. */
				esc_html__( 'Balance payment due in %d day(s)', 'wb-deposits-partial-payments-for-woocommerce' ),
				$days_before
			);

		$body = sprintf(
			/* translators: 1: amount, 2: due date, 3: payment link */
			esc_html__( "Your remaining balance of %1\$s is due on %2\$s.\n\nPay now: %3\$s\n", 'wb-deposits-partial-payments-for-woocommerce' ),
			wp_strip_all_tags( wc_price( (float) $balance_order->get_total() ) ),
			$due_text,
			$balance_order->get_checkout_payment_url()
		);

		wc_mail( $to, $subject, nl2br( esc_html( $body ) ) );
	}

	/**
	 * Send balance payment receipt.
	 *
	 * @param WC_Order $balance_order Balance order.
	 * @return void
	 */
	public static function send_balance_payment_receipt( WC_Order $balance_order ): void {
		if ( 'yes' === (string) $balance_order->get_meta( '_wbdpp_receipt_sent', true ) ) {
			return;
		}

		$to = $balance_order->get_billing_email();
		if ( '' === $to ) {
			return;
		}

		$subject = sprintf(
			/* translators: %d: order id. */
			esc_html__( 'Balance payment received for order #%d', 'wb-deposits-partial-payments-for-woocommerce' ),
			$balance_order->get_id()
		);

		$body = sprintf(
			/* translators: %s: paid amount */
			esc_html__( 'We have received your remaining balance payment of %s. Thank you.', 'wb-deposits-partial-payments-for-woocommerce' ),
			wp_strip_all_tags( wc_price( (float) $balance_order->get_total() ) )
		);

		wc_mail( $to, $subject, nl2br( esc_html( $body ) ) );

		$balance_order->update_meta_data( '_wbdpp_receipt_sent', 'yes' );
		$balance_order->save();
	}
}
