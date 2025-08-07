<?php
/**
 * PayPal Notices Class
 *
 * @package WooCommerce\Gateways
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wc-gateway-paypal-helper.php';

/**
 * Class WC_Gateway_Paypal_Notices.
 */
class WC_Gateway_Paypal_Notices {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_head', array( $this, 'add_paypal_tos_notice' ) );
		add_action( 'admin_init', array( $this, 'handle_paypal_tos_response' ) );
	}

	/**
	 * Add the PayPal TOS notice.
	 *
	 * @return void
	 */
	public function add_paypal_tos_notice() {
		// Show only to users who can manage the site.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! WC_Gateway_Paypal_Helper::is_orders_v2_migration_eligible() ) {
			return;
		}

		if ( WC_Gateway_Paypal_Helper::is_tos_accepted() ) {
			return;
		}

		if ( get_option( 'show_woocommerce_paypal_tos_notice' ) === 'no' ) {
			return;
		}

		// Build URLs for Agree and Dismiss actions with nonces.
		$agree_url   = wp_nonce_url( add_query_arg( 'paypal_tos_action', 'agree' ), 'paypal_tos_action' );
		$dismiss_url = wp_nonce_url( add_query_arg( 'paypal_tos_action', 'dismiss' ), 'paypal_tos_action' );

		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p>By continuing to use PayPal Standard, you agree to our updated <a href="https://www.example.com" target="_blank">Terms of Service</a>.</p>';
		echo '<p>';
		echo '<a href="' . esc_url( $agree_url ) . '" class="components-button is-secondary">Accept</a> ';
		echo '<a href="' . esc_url( $dismiss_url ) . '" class="components-button is-tertiary">Dismiss</a>';
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Handle the action on the PayPal TOS notice.
	 *
	 * @return void
	 */
	public function handle_paypal_tos_response() {
		if ( isset( $_GET['paypal_tos_action'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'paypal_tos_action' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'woocommerce' ) );
			}

			// Check user permissions.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not authorized to perform this action.', 'woocommerce' ) );
			}

			if ( 'agree' === wp_unslash( $_GET['paypal_tos_action'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$this->handle_agree_action();
			} elseif ( 'dismiss' === wp_unslash( $_GET['paypal_tos_action'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$this->handle_dismiss_action();
			}
		}
	}

	/**
	 * Handle the agree action. When user agrees to the TOS,
	 * 1. we set the flag to true in PayPal settings.
	 * 2. we register the site with WPCOM if it is not already registered.
	 * 3. we hide the notice.
	 *
	 * @return void
	 */
	private function handle_agree_action() {
		// Set the flag to true in PayPal settings.
		$settings                    = get_option( 'woocommerce_paypal_settings', array() );
		$settings['is_tos_accepted'] = 'yes';
		update_option( 'woocommerce_paypal_settings', $settings );

		// Register the site with WPCOM if it is not already registered.
		$gateway = $this->get_paypal_gateway();
		if ( $gateway ) {
			$gateway->maybe_register_site_with_wpcom();
		}

		// Hide the notice.
		update_option( 'show_woocommerce_paypal_tos_notice', 'no' );
	}

	/**
	 * Handle the dismiss action.
	 *
	 * @return void
	 */
	private function handle_dismiss_action() {
		// Hide the notice.
		update_option( 'show_woocommerce_paypal_tos_notice', 'no' );
	}

	/**
	 * Get the PayPal gateway.
	 *
	 * @return WC_Gateway_Paypal|null
	 */
	private function get_paypal_gateway() {
		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		if ( ! isset( $payment_gateways['paypal'] ) ) {
			return null;
		}
		return $payment_gateways['paypal'];
	}
}
