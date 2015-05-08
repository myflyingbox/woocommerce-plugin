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
					'description'	=> __( 'Your API login.', 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> __( 'API login', 'my-flying-box' ),
					'desc_tip' => true,
					'required' => true
				),
				array(
					'id' 			=> 'mfb_api_password',
					'title'			=> __( 'API password' , 'my-flying-box' ),
					'description'	=> __( 'Put your API password, corresponding to selected environment.', 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> __( 'API password', 'my-flying-box' ),
					'desc_tip' => true,
					'required' => true
				),
				array(
					'id' 			=> 'mfb_api_env',
					'title'			=> __( 'API Environment', 'my-flying-box' ),
					'description'	=> __( 'Orders in test mode are not taken into account.', 'my-flying-box' ),
					'type'			=> 'radio',
					'options'		=> array( 'staging' => 'Staging (test)', 'production' => 'Production' ),
					'default'		=> 'test',
					'desc_tip' => true,
					'required' => true
				),
				array(
					'id' 			=> 'mfb_default_parcel_description',
					'title'			=> __( 'Default parcel content' , 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'desc_tip' => true,
					'required' => true
				),
				array(
					'id' 			=> 'mfb_default_origin_country',
					'title'			=> __( 'Default country of origin', 'my-flying-box' ),
					'type'			=> 'select',
					'options'		=> WC()->countries->__get( 'countries' ),
					'default'		=> 'FR',
					'desc_tip' => true,
					'required' => true
				)
			)
		);
		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
	}
}

endif;
