<?php
/**
 * Carriers Settings page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'MFB_Settings_Carriers' ) ) :


class MFB_Settings_Carriers extends MFB_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->id    = 'carriers';
		$this->label = __( 'Carriers', 'my-flying-box' );
		
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
	}
	
	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function get_sections() {

		$sections = array(
			'' => __( 'List of carriers', 'my-flying-box' ),
			'weight_options' => __( 'Weight Options', 'my-flying-box' ),
		);

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}
	
	/**
	 * Output the settings
	 */
	public function output() {
		global $current_section;

		if ( $current_section == 'weight_options' ){
			$this->output_weight_options();
		}
		else {
			$this->output_carrier_list( );
		}
	}

	/**
	 * Save settings
	 */
	public function save() {
		global $current_section;
		
		if ( $current_section == 'weight_options' ){	
			$this->save_weight_options();
		} else {
			$this->save_carrier_list();
		}
	}
	
	/**
	 * Output weight options
	 */
	public function output_weight_options() {
		global $current_section;
		
		$dimensions = MFB_Dimension::get_all();

		include( dirname ( dirname( __FILE__ ) ) . '/views/html-admin-carriers-weight-table.php');
	}
	
	/**
	 * Save weight options
	 */
	public function save_weight_options() {
		if ( empty( $_POST ) ) {
			return false;
		} else {

			for ($i=1;$i<=15;$i++) {
				$dimension = MFB_Dimension::get( (int)$_POST['id_'.$i] );

				if ($dimension->index != $i)
					break;
					

				$dimension->weight_to = (float)$_POST['weight_'.$i];
				
				$dimension->length = (int)$_POST['length_'.$i];
				$dimension->width = (int)$_POST['width_'.$i];
				$dimension->height = (int)$_POST['height_'.$i];

				// Dealing with weight in a coherent way
				if ($i == 1) {
					$dimension->weight_from = 0;
				} else {
					// Taking 'weight_from' from the previous reference
					$dimension->weight_from = $previous_dimension->weight_to;
					
					// If weight_to is smaller than the previous weight_to, we automatically
					// adjust.
					if ($dimension->weight_to < $previous_dimension->weight_to) {
						$dimension->weight_to = $previous_dimension->weight_to;
					}
				}
				
				$dimension->save();
				$previous_dimension = $dimension;
			}
		}
	}
	
	/**
	 * Output carrier list
	 */
	public function output_carrier_list( ) {
		global $current_section;
		
		$carriers = MFB_Carrier::get_all();
		
		include( dirname ( dirname( __FILE__ ) ) . '/views/html-admin-carriers-list.php');
	}
	
	/**
	 * Save carriers
	 */
	public function save_carrier_list() {

		// Nothing submitted, we just ignore
		if ( empty( $_POST ) ) {
			return false;

		// Request to refresh the list of available services, pulled from the API
		} elseif ( isset( $_POST['refresh'] ) ) {

			$result = MFB_Carrier::refresh_from_api();
			
			if($result === true) My_Flying_Box_Settings::add_message( __( 'The list of services has been successfully updated.', 'my-flying-box' ) );
			else My_Flying_Box_Settings::add_error( $result );

		// Otherwise, we must update the carriers (setting carriers as active/inactive)
		} else {
			// save carriers
			global $current_section;

			$active_services = array();
			
			if ( isset ( $_POST['services'] ) && !empty ( $_POST['services'] ) ) {
				foreach ( $_POST['services'] as $key => $service_code ) {
					array_push ( $active_services, $service_code );
				}
			}

			// Now we loop around all MFB services, and activate or deactivate
			// as needed
			foreach( MFB_Carrier::get_all() as $carrier) {
				if ( true === $carrier->active && !in_array( $carrier->code, $active_services ) ) {
					$carrier->active = false;
					$carrier->save();
				} elseif ( false === $carrier->active && in_array( $carrier->code, $active_services ) ) {
					$carrier->active = true;
					$carrier->save();
				}
			}
		}
	}
}

endif;

