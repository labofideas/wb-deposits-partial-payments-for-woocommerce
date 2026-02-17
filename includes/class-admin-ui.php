<?php
namespace WBCOM\WBDPP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_UI {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_admin_field_wbdpp_premium_header', array( $this, 'render_premium_header' ) );
		add_action( 'woocommerce_admin_field_wbdpp_info_panel', array( $this, 'render_info_panel' ) );
	}

	/**
	 * Enqueue premium settings UI assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! is_admin() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';

		if ( 'wc-settings' !== $page || 'wbdpp' !== $tab ) {
			return;
		}

		wp_enqueue_style(
			'wbdpp-admin-settings',
			WBDPP_URL . 'assets/css/admin-settings.css',
			array(),
			WBDPP_VERSION
		);

		wp_enqueue_script(
			'wbdpp-admin-settings',
			WBDPP_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			WBDPP_VERSION,
			true
		);
	}

	/**
	 * Render premium header block for each section.
	 *
	 * @param array<string, mixed> $value Setting config.
	 * @return void
	 */
	public function render_premium_header( array $value ): void {
		$title       = isset( $value['title'] ) ? (string) $value['title'] : '';
		$description = isset( $value['desc'] ) ? (string) $value['desc'] : '';
		$eyebrow     = isset( $value['eyebrow'] ) ? (string) $value['eyebrow'] : esc_html__( 'WB Deposits', 'wb-deposits-partial-payments-for-woocommerce' );
		$chips       = isset( $value['chips'] ) && is_array( $value['chips'] ) ? $value['chips'] : array();

		echo '<tr class="wbdpp-admin-header-row"><td colspan="2">';
		echo '<div class="wbdpp-admin-header-card">';
		echo '<p class="wbdpp-admin-eyebrow">' . esc_html( $eyebrow ) . '</p>';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<p>' . esc_html( $description ) . '</p>';
		if ( ! empty( $chips ) ) {
			echo '<div class="wbdpp-admin-chips">';
			foreach ( $chips as $chip ) {
				echo '<span class="wbdpp-admin-chip">' . esc_html( (string) $chip ) . '</span>';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '</td></tr>';
	}

	/**
	 * Render contextual information panel.
	 *
	 * @param array<string, mixed> $value Setting config.
	 * @return void
	 */
	public function render_info_panel( array $value ): void {
		$title = isset( $value['title'] ) ? (string) $value['title'] : '';
		$body  = isset( $value['desc'] ) ? (string) $value['desc'] : '';
		$tone  = isset( $value['tone'] ) ? (string) $value['tone'] : 'neutral';
		$items = isset( $value['items'] ) && is_array( $value['items'] ) ? $value['items'] : array();

		echo '<tr class="wbdpp-admin-info-row"><td colspan="2">';
		echo '<div class="wbdpp-admin-info-panel wbdpp-tone-' . esc_attr( $tone ) . '">';
		if ( '' !== $title ) {
			echo '<h3>' . esc_html( $title ) . '</h3>';
		}
		if ( '' !== $body ) {
			echo '<p>' . esc_html( $body ) . '</p>';
		}
		if ( ! empty( $items ) ) {
			echo '<ul>';
			foreach ( $items as $item ) {
				echo '<li>' . esc_html( (string) $item ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
		echo '</td></tr>';
	}
}
