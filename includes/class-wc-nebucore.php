<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) or exit;

/**
 * Main WC_Nebucore class.
 *
 * @class WC_Nebucore
 */
class WC_Nebucore {

	/**
	 * Required WooCommerce version number
	 */
	const MIN_WOOCOMMERCE_VERSION = '3.0.0';

	/**
	 * URL where order info JSON should be sent.
	 */
	const NEBUCORE_API_URL = 'https://admin2.nebucore.com/api/orders/insert';

	/**
	 * Email to which errors should be reported.
	 */
	const ERROR_REPORT_MAIL = 'daniel@nebucore.com';

	/**
	 * The single instance of the class.
	 *
	 * @var WC_Nebucore
	 */
	protected static $_instance = null;

	/**
	 * NebuCore API key.
	 *
	 * @var string
	 */
	private $_api_key;

	/**
	 * NebuCore API pass.
	 *
	 * @var string
	 */
	private $_api_pass;

	/**
	 * Main WC_Nebucore Instance.
	 *
	 * Ensures only one instance of WC_Nebucore is loaded or can be loaded.
	 *
	 * @static
	 * @return WC_Nebucore - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * WC_Nebucore Constructor.
	 */
	public function __construct() {
		$this->_api_key  = get_option( 'wc_nebucore_api_key' );
		$this->_api_pass = get_option( 'wc_nebucore_pass' );
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		// Include admin only files.
		if ( is_admin() && ! is_ajax() ) {
			require_once $this->plugin_path() . '/includes/admin/class-wc-nebucore-admin.php';
		}
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		// Hook for order status complete
		add_action( 'woocommerce_order_status_processing', array( $this, 'send_order_json' ), 10, 1 );
		// Listen to shipment tracking update API request
		add_action( 'wp_ajax_wc_nebucore_ship_track_update', array( $this, 'ajax_shipment_tracking_update' ) );
		add_action( 'wp_ajax_nopriv_wc_nebucore_ship_track_update', array( $this, 'ajax_shipment_tracking_update' ) );
	}

	/**
	 * Handle AJAX request for Woocommerce shipment tracking update.
	 */
	public function ajax_shipment_tracking_update() {
		$error_msg = null;
		if ( $this->verify_tracking_update_request() ) {
			if ( isset( $_GET['action_type'] ) ) {
				if ( 'add' === $_GET['action_type'] ) {
					if ( $this->add_tracking_info() ) {
						wp_send_json_success( array(
							'message' => __( 'Shipment tracking number successfully added.', 'wc-nebucore' )
						) );
					} else {
						$error_msg = __( 'Failed to add shipment tracking number.', 'wc-nebucore' );
					}
				} else if ( 'delete' === $_GET['action_type'] ) {
					if ( $this->delete_tracking_info() ) {
						wp_send_json_success( array(
							'message' => __( 'Shipment tracking number successfully deleted.', 'wc-nebucore' )
						) );
					} else {
						$error_msg = __( 'Failed to delete shipment tracking number.', 'wc-nebucore' );
					}
				} else {
					$error_msg = __( 'Invalid action specified.', 'wc-nebucore' );
				}
			} else {
				$error_msg = __( 'Action not specified.', 'wc-nebucore' );
			}
		} else {
			$error_msg = __( 'Failed to verify the request.', 'wc-nebucore' );
		}
		wp_send_json_error( array( 'message' => $error_msg ) );
	}

	/**
	 * Security verification of the tracking update request.
	 *
	 * @return bool
	 */
	private function verify_tracking_update_request() {
		return ( isset( $_POST['username'] ) && $this->_api_key == $_POST['username']
		         && isset( $_POST['password'] ) && $this->_api_pass == $_POST['password'] );
	}

	/**
	 * Add shipment tracking info to an order.
	 *
	 * @since 0.1.1
	 *
	 * @return bool
	 */
	private function add_tracking_info() {
		if ( isset( $_POST['order_id'] ) && isset( $_POST['tracking_number'] ) && isset( $_POST['provider'] )
		     && function_exists( 'wc_st_add_tracking_number' ) ) {
			$order_id        = absint( $_POST['order_id'] );
			$tracking_number = $_POST['tracking_number'];
			$provider        = $_POST['provider'];
			$date_shipped    = isset( $_POST['date_shipped'] ) ? $_POST['date_shipped'] : null;
			$custom_url      = isset( $_POST['custom_url'] ) ? $_POST['custom_url'] : false;
			wc_st_add_tracking_number( $order_id, $tracking_number, $provider, $date_shipped, $custom_url );

			// Update order status to completed.
			$order = new WC_Order( $order_id );
			$order->update_status( 'completed' );

			return true;
		}

		return false;
	}

