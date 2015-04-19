<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'MFB_Admin_Menus' ) ) :

class MFB_Admin_Menus {

	/**
	 * Hook in tabs.
	 */
	public function __construct() {
		// Add menus
		add_action( 'admin_menu', array( $this, 'mfb_menu' ), 30 );
	}

	/**
	 * Add menu item
	 */
	public function mfb_menu() {
    
		$page = add_submenu_page( 'woocommerce', __( 'My Flying Box', 'my-flying-box' ),  __( 'My Flying Box', 'my-flying-box' ) , 'manage_options', 'my-flying-box-settings', array( $this, 'settings_page' ) );
	}
	
	/**
	 * Init the settings page
	 */
	public function settings_page() {

		if ( ! class_exists( 'My_Flying_Box_Settings' ) ) {
			include dirname(__FILE__) .'/class-my-flying-box-settings.php';
		}
		My_Flying_Box_Settings::output();
	}
}

endif;

return new MFB_Admin_Menus();
