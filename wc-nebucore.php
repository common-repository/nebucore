<?php
/*
Plugin Name: NebuCore
Plugin URI: https://wordpress.org/plugins/nebucore
Description: Send order data JSON to NebuCore on successful WC order.
Version: 0.1.4
Author: The NebuCore Team
Author URI: https://www.nebucore.com/
Text-Domain: wc-nebucore
Domain Path: /languages
*/

// Exit if accessed directly.
defined( 'ABSPATH' ) or exit;

// Define WC_NEBUCORE_PLUGIN_FILE.
if ( ! defined( 'WC_NEBUCORE_PLUGIN_FILE' ) ) {
	define( 'WC_NEBUCORE_PLUGIN_FILE', __FILE__ );
}

// Include the main WC_Nebucore class.
if ( ! class_exists( 'WC_Nebucore' ) ) {
	require_once dirname( __FILE__ ) . '/includes/class-wc-nebucore.php';
}

// WC version check
if ( ! WC_Nebucore::is_plugin_active( 'woocommerce.php' )  || version_compare( get_option( 'woocommerce_db_version' ),
		WC_Nebucore::MIN_WOOCOMMERCE_VERSION, '<' ) ) {
	add_action( 'admin_notices', array( 'WC_Nebucore', 'render_outdated_wc_version_notice' ) );
	return;
}

// Make sure we're loaded after WC and fire it up!
add_action( 'plugins_loaded', 'wc_nebucore' );

/**
 * Initialize the WC_Nebucore.
 *
 * @return WC_Nebucore
 */
function wc_nebucore() {
	return WC_Nebucore::instance();
}
