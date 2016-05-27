<?php
/**
 * MFB_Shipment
 * 
 */

class MFB_Shipment {

	public $id = 0;
	public $api_order_uuid = 0; // UUID of shipment order on the API
	public $date_booking = null;
	
	public $wc_order_id = 0;
	
	// Currently selected quote and offer for this shipment
	public $quote = null;
	public $offer = null;
	public $collection_date = null; // Selected collection date at time of booking.
	public $delivery_location_code = null; // Selected relay delivery at time of booking.
	
	public $delivery_location = null; // std Object containing the selected delivery location characteristics

	public $post = null;

	public $status = null;
	
	public $shipper = null;
	public $receiver = null;
	
	public $parcels = array();
	
	// Never set this manually!
	private $parcels_count = 0;

	public static $address_fields = array(
		'company',
		'name',
		'street',
		'postal_code',
		'city',
		'state',
		'country_code',
		'phone',
		'email'
	);

	public static $parcel_fields = array(
		'length',
		'width',
		'height',
		'weight',
		'description',
		'value',
		'country_of_origin',
		'shipper_reference',
		'recipient_reference',
		'customer_reference',
		'tracking_number'
	);


	public function __construct() {
		$this->shipper   = new stdClass();
		$this->recipient = new stdClass();
	}

	public static function get( $shipment_id ) {
		
		if ( is_numeric( $shipment_id ) ) {
			$instance = new self();
			$instance->id							= absint( $shipment_id );
			$instance->post						= get_post( $instance->id );
			$instance->status					= $instance->post->post_status;
			$instance->wc_order_id		= $instance->post->post_parent;
			
			$instance->populate();
		}
		return $instance;
	}

	public function populate() {
		$this->api_order_uuid = get_post_meta( $this->id, '_api_uuid', true );
		$this->date_booking = get_post_meta( $this->id, '_date_booking', true );
		
		// Loading quote and offer
		$this->quote = MFB_Quote::get( get_post_meta( $this->id, '_quote_id', true ) );
		$this->offer = MFB_Offer::get( get_post_meta( $this->id, '_offer_id', true ) );
		
		$this->collection_date = get_post_meta( $this->id, '_collection_date', true ); // Selected collection date
		$this->delivery_location_code = get_post_meta( $this->id, '_delivery_location_code', true ); // Selected relay
		$this->delivery_location      = get_post_meta( $this->id, '_delivery_location', true ); // Selected relay (full object with details)

		
		// Loading Shipper and Recipient
		foreach( array('shipper','recipient') as $addresstype ) {
			foreach( self::$address_fields as $fieldname ) {
				$this->$addresstype->$fieldname = get_post_meta( $this->id, '_'.$addresstype.'_'.$fieldname, true );
			}
		}
		
		// Loading parcels
		$this->parcels_count = get_post_meta( $this->id, '_parcels_count', true );
		for( $i = 1; $i <= $this->parcels_count; $i++ ) {
			$this->parcels[$i-1] = new stdClass(); // Initializing parcel object
			foreach( self::$parcel_fields as $fieldname ) { // Looping on each parcel attribute
				$this->parcels[$i-1]->$fieldname = get_post_meta( $this->id, '_parcel_'.$i.'_'.$fieldname, true );
			}
		}
	}

	/**
	 * Returns all existing dimension objects.
	 * If none exist, then we initialize the default values.
	 */
	public static function get_all() {
		
		$all_shipments = get_posts( array(
			'posts_per_page'=> -1,
			'post_type' 	=> 'mfb_shipment',
			'post_status' => 'private',
			'field' => 'ids',
			'orderby'  => array( 'date' => 'DESC' )
		));

		$shipments = array();
		
		foreach($all_shipments as $shipment) {
			$shipments[] = self::get($shipment->ID);
		}

		return $shipments;
	}

	public static function get_all_for_order( $order_id ) {
		
		$all_shipments = get_children( array(
			'post_type'			=> 'mfb_shipment',
			'post_status'		=> array('private','mfb-draft','mfb-booked'),
			'post_parent'		=> $order_id,
			'field'					=> 'ids',
			'orderby'				=> array( 'date' => 'DESC' )
		));

		$shipments = array();
		
		foreach($all_shipments as $shipment) {
			$shipments[] = self::get($shipment->ID);
		}
		
		return $shipments;
	}
	
