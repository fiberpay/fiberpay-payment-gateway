<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Fiberpay_Blocks_Support extends AbstractPaymentMethodType {
    private $gateway;
    
    public function __construct() {
        $this->gateway = new Fiberpay_WC_Payment_Gateway();
    }

    public function get_name() {
        return $this->gateway->id;
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'fiberpay-blocks',
            plugins_url('../../assets/js/blocks.js', __FILE__),
            array('wc-blocks-registry', 'wc-settings', 'wp-element'),
            '1.0.0',
            true
        );

        error_log('FIBER get_payment_method_script_handles');
        return ['fiberpay-blocks'];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->get_title(),
            'description' => $this->gateway->get_description(),
            'supports' => $this->get_supported_features(),
            'icon' => $this->gateway->icon,
        ];
    }

    public function get_supported_features() {
        return [
            'products',
        ];
    }

    public function initialize() {
        // This method can be used to register hooks or do other setup tasks
    }
}
