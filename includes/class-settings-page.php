<?php
namespace WBCOM\WBDPP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Settings_Page' ) ) {
	return;
}

final class Settings_Page extends \WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'wbdpp';
		$this->label = esc_html__( 'WB Deposits', 'wb-deposits-partial-payments-for-woocommerce' );

		parent::__construct();
	}

	/**
	 * Return sections.
	 *
	 * @return array<string, string>
	 */
	public function get_sections() {
		return array(
			''             => esc_html__( 'General', 'wb-deposits-partial-payments-for-woocommerce' ),
			'due_dates'    => esc_html__( 'Due Dates', 'wb-deposits-partial-payments-for-woocommerce' ),
			'checkout_ui'  => esc_html__( 'Checkout Display', 'wb-deposits-partial-payments-for-woocommerce' ),
			'tax_discount' => esc_html__( 'Tax & Discount Rules', 'wb-deposits-partial-payments-for-woocommerce' ),
			'cancellation' => esc_html__( 'Cancellation & Refunds', 'wb-deposits-partial-payments-for-woocommerce' ),
		);
	}

	/**
	 * Return settings per section.
	 *
	 * @param string $current_section Section key.
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_section_core( $current_section ) {
		switch ( (string) $current_section ) {
			case 'due_dates':
				return $this->due_date_settings();
			case 'checkout_ui':
				return $this->checkout_display_settings();
			case 'tax_discount':
				return $this->tax_discount_settings();
			case 'cancellation':
				return $this->cancellation_settings();
			default:
				return $this->general_settings();
		}
	}

	/**
	 * General settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function general_settings(): array {
		return array(
			array(
				'type'    => 'wbdpp_premium_header',
				'title'   => esc_html__( 'General Configuration', 'wb-deposits-partial-payments-for-woocommerce' ),
				'eyebrow' => esc_html__( 'Premium Deposit Controls', 'wb-deposits-partial-payments-for-woocommerce' ),
				'desc'    => esc_html__( 'Configure the default deposit policy for your store. Product and category-level settings can override these defaults.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'chips'   => array(
					esc_html__( 'Global Defaults', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Category Overrides', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Product Priority', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'type'  => 'wbdpp_info_panel',
				'title' => esc_html__( 'Recommended Setup', 'wb-deposits-partial-payments-for-woocommerce' ),
				'desc'  => esc_html__( 'Use these defaults for predictable operations, then override only where needed.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'tone'  => 'success',
				'items' => array(
					esc_html__( 'Keep payment mode optional for catalog-wide flexibility.', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Reserve stock at deposit time for limited inventory products.', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Charge shipping on balance order for physical goods.', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'title'   => esc_html__( 'Enable deposits', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_enabled',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => esc_html__( 'Enable partial payment and balance-order workflows storewide.', 'wb-deposits-partial-payments-for-woocommerce' ),
			),
			array(
				'title'   => esc_html__( 'Default deposit type', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_default_deposit_type',
				'type'    => 'select',
				'default' => 'percentage',
				'options' => array(
					'percentage' => esc_html__( 'Percentage', 'wb-deposits-partial-payments-for-woocommerce' ),
					'fixed'      => esc_html__( 'Fixed amount', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
				'desc_tip' => true,
				'description' => esc_html__( 'Use percentage for price-scaled deposits, or fixed for flat reservation charges.', 'wb-deposits-partial-payments-for-woocommerce' ),
			),
			array(
				'title'             => esc_html__( 'Default deposit value', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'                => 'wbdpp_default_deposit_value',
				'type'              => 'number',
				'default'           => '20',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
				'desc_tip' => true,
				'description' => esc_html__( 'If type is Percentage, this value is treated as %. If Fixed, it uses store currency.', 'wb-deposits-partial-payments-for-woocommerce' ),
			),
			array(
				'title'   => esc_html__( 'Payment mode', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_default_payment_mode',
				'type'    => 'select',
				'default' => 'optional',
				'options' => array(
					'optional'  => esc_html__( 'Optional (full or deposit)', 'wb-deposits-partial-payments-for-woocommerce' ),
					'mandatory' => esc_html__( 'Mandatory deposit', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
				'desc_tip' => true,
				'description' => esc_html__( 'Optional gives customer choice. Mandatory enforces deposit-first checkout.', 'wb-deposits-partial-payments-for-woocommerce' ),
			),
			array(
				'title'   => esc_html__( 'Stock reservation', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_stock_reduce_stage',
				'type'    => 'select',
				'default' => 'deposit',
				'options' => array(
					'deposit' => esc_html__( 'Reduce stock at deposit payment', 'wb-deposits-partial-payments-for-woocommerce' ),
					'final'   => esc_html__( 'Reduce stock at final payment', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
				'desc_tip' => true,
				'description' => esc_html__( 'Deposit-time stock reduction is recommended for reservations.', 'wb-deposits-partial-payments-for-woocommerce' ),
			),
			array(
				'title'   => esc_html__( 'Shipping handling', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_shipping_charge_stage',
				'type'    => 'select',
				'default' => 'balance',
				'options' => array(
					'deposit' => esc_html__( 'Charge shipping in deposit order', 'wb-deposits-partial-payments-for-woocommerce' ),
					'balance' => esc_html__( 'Charge shipping in balance order', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
				'desc_tip' => true,
				'description' => esc_html__( 'Balance-stage shipping is usually preferred for physical goods.', 'wb-deposits-partial-payments-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbdpp_general',
			),
		);
	}

	/**
	 * Due date settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function due_date_settings(): array {
		return array(
			array(
				'type'    => 'wbdpp_premium_header',
				'title'   => esc_html__( 'Due Dates & Reminder Automation', 'wb-deposits-partial-payments-for-woocommerce' ),
				'eyebrow' => esc_html__( 'Collections Workflow', 'wb-deposits-partial-payments-for-woocommerce' ),
				'desc'    => esc_html__( 'Define when remaining balances are due and automate reminder communication.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'chips'   => array(
					esc_html__( 'Fixed or Relative', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Reminder Schedule', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Booking-Aware (Optional)', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'type'  => 'wbdpp_info_panel',
				'title' => esc_html__( 'Collections Best Practice', 'wb-deposits-partial-payments-for-woocommerce' ),
				'desc'  => esc_html__( 'Relative due dates are generally safer for ongoing storefronts than fixed calendar dates.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'tone'  => 'neutral',
				'items' => array(
					esc_html__( 'Use reminders at 7, 3, 1, and 0 days before due date.', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Enable booking-aware due dates only when booking metadata is present.', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'title'   => esc_html__( 'Default due type', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_default_due_type',
				'type'    => 'select',
				'default' => 'relative',
				'options' => array(
					'fixed'    => esc_html__( 'Fixed date', 'wb-deposits-partial-payments-for-woocommerce' ),
					'relative' => esc_html__( 'Relative days after deposit payment', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'title'             => esc_html__( 'Fixed due date', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'                => 'wbdpp_default_due_fixed_date',
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => esc_html__( 'Use format YYYY-MM-DD.', 'wb-deposits-partial-payments-for-woocommerce' ),
			),
			array(
				'title'             => esc_html__( 'Relative due days', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'                => 'wbdpp_default_due_relative',
				'type'              => 'number',
				'default'           => '30',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			array(
				'title'       => esc_html__( 'Reminder schedule', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'          => 'wbdpp_reminder_offsets',
				'type'        => 'text',
				'default'     => '7,3,1,0',
				'desc_tip'    => true,
				'description' => esc_html__( 'Comma-separated days before due date. Example: 7,3,1,0', 'wb-deposits-partial-payments-for-woocommerce' ),
			),
			array(
				'title'   => esc_html__( 'Booking-aware due date', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_enable_booking_due',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => esc_html__( 'If booking metadata exists, set balance due before booking start.', 'wb-deposits-partial-payments-for-woocommerce' ),
			),
			array(
				'title'             => esc_html__( 'Days before booking start', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'                => 'wbdpp_booking_due_days_before',
				'type'              => 'number',
				'default'           => '7',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbdpp_due_dates',
			),
		);
	}

	/**
	 * Checkout display settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function checkout_display_settings(): array {
		return array(
			array(
				'type'    => 'wbdpp_premium_header',
				'title'   => esc_html__( 'Checkout UX & Messaging', 'wb-deposits-partial-payments-for-woocommerce' ),
				'eyebrow' => esc_html__( 'Customer Experience', 'wb-deposits-partial-payments-for-woocommerce' ),
				'desc'    => esc_html__( 'Customize labels and explanatory messaging so customers understand deposit vs balance terms.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'chips'   => array(
					esc_html__( 'Pay Full vs Deposit', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Clear Messaging', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Dynamic Placeholders', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'type'  => 'wbdpp_info_panel',
				'title' => esc_html__( 'Conversion Tip', 'wb-deposits-partial-payments-for-woocommerce' ),
				'desc'  => esc_html__( 'Keep label copy short and explicit so buyers immediately understand payment timing.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'tone'  => 'neutral',
				'items' => array(
					esc_html__( 'Avoid legal-heavy language in labels; keep details in the message template.', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Use placeholders to show exact deposit, balance, and due date at checkout.', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'title'   => esc_html__( 'Pay full label', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_ui_pay_full_label',
				'type'    => 'text',
				'default' => 'Pay full amount',
			),
			array(
				'title'   => esc_html__( 'Pay deposit label', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_ui_pay_deposit_label',
				'type'    => 'text',
				'default' => 'Pay deposit',
			),
			array(
				'title'       => esc_html__( 'Deposit message template', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'          => 'wbdpp_ui_deposit_message',
				'type'        => 'textarea',
				'default'     => 'Deposit: {deposit} | Remaining: {remaining} | Due: {due_date}',
				'desc_tip'    => true,
				'description' => esc_html__( 'Supported placeholders: {deposit}, {remaining}, {due_date}', 'wb-deposits-partial-payments-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbdpp_checkout_ui',
			),
		);
	}

	/**
	 * Tax and discount settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function tax_discount_settings(): array {
		return array(
			array(
				'type'    => 'wbdpp_premium_header',
				'title'   => esc_html__( 'Tax, Coupons & Financial Split', 'wb-deposits-partial-payments-for-woocommerce' ),
				'eyebrow' => esc_html__( 'Pricing Compliance', 'wb-deposits-partial-payments-for-woocommerce' ),
				'desc'    => esc_html__( 'Control how taxes and discount values are allocated across deposit and remaining balance orders.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'chips'   => array(
					esc_html__( 'Tax Allocation', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Coupon Rules', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Accounting Clarity', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'type'  => 'wbdpp_info_panel',
				'title' => esc_html__( 'Finance Recommendation', 'wb-deposits-partial-payments-for-woocommerce' ),
				'desc'  => esc_html__( 'If unsure, start with split tax and proportional coupon allocation for balanced accounting.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'tone'  => 'warn',
				'items' => array(
					esc_html__( 'Validate tax mode with your accountant or local compliance rules.', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Test one discounted order before applying changes storewide.', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'title'   => esc_html__( 'Tax mode', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_tax_mode',
				'type'    => 'select',
				'default' => 'split',
				'options' => array(
					'full_upfront' => esc_html__( 'Charge full tax in deposit order', 'wb-deposits-partial-payments-for-woocommerce' ),
					'split'        => esc_html__( 'Split tax across deposit and balance', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'title'   => esc_html__( 'Coupon split mode', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_coupon_split_mode',
				'type'    => 'select',
				'default' => 'proportional',
				'options' => array(
					'full'         => esc_html__( 'Apply discounts to full amount only', 'wb-deposits-partial-payments-for-woocommerce' ),
					'deposit'      => esc_html__( 'Apply discounts to deposit only', 'wb-deposits-partial-payments-for-woocommerce' ),
					'proportional' => esc_html__( 'Split discounts proportionally', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbdpp_tax_discount',
			),
		);
	}

	/**
	 * Cancellation and refund settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function cancellation_settings(): array {
		return array(
			array(
				'type'    => 'wbdpp_premium_header',
				'title'   => esc_html__( 'Cancellation, Refunds & Dunning', 'wb-deposits-partial-payments-for-woocommerce' ),
				'eyebrow' => esc_html__( 'Risk Management', 'wb-deposits-partial-payments-for-woocommerce' ),
				'desc'    => esc_html__( 'Define cancellation outcomes, deposit refund behavior, and automatic handling for overdue balances.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'chips'   => array(
					esc_html__( 'Refund Policy', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Overdue Controls', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Auto-Cancel Logic', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'type'  => 'wbdpp_info_panel',
				'title' => esc_html__( 'Risk Controls', 'wb-deposits-partial-payments-for-woocommerce' ),
				'desc'  => esc_html__( 'Define cancellation and refund outcomes clearly before taking deposits in production.', 'wb-deposits-partial-payments-for-woocommerce' ),
				'tone'  => 'warn',
				'items' => array(
					esc_html__( 'Use non-refundable or partial refunds when reservations block availability.', 'wb-deposits-partial-payments-for-woocommerce' ),
					esc_html__( 'Enable auto-cancel only after confirming your reminder cadence.', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'title'   => esc_html__( 'Cancel balance order when deposit order is cancelled/refunded', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_cancel_balance_on_parent_cancel',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => esc_html__( 'Deposit refund policy on cancellation', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_deposit_refund_policy',
				'type'    => 'select',
				'default' => 'none',
				'options' => array(
					'none'    => esc_html__( 'Non-refundable deposit', 'wb-deposits-partial-payments-for-woocommerce' ),
					'full'    => esc_html__( 'Refund full deposit', 'wb-deposits-partial-payments-for-woocommerce' ),
					'partial' => esc_html__( 'Refund partial deposit', 'wb-deposits-partial-payments-for-woocommerce' ),
				),
			),
			array(
				'title'             => esc_html__( 'Partial refund percentage', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'                => 'wbdpp_deposit_refund_partial_percent',
				'type'              => 'number',
				'default'           => '50',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '100',
					'step' => '0.01',
				),
			),
			array(
				'title'   => esc_html__( 'Auto-cancel overdue balance orders', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'      => 'wbdpp_auto_cancel_overdue_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'             => esc_html__( 'Cancel after overdue days', 'wb-deposits-partial-payments-for-woocommerce' ),
				'id'                => 'wbdpp_auto_cancel_overdue_days',
				'type'              => 'number',
				'default'           => '7',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wbdpp_cancellation',
			),
		);
	}
}
