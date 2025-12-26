<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;

/**
* FIBERPAYGW_Payment_Gateway
*
* Provides a Fiberpay payment gateway for WooCommerce
*
* @class FIBERPAYGW_Payment_Gateway
* @version 0.1.9
* @package Fiberpay\Payment
*/
class FIBERPAYGW_Payment_Gateway extends WC_Payment_Gateway {
	public ?string $collect_order_code;
	public ?string $is_test_env;
	public ?string $api_key;
	public ?string $secret_key;


	private $callback_url = 'fiberpay-payment-callback';

	const PROD_COLLECT_ORDER_OPTION = 'fiberpaygw_order_data_prod';
	const TEST_COLLECT_ORDER_OPTION = 'fiberpaygw_order_data_test';

	private const CURRENCY_PLN = 'PLN';

	private static $valid_currencies = array(
		self::CURRENCY_PLN,
	);

	/**
	* Array of locales
	*
	* @var array
	*/
	public $locale;

	/**
	 * The *Singleton* instance of this class
	 *
	 * @var FIBERPAYGW_Payment_Gateway|null
	 */
	private static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return FIBERPAYGW_Payment_Gateway The *Singleton* instance.
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

		$this->id                 = 'fiberpay_payments';
		$this->icon               = $this->getIconFilePath();
		$this->has_fields         = false;
		$this->method_title       = __( 'Fiberpay', 'fiberpay-payment-gateway' );
		$this->method_description = __( 'Fiberpay payment gateway', 'fiberpay-payment-gateway' );

		// Define user set variables.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->collect_order_code = $this->get_option( 'collect_order_code' );
		$this->is_test_env        = $this->get_option( 'is_test_env' );
		$this->api_key            = $this->get_option( 'api_key' );
		$this->secret_key         = $this->get_option( 'secret_key' );

		// Actions.
		// add callback endpoint
		add_action( 'woocommerce_api_' . $this->callback_url, array( $this, 'handle_callback' ) );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function get_cached_collect_order() {
		if ( ! $this->is_connected() ) {
			return array();
		}

		$order = $this->read_collect_order_from_cache();
		if ( ! empty( $order ) && isset( $order['data'] ) && $this->collect_order_code === $order['data']['code']) {
			return $order;
		}

		return $this->cache_collect_order();
	}

	private function is_connected() {
		$options = get_option( 'woocommerce_fiberpay_payments_settings', array() );

		return isset( $options['api_key'], $options['secret_key'] ) && trim( $options['api_key'] ) && trim( $options['secret_key'] );
	}

	/**
	 * Caches account data for a period of time.
	 */
	private function cache_collect_order() {
		$expiration = 2 * HOUR_IN_SECONDS;

		$client = $this->getFiberpayClient();
		$order  = json_decode( $client->getCollectOrderInfo( $this->collect_order_code ) );

		if ( ! isset( $order ) || ! isset( $order->data ) ) {
			return array();
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
		$settings_options = get_option( 'woocommerce_fiberpay_payments_settings', array() );
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

		return false === $account_cache ? array() : $account_cache;
	}

	public function handle_callback() {
		if ( ! $this->isApiKeyHeaderValid()) {
			wp_die( 'Api key is not valid', '', array( 'response' => 400 ) );
		}

		$jwt  = file_get_contents( 'php://input' );
		$data = $this->decodeJWT( $jwt );

		$order = $this->getWcOrder( $data->payload );

		$order->payment_complete();

		wp_die( 'OK', '', array( 'response' => 200 ) );
	}

	private function decodeJWT( $jwt ) {
		$jwt_key = new Key( $this->secret_key, 'HS256' );
		try {
			$decoded = JWT::decode( $jwt, $jwt_key );
			return $decoded;
		} catch (SignatureInvalidException $e) {
			wp_die( 'JWT signature is not valid', '', array( 'response' => 400 ) );
		}
	}

	public function getWcOrder( $payload ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- External API response object
		$order_id = $payload->customParams->wc_order_id;
		$order    = wc_get_order( $order_id );

		if ( ! $order) {
			wp_die(
				/* translators: %d: order ID */
				esc_html( sprintf( __( 'Order with id %d not found', 'fiberpay-payment-gateway' ), $order_id ) ),
				'',
				array( 'response' => 400 )
			);
		}

		return $order;
	}

	private function getRequestApiKey(): ?string {
		$api_key_header = null;

		// Use WordPress function to get headers (server-agnostic)
		if (isset( $_SERVER['HTTP_API_KEY'] )) {
			$api_key_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_API_KEY'] ) );
		}
		fiberpaygw_log_debug( 'getRequestApiKey', array( 'api_key_header_exists' => ! empty( $api_key_header ) ) );

		if (empty( $api_key_header ) && function_exists( 'getallheaders' )) {
			$headers = getallheaders();
			fiberpaygw_log_debug( 'getallheaders', array( 'API-Key' => isset( $headers['API-Key'] ) ? 'exists' : 'not found' ) );
			$api_key_header = isset( $headers['API-Key'] ) ? $headers['API-Key'] : '';
		}

		return $api_key_header;
	}

	private function isApiKeyHeaderValid(): bool {
		$api_key_header = $this->getRequestApiKey();
		if (empty( $api_key_header )) {
			fiberpaygw_log_debug( 'api key header not found' );
			return false;
		}

		if (empty( $this->api_key )) {
			fiberpaygw_log_debug( 'api key is not configured' );
			return false;
		}
		return $api_key_header === $this->api_key;
	}

	public function handle_order_cancelled( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $this->id === $order->get_payment_method() ) {
			// Handle order cancellation if needed - placeholder for future implementation.
			fiberpaygw_log_debug( 'Order cancelled', array( 'order_id' => $order_id ) );
		}
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		$currency = get_woocommerce_currency();
		if ( ! in_array( $currency, self::$valid_currencies, true )) {
			return false;
		}

		return parent::is_available();
	}

