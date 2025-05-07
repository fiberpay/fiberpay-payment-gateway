<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class Fiberpay_Blocks_Support
 */
class Fiberpay_Blocks_Support extends AbstractPaymentMethodType {
    /**
     * @var WC_Gateway_Fiberpay
     */
    private $gateway;
    
    /**
     * Constructor
     */
    public function __construct() {
        fiberpaygw_log_debug('Fiberpay_Blocks_Support: Constructor called');
        
        // Get WooCommerce's payment gateways to make sure our gateway is properly initialized
        $payment_gateways = WC()->payment_gateways()->payment_gateways();
        
        if (isset($payment_gateways['fiberpay_payments'])) {
            fiberpaygw_log_debug('Fiberpay_Blocks_Support: Found gateway in WC payment gateways');
            $this->gateway = $payment_gateways['fiberpay_payments'];
        } else {
            fiberpaygw_log_debug('Fiberpay_Blocks_Support: Gateway not found in WC payment gateways, creating new instance');
            $this->gateway = new FIBERPAYGW_Payment_Gateway();
        }
        
        fiberpaygw_log_debug('Fiberpay_Blocks_Support: Gateway instance created, ID: ' . $this->gateway->id);
        fiberpaygw_log_debug('Fiberpay_Blocks_Support: Gateway enabled: ' . ($this->gateway->enabled === 'yes' ? 'yes' : 'no'));

        add_action('wp_enqueue_scripts', [$this, 'enqueue_payment_scripts']);
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        $active = $this->gateway->enabled === 'yes';
        fiberpaygw_log_debug('Fiberpay_Blocks_Support: is_active called, returning: ' . ($active ? 'true' : 'false'));
        return $active;
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        fiberpaygw_log_debug('Fiberpay_Blocks_Support: get_payment_method_script_handles called');
        $script_path = 'assets/js/blocks.js';
        $script_asset_path = dirname(dirname(dirname(__FILE__))) . '/assets/js/blocks.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require($script_asset_path)
            : array(
                'dependencies' => array('wp-blocks', 'wp-element', 'wp-components', 'wc-blocks-registry', 'wc-settings'),
                'version' => '1.0.0'
            );

        wp_register_script(
            'fiberpay-blocks',
            plugins_url($script_path, dirname(dirname(__FILE__))),
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if ($this->is_active()) {
            wp_enqueue_script('fiberpay-blocks');
            wp_localize_script(
                'fiberpay-blocks',
                'fiberpay_payments_data',
                $this->get_payment_method_data()
            );
        }

        fiberpaygw_log_debug('Fiberpay_Blocks_Support: Script registered');
        return ['fiberpay-blocks'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        fiberpaygw_log_debug('Fiberpay_Blocks_Support: get_payment_method_data called');
        $data = [
            'title' => $this->gateway->get_title(),
            'description' => $this->gateway->get_description(),
            'supports' => $this->get_supported_features(),
            'icon' => $this->gateway->icon,
            'enabled' => $this->gateway->enabled === 'yes',
            'is_test_env' => $this->gateway->is_test_env === 'yes',
            'gateway_id' => $this->gateway->id,
        ];
        fiberpaygw_log_debug('Fiberpay_Blocks_Support: Returning data: ' . wp_json_encode($data));
        return $data;
    }

    /**
     * Returns an array of supported features.
     *
     * @return array
     */
    public function get_supported_features() {
        fiberpaygw_log_debug('Fiberpay_Blocks_Support: get_supported_features called');
        return [
            'products'
        ];
    }

    /**
     * Returns the name of the payment method.
     */
    public function get_name() {
        fiberpaygw_log_debug('Fiberpay_Blocks_Support: get_name called, returning: ' . $this->gateway->id);
        return $this->gateway->id;
    }

    /**
     * Enqueue payment scripts
     */
    public function enqueue_payment_scripts() {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'woocommerce-cart')) {
            return;
        }

        if (!$this->is_active()) {
            return;
        }

        $this->get_payment_method_script_handles();
    }

    public function initialize() {
    }
}
