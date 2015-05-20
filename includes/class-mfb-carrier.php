<?php
/**
 * MFB_Carrier
 * 
 * MyFlyingBox carrier object. Serves both to handle MFB services, as imported from API,
 * and the corresponding shipping method when the service is active.
 * 
 */

class MFB_Carrier extends WC_Shipping_Method {

	/**
	 * The carrier (post) ID.
	 *
	 * @var int
	 */
	public $id = 0;
	public $ID = 0;

	/**
	 * $post Stores post data
	 *
	 * @var $post WP_Post
	 */
	public $post = null;


	/**
	 * Service name (stored in the post title field)
	 *
	 * @var string
	 */
	public $name = null;


	/**
	 * Carrier code
	 *
	 * @var string
	 */
	public $code = null;


	/**
	 * Service description
	 *
	 * @var string
	 */
	public $description = null;

	/**
	 * Is the carrier currently active?
	 *
	 * @var boolean
	 */
	public $active = false;


	public $shop_delivery = false;


	public $pickup_supported = false;

	public $dropoff_supported = false;


	public function __construct() {
		$this->init();
	}

	/**
	 * init function.
	 */
	public function init() {

		$this->init_settings();

		// Actions
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}


	/**
	 * __isset function.
	 *
	 * @param mixed $key
	 * @return bool
	 */
	public function __isset( $key ) {
		return metadata_exists( 'post', $this->id, '_' . $key );
	}

	/**
	 * __get function.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get( $key ) {
		$value = get_post_meta( $this->id, '_' . $key, true );

		// Get values or default if not set
		if ( in_array( $key, array( 'active' ) ) ) {
			$value = $value ? $value : 'no';
		} else {
			$value = $value ? $value : '';
		}

		if ( ! empty( $value ) ) {
			$this->$key = $value;
		}

		return $value;
	}

	/**
	 * Get the post data.
	 *
	 * @return object
	 */
	public function get_post_data() {
		return $this->post;
	}

	/**
	 * Returns whether or not the product post exists.
	 *
	 * @return bool
	 */
	public function exists() {
		return empty( $this->post ) ? false : true;
	}

	/**
	 * Initialize attributes, based on post data
	 */
	private function populate() {
		$this->ID           = $this->post->ID;
		$this->name         = $this->post->post_title;
		$this->description  = $this->post->post_content;
		$this->active       = ($this->post->post_status == 'mfb-active') ? true : false;

		$this->code               = get_post_meta( $this->id, '_code', true );
		$this->shop_delivery      = get_post_meta( $this->id, '_shop_delivery', true);
		$this->pickup_supported   = get_post_meta( $this->id, '_pickup_supported', true);
		$this->dropoff_supported  = get_post_meta( $this->id, '_dropoff_supported', true);
	}


	public static function get( $carrier ) {
		
		// Typical case: get a new carrier by ID
		if ( is_numeric( $carrier ) ) {
			$instance = new self();
			$instance->id   = absint( $carrier );
			$instance->post = get_post( $instance->id );
			$instance->populate();

		// Get the carrier based on carrier object
		// So just return the instance
		} elseif ( $carrier instanceof MFB_Carrier ) {
			$instance = $carrier;

		// Get the carrier based on a post object
		} elseif ( isset( $carrier->ID ) ) {
			$instance = new self();
			$instance->id   = absint( $carrier->ID );
			$instance->ID   = $instance->id;
			$instance->post = $carrier;
			$instance->populate();
		}
		return $instance;
	}  


	public static function get_all() {
		$all_carriers = get_posts( array(
			'posts_per_page'=> -1,
			'post_type' 	=> 'mfb_carrier',
			'post_status' => array('mfb-active','mfb-inactive'),
			'field' => 'ids'
		));

		$carriers = array();

		foreach($all_carriers as $carrier) {
			$carriers[] = self::get($carrier->ID);
		}
		return $carriers;
	}  


