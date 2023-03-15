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
	public $bulk_order_id = 0;

	// Recording booking errors (used primarily by bulk order mechanism, with async booking)
	public $last_booking_error = '';

	// Currently selected quote and offer for this shipment
	public $quote = null;
	public $offer = null;
	public $collection_date = null; // Selected collection date at time of booking.
	public $delivery_location_code = null; // Selected relay delivery at time of booking.
	public $insured = false; // Do we insure this shipment?
	public $is_return = false; // Is this a return shipment?

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
		'insurable_value',
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

		$this->bulk_order_id = get_post_meta( $this->id, '_bulk_order_id', true );

		$this->last_booking_error = get_post_meta( $this->id, '_last_booking_error', true );

		// Loading quote and offer
		$this->quote = MFB_Quote::get( get_post_meta( $this->id, '_quote_id', true ) );
		$this->offer = MFB_Offer::get( get_post_meta( $this->id, '_offer_id', true ) );

		$this->collection_date = get_post_meta( $this->id, '_collection_date', true ); // Selected collection date
		$this->delivery_location_code = get_post_meta( $this->id, '_delivery_location_code', true ); // Selected relay
		$this->delivery_location      = get_post_meta( $this->id, '_delivery_location', true ); // Selected relay (full object with details)
		$this->insured                = get_post_meta( $this->id, '_insured', true ); // Do we insure this shipment?
		$this->is_return              = get_post_meta( $this->id, '_is_return', true ); // Is this a return shipment?


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

	public static function get_last_booked_for_order( $order_id, $return = false ) {

		$all_shipments = get_children( array(
			'post_type'     => 'mfb_shipment',
			'post_status'   => array('mfb-booked'),
			'post_parent'   => $order_id,
			'field'         => 'ids',
			'numberposts'   => 1,
			'orderby'       => array( 'date' => 'DESC' )
		));

		$shipments = array();

		foreach($all_shipments as $shipment) {
			$shipment = self::get($shipment->ID);
			if ( $shipment->is_return == $return ) {
				$shipments[] = $shipment;
			}
		}

		if ( !empty( $shipments ) ) {
			return $shipments[0];
		} else {
			return null;
		}
	}


	public static function create_from_order( $order, $bulk_order_id = null, $return_shipment = false ) {

		$shipment = new self();
		$shipment->wc_order_id = $order->get_id();
		if ($bulk_order_id == null) {
			$shipment->status = 'mfb-draft';
		} else {
			$shipment->status = 'mfb-processing';
		}

		$shipment->bulk_order_id = $bulk_order_id;
		$shipment->is_return = $return_shipment;

		// How do we use the fixed address defined in the module?
		// If return shipment, the fixed address it the recipient.
		// Otherwise it is the shipper.
		if ( $return_shipment ) {
			$fixed_address_attribute = 'recipient';
		} else {
			$fixed_address_attribute = 'shipper';
		}

		// Setting default shipper data
		foreach( self::$address_fields as $fieldname ) {
			$shipment->$fixed_address_attribute->$fieldname = get_option( 'mfb_shipper_'.$fieldname );
		}

		// How do we use the dynamic address defined by the customer?
		// If return shipment, the dynamic address it the shipper.
		// Otherwise it is the recipient.
		if ( $return_shipment ) {
			$dynamic_address_attribute = 'shipper';
		} else {
			$dynamic_address_attribute = 'recipient';
		}

		// Setting recipient data from order shipping address
		$shipment->$dynamic_address_attribute->name          = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
		$shipment->$dynamic_address_attribute->company       = $order->get_shipping_company();
		$shipment->$dynamic_address_attribute->street        = $order->get_shipping_address_1();

		$ship_addr2 = trim( $order->get_shipping_address_2() );
		if ( ! empty($ship_addr2) ) $shipment->$dynamic_address_attribute->street .= "\n" . $ship_addr2;

		$shipment->$dynamic_address_attribute->city          = $order->get_shipping_city();
		$shipment->$dynamic_address_attribute->postal_code   = $order->get_shipping_postcode();
		$shipment->$dynamic_address_attribute->state         = $order->get_shipping_state();
		$shipment->$dynamic_address_attribute->country_code  = $order->get_shipping_country();

		$shipment->$dynamic_address_attribute->email         = $order->get_billing_email();
		$shipment->$dynamic_address_attribute->phone         = $order->get_billing_phone();


		// If a location code was associated to the order, we record it here
		$delivery_location_code = get_post_meta( $order->get_id(), '_mfb_delivery_location', true);
		if ( $delivery_location_code && !empty( $delivery_location_code ) ) {
			$shipment->delivery_location_code = $delivery_location_code;
		}

		// Initialize a default parcel based on total weight of order and dimensions if available

		$total_weight = wc_format_decimal(0);
		$total_value  = wc_format_decimal(0);
		$line_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', 'line_item' ) );

		$dimensions_available = true;
		$products = [];
		foreach ( $line_items as $item_id => $item ) {
			$_product = $order->get_product_from_item( $item );

			if ( $_product ) {
				$total_weight += wc_format_decimal( $_product->get_weight() * $item['qty'] );
				$total_value  += wc_format_decimal( isset( $item['line_total'] ) ? $item['line_total'] : 0 );

				$products[] = ['name' => $_product->get_title(), 'price' => wc_format_decimal($_product->get_price()), 'quantity' => $item['qty'], 'weight' => $_product->get_weight(), 'length' => $_product->get_length(), 'width' => $_product->get_width(), 'height' => $_product->get_height()];

			}
		}

		if ( $shipment->is_return ) {
			$shipping_method = $shipment->get_customer_shipping_method( $order );
		} else {
			$shipping_method = false;
		}


		if ( $shipping_method ) {
			$force_dimensions_table = $shipping_method->force_dimensions_table;
		} else {
			$force_dimensions_table = false;
		}

		$calculated_parcels = self::parcel_data_from_items( $products, true );
		$parcels = [];
		foreach($calculated_parcels as $p) {
			$parcel = new stdClass();
			$parcel->length            = $p['length'];
			$parcel->width             = $p['width'];
			$parcel->height            = $p['height'];
			$parcel->weight            = $p['weight'];
			$parcel->description       = get_option( 'mfb_default_parcel_description' );
			$parcel->country_of_origin = get_option( 'mfb_default_origin_country' );
			$parcel->insurable_value     = $p['insured_value'];
			$parcel->value             = $p['value'];
			$parcel->shipper_reference   = '';
			$parcel->recipient_reference = '';
			$parcel->customer_reference  = '';
			$parcel->tracking_number     = '';
			$parcels[] = $parcel;
		}

		$shipment->parcels = $parcels;

		if ( $total_value <= 2000 && My_Flying_Box_Settings::get_option('mfb_insure_by_default') == 'yes' ) {
			$shipment->insured = true;
		}

		$shipment->save();

		// All good. Now we try to get a quote straight away.
		$quote = $shipment->get_new_quote();

		$shipment->save();

		$shipment->autoselect_offer( $order );

		return $shipment;
	}


		public static function create_from_shipment( $mfb_shipment_id, $return_shipment = false, $bulk_order_id = null ) {

			$origin_shipment = self::get( $mfb_shipment_id );

			$shipment = new self();
			$shipment->wc_order_id = $origin_shipment->wc_order_id;
			if ($bulk_order_id == null) {
				$shipment->status = 'mfb-draft';
			} else {
				$shipment->status = 'mfb-processing';
			}

			$shipment->bulk_order_id = $bulk_order_id;

			// For a return shipment, all is reversed
			if ( $return_shipment ) {
				$shipment->is_return = true;
				$shipper_address_attribute 		= 'recipient';
				$recipient_address_attribute 	= 'shipper';
			} else {
				$shipment->is_return = false;
				$shipper_address_attribute 		= 'shipper';
				$recipient_address_attribute 	= 'recipient';
			}

			// Setting address data
			foreach( self::$address_fields as $fieldname ) {
				$shipment->$shipper_address_attribute->$fieldname 	= $origin_shipment->shipper->$fieldname;
				$shipment->$recipient_address_attribute->$fieldname = $origin_shipment->recipient->$fieldname;
			}

			// If a location code was associated to the order, we record it here, unless
			// this is a return shipment in which case this is not applicable anymore.
			if ( !$return_shipment ) {
				$shipment->delivery_location_code = $origin_shipment->delivery_location_code;
			}

			// Initialize parcels on the basis of origin shipment parcels
			for( $i = 1; $i <= $origin_shipment->parcels_count; $i++ ) {
				$shipment->parcels[$i-1] = new stdClass(); // Initializing parcel object
				foreach( self::$parcel_fields as $fieldname ) { // Looping on each parcel attribute
					$shipment->parcels[$i-1]->$fieldname = $origin_shipment->parcels[$i-1]->$fieldname;
				}
			}

			$shipment->insured = $origin_shipment->insured;

			$shipment->save();

			// All good. Now we try to get a quote straight away.
			// get_new_quote will call autoselect_offer, which will save the shipment as well.
			// So we will reload the shipment to make sure that the data is up to date.
			$quote = $shipment->get_new_quote();
			$shipment->populate();

			return $shipment;
		}

  // If preferred product code is specified, it will be used in priority.
	public function autoselect_offer( $order = null, $preferred_product_code = null ) {
		if ( $this->is_return ) {
			// For return shipments, always use services defined in config.
			if ( $this->domestic() ) {
				$default_service = My_Flying_Box_Settings::get_option('mfb_default_domestic_return_service');
			} else {
				$default_service = My_Flying_Box_Settings::get_option('mfb_default_international_return_service');
			}

			if ( $default_service && array_key_exists($default_service, $this->quote->offers)  ) {
				$this->offer = $this->quote->offers[$default_service];
			}
		} else {
			if ( $preferred_product_code ) {
				$this->offer = null;
				if ( array_key_exists($preferred_product_code, $this->quote->offers) ) {
					$this->offer = $this->quote->offers[$preferred_product_code];
				}
			} else if ( $this->quote && $this->wc_order_id ) {
				if ( null === $order ) $order = wc_get_order( $this->wc_order_id );
				$this->offer = null;

				// Auto-setting the offer based on what the customer has chosen
				if ( count($order->get_shipping_methods()) == 1 ) {
					$shipping_methods = $order->get_shipping_methods();
					$methods = array_pop($shipping_methods);
					$chosen_method = explode(':', $methods->get_method_id())[0];

					// Testing if the chosen method is listed on the API offers
					if ( array_key_exists($chosen_method, $this->quote->offers) ) {
						$this->offer = $this->quote->offers[$chosen_method];
					} else {
						// Maybe the offer selected by the customer was not a MFB service.
						// In this case, we try to select a default service, if applicable.
						if ( $this->domestic() ) {
							$default_service = My_Flying_Box_Settings::get_option('mfb_default_domestic_service');
						} else {
							$default_service = My_Flying_Box_Settings::get_option('mfb_default_international_service');
						}
						if ( array_key_exists($default_service, $this->quote->offers)  ) {
							$this->offer = $this->quote->offers[$default_service];
						}
					}
				}
			}
		}
		$this->save();
	}

	public function get_customer_shipping_method( $order = null ) {
		if ( null === $order && $this->wc_order_id ) $order = wc_get_order( $this->wc_order_id );
		if ( null === $order ) return false;

		$shipping_methods = $order->get_shipping_methods();
		$method = array_pop($shipping_methods);

		// There was no shipping method specified in the order, so we just skip altogether
		if ( null === $method ) return false;

		// Separating method name (for instanciation) and instance ID (to pass as parameter)
		$chosen_method = explode( ':', $method->get_method_id() );

		if ( ! class_exists( $chosen_method[0] ) ) {
			$shipping_method = false;
		} else {
			$shipping_method = new $chosen_method[0]( $chosen_method[1] );
		}
		return $shipping_method;
	}


	public function domestic() {
		if ( $this->shipper &&
				 $this->recipient &&
				 $this->shipper->country_code == $this->recipient->country_code ) {
			return true;
		} else {
			return false;
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
			if ($this->status == null) {
				$this->status = 'mfb-draft'; // New shipments should always be created as drafts, unless part of bulk order
			}

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

		update_post_meta( $this->id, '_bulk_order_id', $this->bulk_order_id );

		update_post_meta( $this->id, '_last_booking_error', $this->last_booking_error );

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

		update_post_meta( $this->id, '_insured', $this->insured );
		update_post_meta( $this->id, '_is_return', $this->is_return );

		// Saving the delivery location details, if applicable
		if ( $this->offer && $this->offer->relay == true && !empty($this->delivery_location_code) ) {
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

	public function update_status( $status ) {
		$this->status = $status;
		wp_update_post(array(
			'ID' => $this->id,
			'post_status' => $this->status
		));
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
		$line  = $parcel->length.'x'.$parcel->width.'x'.$parcel->height.'cm, '.$parcel->weight.'kg | ';
		$line .= sprintf(__('value: %s', 'my-flying-box'), $parcel->value.' €');
		if (strlen($parcel->insurable_value) > 0) {
			$line .= ' ' . sprintf(__('(insurable: %s)', 'my-flying-box'), $parcel->insurable_value.' €');
		}
		$line .= ' | '.$parcel->description.' ('.$parcel->country_of_origin.')';
		return $line;
	}

	public function parcel_tracking_link( $parcel ) {

		if ($this->status == 'mfb-draft' || is_null($this->offer)) return;

		$carrier = MFB_Carrier::get_by_code( $this->offer->product_code );

		if ($parcel->tracking_number && strlen($parcel->tracking_number) > 0) {
			if ($carrier && $carrier->tracking_url) {
				$line = "<br/><a href='". $carrier->tracking_url_for( $parcel->tracking_number, $this->receiver->postal_code ) .
					"' target='_blank'>".
					sprintf(__('Tracking: %s', 'my-flying-box'), $parcel->tracking_number).
					"</a>";
				} else {
				$line = "<br/>".sprintf(__('Tracking: %s', 'my-flying-box'), $parcel->tracking_number);
				}
		} else {
			$line = '';
		}
		return $line;
	}

	public function total_value()
	{
			$total_value = 0.0;
			foreach ($this->parcels as $parcel) {
					$total_value = $total_value + $parcel->value;
			}
			return $total_value;
	}

	public function total_insurable_value()
	{
			$total_value = 0.0;
			foreach ($this->parcels as $parcel) {
					$total_value = $total_value + $parcel->insurable_value;
			}
			return $total_value;
	}

	public function is_insurable()
	{
			return ($this->total_insurable_value() < 2000);
	}

	public function insurable_value()
	{
			return $this->total_insurable_value();
	}

	// Completely remove a shipment from the database
	public function destroy() {
		return wp_delete_post( $this->id );
	}

	public function get_new_quote() {

			// Not proceeding unless the shipment is in draft state
			if ($this->status != 'mfb-draft' && $this->status != 'mfb-processing') return false;

			// If we already have a selected offer, we will try to automatically select
			// the same service.
			if ( $this->offer ) {
				$currently_selected_service = $this->offer->product_code;
			} else {
				$currently_selected_service = null;
			}

			$parcels = array();

			foreach( $this->parcels as $parcel ) {
				$params = array(
					'length' => $parcel->length,
					'width'  => $parcel->width,
					'height'  => $parcel->height,
					'weight'  => $parcel->weight
				);

				if ($this->is_insurable()) {
					$params['insured_value'] = $parcel->insurable_value;
					$params['insured_currency'] = 'EUR';
				}

				$parcels[] = $params;
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
					$offer->carrier_code          = $api_offer->product->carrier_code;
					$offer->product_code          = $api_offer->product->code;
					$offer->product_name          = $api_offer->product->name;
					$offer->pickup                = $api_offer->product->pick_up;
					$offer->dropoff               = $api_offer->product->drop_off;
					$offer->relay                 = $api_offer->product->preset_delivery_location;
					$offer->collection_dates      = $api_offer->collection_dates;
					$offer->base_price_in_cents   = $api_offer->price->amount_in_cents;
					$offer->total_price_in_cents  = $api_offer->total_price->amount_in_cents;
					$offer->currency              = $api_offer->total_price->currency;
					if ($api_offer->insurance_price) {
						$offer->insurance_price_in_cents = $api_offer->insurance_price->amount_in_cents;
					}
					$offer->save();
				}
				$this->quote = $quote;
				$this->offer = null;
				$this->save();
			}
			// Refreshing the quote, to get the offers loaded properly
			$quote->populate();
			$this->populate();
			$this->autoselect_offer(null, $currently_selected_service);

			return $quote;
	}

	public function place_booking() {

		if ( !$this->offer ) return false;

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
			// Forcing first collection available if no date has been previously selected (case of bulk order)
			if ( $this->collection_date == null || strlen($this->collection_date) == 0 ) {
				$this->collection_date = $this->offer->collection_dates[0]->date;
			}
			$params['shipper']['collection_date'] = $this->collection_date;
		}

		if( $this->offer->relay == true ) {
			$params['recipient']['location_code'] = $this->delivery_location_code;
		}

		foreach( $this->parcels as $parcel ) {
			$params['parcels'][] = array('description' => $parcel->description, 'value' => $parcel->value, 'currency' => 'EUR', 'country_of_origin' => $parcel->country_of_origin);
		}

		// Insurance
		if ( $this->insured ) {
			$params['insure_shipment'] = true;
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
		return $this->save();
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
				$links[] = array(	'link' => $carrier->tracking_url_for( $tracking_number, $this->receiver->postal_code ),
													'code' => $tracking_number);
			}
		}
		return $links;
	}

	// Returns an array of parcel data to make a quote request, based on the
	// the characteristics of the products passed as argument.
	// Products data must be properly extracted beforehands, either from cart or from
	// order.
	public static function parcel_data_from_items($products, $with_value = false) {
			$parcels = array();
			$articles = array();
			$ignore_dimensions = false;
			$missing_dimension = false;
			$missing_dimensions_details = '';
			$ignored_articles = 0;
			$total_articles = 0;
			$total_weight = 0;
			$total_value = 0;
			// First, we test whether we have dimensions for all articles. If not,
			// We fall back to the weight/dimensions table.
			// If some articles have dimensions and other have no dimensions at all (no weight either), then we totally ignore them
			// The following loop initializes an array of articles with dimensions, that we can use later to determine final pack-list.
			foreach ($products as $product) {
					$total_articles++;

					$weight = $product['weight'];
					$total_weight += ($weight*$product['quantity']);
					$total_value += ($product['price']*$product['quantity']);

					// Some carriers check that length is long enough, but don't care much about other dimensions...
					$dims = array(
						(int)$product['length'],
						(int)$product['width'],
						(int)$product['height']
					);
					sort($dims);

					$length = $dims[2];
					$width = $dims[1];
					$height = $dims[0];

					if ($length <= 0 && $width <= 0 && $height <= 0 && $weight <= 0) {
							// This product has no dimension at all, it will be ignored alltogether.
							$ignored_articles++;
							continue;
					} else if ( My_Flying_Box_Settings::get_option('mfb_force_dimensions_table') == 'yes' ) {
							// Forcing use of weight only
							$ignore_dimensions = true;
					} else if ($length <= 0 || $width <= 0 || $height <= 0 || $weight <= 0) {
							// Some dimensions are missing, so whatever the situation for other products,
							// we will not use real dimensions for parcel simulation, but fall back
							// to standard weight/dimensions correspondance table.
							$ignore_dimensions = true;
							$missing_dimension = true;
							$missing_dimensions_details .= "$length x $width x $height - $weight kg "; // Used for debug output below.
					} else {
							// We have all dimensions for this product.
							// Some carriers do not accept any parcel below 1cm on any side (DHL). Forcing 1cm mini dimension.
							if ($length < 1) {
									$length = 1;
							}
							if ($width < 1) {
									$width = 1;
							}
							if ($height < 1) {
									$height = 1;
							}
					}
					// The same product can be added multiple times. We save articles unit by unit.
					for ($i=0; $i<$product['quantity']; $i++) {
							$articles[] = array(
								'length' => $length,
								'height' => $height,
								'width' => $width,
								'weight' => $weight,
								'value' => $product['price']
							);
					}
			}

			// If all articles were ignored, we just do our best with what we have, which means not much!
			if ($ignored_articles == $total_articles) {
					$weight = round($total_weight, 3);
					if ($weight <= 0) {
							$weight = 0.1; // As ignored artices do not have weight, this will probably be the weight used.
					}
					$dimension = MFB_Dimension::get_for_weight( $weight );
					$parcels =  array(
							array('length' => $dimension->length,
										'width' => $dimension->width,
										'height' => $dimension->height
							)
					);
					if ($with_value) {
						# We set both value and insured value based on the calculated value of the parcel.
						$parcels[0]['value'] = $total_value;
						$parcels[0]['currency'] = 'EUR';
						$parcels[0]['insured_value'] = $total_value;
						$parcels[0]['insured_currency'] = 'EUR';
					}
			} else if ($ignore_dimensions) {

					// In this case, two possibilities:
					//  - if we have a maximum weight per package set in the config, we
					//    have to spread articles in as many packages as needed.
					//  - otherwise, just use the default strategy: total weight + corresponding dimension based on table
					$max_real_weight = My_Flying_Box_Settings::get_option('mfb_max_real_weight_per_package');
					if ($max_real_weight && $max_real_weight > 0) {
						// We must now spread every article in virtual parcels, respecting
						// the defined maximum real weight.
						$parcels = array();
						foreach($articles as $key => $article) {
								if (count($parcels) == 0 || bccomp($article['weight'], $max_real_weight, 3) > 0) {
										// If first article, initialize new parcel.
										// If article has a weight above the limit, it gets its own package.
										$p = array('weight' => $article['weight']);
										if ($with_value) {
											$p['value'] = $article['value'];
											$p['currency'] = 'EUR';
											$p['insured_value'] = $article['value'];
											$p['insured_currency'] = 'EUR';
										}
										$parcels[] = $p;
										continue;
								} else {
										foreach($parcels as &$parcel) {
												// Trying to fit the article in an existing parcel.
												$cumulated_weight = bcadd($parcel['weight'], $article['weight'], 3);
												if ($cumulated_weight <= $max_real_weight) {
													$parcel['weight'] = $cumulated_weight;
													if ($with_value) {
														$parcel['insured_value'] += $article['value'];
														$parcel['value'] += $article['value'];
													}
													unset($article); // Security, to avoid double treatment of the same article.
													break;
												}
										}
										unset($parcel); // Unsetting reference to last $parcel of the loop, to avoid any bad surprise later!

										// If we could not fit the article in any existing package,
										// we simply initialize a new one, and that's it.
										if (isset($article)) {
												$p = array('weight' => $article['weight']);
												if ($with_value) {
													$p['value'] = $article['value'];
													$p['currency'] = 'EUR';
													$p['insured_value'] = $article['value'];
													$p['insured_currency'] = 'EUR';
												}
												$parcels[] = $p;
												continue;
										}
								}
						}
						// Article weight has been spread to relevant parcels. Now we must
						// define parcel dimensions, based on weight.
						foreach($parcels as &$parcel) {
								// First, ensuring the weight is not zero!
								if ($parcel['weight'] <= 0) {
										$parcel['weight'] = 0.1;
								}
								$dimension = MFB_Dimension::get_for_weight( $parcel['weight'] );
								$parcel['length'] = $dimension->length;
								$parcel['height'] = $dimension->height;
								$parcel['width'] = $dimension->width;
						}
						unset($parcel); // Unsetting reference to last $parcel of the loop, to avoid any bad surprise later!

						// Our parcels are now ready.

					} else {
							// Simple case: no dimensions, and no maximum real weight.
							// We just take the total weight and find the corresponding dimensions.
							$weight = round($total_weight, 3);
							if ($weight <= 0) {
									$weight = 0.1;
							}
							$dimension = MFB_Dimension::get_for_weight( $weight );
							$parcels =  array(
									array('length' => $dimension->length,
												'height' => $dimension->height,
												'width' => $dimension->width,
												'weight' => $weight,
									),
							);
							if ($with_value) {
								$parcels[0]['insured_value'] = $total_value;
								$parcels[0]['insured_currency'] = 'EUR';
								$parcels[0]['value'] = $total_value;
								$parcels[0]['currency'] = 'EUR';
							}

					}
			} else {
					// We have dimensions for all articles, so this is a bit more complex.
					// We proceed like above, but we also take into account the dimensions of the articles,
					// in two ways: to determine the dimensions of the packages, and to check, on this basis, the max
					// volumetric weight of the package.

					$max_real_weight = My_Flying_Box_Settings::get_option('mfb_max_real_weight_per_package');
					$max_volumetric_weight = My_Flying_Box_Settings::get_option('mfb_max_volumetric_weight_per_package');

					if ($max_real_weight && $max_real_weight > 0 && $max_volumetric_weight && $max_volumetric_weight > 0) {
						// We must now spread every article in virtual parcels, respecting
						// the defined maximum real weight and volumetric weight, based on dimensions.
						$parcels = array();
						foreach($articles as $key => $article) {
								$article_volumetric_weight = $article['length']*$article['width']*$article['height']/5000;
								if (count($parcels) == 0 || bccomp($article['weight'], $max_real_weight, 3) >= 0 || bccomp($article_volumetric_weight, $max_volumetric_weight, 3) >= 0) {
										// If first article, initialize new parcel.
										// If article has a weight above the limit, it gets its own package.
										$p = array(
												'length' => $article['length'],
												'width' => $article['width'],
												'height' => $article['height'],
												'weight' => $article['weight']
										);
										if ($with_value) {
											$p['insured_value'] = $article['value'];
											$p['insured_currency'] = 'EUR';
											$p['value'] = $article['value'];
											$p['currency'] = 'EUR';
										}
										$parcels[] = $p;

										continue;
								} else {
										foreach($parcels as &$parcel) {
												// Trying to fit the article in an existing parcel.
												$cumulated_weight = bcadd($parcel['weight'], $article['weight'], 3);
												$new_parcel_length = max($parcel['length'], $article['length']);
												$new_parcel_width = max($parcel['width'], $article['width']);
												$new_parcel_height = (int)$parcel['height'] + (int)$article['height'];
												$new_parcel_volumetric_weight = (int)$new_parcel_length*(int)$new_parcel_width*(int)$new_parcel_height/5000;

												if (bccomp($cumulated_weight, $max_real_weight, 3) <= 0 && bccomp($new_parcel_volumetric_weight, $max_volumetric_weight, 3) <= 0) {
													$parcel['weight'] = $cumulated_weight;
													$parcel['length'] = $new_parcel_length;
													$parcel['width'] = $new_parcel_width;
													$parcel['height'] = $new_parcel_height;
													if ($with_value) {
														$parcel['insured_value'] += $article['value'];
														$parcel['value'] += $article['value'];
													}
													unset($article); // Security, to avoid double treatment of the same article.
													break;
												}
										}
										unset($parcel); // Unsetting reference to last $parcel of the loop, to avoid any bad surprise later!

										// If we could not fit the article in any existing package,
										// we simply initialize a new one, and that's it.
										if (isset($article)) {
												$p = array(
														'length' => $article['length'],
														'width' => $article['width'],
														'height' => $article['height'],
														'weight' => $article['weight']
												);
												if ($with_value) {
													$p['insured_value'] = $article['value'];
													$p['insured_currency'] = 'EUR';
													$p['value'] = $article['value'];
													$p['currency'] = 'EUR';
												}
												$parcels[] = $p;
												continue;
										}
								}
						}
					} else {
						// If we are here, it means we do not want to spread articles in parcels of specific characteristics.
						// So we just have one parcel per article.
						$parcels = [];
						foreach($articles as $article) {
							$p = array(
									'length' => $article['length'],
									'width' => $article['width'],
									'height' => $article['height'],
									'weight' => $article['weight']
							);
							if ($with_value) {
								$p['value'] = $article['value'];
								$p['currency'] = 'EUR';
								$p['insured_value'] = $article['value'];
								$p['insured_currency'] = 'EUR';
							}
							$parcels[] = $p;
						}
					}
			}
			return $parcels;
	}

}
