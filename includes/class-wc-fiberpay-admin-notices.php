<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that represents admin notices.
 *
 * @since 4.1.0
 */
class WC_Fiberpay_Admin_Notices {
	/**
	 * Notices (array)
	 *
	 * @var array
	 */
	public $notices = [];

	/**
	 * Constructor
	 *
	 * @since 4.1.0
	 */
	public function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'wp_loaded', [ $this, 'hide_notices' ] );
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function add_admin_notice( $slug, $class, $message, $dismissible = false ) {
		$this->notices[ $slug ] = [
			'class'       => $class,
			'message'     => $message,
			'dismissible' => $dismissible,
		];
	}

	/**
	 * Display any notices we've collected thus far.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$this->check_environment();

		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo '<div class="' . esc_attr( $notice['class'] ) . '" style="position:relative;">';

			if ( $notice['dismissible'] ) {
				?>
<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-fiberpay-hide-notice', $notice_key ), 'wc_fiberpay_hide_notices_nonce', '_wc_fiberpay_notice_nonce' ) ); ?>"
  class="woocommerce-message-close notice-dismiss"
  style="position:relative;float:right;padding:9px 0px 9px 9px 9px;text-decoration:none;"></a>
<?php
			}

			echo '<p>';
			echo wp_kses(
				$notice['message'],
				[
					'a' => [
						'href'   => [],
						'target' => [],
					],
				]
			);
			echo '</p></div>';
		}
	}

	/**
	 * The backup sanity check, in case the plugin is activated in a weird way,
	 * or the environment changes after activation. Also handles upgrade routines.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function check_environment() {
		$changed_keys_notice = get_option( 'wc_fiberpay_payments_show_changed_keys_notice' );
		$options = get_option( 'woocommerce_fiberpay_payments_settings' );
		$is_test_env = (isset( $options['is_test_env'] ) && 'yes' === $options['is_test_env'] ) ? true : false;
        $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
        $secret_key = isset( $options['secret_key'] ) ? $options['secret_key'] : '';
        $collect_order_code = isset( $options['collect_order_code'] ) ? $options['collect_order_code'] : '';

        $order_data = Fiberpay_WC_Payment_Gateway::get_instance()->get_cached_collect_order();
        // if (empty( $order_data ) ) {
            $setting_link = $this->get_setting_link();
            /* translators: 1) link */
            $this->add_admin_notice( 'keys', 'notice notice-error', sprintf( __( 'Your customers cannot use Stripe on checkout, because we couldn\'t connect to your account. Please go to your settings and, <a href="%s">set your Stripe account keys</a>.', 'woocommerce-gateway-stripe' ), $setting_link ), true );
        // }

		if ( isset( $options['enabled'] ) && 'yes' === $options['enabled'] ) {
			if ( empty( $show_curl_notice ) ) {
				if (!function_exists( 'curl_init' ) ) {
					$this->add_admin_notice( 'curl', 'notice notice-warning', __( 'Fiberpay payment plugin - cURL is not installed.', 'fiberpay-payments' ), true );
				}
			}

			if ( empty( $show_keys_notice ) && false) {
				// Check if keys are entered properly per live/test mode.
				if ( $is_test_env ) {
					if (
						! empty( $test_pub_key ) && ! preg_match( '/^pk_test_/', $test_pub_key )
						|| ! empty( $test_secret_key ) && ! preg_match( '/^[rs]k_test_/', $test_secret_key ) ) {
						$setting_link = $this->get_setting_link();
						/* translators: 1) link */
						$this->add_admin_notice( 'keys', 'notice notice-error', sprintf( __( 'Stripe is in test mode however your test keys may not be valid. Test keys start with pk_test and sk_test or rk_test. Please go to your settings and, <a href="%s">set your Stripe account keys</a>.', 'woocommerce-gateway-stripe' ), $setting_link ), true );
					}
				} else {
					if (
						! empty( $live_pub_key ) && ! preg_match( '/^pk_live_/', $live_pub_key )
						|| ! empty( $live_secret_key ) && ! preg_match( '/^[rs]k_live_/', $live_secret_key ) ) {
						$setting_link = $this->get_setting_link();
						/* translators: 1) link */
						$this->add_admin_notice( 'keys', 'notice notice-error', sprintf( __( 'Stripe is in live mode however your live keys may not be valid. Live keys start with pk_live and sk_live or rk_live. Please go to your settings and, <a href="%s">set your Stripe account keys</a>.', 'woocommerce-gateway-stripe' ), $setting_link ), true );
					}
				}

				// Check if Stripe Account data was successfully fetched.
                $order_data = Fiberpay_WC_Payment_Gateway::get_instance()->get_cached_collect_order();
				if (empty( $order_data ) ) {
					$setting_link = $this->get_setting_link();
					/* translators: 1) link */
					$this->add_admin_notice( 'keys', 'notice notice-error', sprintf( __( 'Your customers cannot use Stripe on checkout, because we couldn\'t connect to your account. Please go to your settings and, <a href="%s">set your Stripe account keys</a>.', 'woocommerce-gateway-stripe' ), $setting_link ), true );
				}
			}

			if ( 'yes' === $changed_keys_notice ) {
				// translators: %s is a the URL for the link.
				$this->add_admin_notice( 'changed_keys', 'notice notice-warning', sprintf( __( 'The public and/or secret keys for the Stripe gateway have been changed. This might cause errors for existing customers and saved payment methods. <a href="%s" target="_blank">Click here to learn more</a>.', 'woocommerce-gateway-stripe' ), 'https://woocommerce.com/document/stripe-fixing-customer-errors' ), true );
			}
		}
	}

	/**
	 * Hides any admin notices.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function hide_notices() {
		if ( isset( $_GET['wc-fiberpay-hide-notice'] ) && isset( $_GET['_wc_fiberpay_notice_nonce'] ) ) {
			if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wc_fiberpay_notice_nonce'] ) ), 'wc_fiberpay_hide_notices_nonce' ) ) {
				wp_die( __( 'Action failed. Please refresh the page and retry.', 'woocommerce-gateway-stripe' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
			}

			$notice = wc_clean( wp_unslash( $_GET['wc-fiberpay-hide-notice'] ) );

			switch ( $notice ) {
				case 'curl':
					update_option( 'wc_fiberpay_show_curl_notice', 'no' );
					break;
				case 'keys':
					update_option( 'wc_fiberpay_show_keys_notice', 'no' );
					break;
				case 'changed_keys':
					update_option( 'wc_fiberpay_show_changed_keys_notice', 'no' );
					break;
				default:
					break;
			}
		}
	}

	/**
	 * Get setting link.
	 *
	 * @since 1.0.0
	 *
	 * @return string Setting link
	 */
	public function get_setting_link() {
		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fiberpay_payments&panel=settings' );
	}
}

new WC_Fiberpay_Admin_Notices();
