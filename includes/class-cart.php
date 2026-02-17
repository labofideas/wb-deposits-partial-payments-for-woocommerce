<?php
namespace WBCOM\WBDPP;

use WC_Cart;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cart {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_payment_choice' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'capture_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'render_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_deposit_prices' ), 20 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_coupon_split_adjustment' ), 200 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'attach_order_item_meta' ), 20, 4 );
	}

	/**
	 * Render payment option on PDP.
	 *
	 * @return void
	 */
	public function render_payment_choice(): void {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! $product->is_type( array( 'simple', 'variable', 'variation' ) ) ) {
			return;
		}

		$rule = Rules::resolve_for_product( $product );

		$base_price = (float) wc_get_price_to_display( $product );
		$deposit    = Rules::calculate_deposit( $base_price, $rule );
		$remaining  = max( 0, $base_price - $deposit );

		if ( $deposit <= 0 || $remaining <= 0 ) {
			return;
		}

		$is_mandatory = 'mandatory' === ( $rule['payment_mode'] ?? 'optional' );
		$full_label   = Settings::get( 'wbdpp_ui_pay_full_label' );
		$dep_label    = Settings::get( 'wbdpp_ui_pay_deposit_label' );
		$dep_default  = $is_mandatory ? 'deposit' : 'full';

		echo '<div class="wbdpp-payment-choice" style="margin:12px 0; padding:12px; border:1px solid #ddd;">';
		echo '<strong>' . esc_html__( 'Payment Option', 'wb-deposits-partial-payments-for-woocommerce' ) . '</strong>';
		echo '<p style="margin:8px 0;">';
		echo '<label style="display:block; margin-bottom:4px;">';
		echo '<input type="radio" name="wbdpp_payment_mode" value="full" ' . checked( 'full', $dep_default, false ) . disabled( $is_mandatory, true, false ) . ' /> ';
		echo esc_html( $full_label ) . ' (' . wp_kses_post( wc_price( $base_price ) ) . ')';
		echo '</label>';
		echo '<label style="display:block;">';
		echo '<input type="radio" name="wbdpp_payment_mode" value="deposit" ' . checked( 'deposit', $dep_default, false ) . ' /> ';
		echo esc_html( $dep_label ) . ' (' . wp_kses_post( wc_price( $deposit ) ) . ')';
		echo '</label>';
		echo '</p>';

		$message = Settings::get( 'wbdpp_ui_deposit_message' );
		$message = str_replace(
			array( '{deposit}', '{remaining}', '{due_date}' ),
			array(
				Settings::format_amount( $deposit ),
				Settings::format_amount( $remaining ),
				esc_html( wp_date( get_option( 'date_format' ), Rules::resolve_due_timestamp( time(), $product->get_id() ) ) ),
			),
			$message
		);

		echo '<p style="margin:6px 0 0; font-size:13px;">' . esc_html( $message ) . '</p>';
		echo '</div>';
	}

	/**
	 * Store payment mode and rule snapshot in cart item.
	 *
	 * @param array<string, mixed> $cart_item_data Cart item data.
	 * @param int                  $product_id Product id.
	 * @param int                  $variation_id Variation id.
	 * @return array<string, mixed>
	 */
	public function capture_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		if ( ! Settings::is_enabled() ) {
			return $cart_item_data;
		}

		$target_product_id = $variation_id > 0 ? $variation_id : $product_id;
		$product           = wc_get_product( $target_product_id );
		if ( ! $product instanceof WC_Product ) {
			return $cart_item_data;
		}

		$rule         = Rules::resolve_for_product( $product );
		$selected_raw = filter_input( INPUT_POST, 'wbdpp_payment_mode', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $selected_raw ) || '' === $selected_raw ) {
			$selected_raw = 'full';
		}
		$selected     = Rules::sanitize_payment_mode( $selected_raw, $rule );

		$cart_item_data['wbdpp_payment_mode'] = $selected;
		$cart_item_data['wbdpp_rule']         = $rule;

		return $cart_item_data;
	}

	/**
	 * Show cart item details.
	 *
	 * @param array<int, array<string, string>> $item_data Item data.
	 * @param array<string, mixed>              $cart_item Cart item.
	 * @return array<int, array<string, string>>
	 */
	public function render_cart_item_data( array $item_data, array $cart_item ): array {
		$mode = isset( $cart_item['wbdpp_payment_mode'] ) ? (string) $cart_item['wbdpp_payment_mode'] : 'full';
		if ( 'deposit' !== $mode ) {
			return $item_data;
		}

		$deposit   = isset( $cart_item['wbdpp_deposit_line_total'] ) ? (float) $cart_item['wbdpp_deposit_line_total'] : 0.0;
		$remaining = isset( $cart_item['wbdpp_remaining_line_total'] ) ? (float) $cart_item['wbdpp_remaining_line_total'] : 0.0;

		$item_data[] = array(
			'key'   => esc_html__( 'Payment', 'wb-deposits-partial-payments-for-woocommerce' ),
			'value' => esc_html__( 'Deposit', 'wb-deposits-partial-payments-for-woocommerce' ),
		);

		if ( $deposit > 0 ) {
			$item_data[] = array(
				'key'   => esc_html__( 'Deposit amount', 'wb-deposits-partial-payments-for-woocommerce' ),
				'value' => wp_strip_all_tags( wc_price( $deposit ) ),
			);
		}

		if ( $remaining > 0 ) {
			$item_data[] = array(
				'key'   => esc_html__( 'Remaining balance', 'wb-deposits-partial-payments-for-woocommerce' ),
				'value' => wp_strip_all_tags( wc_price( $remaining ) ),
			);
		}

		return $item_data;
	}

	/**
	 * Apply deposit pricing to cart items.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return void
	 */
	public function apply_deposit_prices( WC_Cart $cart ): void {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( did_action( 'woocommerce_before_calculate_totals' ) > 1 ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				continue;
			}

			$mode = isset( $cart_item['wbdpp_payment_mode'] ) ? (string) $cart_item['wbdpp_payment_mode'] : 'full';

			if ( ! isset( $cart_item['wbdpp_original_unit_price'] ) ) {
				$cart->cart_contents[ $cart_item_key ]['wbdpp_original_unit_price'] = (float) $cart_item['data']->get_price( 'edit' );
			}

			$original_unit = (float) $cart->cart_contents[ $cart_item_key ]['wbdpp_original_unit_price'];
			$qty           = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;

			if ( 'deposit' !== $mode ) {
				$cart->cart_contents[ $cart_item_key ]['data']->set_price( $original_unit );
				$cart->cart_contents[ $cart_item_key ]['wbdpp_deposit_line_total']   = $original_unit * $qty;
				$cart->cart_contents[ $cart_item_key ]['wbdpp_remaining_line_total'] = 0.0;
				$cart->cart_contents[ $cart_item_key ]['wbdpp_full_line_total']      = $original_unit * $qty;
				continue;
			}

			$rule             = isset( $cart_item['wbdpp_rule'] ) && is_array( $cart_item['wbdpp_rule'] ) ? $cart_item['wbdpp_rule'] : Rules::resolve_for_product( $cart_item['data'] );
			$deposit_unit     = Rules::calculate_deposit( $original_unit, $rule );
			$full_line        = $original_unit * $qty;
			$deposit_line     = $deposit_unit * $qty;
			$remaining_line   = max( 0, $full_line - $deposit_line );

			$cart->cart_contents[ $cart_item_key ]['data']->set_price( $deposit_unit );
			$cart->cart_contents[ $cart_item_key ]['wbdpp_deposit_line_total']   = $deposit_line;
			$cart->cart_contents[ $cart_item_key ]['wbdpp_remaining_line_total'] = $remaining_line;
			$cart->cart_contents[ $cart_item_key ]['wbdpp_full_line_total']      = $full_line;
		}
	}

	/**
	 * Reallocate coupon impact on deposit checkout amount based on split mode.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return void
	 */
	public function apply_coupon_split_adjustment( WC_Cart $cart ): void {
		if ( ! Settings::is_enabled() || is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		$coupon_mode = Settings::get( 'wbdpp_coupon_split_mode' );
		if ( 'deposit' === $coupon_mode ) {
			return;
		}

		$deposit_subtotal        = 0.0;
		$full_subtotal           = 0.0;
		$current_deposit_discount = 0.0;

		foreach ( $cart->get_cart() as $cart_item ) {
			$mode = isset( $cart_item['wbdpp_payment_mode'] ) ? (string) $cart_item['wbdpp_payment_mode'] : 'full';
			if ( 'deposit' !== $mode ) {
				continue;
			}

			$deposit_line = isset( $cart_item['wbdpp_deposit_line_total'] ) ? (float) $cart_item['wbdpp_deposit_line_total'] : 0.0;
			$full_line    = isset( $cart_item['wbdpp_full_line_total'] ) ? (float) $cart_item['wbdpp_full_line_total'] : 0.0;

			$line_subtotal = isset( $cart_item['line_subtotal'] ) ? (float) $cart_item['line_subtotal'] : $deposit_line;
			$line_total    = isset( $cart_item['line_total'] ) ? (float) $cart_item['line_total'] : $deposit_line;

			$deposit_subtotal         += max( 0.0, $deposit_line );
			$full_subtotal            += max( 0.0, $full_line );
			$current_deposit_discount += max( 0.0, $line_subtotal - $line_total );
		}

		if ( $deposit_subtotal <= 0 || $current_deposit_discount <= 0 ) {
			return;
		}

		$target_deposit_discount = $current_deposit_discount;
		if ( 'full' === $coupon_mode ) {
			$target_deposit_discount = 0.0;
		} elseif ( 'proportional' === $coupon_mode ) {
			$ratio = $full_subtotal > 0 ? ( $deposit_subtotal / $full_subtotal ) : 1.0;
			$target_deposit_discount = $current_deposit_discount * max( 0.0, min( 1.0, $ratio ) );
		}

		$adjustment = $current_deposit_discount - $target_deposit_discount;
		if ( abs( $adjustment ) < 0.01 ) {
			return;
		}

		$label = 'full' === $coupon_mode
			? esc_html__( 'Discount applied to balance payment', 'wb-deposits-partial-payments-for-woocommerce' )
			: esc_html__( 'Discount split between deposit and balance', 'wb-deposits-partial-payments-for-woocommerce' );

		$cart->add_fee( $label, (float) wc_format_decimal( $adjustment, wc_get_price_decimals() ), false );
	}

	/**
	 * Add deposit metadata to order line items.
	 *
	 * @param \WC_Order_Item_Product $item Item.
	 * @param string                 $cart_item_key Cart key.
	 * @param array<string, mixed>   $values Cart item values.
	 * @param \WC_Order              $order Order.
	 * @return void
	 */
	public function attach_order_item_meta( $item, string $cart_item_key, array $values, $order ): void {
		$mode = isset( $values['wbdpp_payment_mode'] ) ? (string) $values['wbdpp_payment_mode'] : 'full';
		$item->add_meta_data( '_wbdpp_payment_mode', $mode, true );

		$full_line = isset( $values['wbdpp_full_line_total'] ) ? (float) $values['wbdpp_full_line_total'] : (float) $item->get_total();
		$dep_line  = isset( $values['wbdpp_deposit_line_total'] ) ? (float) $values['wbdpp_deposit_line_total'] : (float) $item->get_total();
		$rem_line  = isset( $values['wbdpp_remaining_line_total'] ) ? (float) $values['wbdpp_remaining_line_total'] : 0.0;

		$item->add_meta_data( '_wbdpp_full_line_total', wc_format_decimal( $full_line ), true );
		$item->add_meta_data( '_wbdpp_deposit_line_total', wc_format_decimal( $dep_line ), true );
		$item->add_meta_data( '_wbdpp_remaining_line_total', wc_format_decimal( $rem_line ), true );

		if ( 'deposit' === $mode && $rem_line > 0 ) {
			$order->update_meta_data( '_wbdpp_has_deposit', 'yes' );
		}
	}
}