	public static function create_from_order( $order ) {
		
		$shipment = new self();
		$shipment->wc_order_id = $order->id;
		$shipment->status = 'mfb-draft';
		
		// Setting default shipper data
		foreach( self::$address_fields as $fieldname ) {
			$shipment->shipper->$fieldname = get_option( 'mfb_shipper_'.$fieldname );
		}
		
		// Setting recipient data from order shipping address
		$shipment->recipient->name          = $order->shipping_first_name . ' ' . $order->shipping_last_name;
		$shipment->recipient->company       = $order->shipping_company;
		$shipment->recipient->street        = $order->shipping_address_1;
		
		$ship_addr2 = trim( $order->shipping_address_2 );
		if ( ! empty($ship_addr2) ) $shipment->recipient->street .= "\n" . $ship_addr2;
		
		$shipment->recipient->city          = $order->shipping_city;
		$shipment->recipient->postal_code   = $order->shipping_postcode;
		$shipment->recipient->state         = $order->shipping_state;
		$shipment->recipient->country_code  = $order->shipping_country;
		
		$shipment->recipient->email         = $order->billing_email;
		$shipment->recipient->phone         = $order->billing_phone;
		
		// Initialize a default parcel based on total weight of order
		$total_weight = wc_format_decimal(0);
		$total_value  = wc_format_decimal(0);
		$line_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', 'line_item' ) );

		foreach ( $line_items as $item_id => $item ) {
			$_product = $order->get_product_from_item( $item );
			if ( $_product ) {
				$total_weight += wc_format_decimal( $_product->get_weight() * $item['qty'] );
				$total_value  += wc_format_decimal( isset( $item['line_total'] ) ? $item['line_total'] : 0 );
			}
		}
		
		$parcel = new stdClass();
		$dims = MFB_Dimension::get_for_weight( $total_weight );
		
		$parcel->length            = $dims->length;
		$parcel->width             = $dims->width;
		$parcel->height            = $dims->height;
		$parcel->weight            = $total_weight;
		$parcel->description       = get_option( 'mfb_default_parcel_description' );
		$parcel->country_of_origin = get_option( 'mfb_default_origin_country' );
		$parcel->value             = $total_value;
		
		$shipment->parcels[0] = $parcel;
		
		$shipment->save();
		
		// All good. Now we try to get a quote straight away.
		$quote = $shipment->get_new_quote();
		
		$shipment->save();
		
		$shipment->autoselect_offer( $order );
		
		return $shipment;
	}
	
	public function autoselect_offer( $order = null ) {
		if ( $this->quote && $this->wc_order_id ) {
			if ( null === $order ) $order = wc_get_order( $this->wc_order_id );
			$this->offer = null;
			
			// Auto-setting the offer based on what the customer has chosen
			if ( count($order->get_shipping_methods()) == 1 ) {
				$methods = array_pop($order->get_shipping_methods());
				$chosen_method = $methods['item_meta']['method_id'][0];
				if ( $this->quote->offers[$chosen_method] ) {
					$this->offer = $this->quote->offers[$chosen_method];
				}
			}
			$this->save();
		}
	}
	
