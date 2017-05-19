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
			)
		);
		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
	}
}

endif;