	private function getIconFilePath() {
		return dirname( plugin_dir_url( __FILE__ ) ) . '/assets/logo-fiberpay.png';
	}

	private function getCallbackUrl() {
		$url = WC()->api_request_url( $this->callback_url );
		return $url;
	}

	/**
	* Initialise Gateway Settings Form Fields.
	*/
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'fiberpay-payment-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Fiberpay payments', 'fiberpay-payment-gateway' ),
				'default' => 'no',
			),
			'title'              => array(
				'title'       => __( 'Title', 'fiberpay-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'fiberpay-payment-gateway' ),
				'default'     => __( 'Fiberpay quick money transfer', 'fiberpay-payment-gateway' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Description', 'fiberpay-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'fiberpay-payment-gateway' ),
				'default'     => __( 'Make your payment with quick money transfer or traditional bank transfer.', 'fiberpay-payment-gateway' ),
				'desc_tip'    => true,
			),
			'is_test_env'        => array(
				'title'       => __( 'Test Environment', 'fiberpay-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Test Environment', 'fiberpay-payment-gateway' ),
				'description' => __( 'Check for test environment usage', 'fiberpay-payment-gateway' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'api_key'            => array(
				'title'       => __( 'Api Key', 'fiberpay-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your Fiberpay Api Key', 'fiberpay-payment-gateway' ),
				'desc_tip'    => true,
			),
			'secret_key'         => array(
				'title'       => __( 'Secret Key', 'fiberpay-payment-gateway' ),
				'type'        => 'password',
				'description' => __( 'Your Fiberpay Secret Key', 'fiberpay-payment-gateway' ),
				'desc_tip'    => true,
			),
			'collect_order_code' => array(
				'title'       => __( 'Collect order code', 'fiberpay-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your Fiberpay Collect Order Code', 'fiberpay-payment-gateway' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	* Process the payment and return the result.
	*
	* @param int $order_id Order ID.
	* @return array
	*/
	public function process_payment( $order_id ) {
		try {
			$order = wc_get_order( $order_id );
			if ( ! $order) {
				fiberpaygw_log_debug( 'Order not found for processing payment', array( 'order_id' => $order_id ) );
				return array(
					'result'   => 'failure',
					'message'  => __( 'Order not found', 'fiberpay-payment-gateway' ),
					'response' => 404,
				);
			}

			$total_amount = $order->get_total();

			if ( $total_amount > 0 ) {
				$order_item = $order->get_meta( '_fiberpaygw_create_item_response' );
				if ( ! empty( $order_item )) {
					$redirect = json_decode( $order_item )->data->redirect;
					fiberpaygw_log_debug( 'Using existing Fiberpay order item', array( 'order_id' => $order_id ) );
				} else {
					$client = $this->getFiberpayClient();

					if ( ! $client) {
						fiberpaygw_log_debug( 'Failed to initialize Fiberpay client', array( 'order_id' => $order_id ) );
						return array(
							'result'   => 'failure',
							'message'  => __( 'Payment gateway configuration error', 'fiberpay-payment-gateway' ),
							'response' => 500,
						);
					}

					$shopname = get_bloginfo( 'name' );
					/* translators: The first placeholder is a shop name, the second is a order identifier. */
					$description = sprintf( __( '%1$s - Payment for order #%2$s', 'fiberpay-payment-gateway' ), $shopname, $order_id );

					$currency = $order->get_data()['currency'];

					$callback_url    = $this->getCallbackUrl();
					$callback_params = wp_json_encode(
						array(
							'wc_order_id' => $order_id,
						)
					);

					$redirect_url = $order->get_checkout_order_received_url();

					$res = $client->addCollectItem(
						$this->collect_order_code,
						$description,
						$total_amount,
						$currency,
						$callback_url,
						$callback_params,
						null,
						$redirect_url
					);

					if ( ! $res) {
						fiberpaygw_log_debug( 'Fiberpay API returned empty response', array( 'order_id' => $order_id ) );
						return array(
							'result'   => 'failure',
							'message'  => __( 'Payment processing error', 'fiberpay-payment-gateway' ),
							'response' => 500,
						);
					}

					$order->update_meta_data( '_fiberpaygw_create_item_response', $res );
					$res = json_decode( $res );

					if ( ! $res || ! isset( $res->data ) || ! isset( $res->data->redirect )) {
						fiberpaygw_log_debug(
							'Invalid Fiberpay response structure',
							array(
								'order_id' => $order_id,
								'response' => $res,
							)
						);
						return array(
							'result'   => 'failure',
							'message'  => __( 'Invalid payment response', 'fiberpay-payment-gateway' ),
							'response' => 500,
						);
					}

					$order->update_meta_data( '_fiberpaygw_order_item_code', $res->data->code );
					$order->save();
					$redirect = $res->data->redirect;
					fiberpaygw_log_debug(
						'Fiberpay order item created successfully',
						array(
							'order_id' => $order_id,
							'redirect' => $redirect,
						)
					);
				}
			} else {
				$order->payment_complete();
				fiberpaygw_log_debug( 'Payment completed for zero amount order', array( 'order_id' => $order_id ) );
				$redirect = $order->get_checkout_order_received_url();
			}

			// Remove cart.
			WC()->cart->empty_cart();

			$ret = array(
				'result'   => 'success',
				'redirect' => $redirect,
			);

			return $ret;

		} catch (Exception $e) {
			fiberpaygw_log_debug( 'Payment processing error: ' . $e->getMessage(), array( 'order_id' => $order_id ) );
			return array(
				'result'   => 'failure',
				'message'  => __( 'Payment processing error', 'fiberpay-payment-gateway' ),
				'response' => 500,
			);
		}
	}

	public function process_admin_options(): bool {
		// Load all old values before the new settings get saved.
		$old_api_key     = $this->get_option( 'api_key' );
		$old_secret_key  = $this->get_option( 'secret_key' );
		$old_is_test_env = $this->get_option( 'is_test_env' );

		$result = parent::process_admin_options();

		// Load all old values after the new settings have been saved.
		$new_api_key     = $this->get_option( 'api_key' );
		$new_secret_key  = $this->get_option( 'secret_key' );
		$new_is_test_env = $this->get_option( 'is_test_env' );

		// Checks whether a value has transitioned from a non-empty value to a new one.
		$has_changed = function ( $old_value, $new_value ) {
			return ! empty( $old_value ) && ( $old_value !== $new_value );
		};

		$should_update = $has_changed( $old_api_key, $new_api_key )
		|| $has_changed( $old_secret_key, $new_secret_key )
		|| $has_changed( $old_is_test_env, $new_is_test_env );

		update_option( 'fiberpaygw_payments_show_changed_keys_notice', $should_update ? 'yes' : 'no' );

		return $result;
	}

	private function getFiberpayClient() {
		$client = new \FiberPay\FiberPayClient( $this->api_key, $this->secret_key, 'yes' === $this->is_test_env );
		return $client;
	}
}
