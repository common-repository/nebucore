<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) or exit;

/**
 * Class WC_Nebucore_Admin
 */
class WC_Nebucore_Admin {

	public function __construct() {
		// Hook to add action links on plugins screen.
		add_filter( 'plugin_action_links_' . plugin_basename( WC_NEBUCORE_PLUGIN_FILE ),
			array( $this, 'add_action_links' ) );
		// Register a section beneath Woocommerce->Settings->Advanced tab.
		add_filter( 'woocommerce_get_sections_advanced', array( $this, 'add_nebucore_advanced_section' ) );
		// Add settings for NebuCore.
		add_filter( 'woocommerce_get_settings_advanced', array( $this, 'add_nebucore_advanced_settings' ), 10, 2 );
		// Inject password show/hide button only on woocommerce settings page.
		add_action( 'admin_print_footer_scripts-woocommerce_page_wc-settings', array(
			$this,
			'password_show_hide_button'
		) );
		// Check if WooCommerce Shipment Tracking plugin is active.
		add_action( 'admin_init', array( __CLASS__, 'check_shipment_tracking_active' ) );
	}

	public function add_action_links( $links ) {
		$nebucore_links = array(
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=nebucore' ) ),
				__( 'Settings', 'wc-nebucore' )
			)
		);

		return array_merge( $nebucore_links, $links );
	}

	/**
	 * Add a section beneath Woocommerce->Settings->Advanced tab.
	 *
	 * @param $sections
	 *
	 * @return array mixed
	 */
	public function add_nebucore_advanced_section( $sections ) {
		$sections['nebucore'] = 'NebuCore';

		return $sections;
	}

	/**
	 * Add settings to NebuCore section in WC Advanced settings.
	 *
	 * @param $settings
	 * @param $current_section
	 *
	 * @return array
	 */
	public function add_nebucore_advanced_settings( $settings, $current_section ) {
		// Check that current section is what we want.
		if ( 'nebucore' === $current_section ) {
			return array(
				array(
					'name' => __( 'NebuCore API Settings', 'wc-nebucore' ),
					'type' => 'title',
					'desc' => __( 'Configure API settings for sending order info JSON.', 'wc-nebucore' ),
					'id'   => 'wc_nebucore_settings_section'
				),
				array(
					'name'     => __( 'API Key', 'wc-nebucore' ),
					'desc_tip' => __( 'Alphanumeric API key to be used.', 'wc-nebucore' ),
					'id'       => 'wc_nebucore_api_key',
					'type'     => 'text',
				),
				array(
					'name'     => __( 'Password', 'wc-nebucore' ),
					'desc_tip' => __( 'Password to be used along with API key.', 'wc-nebucore' ),
					'id'       => 'wc_nebucore_pass',
					'type'     => 'password',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'wc_nebucore_settings_section',
				)

			);
		} else {
			// Return without touching anything.
			return $settings;
		}
	}

	/**
	 * Inject a show/hide button to password field via JS.
	 */
	public function password_show_hide_button() {
		?>
        <style>
            /* Fix password field style */
            .woocommerce #wc_nebucore_pass {
                width: 400px;
                padding: 6px;
            }
        </style>
        <script>
            jQuery(document).ready(function ($) {
                let $passField = $('.woocommerce #wc_nebucore_pass');
                let $passFieldParent = $passField.parent();
                $passField.wrap($('<span />', {class: 'password-input-wrapper'}));
                let $passFieldWrapper = $passFieldParent.find('span.password-input-wrapper');
                $passFieldWrapper.append($('<input />', {
                    type: 'text',
                    id: 'wc_nebucore_pass-text',
                    style: 'display: none;'
                }));
                let $passTextField = $passFieldWrapper.find('#wc_nebucore_pass-text');
                $passTextField.on('change', function (e) {
                    $passField.attr('value', $(this).val());
                });
                let passwordToggle =
                    '<button type="button" class="button wp-hide-pw hide-if-no-js">' +
                    '<span class="dashicons dashicons-visibility"></span>' +
                    '<span class="text"> Show</span>' +
                    '</button>';
                $passFieldParent.append(passwordToggle);
                $passFieldParent.find('.wp-hide-pw').on('click', function (e) {
                    let pass;
                    if ($passFieldWrapper.hasClass('pw-shown')) {
                        $passFieldWrapper.removeClass('pw-shown');
                        $passTextField.hide();
                        $(this).find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
                        $(this).find('.text').text(' Show');
                        $passField.show();
                    } else {
                        $passFieldWrapper.addClass('pw-shown');
                        pass = $passField.val();
                        $passField.hide();
                        $(this).find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
                        $(this).find('.text').text(' Hide');
                        $passTextField.attr('value', pass).show();
                    }
                });
            });
        </script>
		<?php
	}

	/**
	 * Check if WooCommerce Shipment Tracking plugin is active else show warning notice.
     *
     * @since 0.1.1
	 */
	public static function check_shipment_tracking_active() {
		if ( ! is_plugin_active( 'woocommerce-shipment-tracking/woocommerce-shipment-tracking.php' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'missing_shipment_tracking_notice' ) );
		}
	}

	/**
	 * Renders a warning admin notice when WooCommerce Shipment Tracking plugin is not active.
	 *
     * @since 0.1.1
	 * @static
	 */
	public static function missing_shipment_tracking_notice() {
		$message = sprintf(
			esc_html__(
				'%1$sNebuCore%2$s plugin requires %1$sWoocommerce Shipment Tracking%2$s plugin to be installed and active.',
				'wc-nebucore'
			),
			'<strong>',
			'</strong>'
		);
		printf( '<div class="notice notice-warning"><p>%s</p></div>', $message );
	}
}

return new WC_Nebucore_Admin();
