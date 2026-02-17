<?php
namespace WBCOM\WBDPP;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Balance_Ops {

	/**
	 * Cancel overdue balance orders.
	 *
	 * @param array<string, mixed> $args Query/control arguments.
	 * @return array{count:int,ids:array<int,int>}
	 */
	public static function cancel_overdue_balance_orders( array $args = array() ): array {
		$defaults = array(
			'overdue_days'      => max( 0, (int) Settings::get( 'wbdpp_auto_cancel_overdue_days' ) ),
			'limit'             => 100,
			'source'            => 'system',
			'reason'            => '',
			'dry_run'           => false,
			'skip_setting_gate' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( ! $args['skip_setting_gate'] && 'yes' !== Settings::get( 'wbdpp_auto_cancel_overdue_enabled' ) ) {
			return array( 'count' => 0, 'ids' => array() );
		}

		$overdue_days = max( 0, (int) $args['overdue_days'] );
		$overdue_days = (int) apply_filters( 'wbdpp_overdue_days_threshold', $overdue_days, $args );
		$threshold    = time() - ( $overdue_days * DAY_IN_SECONDS );

		$query_args = array(
			'limit'      => max( 1, (int) $args['limit'] ),
			'return'     => 'objects',
			'status'     => array( 'wc-pending', 'wc-on-hold', 'wc-failed' ),
			'meta_query' => array(
				array(
					'key'   => '_wbdpp_is_balance_order',
					'value' => 'yes',
				),
				array(
					'key'     => '_wb_balance_due_date',
					'value'   => $threshold,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				),
			),
		);

		$query_args = apply_filters( 'wbdpp_overdue_balance_query_args', $query_args, $threshold, $args );

		$query  = new \WC_Order_Query( $query_args );
		$orders = $query->get_orders();

		if ( ! is_array( $orders ) ) {
			return array( 'count' => 0, 'ids' => array() );
		}

		$cancelled_ids = array();
		$default_reason = esc_html__( 'Auto-cancelled: overdue balance payment window expired.', 'wb-deposits-partial-payments-for-woocommerce' );
		$reason         = is_string( $args['reason'] ) && '' !== $args['reason'] ? $args['reason'] : $default_reason;
		$reason         = (string) apply_filters( 'wbdpp_overdue_cancel_reason', $reason, $args );

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$order_id = $order->get_id();

			if ( ! empty( $args['dry_run'] ) ) {
				$cancelled_ids[] = $order_id;
				continue;
			}

			$order->update_status( 'cancelled', $reason );
			$cancelled_ids[] = $order_id;
		}

		$result = array(
			'count' => count( $cancelled_ids ),
			'ids'   => $cancelled_ids,
		);

		if ( ! empty( $cancelled_ids ) ) {
			self::notify_admin_of_cancellations( $result, (string) $args['source'], $overdue_days, ! empty( $args['dry_run'] ) );
		}

		do_action( 'wbdpp_overdue_orders_cancelled', $result, $args );

		return $result;
	}

	/**
	 * Send admin summary notification.
	 *
	 * @param array{count:int,ids:array<int,int>} $result Result.
	 * @param string                               $source Source tag.
	 * @param int                                  $overdue_days Overdue threshold.
	 * @param bool                                 $dry_run Dry-run flag.
	 * @return void
	 */
	private static function notify_admin_of_cancellations( array $result, string $source, int $overdue_days, bool $dry_run ): void {
		$to = apply_filters( 'wbdpp_admin_cancel_email_recipient', get_option( 'admin_email' ), $source, $result );
		if ( ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: source, 2: count */
			esc_html__( '[WB Deposits] %1$s overdue cancellation summary (%2$d)', 'wb-deposits-partial-payments-for-woocommerce' ),
			$source,
			$result['count']
		);

		$body = sprintf(
			/* translators: 1: source, 2: count, 3: days, 4: order ids, 5: dry-run yes/no */
			esc_html__( "Source: %1\$s\nCancelled Orders: %2\$d\nOverdue Threshold (days): %3\$d\nDry Run: %5\$s\nOrder IDs: %4\$s", 'wb-deposits-partial-payments-for-woocommerce' ),
			$source,
			$result['count'],
			$overdue_days,
			implode( ',', $result['ids'] ),
			$dry_run ? 'yes' : 'no'
		);

		$subject = apply_filters( 'wbdpp_admin_cancel_email_subject', $subject, $source, $result );
		$body    = apply_filters( 'wbdpp_admin_cancel_email_body', $body, $source, $result, $overdue_days, $dry_run );

		wc_mail( $to, $subject, nl2br( esc_html( $body ) ) );
	}
}
