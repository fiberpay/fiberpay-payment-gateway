<?php

/**
* Fiberpay_WC_Payment_Gateway.
*
* Provides a Fiberpay_WC_Payment_Gateway
*
* @class Fiberpay_WC_Payment_Gateway
* @extends WC_Payment_Gateway
* @version 0.1.0
* @package Fiberpay\Payment
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
		$this->id = 'fiberpay_payments';
		$this->icon = $this->getIconFilePath();
		$this->has_fields = false;
		$this->method_title = __('Fiberpay', 'woocommerce');
		$this->method_description = __('Fiberpay payment gateway', 'woocommerce');

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->instructions = $this->get_option('instructions');
		$this->collect_order_code = $this->get_option('collect_order_code');
		$this->is_test_env = $this->get_option('is_test_env');
		$this->api_key = $this->get_option('api_key');
		$this->secret_key = $this->get_option('secret_key');

		// Actions.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options'));
		add_action('woocommerce_thankyou_bacs', array( $this, 'thankyou_page'));

		// Customer Emails.
		add_action('woocommerce_email_before_order_table', array( $this, 'email_instructions'), 10, 3);
	}

	private function getIconFilePath()
	{
		return dirname(plugin_dir_url(__FILE__)) . '/assets/logo-fiberpay.png';
	}

	/**
	* Initialise Gateway Settings Form Fields.
	*/
	public function init_form_fields() {

		$this->form_fields = [
			'enabled' => [
				'title' => __('Enable/Disable', 'woocommerce'),
				'type' => 'checkbox',
				'label' => __('Enable Fiberpay payments', 'woocommerce'),
				'default' => 'no',
			],
			'title' => [
				'title' => __('Title', 'woocommerce'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
				'default' => __('Fiberpay quick money transfer', 'woocommerce'),
				'desc_tip' => true,
			],
			'description' => [
				'title' => __('Description', 'woocommerce'),
				'type' => 'textarea',
				'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
				'default' => __('Make your payment directly into our bank account. Please use your Order ID as the payment reference.Your order will not be shipped until the funds have cleared in our account.', 'woocommerce'),
				'desc_tip' => true,
			],
			'is_test_env' => [
				'title' => __('Test Environment', 'woocommerce'),
				'type' => 'checkbox',
				'label' => __('Test Environment', 'woocommerce'),
				'description' => __('Check for test environment usage', 'woocommerce'),
				'default' => 'no',
				'desc_tip' => true,
			],
			'api_key' => [
				'title' => __('Api Key', 'woocommerce'),
				'type' => 'text',
				'description' => __('Your Fiberpay Api Key', 'woocommerce'),
				'desc_tip' => true,
			],
			'secret_key' => [
				'title' => __('Secret Key', 'woocommerce'),
				'type' => 'text',
				'description' => __('Your Fiberpay Secret Key', 'woocommerce'),
				'desc_tip' => true,
			],
			'collect_order_code' => [
				'title' => __('Collect order code', 'woocommerce'),
				'type' => 'text',
				'description' => __('Your Fiberpay Collect Order Code', 'woocommerce'),
				'desc_tip' => true,
			],
			'instructions' => [
				'title' => __('Instructions', 'woocommerce'),
				'type' => 'textarea',
				'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce'),
				'default' => '',
				'desc_tip' => true,
			],
		];

	}

	/**
	* Output for the order received page.
	*
	* @param int $order_id Order ID.
	*/
	public function thankyou_page( $order_id ) {

		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( wp_kses_post( $this->instructions ) ) ));
		}

	}

	/**
	* Add content to the WC emails.
	*
	* @param WC_Order $order Order object.
	* @param bool $sent_to_admin Sent to admin.
	* @param bool $plain_text Email format: plain text or HTML.
	*/
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

		if ( ! $sent_to_admin && 'bacs' === $order->get_payment_method() && $order->has_status('on-hold') ) {
			if ( $this->instructions ) {
				echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL);
			}
		}

	}

// $order_statuses = array(
// 'wc-pending' => _x( 'Pending payment', 'Order status', 'woocommerce' ),
// 'wc-processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
// 'wc-on-hold' => _x( 'On hold', 'Order status', 'woocommerce' ),
// 'wc-completed' => _x( 'Completed', 'Order status', 'woocommerce' ),
// 'wc-cancelled' => _x( 'Cancelled', 'Order status', 'woocommerce' ),
// 'wc-refunded' => _x( 'Refunded', 'Order status', 'woocommerce' ),
// 'wc-failed' => _x( 'Failed', 'Order status', 'woocommerce' ),
// );

	/**
	* Process the payment and return the result.
	*
	* @param int $order_id Order ID.
	* @return array
	*/
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id);

		if ( $order->get_total() > 0 ) {
			// Mark as on-hold (we're awaiting the payment).
			$order->update_status( apply_filters('woocommerce_bacs_process_payment_order_status', 'on-hold', $order ), __('Awaiting BACS payment', 'woocommerce'));
		} else {
			$order->payment_complete();
		}

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return [
			'result' => 'success',
			'redirect' => $this->get_return_url( $order ),
		];

	}

	/**
	* Return whether or not this gateway still requires setup to function.
	*
	* When this gateway is toggled on via AJAX, if this returns true a
	* redirect will occur to the settings page instead.
	*
	* @since 3.4.0
	* @return bool
	*/
	public function needs_setup() {
		return false;
	}

	private function checkConfig()
	{
		// $client = new \FiberPay\FiberPayClient($this->api_key, $this->secret_key, $this->is_test_env);
		// $ret = json_decode($client->getCollectOrderInfo($this->collect_order_code));

		// return isset($ret->data);
	}

}