	/**
	 * Delete shipment tracking info from an order.
	 *
	 * @since 0.1.1
	 *
	 * @return bool
	 */
	private function delete_tracking_info() {
		if ( isset( $_POST['order_id'] ) && isset( $_POST['tracking_number'] ) && function_exists( 'wc_st_delete_tracking_number' ) ) {
			$order_id        = absint( $_POST['order_id'] );
			$tracking_number = $_POST['tracking_number'];
			$provider        = $_POST['provider'] ? $_POST['provider'] : false;
			wc_st_delete_tracking_number( $order_id, $tracking_number, $provider );

			return true;
		}

		return false;
	}

	/**
	 * Send order data JSON to NEBUCORE_API_URL.
	 *
	 * @param $order_id
	 */
	public function send_order_json( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( false === $order || false === $this->_api_key || false === $this->_api_pass ) {
			// Do nothing if something required not found.
			return;
		}
		$order_data           = array();
		$order_data['orders'] = $this->get_prepared_order_data( $order );
		// Include API key and pass also in body.
		$request_body = wp_json_encode( array_merge( array(
			'username' => $this->_api_key,
			'password' => $this->_api_pass,
		), $order_data ) );
		// Send POST request to NEBUCORE_API_URL with order data JSON body.
		$response = wp_remote_post( self::NEBUCORE_API_URL, array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'Expect'       => '', // Fix if body contains larger than 1024
				),
				'body'    => $request_body,
			)
		);
		$this->process_remote_response( $response, $order_data );
	}


	private function process_remote_response( $response, $order_data ) {
		// Report if any error occurred.
		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
		} else if ( 200 !== $response['response']['code'] ) {
			$error_msg = $response['response']['code'] . ': ' . $response['response']['message'];
		} else {
			// Response is 200 OK.
			$result = json_decode( $response['body'], true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				// Valid JSON.
				if ( isset( $result['type'] ) && 'error' === $result['type'] ) {
					// There is an error reported back.
					$error_msg = isset( $result['message'] ) ? $result['message'] : 'Unknown error';
				}
			} else {
				$error_msg = 'API response JSON error: ' . json_last_error_msg();
			}
		}

		if ( isset( $error_msg ) ) {
			// Somewhere in the process error occurred.
			// Report the problem.
			$to      = self::ERROR_REPORT_MAIL;
			$subject = 'Alert: API Call Failed';
			// Customize message as wanted.
			// make sure to match the array_keys to the return array of
			// $this->get_prepared_order_data(), while accessing $order_data below.
			$message = "NebuCore API call failed. Please review the details below:\r\n"
			           . "Error: " . $error_msg . "\r\n"
			           . "WordPress Installation URL: " . site_url() . "\r\n"
			           . "Order id: " . $order_data['po_num'] . "\r\n"
			           . "Customer id: " . $order_data['wc_customer_id'] . "\r\n"
			           . "Order Total: " . $order_data['total'] . "\r\n";
			// Send email
			wp_mail( $to, $subject, $message );
		}
	}

	/**
	 * Prepare order data as an associative array.
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	private function get_prepared_order_data( $order ) {
		// Why prepare yourself?, Use DRY and get from WooCommerce REST API Controller
		$orders_controller = new WC_REST_Orders_Controller();
		$rest_request      = new WP_REST_Request();
		$response          = rest_ensure_response( $orders_controller->prepare_object_for_response( $order, $rest_request ) );
		$data              = $response->get_data();
		error_log( var_export( $data, true ) );

		// Prepare in the format expected by NebuCore insert API endpoint.
		return array(
			array(
				'fname'                 => $data['billing']['first_name'],
				'lname'                 => $data['billing']['last_name'],
				'company'               => $data['billing']['company'],
				'customer_email'        => $data['billing']['email'],
				'phone'                 => $data['billing']['phone'],
				'notes'                 => '',
				'fax'                   => '',
				'gender'                => '',
				'phone_ext'             => '',
				'website'               => '',
				'default_bill_id'       => '',
				'default_ship_id'       => '',
				'ship_fname'            => $data['shipping']['first_name'],
				'ship_lname'            => $data['shipping']['last_name'],
				'ship_address1'         => $data['shipping']['address_1'],
				'ship_address2'         => $data['shipping']['address_2'],
				'ship_company'          => $data['shipping']['company'],
				'ship_city'             => $data['shipping']['city'],
				'ship_state'            => $data['shipping']['state'],
				'ship_country'          => $data['shipping']['country'],
				'ship_zip'              => $data['shipping']['postcode'],
				'ship_phone'            => '',
				'ship_phone_ext'        => '',
				'ship_fax'              => '',
				'bill_fname'            => $data['billing']['first_name'],
				'bill_lname'            => $data['billing']['last_name'],
				'bill_address1'         => $data['billing']['address_1'],
				'bill_address2'         => $data['billing']['address_2'],
				'bill_company'          => $data['billing']['company'],
				'bill_city'             => $data['billing']['city'],
				'bill_state'            => $data['billing']['state'],
				'bill_country'          => $data['billing']['country'],
				'bill_zip'              => $data['billing']['postcode'],
				'bill_phone'            => $data['billing']['phone'],
				'bill_phone_ext'        => '',
				'bill_fax'              => '',
				'discount_amount'       => $data['discount_total'],
				'details'               => $this->get_prepared_line_items( $data['line_items'] ),
				'store_name'            => get_bloginfo( 'name' ),
				'tracking_num'          => '',
				'customer_notes'        => $data['customer_note'],
				'time'                  => $data['date_created'],
				'modified_on'           => $data['date_modified'],
				'total'                 => $data['total'],
				'total_tax'             => $data['total_tax'],
				'shipping_fee'          => $data['shipping_total'],
				'status'                => $data['status'],
				// Additional useful data, not required by NebuCore
				'po_num'                => $order->get_id(),
				// Prefix with wc so doesn't collide with NebuCore internal
				'wc_customer_id'        => $data['customer_id'],
				'transactions'          => array(
					array(
						'transaction_log'              => '',
						'transaction_amount'           => $data['total'],
						'transaction_last_four_digits' => '0000',
						'transaction_id'               => empty( $data['transaction_id'] ) ? '0000' : $data['transaction_id'],
						'transaction_status_id'        => 2,
						'transaction_payment_method'   => 'WooCommerce Payment',
					),
				),
				'shipping_method_title' => count( $data['shipping_lines'] ) > 0 ? $data['shipping_lines'][0]['method_title'] : '',
			)
		);
	}

	/**
	 * Prepare line items in expected format of NebuCore.
	 * Helper method to $this->get_prepared_order_data()
	 *
	 * @param array $line_items
	 *
	 * @return array
	 */
	private function get_prepared_line_items( array $line_items ) {
		$prepared = array();
		foreach ( $line_items as $line_item ) {
			$prepared[] = array(
				'name'  => $line_item['name'],
				'qty'   => $line_item['quantity'],
				'price' => $line_item['price'],
				'sku'   => $line_item['sku'],
			);
		}

		return $prepared;
	}

	/**
	 * Get shipment tracking info for an order, if available.
	 *
	 * @since 0.1.1
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	private function get_shipment_tracking_info( $order_id ) {
		$tracking_info = array();
		if ( class_exists( 'WC_Shipment_Tracking_Actions' ) ) {
			$tracking_actions = WC_Shipment_Tracking_Actions::get_instance();
			$tracking_info    = (array) $tracking_actions->get_tracking_items( $order_id, true );
		}

		return $tracking_info;
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', WC_NEBUCORE_PLUGIN_FILE ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( WC_NEBUCORE_PLUGIN_FILE ) );
	}

	/**
	 * Helper function to determine whether a plugin is active.
	 *
	 * @static
	 *
	 * @param string $plugin_name plugin name, as the plugin-filename.php
	 *
	 * @return boolean true if the named plugin is installed and active
	 */
	public static function is_plugin_active( $plugin_name ) {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, array_keys( get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$plugin_filenames = array();
		foreach ( $active_plugins as $plugin ) {
			if ( false !== strpos( $plugin, '/' ) ) {
				// normal plugin name (plugin-dir/plugin-filename.php)
				list( , $filename ) = explode( '/', $plugin );
			} else {
				// no directory, just plugin file
				$filename = $plugin;
			}
			$plugin_filenames[] = $filename;
		}

		return in_array( $plugin_name, $plugin_filenames );
	}

	/**
	 * Renders a notice when WooCommerce version is outdated.
	 *
	 * @static
	 */
	public static function render_outdated_wc_version_notice() {
		$message = sprintf(
		/* translators: Placeholders: %1$s <strong>, %2$s - </strong>, %3$s - version number, %4$s + %6$s - <a> tags, %5$s - </a> */
			esc_html__(
				'%1$sNebuCore is inactive.%2$s This plugin requires WooCommerce %3$s or newer. Please %4$supdate WooCommerce%5$s or %6$srun the WooCommerce database upgrade%5$s.',
				'wc-nebucore'
			),
			'<strong>',
			'</strong>',
			self::MIN_WOOCOMMERCE_VERSION,
			'<a href="' . admin_url( 'plugins.php' ) . '">',
			'</a>',
			'<a href="' . admin_url( 'plugins.php?do_update_woocommerce=true' ) . '">'
		);
		printf( '<div class="notice notice-error"><p>%s</p></div>', $message );
	}
}