	public static function get_all_active() {
		$active_carriers = get_posts( array(
			'posts_per_page'=> -1,
			'post_type' 	=> 'mfb_carrier',
			'post_status' => 'mfb-active',
			'field' => 'ids'
		));

		$carriers = array();

		foreach($active_carriers as $carrier) {
			$carriers[] = self::get($carrier->ID);
		}
		return $carriers;
	}  


	/**
	 * Refresh the list of carriers services, based on available
	 * products sent by the API.
	 */
	public static function refresh_from_api() {
		$products = Lce\Resource\Product::findAll();

		foreach($products as $product) {
			if (in_array( My_Flying_Box_Settings::get_option('mfb_shipper_country_code'), $product->export_from ) ) {

				$carrier = self::get_by_code( trim($product->code) );

				if ( ! $carrier ) {
					self::create_carrier($product);
					
				} else {
					// Applying systematic updates of important process attributes
					update_post_meta( $carrier->id, '_shop_delivery',      ($product->preset_delivery_location == 1 ? true : false) );
					update_post_meta( $carrier->id, '_pickup_supported',   ($product->pick_up == 1 ? true : false) );
					update_post_meta( $carrier->id, '_dropoff_supported',  ($product->drop_off == 1 ? true : false) );
					
				}
			}
		}

		return true;
	}

	/**
	 * Creates a new Carrier and saves it, based on a carrier service object
	 * as transmitted by the Lce API lib.
	 * Returns an instanciated carrier in case of success, an WP_Error otherwise.
	 */
	public static function create_carrier( $api_service ) {

		// We only create a new carrier if it does not exist already
		if (!self::get_by_code( trim($api_service->code) ) ) {

			$carrier_data = array();
			$carrier_data['post_type']      = 'mfb_carrier';
			$carrier_data['post_status']    = 'private';
			$carrier_data['ping_status']    = 'closed';
			$carrier_data['comment_status'] = 'closed';
			$carrier_data['post_author']    = 1;
			$carrier_data['post_password']  = uniqid( 'carrier_' );
			$carrier_data['post_title']     = $api_service->name;
			$carrier_data['post_name']      = trim($api_service->code);
			$carrier_data['post_content']   = $api_service->details->fr;

			$carrier_id = wp_insert_post( $carrier_data, true );

			if ( !$carrier_id || is_wp_error( $carrier_id ) ) {
				return $carrier_id;
			} else {
				// Updating status to custom status
				wp_update_post(array(
					'ID' => $carrier_id,
					'post_status' => 'mfb-inactive'
				));
				update_post_meta( $carrier_id, '_code',               trim($api_service->code) );
				update_post_meta( $carrier_id, '_shop_delivery',      $api_service->preset_delivery_location );
				update_post_meta( $carrier_id, '_pickup_supported',   $api_service->pick_up );
				update_post_meta( $carrier_id, '_dropoff_supported',  $api_service->drop_off );
				
				return self::get( $carrier_id );
			}
		} else {
			return false;
		}
	}

	/**
	 * Returns the first carrier with the code passed as parameter
	 */
	public static function get_by_code( $carrier_code ) {

		$args = array(
			'post_type' => 'mfb_carrier',
			'post_status' => array('mfb-inactive','mfb-active'),
			'meta_key' => '_code',
			'meta_value' => $carrier_code
		);
		
		// The Query
		$query = get_posts( $args );
		
		if ( count($query) > 0 ) {
			return self::get($query[0]->ID);
		} else {
			return false;
		}
	}

	public function save() {

		// Preparing the data to update
		$post_id      = $this->id;
		$post_status  = $this->active ? 'mfb-active' : 'mfb-inactive';
		$post_title   = $this->name;
		$post_content = $this->description;
		
		wp_update_post( array(
			'ID'            => $post_id,
			'post_status'   => $post_status,
			'post_title'    => $post_title,
			'post_content'  => $post_content
		) );
	}

}
