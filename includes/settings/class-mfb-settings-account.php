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
			)
		);
		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
	}
}

endif;
