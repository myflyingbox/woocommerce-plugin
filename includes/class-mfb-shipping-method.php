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

	public $included_destinations = array();
	public $excluded_destinations = array();

	public $flat_rates = array();

	public function __construct( $instance_id = 0 ) {
		$this->id = get_called_class();
		$this->instance_id       = absint( $instance_id );
		$this->supports              = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal'
		);
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
		$this->description        = apply_filters( 'mfb_shipping_method_description', $this->get_option( 'description' ) );

		$this->method_title       = $this->id;
		$this->method_description = $this->description;

		$this->load_destination_restrictions();

		// Actions
		add_filter( 'woocommerce_calculated_shipping',  array( $this, 'calculate_shipping'));
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

		do_action ( 'mfb_shipping_method_initialized', $this->id );
	}

	private function load_destination_restrictions() {
		// Loading included destinations first
		$rules = array();

		if(!empty($this->get_option('included_postcodes')))
		{
			foreach( explode(',', $this->get_option('included_postcodes')) as $part1) {
				foreach( explode('\r\n', $part1) as $part2 ) {
					$rules[] = $part2;
				}
			}
		}
		foreach( $rules as $rule ) {
			$split = explode('-', $rule);
			$country = trim($split[0]);
			if ( count($split) == 2) {
				$postcode = trim($split[1]);
			} else {
				$postcode = false;
			}
			// Now we have the country and the (optional) postcode for this rule
			if ( ! array_key_exists($country, $this->included_destinations) && !empty( $country ) ) {
				$this->included_destinations[$country] = array();
			}
			if ( $postcode ) {
				$this->included_destinations[$country][] = $postcode;
			}
		}


		// Loading excluded destinations
		$rules = array();
		if(!empty($this->get_option('excluded_postcodes')))
		{
			foreach( explode(',', $this->get_option('excluded_postcodes')) as $part1) {
				foreach( explode("\n", $part1) as $part2 ) {
					$rules[] = $part2;
				}
			}
		}
		foreach( $rules as $rule ) {
			$split = explode('-', $rule);
			$country = trim($split[0]);
			if ( count($split) == 2) {
				$postcode = trim($split[1]);
			} else {
				$postcode = false;
			}
			// Now we have the country and the (optional) postcode for this rule
			if ( ! array_key_exists($country, $this->excluded_destinations) && !empty( $country )  ) {
				$this->excluded_destinations[$country] = array();
			}
			if ( $postcode ) {
				$this->excluded_destinations[$country][] = $postcode;
			}
		}
	}

	public function load_flat_rates( $country ) {
		$this->flat_rates = array();
		$rates = array();
		if(!empty($this->get_option('flatrate_prices')))
		{
			foreach( explode(',', $this->get_option('flatrate_prices')) as $part1) {
				foreach( explode(PHP_EOL, $part1) as $part2 ) {
					$rates[] = $part2;
				}
			}
		}
		$previous_weight = 0;
		$country_specific_pricelist = false;

		$valid_rates = array(); // Storing applicable rates (for current country)
		foreach( $rates as $rate ) {
			$split = explode('|', $rate);

			// No country specific rate
			if ( count($split) == 2) {
				$weight = trim($split[0]);
				$price = trim($split[1]);
				$valid_rates[] = array( null, (float)$weight, (float)$price );
			// Rate for specific country
			} else if ( count($split) == 3 && $country != null ) {
				$rate_country = trim($split[0]);
				$weight = trim($split[1]);
				$price = trim($split[2]);
				if ( $rate_country == $country ) {
					// We have at least one tariff specific to this country, to we'll ignore any generic tariff
					$country_specific_pricelist = true;
					$valid_rates[] = array( $country, (float)$weight, (float)$price );
				}
			}
		}
		// Removing duplicates, keeping only latest defined rate and rates applicable to this country, if applicable
		$weights = array();
		$applicable_country = $country_specific_pricelist ? $country : null;
		foreach ( array_reverse($valid_rates) as $rate ) {
			if ( !in_array( $rate[1], $weights ) && $rate[0] == $applicable_country ) {
				$this->flat_rates[] = array($rate[1], $rate[2]);
			}
			$weights[] = $rate[1];
		}

		// Sorting by weight
		$weights = array();
		foreach($this->flat_rates as $key => $value){
			$weights[$key] = $value[1];
		}
		array_multisort($weights, SORT_ASC, SORT_NUMERIC, $this->flat_rates);

		// And finally, setting 'previous_weight' values to ease extraction
		$previous_weight = 0;
		foreach($this->flat_rates as $key => $value) {
			$this->flat_rates[$key] = array($previous_weight, $value[0], $value[1]);
			$previous_weight = $value[0];
		}
	}

	/**
	 * This is the form definition for the shipping method settings.
	 */
	public function init_form_fields() {
		$fields = array(
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
			),
			'tracking_url' => array(
				'title'       => __( 'Tracking URL', 'my-flying-box' ),
				'type'        => 'text',
				'description' => __( 'Put the variable TRACKING_NUMBER in the URL, it will be automatically replaced with the real tracking number when generating the link.', 'my-flying-box' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'included_postcodes' => array(
				'title'       => __( 'Included postcodes', 'my-flying-box' ),
				'type'        => 'textarea',
				'description' => __( 'Limits the service only to these postcodes. One rule per line (or same line and separated by a comma), using the following format: XX-YYYYY (XX = alpha2 country code, YYYYY = full postcode or prefix). Whitespaces will be ignored. You can also specify country codes without postcodes (just "XX"). Leave blank to apply no limitation.', 'my-flying-box' ),
				'default'     => '',
				'placeholder' => 'FR, ES-28...',
				'desc_tip'    => true,
			),
			'excluded_postcodes' => array(
				'title'       => __( 'Excluded postcodes', 'my-flying-box' ),
				'type'        => 'textarea',
				'description' => __( 'Excludes postcodes matching this list. One rule per line, using the following format: XX-YYYYY (XX = alpha2 country code, YYYYY = full postcode or just beginning). Whitespaces will be ignored. You can also specify country codes without postcodes (just "XX").', 'my-flying-box' ),
				'default'     => '',
				'placeholder' => 'FR-75, FR-974...',
				'desc_tip'    => true,
			),
			'flatrate_pricing' => array(
				'title'   => __( 'Flat-rate pricing', 'my-flying-box' ),
				'type'    => 'checkbox',
				'label'   => __( 'Check to enable pricing based on flat-rate pricing table, as defined below. When enabled, the prices returned by the API will not be used.', 'my-flying-box' ),
				'default' => 'no'
			),
			'flatrate_prices' => array(
				'title'       => __( 'Flat-rate prices', 'my-flying-box' ),
				'type'        => 'textarea',
				'description' => __( 'Prices are based on weight, so you can define as many rules as you want in the following format: Weight (up to, in kg, e.g. 6 or 7.5) | Price (float with dot as separator, e.g. 3.54). One rule per line.', 'my-flying-box' ),
				'default'     => '',
				'placeholder' => '6 | 3.5',
				'desc_tip'    => true,
			),
			'reduce_api_calls' => array(
				'title'   => __( 'Reduce API calls', 'my-flying-box' ),
				'type'    => 'checkbox',
				'label'   => __( 'Check to limit the number of API calls, improving performance of checkout process. WARNING: use only in conjunction with flatrate pricing (internal or from other extension) and an external method to determine whether this service should be available or not. As no request will be sent to the API, the module cannot determine whether the service is available or not for the destination, so you must use another mechanism for this!', 'my-flying-box' ),
				'default' => 'no'
			),
		);
		$this->instance_form_fields = apply_filters( 'mfb_shipping_method_form_fields', $fields );
		$this->form_fields = apply_filters( 'mfb_shipping_method_form_fields', $fields );
	}

	/**
	 * calculate_shipping function.
	 */
	public function calculate_shipping( $package = array() ) {

		// If this shipping is not enabled, no need to proceed any further
		if ( ! $this->enabled ) return false;

		$recipient_city = (isset($package['destination']['city']) && !empty($package['destination']['city']) ? $package['destination']['city']     : '');
		$recipient_postal_code = ( isset($package['destination']['postcode'])  ? $package['destination']['postcode'] : '' );
		$recipient_country = ( isset($package['destination']['country'])   ? $package['destination']['country']  : '' );

		// If this destination is not supported by this method, no need to go further either
		if ( ! $this->destination_supported( $recipient_postal_code, $recipient_country) ) return false;

		if ( $this->get_option('flatrate_pricing') == 'yes' ) $this->load_flat_rates( $recipient_country );

		// Extracting total weight from the WC CART
		$weight = 0;
		$dimensions_available = true;
		$products = [];
		foreach ( WC()->cart->get_cart() as $item_id => $values ) {
			$product = $values['data'];
			if( $product->needs_shipping() ) {
				$product_weight = $product->get_weight() ? wc_format_decimal( wc_get_weight($product->get_weight(),'kg'), 2 ) : 0;
				$weight += ($product_weight*$values['quantity']);

				if ($product->get_length() > 0 && $product->get_width() > 0 && $product->get_height() > 0) {
					$products[] = ['name' => $product->get_title(), 'price' => wc_format_decimal($product->get_price()), 'quantity' => $values['quantity'], 'weight' => $product->get_weight(), 'length' => $product->get_length(), 'width' => $product->get_width(), 'height' => $product->get_height()];
				} else {
					$dimensions_available = false;
				}
			}
		}

		if ( 0 == $weight)
			$weight = 0.2;

		// Loading existing quote, if available, so as not to send useless requests to the API
		$saved_quote_id = WC()->session->get('myflyingbox_shipment_quote_id');
		$quote_request_time = WC()->session->get('myflyingbox_shipment_quote_timestamp');

		if ( is_numeric( $saved_quote_id ) && $quote_request_time && $quote_request_time == $_SERVER['REQUEST_TIME'] ) {
			// A quote was already requested in the same server request, we just load it
			$quote = MFB_Quote::get( $saved_quote_id );

		} else if (
					is_numeric( $saved_quote_id ) &&
					$quote_request_time &&
					$quote_request_time != $_SERVER['REQUEST_TIME'] &&
					time() - $_SERVER['REQUEST_TIME'] < 600 &&
					$this->quote_still_valid( $saved_quote_id, $package )
				) {
			// A quote was requested in a recent previous request, with the same parameters. We can use it as is.
			$quote = MFB_Quote::get( $saved_quote_id );

		} else {
			// We don't have any existing valid quote

			// Removing any remains of old quote references
			WC()->session->set( 'myflyingbox_shipment_quote_id', null );
			WC()->session->set( 'myflyingbox_shipment_quote_timestamp', null );


			// In some cases, we can avoid calling the API altogether, improving performances.
			if ( $this->get_option('reduce_api_calls') == 'yes' && $this->get_option('flatrate_pricing') == 'yes' ) {

				$price = $this->get_flatrate_price( $weight, $recipient_country );
				$rate = array(
					'id' 		=> $this->id,
					'label' 	=> $this->title,
					'cost' => apply_filters( 'mfb_shipping_rate_price', $price )
				);
				$this->add_rate( $rate );
				return true;
			}
			// If we continue to run here, that means we must get a quote from the API.

			// We prepare the parcels data depending on whether or not we have product dimensions
			$parcels = [];
			if ($dimensions_available) {
				foreach($products as $product) {
					for($i = 1; $i <= $product['quantity']; $i++){
						$parcel = [];
						$parcel['length']            = $product['length'];
						$parcel['width']             = $product['width'];
						$parcel['height']            = $product['height'];
						$parcel['weight']            = $product['weight'];
						$parcels[] = $parcel;
					}
				}
			} else {
				$parcel = [];
				$dims = MFB_Dimension::get_for_weight( $weight );

				# We should use weight/dimensions correspondance; if we don't have any, we can't get a tariff...
				if (!$dims) return false;

				$parcel['length']            = $dims->length;
				$parcel['width']             = $dims->width;
				$parcel['height']            = $dims->height;
				$parcel['weight']            = $total_weight;
				$parcels[] = $parcel;
			}

			// We need destination info to be able to send a quote
			if ( empty($recipient_postal_code) || empty($recipient_country) ) return false;

			// However, when having a postal code but no city, this should not block the quote request. In most cases the quote is based on postal code only anyway!
			if (empty($recipient_city) && !empty($recipient_postal_code)) $recipient_city = 'N/A';

			// And then we build the quote request params' array
			$params = array(
				'shipper' => array(
					'city'         => My_Flying_Box_Settings::get_option('mfb_shipper_city'),
					'postal_code'  => My_Flying_Box_Settings::get_option('mfb_shipper_postal_code'),
					'country'      => My_Flying_Box_Settings::get_option('mfb_shipper_country_code')
				),
				'recipient' => array(
					'city'         => $recipient_city,
					'postal_code'  => $recipient_postal_code,
					'country'      => $recipient_country,
					'is_a_company' => false
				),
				'parcels' => $parcels
			);

			$api_quote = Lce\Resource\Quote::request($params);

			$quote = new MFB_Quote();
			$quote->api_quote_uuid = $api_quote->id;
			$quote->params         = $params;

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
			$price = $quote->offers[$this->id]->base_price_in_cents / 100;

			// Overriding the API price is we use flatrate pricing
			if ( $this->get_option('flatrate_pricing') == 'yes') {
				$price = $this->get_flatrate_price( $weight, $recipient_country );
			}

			$price = apply_filters( 'mfb_shipping_rate_price', $price, $this->id );
			$rate = array(
				'id' 		=> $this->id,
				'label' 	=> $this->title,
				'cost' => $price,
				'taxes' => apply_filters( 'mfb_shipping_rate_taxes', '', $this->id, $price ),
			);
			$this->add_rate( $rate );
		}
	}

	// Checking whether a quote is still valid for the given params
	private function quote_still_valid( $quote_id, $params ) {
		$quote = MFB_Quote::get( $quote_id );
		if (
			$quote &&
			$quote->params['recipient']['city']        == $params['destination']['city'] &&
			$quote->params['recipient']['postal_code'] == $params['destination']['postcode'] &&
			$quote->params['recipient']['country']     == $params['destination']['country']
		) {
			return true;
		} else {
			return false;
		}
	}

	private function get_flatrate_price( $weight ) {
		foreach( $this->flat_rates as $rate ) {
			if ( $weight > $rate[0] && $weight < $rate[1] ) return $rate[2];
		}
	}

	// Controls whether this method should be proposed or not
	public function is_available( $package ) {
		return apply_filters( 'mfb_shipping_method_available', $this->enabled, $package, $this->id);
	}

	public function destination_supported( $postal_code, $country ) {
		// Result tag; by default, all destinations are supported.
		$supported = true;

		// First, checking if inclusion restrictions apply
		if ( count($this->included_destinations) > 0 ) {
			if ( ! array_key_exists($country, $this->included_destinations) ) {
				// The country is not included, we can get out now.
				$supported = false;
			} else {
				// Country is included, let's check the postcodes, if necessary
				if (count($this->included_destinations[$country]) > 0 ) {
					$included = false;
					foreach( $this->included_destinations[$country] as $included_postcode ) {
						if( strrpos($postal_code, $included_postcode, -strlen($postal_code)) !== FALSE ) { // This code checks whether postal code starts with the characters from excluded_postcode
							$included = true;
						}
					}
					if ( ! $included ) $supported = false; // The postal code was not included in any way, so we just get out.
				}
			}
		}

		// If arrive here, it means that either there is no inclusion restriction, or we have passed
		// all inclusion rules.
		// We now check that there is no applicable exclusion rule.
		// If any exclusion rule matches, we return false.
		if ( count($this->excluded_destinations) > 0 ) {
			if ( array_key_exists($country, $this->excluded_destinations) ) {
				// The country has some exclusion rules, let's check the postcodes, if necessary
				if (count($this->excluded_destinations[$country]) > 0 ) {
					foreach( $this->excluded_destinations[$country] as $excluded_postcode ) {
						// Checks whether postal code starts with the characters from excluded_postcode
						// If any match, we return false.
						if( strrpos($postal_code, $excluded_postcode, -strlen($postal_code)) !== FALSE ) {
							$supported = false;
						}
					}
				} else {
					// There are no postcode exclusion rules, so the whole country is excluded.
					$supported = false;
				}
			}
		}
		// We made it through without any exclusion match, we can return true!
		return apply_filters( 'mfb_shipping_method_destination_supported', $supported );
	}

}
