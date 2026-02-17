<?php
namespace WBCOM\WBDPP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CLI {

	/**
	 * Register CLI command.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! class_exists( '\\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'wbdpp cancel-overdue', array( __CLASS__, 'cancel_overdue' ) );
	}

	/**
	 * Cancel overdue balance orders.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Overdue threshold in days.
	 *
	 * [--limit=<limit>]
	 * : Maximum number of orders to process.
	 *
	 * [--dry-run]
	 * : Show candidate orders without cancelling.
	 *
	 * [--ignore-setting-gate]
	 * : Run even if auto-cancel setting is disabled.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wbdpp cancel-overdue
	 *     wp wbdpp cancel-overdue --days=10 --limit=200
	 *     wp wbdpp cancel-overdue --dry-run
	 *
	 * @param array<int, string>       $args Positional args.
	 * @param array<string, string|bool> $assoc_args Assoc args.
	 * @return void
	 */
	public static function cancel_overdue( array $args, array $assoc_args ): void {
		unset( $args );

		$days             = isset( $assoc_args['days'] ) ? max( 1, (int) $assoc_args['days'] ) : max( 1, (int) Settings::get( 'wbdpp_auto_cancel_overdue_days' ) );
		$limit            = isset( $assoc_args['limit'] ) ? max( 1, (int) $assoc_args['limit'] ) : 100;
		$dry_run          = isset( $assoc_args['dry-run'] );
		$skip_setting_gate = isset( $assoc_args['ignore-setting-gate'] );

		$result = Balance_Ops::cancel_overdue_balance_orders(
			array(
				'overdue_days'      => $days,
				'limit'             => $limit,
				'source'            => 'wp-cli',
				'reason'            => esc_html__( 'Cancelled via WP-CLI overdue balance operation.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'dry_run'           => $dry_run,
				'skip_setting_gate' => $skip_setting_gate,
			)
		);

		if ( $dry_run ) {
			\WP_CLI::success( sprintf( 'Dry run complete. %d overdue balance order(s) matched. IDs: %s', $result['count'], implode( ',', $result['ids'] ) ) );
			return;
		}

		\WP_CLI::success( sprintf( 'Cancelled %d overdue balance order(s). IDs: %s', $result['count'], implode( ',', $result['ids'] ) ) );
	}
}
