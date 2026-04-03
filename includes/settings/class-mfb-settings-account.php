<?php
/**
 * Account settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'MFB_Settings_Account' ) ) :


class MFB_Settings_Account extends MFB_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->id    = 'account';
		$this->label = __( 'Account settings', 'my-flying-box' );

		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
	}

	/**
	 * Output settings page, ensuring shop UUID exists before rendering.
	 */
	public function output() {
		// Auto-generate shop UUID if not set
		$uuid = get_option( 'mfb_shop_uuid', '' );
		if ( empty( $uuid ) ) {
			if ( function_exists( 'wp_generate_uuid4' ) ) {
				$uuid = wp_generate_uuid4();
			} else {
				$uuid = sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
					mt_rand(0, 0xffff), mt_rand(0, 0xffff),
					mt_rand(0, 0xffff),
					mt_rand(0, 0x0fff) | 0x4000,
					mt_rand(0, 0x3fff) | 0x8000,
					mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
				);
			}
			update_option( 'mfb_shop_uuid', $uuid );
		}

		// Auto-generate webhooks signature key if not set
		$sig_key = get_option( 'mfb_webhooks_signature_key', '' );
		if ( empty( $sig_key ) ) {
			$sig_key = base64_encode( random_bytes( 64 ) );
			update_option( 'mfb_webhooks_signature_key', $sig_key );
		}

		parent::output();

		// Inline JS for JWT secret generate/regenerate button
		?>
		<script type="text/javascript">
		(function() {
			var btn = document.getElementById('mfb_generate_jwt_secret');
			if (btn) {
				btn.addEventListener('click', function(e) {
					e.preventDefault();
					var field = document.getElementById('mfb_api_jwt_shared_secret');
					if (field.value && !confirm('<?php echo esc_js( __( 'Are you sure you want to regenerate the authentication key? You will need to update the key in the MyFlyingBox dashboard as well. Remember to submit the form to persist the changes.', 'my-flying-box' ) ); ?>')) {
						return;
					}
					var array = new Uint8Array(32);
					window.crypto.getRandomValues(array);
					var hex = Array.from(array, function(b) { return ('0' + b.toString(16)).slice(-2); }).join('');
					field.value = hex;
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Get settings array
	 *
	 * @return array
	 */
	public function get_settings() {

		$settings = apply_filters( 'woocommerce_' . $this->id . '_settings', array(

				array( 'title' => __( 'API account', 'my-flying-box' ), 'type' => 'title', 'id' => 'account_options' ),

				array(
					'id' 			=> 'mfb_api_login',
					'title'			=> __( 'Login' , 'my-flying-box' ),
					'desc'	=> __( 'Your API login.', 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> __( 'API login', 'my-flying-box' ),
					'required' => true
				),
				array(
					'id' 			=> 'mfb_api_password',
					'title'			=> __( 'API password' , 'my-flying-box' ),
					'desc'	=> __( 'Put your API password, corresponding to selected environment.', 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> __( 'API password', 'my-flying-box' ),
					'required' => true
				),
				array(
					'id' 			=> 'mfb_api_env',
					'title'			=> __( 'API Environment', 'my-flying-box' ),
					'desc'	=> __( 'Orders in test mode are not taken into account.', 'my-flying-box' ),
					'type'			=> 'radio',
					'options'		=> array( 'staging' => 'Staging (test)', 'production' => 'Production' ),
					'default'		=> 'test',
					'required' => true
				),
				array(
					'id'      => 'mfb_google_api_key',
					'title'     => __( 'Google API key' , 'my-flying-box' ),
					'desc' => __( 'You need a valid google API key with Maps Javascript API and Maps Geocoding API access to be able to display a map with available shop delivery locations during checkout (available on selected services).', 'my-flying-box' ),
					'type'      => 'text',
					'default'   => '',
					'placeholder' => __( 'Google API Key', 'my-flying-box' ),
					'required' => true
				),
				array(
					'id'      => 'mfb_token_auth',
					'title'     => __( 'MFB Token' , 'my-flying-box' ),
					'type'      => 'text',
					'default'   => '',
					'required' => true
				),
				array(
					'id' 			=> 'mfb_default_parcel_description',
					'title'			=> __( 'Default parcel content' , 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'required' => true
				),
				array(
					'id' 			=> 'mfb_default_origin_country',
					'title'			=> __( 'Default country of origin', 'my-flying-box' ),
					'type'			=> 'select',
					'options'		=> WC()->countries->__get( 'countries' ),
					'default'		=> 'FR',
					'required' => true
				),
				array(
					'id'      => 'mfb_default_domestic_service',
					'title'     => __( 'Default service for domestic shipments', 'my-flying-box' ),
					'desc' => __( 'Only used in back-office for orders with no MFB service selected by customer.', 'my-flying-box' ),
					'type'      => 'select',
					'options'   => array( '' => __( 'Select a service...', 'my-flying-box' ) ) + MFB_Carrier::get_all_for_select(),
					'default'   => '',
					'required' => false
				),
				array(
					'id'      => 'mfb_default_international_service',
					'title'     => __( 'Default service for international shipments', 'my-flying-box' ),
					'desc' => __( 'Only used in back-office for orders with no MFB service selected by customer.', 'my-flying-box' ),
					'type'      => 'select',
					'options'   => array( '' => __( 'Select a service...', 'my-flying-box' ) ) + MFB_Carrier::get_all_for_select(),
					'default'   => '',
					'required' => false
				),
				array(
					'id'      => 'mfb_default_domestic_return_service',
					'title'     => __( 'Default service for return shipments (domestic)', 'my-flying-box' ),
					'desc' => __( 'Only used in back-office when generating return shipments.', 'my-flying-box' ),
					'type'      => 'select',
					'options'   => array( '' => __( 'Select a service...', 'my-flying-box' ) ) + MFB_Carrier::get_all_for_select(),
					'default'   => '',
					'required' => false
				),
				array(
					'id'      => 'mfb_default_international_return_service',
					'title'     => __( 'Default service for return shipments (international)', 'my-flying-box' ),
					'desc' => __( 'Only used in back-office when generating return shipments.', 'my-flying-box' ),
					'type'      => 'select',
					'options'   => array( '' => __( 'Select a service...', 'my-flying-box' ) ) + MFB_Carrier::get_all_for_select(),
					'default'   => '',
					'required' => false
				),
				array(
					'id'            => 'mfb_thermal_printing',
					'title'         => __( 'Thermal printing', 'my-flying-box' ),
					'desc'          => __( 'Get shipment labels in a thermal-printer friendly format', 'my-flying-box' ),
					'default'       => 'no',
					'type'          => 'checkbox',
					'autoload'      => false
				),
	      array(
	        'id'            => 'mfb_insure_by_default',
	        'title'         => __( 'Insurance', 'my-flying-box' ),
	        'desc'          => __( 'Insure shipments by default (taken into account when calculating shipping cost during checkout and for bulk orders)', 'my-flying-box' ),
	        'default'       => 'no',
	        'type'          => 'checkbox',
	        'autoload'      => false
	      ),
	      array(
	        'id'            => 'mfb_use_total_price_with_vat',
	        'title'         => __( 'Use price with VAT', 'my-flying-box' ),
	        'desc'          => __( 'If checked, the shipping cost returned by the module during checkout and in back-office will include VAT (when applicable). Only use this option if you are not VAT-registered and you do not use WooCommerce VAT mechanisms.', 'my-flying-box' ),
	        'default'       => 'no',
	        'type'          => 'checkbox',
	        'autoload'      => false
	      ),
	      array(
	        'id'            => 'mfb_force_dimensions_table',
	        'title'         => __( 'Force use of dimensions table', 'my-flying-box' ),
	        'desc'          => __( 'If checked, the pack-list calculation mechanisms will always use the package dimensions per weight defined in the module settings even when item dimensions are available and would allow for dynamic calculations of package measurements.', 'my-flying-box' ),
	        'default'       => 'no',
	        'type'          => 'checkbox',
	        'autoload'      => false
	      ),
	      array(
	        'id'            => 'mfb_max_real_weight_per_package',
	        'title'         => __( 'Max real weight per package', 'my-flying-box' ),
	        'desc'          => __( 'In KG. Used to determine how to spread articles in a cart into several simulated parcels, based on real weight.', 'my-flying-box' ),
	        'default'       => '',
	        'type'          => 'number',
	        'autoload'      => false
	      ),
	      array(
	        'id'            => 'mfb_max_volumetric_weight_per_package',
	        'title'         => __( 'Max volumetric weight per package', 'my-flying-box' ),
	        'desc'          => __( 'In KG. Used to determine how to spread articles in a cart into several simulated parcels, based on volumetric weight.', 'my-flying-box' ),
	        'default'       => '',
	        'type'          => 'number',
	        'autoload'      => false
	      ),

				array( 'type' => 'sectionend', 'id' => 'account_options' ),

				// ── Dashboard Synchronization ──

				array( 'title' => __( 'Dashboard synchronization', 'my-flying-box' ), 'type' => 'title', 'id' => 'dashboard_sync_options',
					'desc' => __( 'Configure synchronization between this WooCommerce shop and the MyFlyingBox dashboard. This allows the dashboard to retrieve orders and push shipment data back to WooCommerce.', 'my-flying-box' ),
				),

				array(
					'id'       => 'mfb_shop_uuid',
					'title'    => __( 'Shop identifier (UUID)', 'my-flying-box' ),
					'desc'     => __( 'Unique identifier for this shop (auto-generated). Copy this value into the dashboard connection form.', 'my-flying-box' ),
					'type'     => 'text',
					'default'  => '',
					'css'      => 'min-width:350px;',
					'custom_attributes' => array( 'readonly' => 'readonly' ),
				),

				array(
					'id'       => 'mfb_api_jwt_shared_secret',
					'title'    => __( 'Authentication key (JWT shared secret)', 'my-flying-box' ),
					'desc'     => __( 'Shared secret used for JWT authentication between the dashboard and this plugin. Copy this value into the dashboard connection form.', 'my-flying-box' )
						. ' <button type="button" class="button" id="mfb_generate_jwt_secret">'
						. esc_html__( 'Generate new key', 'my-flying-box' )
						. '</button>',
					'type'     => 'text',
					'default'  => '',
					'css'      => 'min-width:350px;',
				),

				array(
					'id'       => 'mfb_webhooks_signature_key',
					'title'    => __( 'Webhooks signature key', 'my-flying-box' ),
					'desc'     => __( 'Secret key used to sign webhook notifications sent to the dashboard. Auto-generated. Copy this value into the dashboard connection form.', 'my-flying-box' ),
					'type'     => 'text',
					'default'  => '',
					'css'      => 'min-width:350px;',
					'custom_attributes' => array( 'readonly' => 'readonly' ),
				),

				array(
					'id'       => 'mfb_dashboard_sync_behavior',
					'title'    => __( 'Synchronization with dashboard', 'my-flying-box' ),
					'desc'     => __( 'Controls whether the dashboard can access this shop\'s order data via API.', 'my-flying-box' ),
					'type'     => 'select',
					'options'  => array(
						'never'     => __( 'Never (API disabled)', 'my-flying-box' ),
						'on_demand' => __( 'On demand', 'my-flying-box' ),
						'always'    => __( 'Always', 'my-flying-box' ),
					),
					'default'  => 'never',
				),

				array(
					'id'       => 'mfb_sync_history_max_past_days',
					'title'    => __( 'Max history (days)', 'my-flying-box' ),
					'desc'     => __( 'Maximum number of days in the past from which orders can be retrieved via the API.', 'my-flying-box' ),
					'type'     => 'number',
					'default'  => '30',
					'custom_attributes' => array( 'min' => '1', 'max' => '365' ),
				),

				array(
					'id'       => 'mfb_sync_order_max_duration',
					'title'    => __( 'Max order age (days)', 'my-flying-box' ),
					'desc'     => __( 'Orders older than this number of days cannot be retrieved individually via the API.', 'my-flying-box' ),
					'type'     => 'number',
					'default'  => '90',
					'custom_attributes' => array( 'min' => '1', 'max' => '365' ),
				),

				array(
					'id'       => 'mfb_update_order_status_on_shipment',
					'title'    => __( 'Update order status on shipment', 'my-flying-box' ),
					'desc'     => __( 'Automatically set orders to "Completed" when a shipment is created from the MyFlyingBox dashboard.', 'my-flying-box' ),
					'type'     => 'checkbox',
					'default'  => 'no',
				),

				array( 'type' => 'sectionend', 'id' => 'dashboard_sync_options' ),
			)
		);
		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
	}
}

endif;
