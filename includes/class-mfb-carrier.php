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

	public $tracking_url = null;


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
		$this->carrier_name       = get_post_meta( $this->id, '_carrier_name', true);
		$this->shop_delivery      = get_post_meta( $this->id, '_shop_delivery', true);
		$this->pickup_supported   = get_post_meta( $this->id, '_pickup_supported', true);
		$this->dropoff_supported  = get_post_meta( $this->id, '_dropoff_supported', true);

		$method_options           = get_option('woocommerce_'.$this->code.'_settings');
		$this->tracking_url       = ( isset($method_options['tracking_url']) && !empty( $method_options['tracking_url'] )) ? $method_options['tracking_url'] : $this->guess_tracking_url();
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

  public static function get_all_for_select() {
    $options = array();
    $carriers = self::get_all();
    foreach ($carriers as $carrier) {
      $options[$carrier->code] = $carrier->carrier_name.' - '.$carrier->name;
    }
    return $options;
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
					update_post_meta( $carrier->id, '_carrier_name',  		 self::convert_carrier_code_to_carrier_name($product->carrier_code) );
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
				update_post_meta( $carrier_id, '_carrier_name',  			self::convert_carrier_code_to_carrier_name($api_service->carrier_code) );

				return self::get( $carrier_id );
			}
		} else {
			return false;
		}
	}

	/**
	 * For a given carrier code (dhl, mondial_relay, etc.), return a human-friendly
	 * carrier name (DHL, Mondial Relay).
	 */
	public static function convert_carrier_code_to_carrier_name( $carrier_code ) {
		if ( strlen($carrier_code) > 4 ) {
				 $str = '';
				 $parts = explode('_',$carrier_code);
				 foreach($parts as $part){
					 $str.= ucfirst($part).' ';
				 }
				 return trim($str);
			} else {
				 return strtoupper($carrier_code);
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

	public function guess_tracking_url() {
		if (preg_match('/^dhl/', $this->code)) {
			$link = 'http://www.dhl.fr/fr/dhl_express/suivi_expedition.html?AWB=TRACKING_NUMBER';
		} else if (preg_match('/^ups/', $this->code)) {
			$link = 'https://wwwapps.ups.com/WebTracking/track?loc=fr_FR&track.x=Track&trackNums=TRACKING_NUMBER';
		} else if (preg_match('/^chronopost/', $this->code)) {
			$link = 'http://www.chronopost.fr/fr/chrono_suivi_search?listeNumerosLT=TRACKING_NUMBER';
		} else if (preg_match('/^colissimo/', $this->code)) {
			$link = 'http://www.colissimo.fr/portail_colissimo/suivre.do?colispart=TRACKING_NUMBER';
		} else if (preg_match('/^correos_express/', $this->code)) {
			$link = 'https://s.correosexpress.com/SeguimientoSinCP/search?request_locale=es_ES&shippingNumber=TRACKING_NUMBER';
		} else if (preg_match('/^bpost/', $this->code)) {
			$link = 'https://track.bpost.cloud/btr/web/#/search?itemCode=TRACKING_NUMBER&lang=fr&postalCode=POSTAL_CODE';
		} else if (preg_match('/^dpd/', $this->code)) {
			$link = 'https://trace.dpd.fr/fr/trace/TRACKING_NUMBER';
		} else if (preg_match('/^fedex/', $this->code)) {
			$link = 'https://www.fedex.com/fedextrack/?trknbr=TRACKING_NUMBER';
		} else if (preg_match('/^purolator/', $this->code)) {
			$link = 'https://www.purolator.com/en/shipping/tracker?pin=TRACKING_NUMBER';
		} else if (preg_match('/^usps/', $this->code)) {
			$link = 'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=TRACKING_NUMBER';
		} else if (preg_match('/^zeleris/', $this->code)) {
			$link = 'https://www.zeleris.com/seguimiento_envio.aspx?id_seguimiento=TRACKING_NUMBER';
		} else if (preg_match('/^mondial_relay/', $this->code)) {
			$link = 'https://www.mondialrelay.fr/suivi-de-colis/?numeroExpedition=TRACKING_NUMBER&codePostal=POSTAL_CODE';
		} else if (preg_match('/^colis_prive/', $this->code)) {
			$link = 'https://colisprive.com/moncolis/pages/detailColis.aspx?numColis=TRACKING_NUMBER&lang=FR';
		} else {
			$link = null;
		}
		return $link;
	}

	public function tracking_url_for( $tracking_number, $destination_postal_code ) {
		// Getting the reference URL
		$link = $this->tracking_url;
		// Replacing tracking number
		$link = str_replace( 'TRACKING_NUMBER', $tracking_number, $link );
		// Replacing postal code
		$link = str_replace( 'POSTAL_CODE', $destination_postal_code, $link );
		return $link;
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