	public function save() {
		// ID equal to zero, this is a new record
		if ($this->id == 0) {
			$shipment = array(
				'post_type' => 'mfb_shipment',
				'post_status' => 'private',
				'ping_status' => 'closed',
				'comment_status' => 'closed',
				'post_author' => 1,
				'post_password' => uniqid( 'shipment_' ),
				'post_parent' => $this->wc_order_id,
				'post_title' => 'MyFlyingBox Shipment'
			);

			$this->id = wp_insert_post( $shipment, true );
			$this->post = get_post( $this->id );
			$this->status = 'mfb-draft'; // New shipments should always be created as drafts.
		}
		wp_update_post(array(
			'ID' => $this->id,
			'post_status' => $this->status
		));
		
		// Whether it is a new record or not, we update all meta fields
		foreach( array('shipper','recipient') as $addresstype ) {
			foreach( self::$address_fields as $fieldname ) {
				update_post_meta( $this->id, '_'.$addresstype.'_'.$fieldname, $this->$addresstype->$fieldname );
			}
		}
		
		// Parcels are a bit specific: we must first erase existing parcel data
		// because their number can change...
		$parcels_number = $this->parcels_count;
		if ( $parcels_number > 0 ) {
			for( $i = 1; $i <= $parcels_number; $i++ ) {
				foreach( self::$parcel_fields as $fieldname ) {
					delete_post_meta( $this->id, '_parcel_'.$i.'_'.$fieldname);
				}
			}
		}
		
		// Now we can save the latest parcel data, after regenerating a clean, linear array index
		$this->parcels = array_values( $this->parcels );
		for( $i = 0; $i < count($this->parcels); $i++ ) {
			foreach( self::$parcel_fields as $fieldname ) {
				update_post_meta( $this->id, '_parcel_'.($i+1).'_'.$fieldname, $this->parcels[$i]->$fieldname);
			}
		}
		update_post_meta( $this->id, '_parcels_count', count($this->parcels) );
		update_post_meta( $this->id, '_api_uuid', $this->api_order_uuid );
		
		if ( $this->quote ) {
			update_post_meta( $this->id, '_quote_id', $this->quote->id );
		} else {
			update_post_meta( $this->id, '_quote_id', null );
		}
		
		if ( $this->offer ) {
			update_post_meta( $this->id, '_offer_id', $this->offer->id );
		} else {
			update_post_meta( $this->id, '_offer_id', null );
		}
		
		update_post_meta( $this->id, '_collection_date', $this->collection_date );
		update_post_meta( $this->id, '_delivery_location_code', $this->delivery_location_code );
		
		// Saving the delivery location details, if applicable
		if ( $this->offer->relay == true && !empty($this->delivery_location_code) ) {
			$loc_params = array(
				'street' => $this->recipient->street,
				'city' => $this->recipient->city
			);
			$locations = $this->offer->get_delivery_locations($loc_params);
			foreach( $locations as $loc ) {
				if ( $loc->code == $this->delivery_location_code ) {
					$this->delivery_location = $loc;
				}
			}
		}
		
		update_post_meta( $this->id, '_delivery_location', $this->delivery_location );
		
		
		// Reloading object
		$this->populate();
		return true;
	}
	
	public function formatted_address( $address_type ) {
		// Formatted Addresses
		$street = explode("\n", $this->$address_type->street, 2);
		if (!isset($street[1])){
			$street[1] = '';
		}
		$address = array(
			'last_name'     => $this->$address_type->name,
			'company'       => $this->$address_type->company,
			'address_1'     => $street[0],
			'address_2'     => $street[1],
			'city'          => $this->$address_type->city,
			'state'         => $this->$address_type->state,
			'postcode'      => $this->$address_type->postal_code,
			'country'       => $this->$address_type->country_code
		);
		$formatted = WC()->countries->get_formatted_address( $address );
		return $formatted;
	}
	
	public function formatted_shipper_address() {
		return $this->formatted_address( 'shipper' );
	}
	
	public function formatted_recipient_address() {
		return $this->formatted_address( 'recipient' );
	}
	
	public static function formatted_parcel_line( $parcel ) {
		$line = $parcel->length.'x'.$parcel->width.'x'.$parcel->height.'cm, '.$parcel->weight.'kg | '.$parcel->value.' € | '.$parcel->description.' ('.$parcel->country_of_origin.')';
		return $line;
	}
	// Completely remove a shipment from the database
	public function destroy() {
		return wp_delete_post( $this->id );
	}
	
