<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;

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
	   public mixed $collect_order_code;
	   public mixed $is_test_env;
	   public mixed $api_key;
	   public mixed $secret_key;


	private $CALLBACK_URL = "fiberpay-payment-callback";

	const PROD_COLLECT_ORDER_OPTION    = 'wc_fiberpay_order_data_prod';
	const TEST_COLLECT_ORDER_OPTION    = 'wc_fiberpay_order_data_test';

	private const CURRENCY_PLN = 'PLN';

	private static $valid_currencies = [
		self::CURRENCY_PLN,
	];

	/**
	* Array of locales
	*
	* @var array
	*/
	public $locale;

	/**
	 * The *Singleton* instance of this class
	 *
	 * @var Singleton
	 */
	private static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return Singleton The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private clone method to prevent cloning of the instance of the
	 * *Singleton* instance.
	 *
	 * @return void
	 */
	public function __clone() {}

	/**
	 * Private unserialize method to prevent unserializing of the *Singleton*
	 * instance.
	 *
	 * @return void
	 */
	public function __wakeup() {}

	/**
	* Constructor for the gateway.
	*/
	public function __construct() {
		// Load the settings.
		$this->init_form_fields();
		// $this->init_settings();

		$this->id = 'fiberpay_payments';
		$this->icon = $this->getIconFilePath();
		$this->has_fields = false;
		$this->method_title = __('Fiberpay', 'fiberpay-payments');
		$this->method_description = __('Fiberpay payment gateway', 'fiberpay-payments');

		// Define user set variables.
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->collect_order_code = $this->get_option('collect_order_code');
		$this->is_test_env = $this->get_option('is_test_env');
		$this->api_key = $this->get_option('api_key');
		$this->secret_key = $this->get_option('secret_key');

		// Actions for traditional checkout
		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
		add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);

		// Add callback endpoint
		add_action('woocommerce_api_'. $this->CALLBACK_URL, [$this, 'handle_callback']);

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
	}

	public function get_cached_collect_order() {
		if ( ! $this->is_connected() ) {
			return [];
		}

		$order = $this->read_collect_order_from_cache();
		if ( ! empty( $order ) && isset($order['data']) && $this->collect_order_code === $order['data']['code']) {
			return $order;
		}

		return $this->cache_collect_order();
	}

	private function is_connected() {

		$options = get_option( 'woocommerce_fiberpay_payments_settings', [] );

		return isset( $options['api_key'], $options['secret_key'] ) && trim( $options['api_key'] ) && trim( $options['secret_key'] );
	}

	/**
	 * Caches account data for a period of time.
	 */
	private function cache_collect_order() {
		$expiration = 2 * HOUR_IN_SECONDS;

		$client = $this->getFiberpayClient();
		$order = json_decode($client->getCollectOrderInfo($this->collect_order_code));

		if (!isset($order) || !isset($order->data) ) {
			return [];
		}

		set_transient( $this->get_transient_key(), $order, $expiration );

		return json_decode( wp_json_encode( $order ), true );
	}

	/**
	 * Checks Fiberpay connection mode if it is test mode or prod mode
	 *
	 * @return string Transient key of test mode when testmode is enabled, otherwise returns the key of prod mode.
	 */
	private function get_transient_key() {
		$settings_options = get_option( 'woocommerce_fiberpay_payments_settings', [] );
		$key              = isset( $settings_options['is_test_env'] ) && 'yes' === $settings_options['is_test_env'] ? self::TEST_COLLECT_ORDER_OPTION : self::PROD_COLLECT_ORDER_OPTION;

		return $key;
	}

		/**
	 * Read the account from the WP option we cache it in.
	 *
	 * @return array empty when no data found in transient, otherwise returns cached data
	 */
	private function read_collect_order_from_cache() {
		$account_cache = json_decode( wp_json_encode( get_transient( $this->get_transient_key() ) ), true );

		return false === $account_cache ? [] : $account_cache;
	}

	public function handle_callback()
	{
		if(!$this->isApiKeyHeaderValid()) {
			wp_die('Api key is not valid', '', ['response' => 400]);
		};

		$jwt = file_get_contents('php://input');
		$data = $this->decodeJWT($jwt);

		$order = $this->getWcOrder($data->payload);

		$order->payment_complete();

		wp_die('OK', '', ['response' => 200]);
	}

	private function decodeJWT($jwt)
	{
		$jwtKey = new Key($this->secret_key, 'HS256');
		try {
			$decoded = JWT::decode($jwt, $jwtKey);
		} catch (SignatureInvalidException $e) {
			wp_die('JWT signature is not valid', '', ['response' => 400]);
		}

		return $decoded;
	}

	public function getWcOrder($payload)
	{
		$orderId = $payload->customParams->wc_order_id;
		$order = wc_get_order($orderId);

		if(!$order) {
			wp_die("Order with id $orderId not found", '', ['response' => 400]);
		}

		return $order;
	}

	private function isApiKeyHeaderValid()
	{
		$headers = apache_request_headers();
		if($headers) {
			$apiKeyHeader = $headers['Api-Key'];
		} else {
			$apiKeyHeader = $_SERVER['HTTP_API_KEY'];
		};

		return $apiKeyHeader === $this->api_key;
	}

	public function handle_order_cancelled($order_id)
	{
		$order = wc_get_order($order_id);
		if ($order->get_payment_method() === $this->id ) {
			// $captured = $order->get_meta( '_stripe_charge_captured', true );
			// if ( 'no' === $captured ) {
				// $this->process_refund( $order_id );
			// }

			// This hook fires when admin manually changes order status to cancel.
			// do_action( 'woocommerce_stripe_process_manual_cancel', $order );
		}
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$currency  = get_woocommerce_currency();
		if(!in_array($currency, self::$valid_currencies)) {
			return false;
		};

		return parent::is_available();
	}


	private function getIconFilePath()
	{
		return dirname(plugin_dir_url(__FILE__)) . '/assets/logo-fiberpay.png';
	}

	private function getCallbackUrl()
	{
		$url = WC()->api_request_url( $this->CALLBACK_URL );
		return $url;
	}

	/**
	* Initialise Gateway Settings Form Fields.
	*/
	public function init_form_fields() {

		$this->form_fields = [
			'enabled' => [
				'title' => __('Enable/Disable', 'fiberpay-payments'),
				'type' => 'checkbox',
				'label' => __('Enable Fiberpay payments', 'fiberpay-payments'),
				'default' => 'no',
			],
			'title' => [
				'title' => __('Title', 'fiberpay-payments'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'fiberpay-payments'),
				'default' => __('Fiberpay quick money transfer', 'fiberpay-payments'),
				'desc_tip' => true,
			],
			'description' => [
				'title' => __('Description', 'fiberpay-payments'),
				'type' => 'textarea',
				'description' => __('Payment method description that the customer will see on your checkout.', 'fiberpay-payments'),
				'default' => __('Make your payment with quick money transfer or traditional bank transfer.', 'fiberpay-payments'),
				'desc_tip' => true,
			],
			'is_test_env' => [
				'title' => __('Test Environment', 'fiberpay-payments'),
				'type' => 'checkbox',
				'label' => __('Test Environment', 'fiberpay-payments'),
				'description' => __('Check for test environment usage', 'fiberpay-payments'),
				'default' => 'no',
				'desc_tip' => true,
			],
			'api_key' => [
				'title' => __('Api Key', 'fiberpay-payments'),
				'type' => 'text',
				'description' => __('Your Fiberpay Api Key', 'fiberpay-payments'),
				'desc_tip' => true,
			],
			'secret_key' => [
				'title' => __('Secret Key', 'fiberpay-payments'),
				'type' => 'text',
				'description' => __('Your Fiberpay Secret Key', 'fiberpay-payments'),
				'desc_tip' => true,
			],
			'collect_order_code' => [
				'title' => __('Collect order code', 'fiberpay-payments'),
				'type' => 'text',
				'description' => __('Your Fiberpay Collect Order Code', 'fiberpay-payments'),
				'desc_tip' => true,
			],
		];

	}

	/**
	* Process the payment and return the result.
	*
	* @param int $order_id Order ID.
	* @return array
	*/
	public function process_payment($order_id) {

		$order = wc_get_order($order_id);

		$totalAmount = $order->get_total();

		if ( $totalAmount > 0 ) {
			$orderItem = $order->get_meta('_fiberpay_create_item_response');
			if(!empty($orderItem)) {
				$redirect = json_decode($orderItem)->data->redirect;
			} else {
				$client = $this->getFiberpayClient();

				$shopname = get_bloginfo('name');
				/* translators: The first placeholder is a shop name, the second is a order identifier. */
				$description = sprintf( __( '%1$s - Payment for order #%2$s', 'fiberpay-payments' ), $shopname, $order_id );

				$currency = $order->get_data()['currency'];
				// $buyerFirstName = $order->get_billing_first_name();
				// $buyerLastName = $order->get_billing_last_name();
				// $buyerEmail = $order->get_billing_email();

				$callbackUrl = $this->getCallbackUrl();
				$callbackParams = json_encode([
					'wc_order_id' => $order_id,
				]);

				$redirectUrl = $order->get_checkout_order_received_url();

				$res = $client->addCollectItem(
					$this->collect_order_code,
					$description,
					$totalAmount,
					$currency,
					$callbackUrl,
					$callbackParams,
					null,
					$redirectUrl
				);

				$order->update_meta_data( '_fiberpay_create_item_response', $res );
				$res = json_decode($res);
				$order->update_meta_data( '_fiberpay_order_item_code', $res->data->code );
				$order->save();
				$redirect = $res->data->redirect;
			}
		} else {
			$order->payment_complete();
		}

		// Remove cart.
		WC()->cart->empty_cart();

		$ret = [
			'result' => 'success',
			'redirect' => $redirect,
		];

		return $ret;

	}

	public function process_admin_options() {
		// Load all old values before the new settings get saved.
		$old_api_key      = $this->get_option( 'api_key' );
		$old_secret_key   = $this->get_option( 'secret_key' );
		$old_is_test_env   = $this->get_option( 'is_test_env' );

		parent::process_admin_options();

		// Load all old values after the new settings have been saved.
		$new_api_key      = $this->get_option( 'api_key' );
		$new_secret_key           = $this->get_option( 'secret_key' );
		$new_is_test_env           = $this->get_option( 'is_test_env' );

		// Checks whether a value has transitioned from a non-empty value to a new one.
		$has_changed = function( $old_value, $new_value ) {
			return ! empty( $old_value ) && ( $old_value !== $new_value );
		};

		$shouldUpdate = $has_changed( $old_api_key, $new_api_key )
		|| $has_changed( $old_secret_key, $new_secret_key )
		|| $has_changed( $old_is_test_env, $new_is_test_env );

		update_option( 'wc_fiberpay_payments_show_changed_keys_notice', $shouldUpdate ? 'yes' : 'no');
	}

	private function getFiberpayClient()
	{
		$client = new \FiberPay\FiberPayClient($this->api_key, $this->secret_key, $this->is_test_env);
		return $client;
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page($order_id) {
		$order = wc_get_order($order_id);
		if ($order && $order->get_payment_method() === $this->id) {
			if ($order->has_status('on-hold')) {
				echo '<p class="woocommerce-notice woocommerce-notice--success">' . 
					 esc_html__('Thank you for your order. We are now redirecting you to Fiberpay to make payment.', 'fiberpay-payments') . 
					 '</p>';
			}
		}
	}

	/**
	 * Add content to the WC emails.
	 */
	public function email_instructions($order, $sent_to_admin, $plain_text = false) {
		if ($sent_to_admin || $order->get_payment_method() !== $this->id || !$order->has_status('on-hold')) {
			return;
		}

		echo wp_kses_post(wpautop(wptexturize($this->description)));
	}

	/**
	 * Receipt page for traditional checkout
	 */
	public function receipt_page($order_id) {
		$order = wc_get_order($order_id);
		
		echo '<p>' . esc_html__('Thank you for your order, please click the button below to pay with Fiberpay.', 'fiberpay-payments') . '</p>';
		
		$orderItem = $order->get_meta('_fiberpay_create_item_response');
		if (!empty($orderItem)) {
			$redirect = json_decode($orderItem)->data->redirect;
			echo '<p><a class="button" href="' . esc_url($redirect) . '">' . 
				 esc_html__('Pay with Fiberpay', 'fiberpay-payments') . 
				 '</a></p>';
		}
	}

}
