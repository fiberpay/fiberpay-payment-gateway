<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that represents admin notices.
 *
 * @since 4.1.0
 */
class FIBERPAYGW_Admin_Notices {
	/**
	 * Notices (array)
	 *
	 * @var array
	 */
	public $notices = array();

	/**
	 * Constructor
	 *
	 * @since 4.1.0
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_loaded', array( $this, 'hide_notices' ) );
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function add_admin_notice( $slug, $class, $message, $dismissible = false ) {
		$this->notices[ $slug ] = array(
			'class'       => $class,
			'message'     => $message,
			'dismissible' => $dismissible,
		);
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
<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'fiberpaygw-hide-notice', $notice_key ), 'fiberpaygw_hide_notices_nonce', '_fiberpaygw_notice_nonce' ) ); ?>"
	class="woocommerce-message-close notice-dismiss"
	style="position:relative;float:right;padding:9px 0px 9px 9px 9px;text-decoration:none;"></a>
				<?php
			}

			echo '<p>';
			echo wp_kses(
				$notice['message'],
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
					),
				)
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
		$changed_keys_notice = get_option( 'fiberpaygw_payments_show_changed_keys_notice' );
		$options             = get_option( 'woocommerce_fiberpay_payments_settings' );
		$collect_order_code  = isset( $options['collect_order_code'] ) ? $options['collect_order_code'] : '';

		if ( isset( $options['enabled'] ) && 'yes' === $options['enabled'] ) {
			$show_curl_notice = get_option( 'fiberpaygw_show_curl_notice', 'yes' );
			if ( 'yes' === $show_curl_notice ) {
				if ( ! function_exists( 'curl_init' ) ) {
					$this->add_admin_notice( 'curl', 'notice notice-warning', __( 'Fiberpay payment plugin - cURL is not installed.', 'fiberpay-payment-gateway' ), true );
				}
			}

			$order_data = FIBERPAYGW_Payment_Gateway::get_instance()->get_cached_collect_order();
			if ( $changed_keys_notice && ( empty( $order_data ) || $collect_order_code !== $order_data['data']['code'] ) ) {
				$this->add_admin_notice( 'keys', 'notice notice-error', __( 'Connection with Fiberpay service has been detected. Check if environment, API key, secret key, Collect order code and selected environment settings are correct.', 'fiberpay-payment-gateway' ), true );
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
		if ( isset( $_GET['fiberpaygw-hide-notice'] ) && isset( $_GET['_fiberpaygw_notice_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_fiberpaygw_notice_nonce'] ) ), 'fiberpaygw_hide_notices_nonce' ) ) {
				wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'fiberpay-payment-gateway' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
					wp_die( esc_html__( 'Current user can not manage woocommerce.', 'fiberpay-payment-gateway' ) );
			}

			$notice = sanitize_text_field( wp_unslash( $_GET['fiberpaygw-hide-notice'] ) );

			switch ( $notice ) {
				case 'curl':
					update_option( 'fiberpaygw_show_curl_notice', 'no' );
					break;
				case 'keys':
					update_option( 'fiberpaygw_show_keys_notice', 'no' );
					break;
				default:
					break;
			}
		}
	}
}

new FIBERPAYGW_Admin_Notices();