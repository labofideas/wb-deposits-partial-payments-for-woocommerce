<?php
namespace WBCOM\WBDPP;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rules {

	/**
	 * Resolve deposit rule for a product.
	 * Product > category > global.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string, mixed>
	 */
	public static function resolve_for_product( WC_Product $product ): array {
		$product_id = $product->get_id();

		$rule = self::rule_from_product_meta( $product_id );
		if ( ! empty( $rule ) ) {
			return self::normalize_rule( $rule );
		}

		$category_rule = self::rule_from_categories( $product_id );
		if ( ! empty( $category_rule ) ) {
			return self::normalize_rule( $category_rule );
		}

		$global_rule = array(
			'deposit_type'  => Settings::get( 'wbdpp_default_deposit_type' ),
			'deposit_value' => Settings::get( 'wbdpp_default_deposit_value' ),
			'payment_mode'  => Settings::get( 'wbdpp_default_payment_mode' ),
		);

		return self::normalize_rule( $global_rule );
	}

	/**
	 * Calculate deposit amount.
	 *
	 * @param float               $full_amount Full amount.
	 * @param array<string, mixed> $rule Rule.
	 * @return float
	 */
	public static function calculate_deposit( float $full_amount, array $rule ): float {
		$full_amount = max( 0.0, $full_amount );
		$type        = isset( $rule['deposit_type'] ) ? (string) $rule['deposit_type'] : 'percentage';
		$value       = isset( $rule['deposit_value'] ) ? (float) $rule['deposit_value'] : 0.0;

		if ( $full_amount <= 0 ) {
			return 0.0;
		}

		if ( 'fixed' === $type ) {
			return min( $full_amount, max( 0.0, $value ) );
		}

		$percentage = min( 100.0, max( 0.0, $value ) );
		$deposit    = $full_amount * ( $percentage / 100 );

		return min( $full_amount, max( 0.0, $deposit ) );
	}

	/**
	 * Check if selected mode is valid for rule.
	 *
	 * @param string              $selected_mode Mode.
	 * @param array<string, mixed> $rule Rule.
	 * @return string
	 */
	public static function sanitize_payment_mode( string $selected_mode, array $rule ): string {
		$mode = in_array( $selected_mode, array( 'deposit', 'full' ), true ) ? $selected_mode : 'full';

		if ( 'mandatory' === ( $rule['payment_mode'] ?? 'optional' ) ) {
			return 'deposit';
		}

		return $mode;
	}

	/**
	 * Build due date timestamp from settings.
	 *
	 * @param int  $from_timestamp Base timestamp.
	 * @param int  $product_id Product id.
	 * @return int
	 */
	public static function resolve_due_timestamp( int $from_timestamp, int $product_id = 0 ): int {
		$due_type = Settings::get( 'wbdpp_default_due_type' );
		$fixed    = Settings::get( 'wbdpp_default_due_fixed_date' );
		$relative = max( 0, (int) Settings::get( 'wbdpp_default_due_relative' ) );

		if ( $product_id > 0 ) {
			$product_due_type = (string) get_post_meta( $product_id, '_wbdpp_due_type', true );
			if ( in_array( $product_due_type, array( 'fixed', 'relative' ), true ) ) {
				$due_type = $product_due_type;
			}

			$product_fixed = (string) get_post_meta( $product_id, '_wbdpp_due_fixed_date', true );
			if ( '' !== $product_fixed ) {
				$fixed = $product_fixed;
			}

			$product_relative = (int) get_post_meta( $product_id, '_wbdpp_due_relative', true );
			if ( $product_relative > 0 ) {
				$relative = $product_relative;
			}
		}

		$tz = wp_timezone();

		if ( 'fixed' === $due_type && '' !== $fixed ) {
			$dt = date_create_immutable( $fixed . ' 23:59:59', $tz );
			if ( $dt instanceof \DateTimeImmutable ) {
				return (int) $dt->format( 'U' );
			}
		}

		$base = max( time(), $from_timestamp );
		return $base + ( DAY_IN_SECONDS * $relative );
	}

	/**
	 * Get rule from product meta.
	 *
	 * @param int $product_id Product id.
	 * @return array<string, string>
	 */
	private static function rule_from_product_meta( int $product_id ): array {
		$enabled = (string) get_post_meta( $product_id, '_wbdpp_enable_rule', true );

		if ( 'yes' !== $enabled ) {
			return array();
		}

		return array(
			'deposit_type'  => (string) get_post_meta( $product_id, '_wbdpp_deposit_type', true ),
			'deposit_value' => (string) get_post_meta( $product_id, '_wbdpp_deposit_value', true ),
			'payment_mode'  => (string) get_post_meta( $product_id, '_wbdpp_payment_mode', true ),
		);
	}

	/**
	 * Get first matching category rule.
	 *
	 * @param int $product_id Product id.
	 * @return array<string, string>
	 */
	private static function rule_from_categories( int $product_id ): array {
		$term_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return array();
		}

		foreach ( $term_ids as $term_id ) {
			$enabled = (string) get_term_meta( (int) $term_id, '_wbdpp_enable_rule', true );
			if ( 'yes' !== $enabled ) {
				continue;
			}

			return array(
				'deposit_type'  => (string) get_term_meta( (int) $term_id, '_wbdpp_deposit_type', true ),
				'deposit_value' => (string) get_term_meta( (int) $term_id, '_wbdpp_deposit_value', true ),
				'payment_mode'  => (string) get_term_meta( (int) $term_id, '_wbdpp_payment_mode', true ),
			);
		}

		return array();
	}

	/**
	 * Normalize and validate a rule.
	 *
	 * @param array<string, mixed> $rule Rule.
	 * @return array<string, mixed>
	 */
	private static function normalize_rule( array $rule ): array {
		$type         = isset( $rule['deposit_type'] ) ? (string) $rule['deposit_type'] : 'percentage';
		$value        = isset( $rule['deposit_value'] ) ? (float) $rule['deposit_value'] : 0;
		$payment_mode = isset( $rule['payment_mode'] ) ? (string) $rule['payment_mode'] : 'optional';

		if ( ! in_array( $type, array( 'percentage', 'fixed' ), true ) ) {
			$type = 'percentage';
		}

		if ( ! in_array( $payment_mode, array( 'optional', 'mandatory' ), true ) ) {
			$payment_mode = 'optional';
		}

		if ( 'percentage' === $type ) {
			$value = min( 100.0, max( 0.0, $value ) );
		} else {
			$value = max( 0.0, $value );
		}

		return array(
			'deposit_type'  => $type,
			'deposit_value' => $value,
			'payment_mode'  => $payment_mode,
		);
	}
}
