<?php
namespace WBCOM\WBDPP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	/**
	 * Default option values.
	 *
	 * @var array<string, string>
	 */
	private static array $defaults = array(
		'wbdpp_enabled'                 => 'yes',
		'wbdpp_default_deposit_type'    => 'percentage',
		'wbdpp_default_deposit_value'   => '20',
		'wbdpp_default_payment_mode'    => 'optional',
		'wbdpp_default_due_type'        => 'relative',
		'wbdpp_default_due_fixed_date'  => '',
		'wbdpp_default_due_relative'    => '30',
		'wbdpp_enable_booking_due'      => 'no',
		'wbdpp_booking_due_days_before' => '7',
		'wbdpp_reminder_offsets'        => '7,3,1,0',
		'wbdpp_shipping_charge_stage'   => 'balance',
		'wbdpp_tax_mode'                => 'split',
		'wbdpp_coupon_split_mode'       => 'proportional',
		'wbdpp_cancel_balance_on_parent_cancel' => 'yes',
		'wbdpp_deposit_refund_policy'   => 'none',
		'wbdpp_deposit_refund_partial_percent' => '50',
		'wbdpp_auto_cancel_overdue_enabled' => 'no',
		'wbdpp_auto_cancel_overdue_days' => '7',
		'wbdpp_stock_reduce_stage'      => 'deposit',
		'wbdpp_ui_pay_full_label'       => 'Pay full amount',
		'wbdpp_ui_pay_deposit_label'    => 'Pay deposit',
		'wbdpp_ui_deposit_message'      => 'Deposit: {deposit} | Remaining: {remaining} | Due: {due_date}',
	);

	/**
	 * Get option with default fallback.
	 *
	 * @param string $key Option key.
	 * @return string
	 */
	public static function get( string $key ): string {
		$default = self::$defaults[ $key ] ?? '';
		$value   = get_option( $key, $default );

		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (string) $value;
		}

		return $default;
	}

	/**
	 * Check if plugin is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'yes' === self::get( 'wbdpp_enabled' );
	}

	/**
	 * Get default config.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return self::$defaults;
	}

	/**
	 * Get reminder offsets in days.
	 *
	 * @return array<int, int>
	 */
	public static function reminder_offsets(): array {
		$raw   = self::get( 'wbdpp_reminder_offsets' );
		$parts = array_filter( array_map( 'trim', explode( ',', $raw ) ), 'strlen' );
		$days  = array();

		foreach ( $parts as $part ) {
			$days[] = max( 0, (int) $part );
		}

		$days = array_values( array_unique( $days ) );
		rsort( $days );

		if ( empty( $days ) ) {
			$days = array( 7, 3, 1, 0 );
		}

		return $days;
	}

	/**
	 * Format amount using store currency settings.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function format_amount( float $amount ): string {
		return wp_strip_all_tags( wc_price( wc_format_decimal( $amount, wc_get_price_decimals() ) ) );
	}
}
