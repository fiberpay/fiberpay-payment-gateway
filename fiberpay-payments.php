<?php
/*
 * Plugin Name: Fiberpay Payment Plugin
 * Plugin URI: https://fiberpay.pl
 * Description: Take instant payments on your store.
 * Author: Fiberpay
 * Author URI: https://fiberpay.pl
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

function fiberpay_add_gateway_class( $gateways ) {
	$gateways[] = 'Fiberpay_WC_Payment_Gateway';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'fiberpay_add_gateway_class' );

add_action( 'plugins_loaded', 'fiberpay_init_gateway_class', 11 );
function fiberpay_init_gateway_class() {
	/**
 	* Fiberpay_WC_Payment_Gateway.
 	*
 	* Provides a Fiberpay_WC_Payment_Gateway
 	*
 	* @class       Fiberpay_WC_Payment_Gateway
 	* @extends     WC_Payment_Gateway
 	* @version     0.1.0
 	* @package     Fiberpay\Payment
 	*/
	class Fiberpay_WC_Payment_Gateway extends WC_Payment_Gateway {

		/**
		 * Array of locales
		 *
		 * @var array
		 */
		public $locale;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {

			$this->id                 = 'fiberpay_payments';
			$this->icon               = apply_filters( 'woocommerce_bacs_icon', '' );
			$this->has_fields         = false;
			$this->method_title       = __( 'Fiberpay', 'woocommerce' );
			$this->method_description = __( 'Fiberpay payment gateway', 'woocommerce' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables.
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions' );

			// BACS account fields shown on the thanks page and in emails.
			$this->account_details = get_option(
				'woocommerce_bacs_accounts',
				array(
					array(
						'account_name'   => $this->get_option( 'account_name' ),
						'account_number' => $this->get_option( 'account_number' ),
						'sort_code'      => $this->get_option( 'sort_code' ),
						'bank_name'      => $this->get_option( 'bank_name' ),
						'iban'           => $this->get_option( 'iban' ),
						'bic'            => $this->get_option( 'bic' ),
					),
				)
			);

			// Actions.
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_bacs', array( $this, 'thankyou_page' ) );

			// Customer Emails.
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields() {

			$this->form_fields = array(
				'enabled'         => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Fiberpay payments', 'woocommerce' ),
					'default' => 'no',
				),
				'title'           => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Fiberpay quick money transfer', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'description'     => array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'instructions'    => array(
					'title'       => __( 'Instructions', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			);

		}

		/**
		 * Output for the order received page.
		 *
		 * @param int $order_id Order ID.
		 */
		public function thankyou_page( $order_id ) {

			if ( $this->instructions ) {
				echo wp_kses_post( wpautop( wptexturize( wp_kses_post( $this->instructions ) ) ) );
			}

		}

		/**
		 * Add content to the WC emails.
		 *
		 * @param WC_Order $order Order object.
		 * @param bool     $sent_to_admin Sent to admin.
		 * @param bool     $plain_text Email format: plain text or HTML.
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

			if ( ! $sent_to_admin && 'bacs' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
				if ( $this->instructions ) {
					echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
				}
			}

		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id Order ID.
		 * @return array
		 */
		public function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );

			if ( $order->get_total() > 0 ) {
				// Mark as on-hold (we're awaiting the payment).
				$order->update_status( apply_filters( 'woocommerce_bacs_process_payment_order_status', 'on-hold', $order ), __( 'Awaiting BACS payment', 'woocommerce' ) );
			} else {
				$order->payment_complete();
			}

			// Remove cart.
			WC()->cart->empty_cart();

			// Return thankyou redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		}

		/**
		 * Get country locale if localized.
		 *
		 * @return array
		 */
		public function get_country_locale() {

			if ( empty( $this->locale ) ) {

				// Locale information to be used - only those that are not 'Sort Code'.
				$this->locale = apply_filters(
					'woocommerce_get_bacs_locale',
					array(
						'AU' => array(
							'sortcode' => array(
								'label' => __( 'BSB', 'woocommerce' ),
							),
						),
						'CA' => array(
							'sortcode' => array(
								'label' => __( 'Bank transit number', 'woocommerce' ),
							),
						),
						'IN' => array(
							'sortcode' => array(
								'label' => __( 'IFSC', 'woocommerce' ),
							),
						),
						'IT' => array(
							'sortcode' => array(
								'label' => __( 'Branch sort', 'woocommerce' ),
							),
						),
						'NZ' => array(
							'sortcode' => array(
								'label' => __( 'Bank code', 'woocommerce' ),
							),
						),
						'SE' => array(
							'sortcode' => array(
								'label' => __( 'Bank code', 'woocommerce' ),
							),
						),
						'US' => array(
							'sortcode' => array(
								'label' => __( 'Routing number', 'woocommerce' ),
							),
						),
						'ZA' => array(
							'sortcode' => array(
								'label' => __( 'Branch code', 'woocommerce' ),
							),
						),
					)
				);

			}

			return $this->locale;

		}
	}

}