	public function get_new_quote() {
			
			// Not proceeding unless the shipment is in draft state
			if ($this->status != 'mfb-draft') return false;
			
			$parcels = array();
			foreach( $this->parcels as $parcel ) {
				$parcels[] = array(
					'length' => $parcel->length,
					'width'  => $parcel->width,
					'height'  => $parcel->height,
					'weight'  => $parcel->weight
				);
				
			}
			$params = array(
				'shipper' => array(
					'city'         => $this->shipper->city,
					'postal_code'  => $this->shipper->postal_code,
					'country'      => $this->shipper->country_code,
				),
				'recipient' => array(
					'city'         => $this->recipient->city,
					'postal_code'  => $this->recipient->postal_code,
					'country'      => $this->recipient->country_code,
					'is_a_company' => !empty($this->recipient->company)
				),
				'parcels' => $parcels
			);
			
			$api_quote = Lce\Resource\Quote::request($params);
			
			$quote = new MFB_Quote();
			$quote->api_quote_uuid = $api_quote->id;
			$quote->shipment_id = $this->id;
			$quote->params = $params;
			
			if ($quote->save()) {
				// Now we create the offers

				foreach($api_quote->offers as $k => $api_offer) {
					$offer = new MFB_Offer();
					$offer->quote_id              = $quote->id;
					$offer->api_offer_uuid        = $api_offer->id;
					$offer->product_code          = $api_offer->product->code;
					$offer->product_name          = $api_offer->product->name;
					$offer->pickup                = $api_offer->product->pick_up;
					$offer->dropoff               = $api_offer->product->drop_off;
					$offer->relay                 = $api_offer->product->preset_delivery_location;
					$offer->collection_dates      = $api_offer->collection_dates;
					$offer->base_price_in_cents   = $api_offer->price->amount_in_cents;
					$offer->total_price_in_cents  = $api_offer->total_price->amount_in_cents;
					$offer->currency              = $api_offer->total_price->currency;
					$offer->save();
				}
				$this->quote = $quote;
				$this->offer = null;
				$this->save();


			}
			// Refreshing the quote, to get the offers loaded properly
			$quote->populate();
			$this->populate();
			$this->autoselect_offer();
			
			return $quote;
	}

	public function place_booking() {
	
		$params = array(
			'shipper' => array(
				'company' => $this->shipper->company,
				'name' => $this->shipper->name,
				'street' => $this->shipper->street,
				'city' => $this->shipper->city,
				'state' => $this->shipper->state,
				'phone' => $this->shipper->phone,
				'email' => $this->shipper->email
			),
			'recipient' => array(
				'company' => $this->recipient->company,
				'name' => $this->recipient->name,
				'street' => $this->recipient->street,
				'city' => $this->recipient->city,
				'state' => $this->recipient->state,
				'phone' => $this->recipient->phone,
				'email' => $this->recipient->email
			),
			'parcels' => array()
		);
		
		$thermal_printing = get_option('mfb_thermal_printing');
		
		$params['thermal_labels'] = $thermal_printing == 'yes' ? true : false;
		
		if( $this->offer->pickup == true ) {
			$params['shipper']['collection_date'] = $this->collection_date;
		}

		if( $this->offer->relay == true ) {
			$params['recipient']['location_code'] = $this->delivery_location_code;
		}
		
		foreach( $this->parcels as $parcel ) {
			$params['parcels'][] = array('description' => $parcel->description, 'value' => $parcel->value, 'currency' => 'EUR', 'country_of_origin' => $parcel->country_of_origin);
		}

		// Placing the order on the API
		$api_order = Lce\Resource\Order::place($this->offer->api_offer_uuid, $params);
		
		// Saving the order uuid
		$this->api_order_uuid = $api_order->id;
		$this->date_booking = date('Y-m-d H:i:s');
		$this->status = 'mfb-booked';
		
		foreach ( $api_order->parcels as $key => $parcel) {
			$this->parcels[$key]->tracking_number = $parcel->reference;
		}
		
		$this->save();
	}

	// Returns an array of links and codes, for tracking
	public function tracking_links() {
		$links = array();
		// We only continue if we find the corresponding carrier and it has a tracking URL
		$carrier = MFB_Carrier::get_by_code( $this->offer->product_code );
		if ( $this->status == 'mfb-booked' && $carrier && $carrier->tracking_url ) {
			$tracking_numbers = array();
			foreach ( $this->parcels as $parcel ) {
				$tracking_numbers[] = $parcel->tracking_number;
			}
			$tracking_numbers = array_unique( $tracking_numbers );
			$tracking_numbers = array_filter( $tracking_numbers, function($v){ return !empty($v);});
			foreach( $tracking_numbers as $tracking_number ) {
				$links[] = array(	'link' => str_replace( 'TRACKING_NUMBER', $tracking_number, $carrier->tracking_url ),
													'code' => $tracking_number);
			}
		}
		return $links;
	}

}
