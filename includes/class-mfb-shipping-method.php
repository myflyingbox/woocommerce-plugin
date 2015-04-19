<?php
/**
 * 
 */

class MFB_Shipping_Method extends WC_Shipping_Method {

	public $id = 0;
	public $title = null;
  public $method_title = null;
  public $method_description = null;
	public $description = null;
	public $enabled = false;
  public $carrier = null;

	public function __construct() {
		$this->id = get_called_class();    
    $this->init();
	}

	/**
	 * init function.
	 */
	public function init() {

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

    $this->carrier = MFB_Carrier::get_by_code( $this->id );

		// Define user set variables
		$this->enabled		        = $this->get_option( 'enabled' ) == 'yes' ? true : false;
		$this->title		          = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
    
    $this->method_title       = $this->title;
    $this->method_description = $this->description;

		// Actions
		add_filter( 'woocommerce_calculated_shipping',  array( $this, 'calculate_shipping'));
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * This is the form definition for the shipping method settings.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable', 'my-flying-box' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this service', 'my-flying-box' ),
				'default' => 'yes'
			),
			'title' => array(
				'title'       => __( 'Title', 'my-flying-box' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'my-flying-box' ),
				'default'     => $this->id,
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'my-flying-box' ),
				'type'        => 'text',
				'description' => __( 'This controls the description of this shipping method, which the user sees during checkout.', 'my-flying-box' ),
				'default'     => '',
				'desc_tip'    => true,
			)
		);
	}

	/**
	 * calculate_shipping function.
	 */
	public function calculate_shipping( $package = array() ) {
    if ( ! $this->enabled ) return false;
    
    // Loading existing quote, if available, so as not to send useless requests to the API
    $saved_quote_id = WC()->session->get('myflyingbox_shipment_quote_id');
    $quote_request_time = WC()->session->get('myflyingbox_shipment_quote_timestamp');
    
    if ( $saved_quote_id && $quote_request_time && $quote_request_time == $_SERVER['REQUEST_TIME'] ) {
      $quote = MFB_Quote::get( $saved_quote_id );

    } else {

      // Extracting total weight from the WC CART
      $weight = 0;
      foreach ( WC()->cart->get_cart() as $item_id => $values ) {
        $product = $values['data'];
        if( $product->needs_shipping() ) {
          $product_weight = $product->get_weight() ? wc_format_decimal( wc_get_weight($product->get_weight(),'kg'), 2 ) : 0;
          $weight += $product_weight;
        }
      }
      if ( 0 == $weight)
        $weight = 0.2;

      // Next, we get the computed dimensions based on this total weight      
      $dimension = MFB_Dimension::get_for_weight( $weight );

      if ( ! $dimension ) return false;

      // And then we build the quote request params' array
      $params = array(
        'shipper' => array(
          'city'         => My_Flying_Box_Settings::get_option('mfb_shipper_city'),
          'postal_code'  => My_Flying_Box_Settings::get_option('mfb_shipper_postal_code'),
          'country'      => My_Flying_Box_Settings::get_option('mfb_shipper_country_code')
        ),
        'recipient' => array(
          'city'         => ( isset($package['destination']['city']) && !empty($package['destination']['city']) ? $package['destination']['city']     : 'N/A' ),
          'postal_code'  => ( isset($package['destination']['postcode'])  ? $package['destination']['postcode'] : '' ),
          'country'      => ( isset($package['destination']['country'])   ? $package['destination']['country']  : '' ),
          'is_a_company' => false
        ),
        'parcels' => array(
          array('length' => $dimension->length, 'height' => $dimension->height, 'width' => $dimension->width, 'weight' => $weight)
        )
      );
      
      $api_quote = Lce\Resource\Quote::request($params);
      
      $quote = new MFB_Quote();
      $quote->api_quote_uuid = $api_quote->id;
      
      if ($quote->save()) {
        // Now we create the offers

        foreach($api_quote->offers as $k => $api_offer) {
          $offer = new MFB_Offer();
          $offer->quote_id = $quote->id;
          $offer->api_offer_uuid = $api_offer->id;
          $offer->product_code = $api_offer->product->code;
          $offer->base_price_in_cents = $api_offer->price->amount_in_cents;
          $offer->total_price_in_cents = $api_offer->total_price->amount_in_cents;
          $offer->currency = $api_offer->total_price->currency;
          $offer->save();
        }
      }
      // Refreshing the quote, to get the offers loaded properly
      $quote->populate();

    }

    // And now we save the ID in session so that we can load this quote for other
    // shipping methods, instead of sending another query to the server.
    // A quote is valid only for a given request, which is not perfect, but already better
    // than having several API calls for a single request...
    WC()->session->set( 'myflyingbox_shipment_quote_id', $quote->id );
    WC()->session->set( 'myflyingbox_shipment_quote_timestamp', $_SERVER['REQUEST_TIME'] );
    
    if ( isset($quote->offers[$this->id]) ) {
      $rate = array(
        'id' 		=> $this->id,
        'label' 	=> $this->title,
        'cost' => $quote->offers[$this->id]->base_price_in_cents / 100
      );
      $this->add_rate( $rate );
    }
	}

  // Controls whether this method should be proposed or not
	public function is_available( $package ) {
		return $this->enabled;
  }
}
