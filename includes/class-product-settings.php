<?php
namespace WBCOM\WBDPP;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Product_Settings {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_product_options_pricing', array( $this, 'render_product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_fields' ) );
		add_action( 'product_cat_add_form_fields', array( $this, 'render_category_add_fields' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'render_category_edit_fields' ), 10, 1 );
		add_action( 'created_product_cat', array( $this, 'save_category_fields' ), 10, 1 );
		add_action( 'edited_product_cat', array( $this, 'save_category_fields' ), 10, 1 );
	}

	/**
	 * Render product level settings.
	 *
	 * @return void
	 */
	public function render_product_fields(): void {
		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id'          => '_wbdpp_enable_rule',
				'label'       => esc_html__( 'Enable deposit rule', 'wb-deposits-partial-payments-for-woocommerce' ),
				'description' => esc_html__( 'Override global/category deposit rule for this product.', 'wb-deposits-partial-payments-for-woocommerce' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => '_wbdpp_deposit_type',
				'label'   => esc_html__( 'Deposit type', 'wb-deposits-partial-payments-for-woocommerce' ),
				'options' => array(
					'percentage' => esc_html__( 'Percentage', 'wb-deposits-partial-payments-for-woocommerce' ),
					'fixed'      => esc_html__( 'Fixed amount', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_wbdpp_deposit_value',
				'label'             => esc_html__( 'Deposit value', 'wb-deposits-partial-payments-for-woocommerce' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => '_wbdpp_payment_mode',
				'label'   => esc_html__( 'Payment mode', 'wb-deposits-partial-payments-for-woocommerce' ),
				'options' => array(
					'optional'  => esc_html__( 'Optional', 'wb-deposits-partial-payments-for-woocommerce' ),
					'mandatory' => esc_html__( 'Mandatory deposit', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => '_wbdpp_due_type',
				'label'   => esc_html__( 'Due type override', 'wb-deposits-partial-payments-for-woocommerce' ),
				'options' => array(
					''         => esc_html__( 'Use global setting', 'wb-deposits-partial-payments-for-woocommerce' ),
					'fixed'    => esc_html__( 'Fixed date', 'wb-deposits-partial-payments-for-woocommerce' ),
					'relative' => esc_html__( 'Relative days', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_wbdpp_due_fixed_date',
				'label'       => esc_html__( 'Due fixed date', 'wb-deposits-partial-payments-for-woocommerce' ),
				'description' => esc_html__( 'Format: YYYY-MM-DD', 'wb-deposits-partial-payments-for-woocommerce' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_wbdpp_due_relative',
				'label'             => esc_html__( 'Due relative days', 'wb-deposits-partial-payments-for-woocommerce' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => '_wbdpp_refund_policy',
				'label'   => esc_html__( 'Refund policy override', 'wb-deposits-partial-payments-for-woocommerce' ),
				'options' => array(
					''        => esc_html__( 'Use global setting', 'wb-deposits-partial-payments-for-woocommerce' ),
					'none'    => esc_html__( 'Non-refundable deposit', 'wb-deposits-partial-payments-for-woocommerce' ),
					'full'    => esc_html__( 'Refund full deposit', 'wb-deposits-partial-payments-for-woocommerce' ),
					'partial' => esc_html__( 'Refund partial deposit', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_wbdpp_refund_partial_percent',
				'label'             => esc_html__( 'Partial refund percentage override', 'wb-deposits-partial-payments-for-woocommerce' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '100',
					'step' => '0.01',
				),
			)
		);

		echo '</div>';
	}

	/**
	 * Save product settings.
	 *
	 * @param WC_Product $product Product.
	 * @return void
	 */
	public function save_product_fields( WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification is intentionally performed below.
		$nonce = isset( $_POST['woocommerce_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( '_wbdpp_enable_rule', isset( $_POST['_wbdpp_enable_rule'] ) ? 'yes' : 'no' );
		$product->update_meta_data( '_wbdpp_deposit_type', isset( $_POST['_wbdpp_deposit_type'] ) ? sanitize_text_field( wp_unslash( $_POST['_wbdpp_deposit_type'] ) ) : 'percentage' );
		$product->update_meta_data( '_wbdpp_deposit_value', isset( $_POST['_wbdpp_deposit_value'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_wbdpp_deposit_value'] ) ) ) : '' );
		$product->update_meta_data( '_wbdpp_payment_mode', isset( $_POST['_wbdpp_payment_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['_wbdpp_payment_mode'] ) ) : 'optional' );
		$product->update_meta_data( '_wbdpp_due_type', isset( $_POST['_wbdpp_due_type'] ) ? sanitize_text_field( wp_unslash( $_POST['_wbdpp_due_type'] ) ) : '' );
		$product->update_meta_data( '_wbdpp_due_fixed_date', isset( $_POST['_wbdpp_due_fixed_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_wbdpp_due_fixed_date'] ) ) : '' );
		$product->update_meta_data( '_wbdpp_due_relative', isset( $_POST['_wbdpp_due_relative'] ) ? absint( wp_unslash( $_POST['_wbdpp_due_relative'] ) ) : 0 );
		$product->update_meta_data( '_wbdpp_refund_policy', isset( $_POST['_wbdpp_refund_policy'] ) ? sanitize_text_field( wp_unslash( $_POST['_wbdpp_refund_policy'] ) ) : '' );
		$product->update_meta_data( '_wbdpp_refund_partial_percent', isset( $_POST['_wbdpp_refund_partial_percent'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_wbdpp_refund_partial_percent'] ) ) ) : '' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Render category add fields.
	 *
	 * @return void
	 */
	public function render_category_add_fields(): void {
		wp_nonce_field( 'wbdpp_save_category_fields', 'wbdpp_category_fields_nonce' );
		?>
		<div class="form-field">
			<label for="_wbdpp_enable_rule"><?php esc_html_e( 'Enable deposit rule', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label>
			<select name="_wbdpp_enable_rule" id="_wbdpp_enable_rule">
				<option value="no"><?php esc_html_e( 'No', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
				<option value="yes"><?php esc_html_e( 'Yes', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
			</select>
		</div>
		<div class="form-field">
			<label for="_wbdpp_deposit_type"><?php esc_html_e( 'Deposit type', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label>
			<select name="_wbdpp_deposit_type" id="_wbdpp_deposit_type">
				<option value="percentage"><?php esc_html_e( 'Percentage', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
				<option value="fixed"><?php esc_html_e( 'Fixed amount', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
			</select>
		</div>
		<div class="form-field">
			<label for="_wbdpp_deposit_value"><?php esc_html_e( 'Deposit value', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label>
			<input type="number" min="0" step="0.01" name="_wbdpp_deposit_value" id="_wbdpp_deposit_value" value="" />
		</div>
		<div class="form-field">
			<label for="_wbdpp_payment_mode"><?php esc_html_e( 'Payment mode', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label>
			<select name="_wbdpp_payment_mode" id="_wbdpp_payment_mode">
				<option value="optional"><?php esc_html_e( 'Optional', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
				<option value="mandatory"><?php esc_html_e( 'Mandatory deposit', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
			</select>
		</div>
		<div class="form-field">
			<label for="_wbdpp_refund_policy"><?php esc_html_e( 'Refund policy override', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label>
			<select name="_wbdpp_refund_policy" id="_wbdpp_refund_policy">
				<option value=""><?php esc_html_e( 'Use global setting', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
				<option value="none"><?php esc_html_e( 'Non-refundable deposit', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
				<option value="full"><?php esc_html_e( 'Refund full deposit', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
				<option value="partial"><?php esc_html_e( 'Refund partial deposit', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
			</select>
		</div>
		<div class="form-field">
			<label for="_wbdpp_refund_partial_percent"><?php esc_html_e( 'Partial refund percentage override', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label>
			<input type="number" min="0" max="100" step="0.01" name="_wbdpp_refund_partial_percent" id="_wbdpp_refund_partial_percent" value="" />
		</div>
		<?php
	}

	/**
	 * Render category edit fields.
	 *
	 * @param \WP_Term $term Category term.
	 * @return void
	 */
	public function render_category_edit_fields( \WP_Term $term ): void {
		wp_nonce_field( 'wbdpp_save_category_fields', 'wbdpp_category_fields_nonce' );
		$enable_rule   = (string) get_term_meta( $term->term_id, '_wbdpp_enable_rule', true );
		$deposit_type  = (string) get_term_meta( $term->term_id, '_wbdpp_deposit_type', true );
		$deposit_value = (string) get_term_meta( $term->term_id, '_wbdpp_deposit_value', true );
		$payment_mode  = (string) get_term_meta( $term->term_id, '_wbdpp_payment_mode', true );
		$refund_policy = (string) get_term_meta( $term->term_id, '_wbdpp_refund_policy', true );
		$refund_pct    = (string) get_term_meta( $term->term_id, '_wbdpp_refund_partial_percent', true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="_wbdpp_enable_rule"><?php esc_html_e( 'Enable deposit rule', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label></th>
			<td>
				<select name="_wbdpp_enable_rule" id="_wbdpp_enable_rule">
					<option value="no" <?php selected( 'no', $enable_rule ); ?>><?php esc_html_e( 'No', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
					<option value="yes" <?php selected( 'yes', $enable_rule ); ?>><?php esc_html_e( 'Yes', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="_wbdpp_deposit_type"><?php esc_html_e( 'Deposit type', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label></th>
			<td>
				<select name="_wbdpp_deposit_type" id="_wbdpp_deposit_type">
					<option value="percentage" <?php selected( 'percentage', $deposit_type ); ?>><?php esc_html_e( 'Percentage', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
					<option value="fixed" <?php selected( 'fixed', $deposit_type ); ?>><?php esc_html_e( 'Fixed amount', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="_wbdpp_deposit_value"><?php esc_html_e( 'Deposit value', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label></th>
			<td><input type="number" min="0" step="0.01" name="_wbdpp_deposit_value" id="_wbdpp_deposit_value" value="<?php echo esc_attr( $deposit_value ); ?>" /></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="_wbdpp_payment_mode"><?php esc_html_e( 'Payment mode', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label></th>
			<td>
				<select name="_wbdpp_payment_mode" id="_wbdpp_payment_mode">
					<option value="optional" <?php selected( 'optional', $payment_mode ); ?>><?php esc_html_e( 'Optional', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
					<option value="mandatory" <?php selected( 'mandatory', $payment_mode ); ?>><?php esc_html_e( 'Mandatory deposit', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="_wbdpp_refund_policy"><?php esc_html_e( 'Refund policy override', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label></th>
			<td>
				<select name="_wbdpp_refund_policy" id="_wbdpp_refund_policy">
					<option value="" <?php selected( '', $refund_policy ); ?>><?php esc_html_e( 'Use global setting', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
					<option value="none" <?php selected( 'none', $refund_policy ); ?>><?php esc_html_e( 'Non-refundable deposit', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
					<option value="full" <?php selected( 'full', $refund_policy ); ?>><?php esc_html_e( 'Refund full deposit', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
					<option value="partial" <?php selected( 'partial', $refund_policy ); ?>><?php esc_html_e( 'Refund partial deposit', 'wb-deposits-partial-payments-for-woocommerce' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="_wbdpp_refund_partial_percent"><?php esc_html_e( 'Partial refund percentage override', 'wb-deposits-partial-payments-for-woocommerce' ); ?></label></th>
			<td><input type="number" min="0" max="100" step="0.01" name="_wbdpp_refund_partial_percent" id="_wbdpp_refund_partial_percent" value="<?php echo esc_attr( $refund_pct ); ?>" /></td>
		</tr>
		<?php
	}

	/**
	 * Save category settings.
	 *
	 * @param int $term_id Category id.
	 * @return void
	 */
	public function save_category_fields( int $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification is intentionally performed below.
		$nonce = isset( $_POST['wbdpp_category_fields_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wbdpp_category_fields_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wbdpp_save_category_fields' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['_wbdpp_enable_rule'] ) ) {
			update_term_meta( $term_id, '_wbdpp_enable_rule', sanitize_text_field( wp_unslash( $_POST['_wbdpp_enable_rule'] ) ) );
		}

		if ( isset( $_POST['_wbdpp_deposit_type'] ) ) {
			update_term_meta( $term_id, '_wbdpp_deposit_type', sanitize_text_field( wp_unslash( $_POST['_wbdpp_deposit_type'] ) ) );
		}

		if ( isset( $_POST['_wbdpp_deposit_value'] ) ) {
			update_term_meta( $term_id, '_wbdpp_deposit_value', wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_wbdpp_deposit_value'] ) ) ) );
		}

		if ( isset( $_POST['_wbdpp_payment_mode'] ) ) {
			update_term_meta( $term_id, '_wbdpp_payment_mode', sanitize_text_field( wp_unslash( $_POST['_wbdpp_payment_mode'] ) ) );
		}

		if ( isset( $_POST['_wbdpp_refund_policy'] ) ) {
			update_term_meta( $term_id, '_wbdpp_refund_policy', sanitize_text_field( wp_unslash( $_POST['_wbdpp_refund_policy'] ) ) );
		}

		if ( isset( $_POST['_wbdpp_refund_partial_percent'] ) ) {
			update_term_meta( $term_id, '_wbdpp_refund_partial_percent', wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_wbdpp_refund_partial_percent'] ) ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
