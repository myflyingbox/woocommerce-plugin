<?php
/**
 * Account settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'MFB_Settings_Shipper' ) ) :


class MFB_Settings_Shipper extends MFB_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->id    = 'shipper';
		$this->label = __( 'Shipper settings', 'my-flying-box' );
		
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
			
				array( 'title' => __( 'Shipper information', 'my-flying-box' ), 'type' => 'title', 'id' => 'shipper_options' ),

				array(
					'id' 			=> 'mfb_shipper_name',
					'title'			=> __( 'Shipper name' , 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'desc_tip' => true,
					'required' => true
				),
				array(
					'id' 			=> 'mfb_shipper_company',
					'title'			=> __( 'Shipper company' , 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'desc_tip' => true,
					'required' => true
				),
				array(
					'id' 			=> 'mfb_shipper_street',
					'title'			=> __( 'Street' , 'my-flying-box' ),
					'description'	=> __( 'Two lines maximum!', 'my-flying-box' ),
					'type'			=> 'textarea',
					'default'		=> '',
					'desc_tip' => true,
					'required' => true
				),
				array(
					'id' 			=> 'mfb_shipper_city',
					'title'			=> __( 'City' , 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'desc_tip' => true,
					'required' => true
				),
				array(
					'id' 			=> 'mfb_shipper_state',
					'title'			=> __( 'State' , 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'desc_tip' => true
				),
				array(
					'id' 			=> 'mfb_shipper_postal_code',
					'title'			=> __( 'Postal code' , 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'desc_tip' => true,
					'required' => true
				),
				array(
					'id' 			=> 'mfb_shipper_country_code',
					'title'			=> __( 'Country', 'my-flying-box' ),
					'type'			=> 'select',
					'options'		=> WC()->countries->__get( 'countries' ),
					'default'		=> 'FR',
					'desc_tip' => true,
					'required' => true
				),
				array(
					'id' 			=> 'mfb_shipper_phone',
					'title'			=> __( 'Phone number' , 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'desc_tip' => true,
					'required' => true
				),
				array(
					'id' 			=> 'mfb_shipper_email',
					'title'			=> __( 'Email' , 'my-flying-box' ),
					'type'			=> 'text',
					'default'		=> '',
					'desc_tip' => true,
					'required' => true
				)
			)
		);
		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
	}
	
}

endif;
