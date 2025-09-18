<?php

/**
 *
 */

class MFB_Shipping_Method extends WC_Shipping_Method
{

	public $id = 0;
	public $title = null;
	public $method_title = null;
	public $method_description = null;
	public $description = null;
	public $enabled = false;
	public $carrier = null;

	public $included_destinations = array();
	public $excluded_destinations = array();
	public $min_weight = null;
	public $max_weight = null;
	public $min_order_price = null;
	public $max_order_price = null;

	public $flat_rates = array();

	// When set to true, ignore product dimensions to determine packlist, using only
	// the weight/dimensions table configured in module settings.
	public $force_dimensions_table = false;

	public $extended_cover = false;

	public function __construct($instance_id = 0)
	{


		$this->id = get_called_class();
		$this->instance_id = absint($instance_id);
		$this->supports = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal'
		);

		$this->init();
	}

	/**
	 * init function.
	 */
	public function init()
	{

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		$this->carrier = MFB_Carrier::get_by_code($this->id);

		// Define user set variables
		$this->enabled = $this->get_option('enabled') == 'yes' ? true : false;
		$this->title = $this->get_option('title');
		$this->description = apply_filters('mfb_shipping_method_description', $this->get_option('description'));

		$this->force_dimensions_table = $this->get_option('force_dimensions_table');

		$this->extended_cover = $this->get_option('extended_cover');

		$this->method_title = $this->id;
		$this->method_description = $this->description;

		$this->load_destination_restrictions();
		$this->load_weight_restrictions();
		$this->load_order_price_restrictions();

		// Actions
		// add_filter( 'woocommerce_calculated_shipping',  array( $this, 'calculate_shipping'));
		add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));

		if (empty($this->get_option('carrier_logo'))) {
			$filename = $this->carrier ? $this->carrier->default_logo_filename() : null;
			$custom_logo_path = dirname(plugin_dir_path(__FILE__)) . "/assets/carrier_logos/{$filename}";
			$custom_logo_url = plugins_url("assets/carrier_logos/{$filename}", dirname(__FILE__));
			if ($filename && file_exists($custom_logo_path)) {
				$this->instance_settings['carrier_logo'] = $custom_logo_url;
				update_option($this->get_instance_option_key(), $this->instance_settings);
			}
		}
		do_action('mfb_shipping_method_initialized', $this->id);
	}

	private function load_destination_restrictions()
	{
		// Loading included destinations first
		$rules = array();
		$included_postcodes = $this->get_option('included_postcodes');

		if (!empty($included_postcodes)) {
			foreach (explode(',', $this->get_option('included_postcodes')) as $part1) {
				foreach (explode('\r\n', $part1) as $part2) {
					$rules[] = $part2;
				}
			}
		}
		foreach ($rules as $rule) {
			$split = explode('-', $rule);
			$country = trim($split[0]);
			if (count($split) == 2) {
				$postcode = trim($split[1]);
			} else {
				$postcode = false;
			}
			// Now we have the country and the (optional) postcode for this rule
			if (!array_key_exists($country, $this->included_destinations) && !empty($country)) {
				$this->included_destinations[$country] = array();
			}
			if ($postcode) {
				$this->included_destinations[$country][] = $postcode;
			}
		}


		// Loading excluded destinations
		$rules = array();
		$excluded_postcodes = $this->get_option('excluded_postcodes');
		if (!empty($excluded_postcodes)) {
			foreach (explode(',', $this->get_option('excluded_postcodes')) as $part1) {
				foreach (explode("\n", $part1) as $part2) {
					$rules[] = $part2;
				}
			}
		}
		foreach ($rules as $rule) {
			$split = explode('-', $rule);
			$country = trim($split[0]);
			if (count($split) == 2) {
				$postcode = trim($split[1]);
			} else {
				$postcode = false;
			}
			// Now we have the country and the (optional) postcode for this rule
			if (!array_key_exists($country, $this->excluded_destinations) && !empty($country)) {
				$this->excluded_destinations[$country] = array();
			}
			if ($postcode) {
				$this->excluded_destinations[$country][] = $postcode;
			}
		}
	}

	private function load_weight_restrictions()
	{
		$setting_value = $this->get_option('min_weight');
		if (!empty($setting_value) && is_numeric($setting_value)) {
			$this->min_weight = (float) wc_format_decimal($setting_value);
		}
		$setting_value = $this->get_option('max_weight');
		if (!empty($setting_value) && is_numeric($setting_value)) {
			$this->max_weight = (float) wc_format_decimal($setting_value);
		}
	}

	private function load_order_price_restrictions()
	{
		$setting_value = $this->get_option('min_order_price');
		if (!empty($setting_value) && is_numeric($setting_value)) {
			$this->min_order_price = (float) wc_format_decimal($setting_value);
		}
		$setting_value = $this->get_option('max_order_price');
		if (!empty($setting_value) && is_numeric($setting_value)) {
			$this->max_order_price = (float) wc_format_decimal($setting_value);
		}
	}



	//
	// Loads flat rates for this shipping method.
	//
	// The flat rate DSL is pretty complex. All rate definitions must be separated by either
	// a line break (PHP_EOL) or a comma.
	//
	// Here is the list of possible valid formats:
	// 6|12.5 -> costs 12,5 for cart up to 6kg.
	// ES|6|12.5 -> costs 12,5 for cart up to 6kg, only if destination country is ES
	// 3|6|12.5 -> costs 12,5 for cart between 3 and 6 kg
	// ES|3|6|12.5 -> costs 12,5 for cart between 3 and 6 kg, only if destination country is ES
	//
	// And for all above examples, it is also possible to limit the rate to a set of specific
	// shipping classes, by adding a double pipe followed by a list of ;-separated IDs: "||5;22"
	// Examples:
	// 6|12.5||5;22
	// ES|6|12.5||5;22
	//
	// If there is any rule with a country prefix, that means that all other rules that do not have
	// the same country prefix will be totally ignored.
	public function load_flat_rates($country)
	{
		$this->flat_rates = array();
		$rates = array();
		$flatrate_prices = $this->get_option('flatrate_prices');
		if (!empty($flatrate_prices)) {
			foreach (explode(',', $this->get_option('flatrate_prices')) as $part1) {
				foreach (explode(PHP_EOL, $part1) as $part2) {
					$rates[] = $part2;
				}
			}
		}
		$previous_weight = 0;
		$country_specific_pricelist = false;

		$valid_rates = array(); // Storing applicable rates (for current country)

		foreach ($rates as $rate) {
			$split1 = explode('||', $rate); // Extracting shipping classes, if applicable
			$split2 = explode('|', $split1[0]);

			// Extracting shipping classes, if present
			if (count($split1) == 2) {
				$shipping_classes = array_map('intval', explode(';', $split1[1]));
			} else {
				$shipping_classes = null;
			}

			// Extracting country, if present
			if (preg_match("/^[a-z]{2}$/i", $split2[0])) {
				$rate_country = array_shift($split2);
				if ($rate_country == $country) {
					// We have at least one tariff specific to this country, so we'll ignore any generic tariff
					$country_specific_pricelist = true;
				}
			} else {
				$rate_country = null;
			}

			// Now we only have the weight and price characteristics, with two possible syntaxes:
			// min weight, max weight, price
			// max weight, price (min weight is implied by previous valid rate)
			if (count($split2) == 2) {
				$min_weight = null;
				$max_weight = (float) trim($split2[0]);
				$rate_price = (float) trim($split2[1]);
			} else {
				$min_weight = (float) trim($split2[0]);
				$max_weight = (float) trim($split2[1]);
				$rate_price = (float) trim($split2[2]);
			}

			// Saving rate in temporary array, awaiting further cleanup.
			$valid_rates[] = array($rate_country, $min_weight, $max_weight, $rate_price, $shipping_classes);
		}

		// Keeping only rates that match the applicable country
		$applicable_country = $country_specific_pricelist ? $country : null;
		$rates_for_country = array();
		foreach ($valid_rates as $rate) {
			if ($rate[0] == $applicable_country) {
				$rates_for_country[] = array('min_weight' => $rate[1], 'max_weight' => $rate[2], 'price' => $rate[3], 'shipping_classes' => $rate[4]);
			}
		}

		// Now we only have rates that we really want to return. But we must ensure that
		// all min-weights are set.
		// For this purpose, we need to first separate the rates based on their applicable shipping classes.
		$rates_by_shipping_class = array();
		$shipping_class_identifiers = array();
		foreach ($rates_for_country as $rate) {

			if ($rate['shipping_classes'] == null) {
				$shipping_class_identifiers[] = 'none';
				if (!array_key_exists('none', $rates_by_shipping_class))
					$rates_by_shipping_class['none'] = array();

				$rates_by_shipping_class['none'][] = $rate;
			} else {
				$class_identifier = join('-', $rate['shipping_classes']);
				$shipping_class_identifiers[] = $class_identifier;
				if (!array_key_exists($class_identifier, $rates_by_shipping_class))
					$rates_by_shipping_class[$class_identifier] = array();

				$rates_by_shipping_class[$class_identifier][] = $rate;
			}
		}

		// The data is organized. Now we parse all rate series (grouped by shipping class)
		// and add min-weight information wherever this is not already specified.
		$shipping_class_identifiers = array_unique($shipping_class_identifiers);

		foreach ($shipping_class_identifiers as $class_id) {
			$rates_for_class = $rates_by_shipping_class[$class_id];

			// Sorting by max weight
			$weights = array();
			foreach ($rates_for_class as $rate) {
				$weights[] = $rate['max_weight'];
			}
			array_multisort($weights, SORT_ASC, SORT_NUMERIC, $rates_for_class);

			$previous_weight = 0;
			foreach ($rates_for_class as $rate) {
				if ($rate['min_weight'] == null) {
					$rate['min_weight'] = $previous_weight;
				}
				// Now we register the rate in the dedicated instance attributes.
				// All rates now have a min weight, max weight, price, and shipping class IDs.
				$this->flat_rates[] = $rate;
				$previous_weight = $rate['max_weight'];
			}
		}
	}

	/**
	 * This is the form definition for the shipping method settings.
	 */
	public function init_form_fields()
	{
		$fields = array(
			'enabled' => array(
				'title' => __('Enable', 'my-flying-box'),
				'type' => 'checkbox',
				'label' => __('Enable this service', 'my-flying-box'),
				'default' => 'yes'
			),
			'carrier_logo' => array(
				'title' => __('Carrier logo', 'my-flying-box'),
				'type' => 'image_logo',
				'description' => __('Upload or select a logo', 'my-flying-box'),
				'desc_tip' => true,
			),
			'title' => array(
				'title' => __('Title', 'my-flying-box'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'my-flying-box'),
				'default' => $this->id,
				'desc_tip' => true,
			),
			'description' => array(
				'title' => __('Description', 'my-flying-box'),
				'type' => 'text',
				'description' => __('This controls the description of this shipping method, which the user sees during checkout.', 'my-flying-box'),
				'default' => '',
				'desc_tip' => true,
			),
			'tracking_url' => array(
				'title' => __('Tracking URL', 'my-flying-box'),
				'type' => 'text',
				'description' => __('Put the variables TRACKING_NUMBER and POSTAL_CODE (optional) in the URL, they will be automatically replaced with the real tracking number and the receiver postal code when generating the link.', 'my-flying-box'),
				'default' => '',
				'desc_tip' => true,
			),
			'min_weight' => array(
				'title' => __('Minimum weight', 'my-flying-box'),
				'type' => 'number',
				'description' => __('If total weight of the cart is below this value, the service will not be proposed.', 'my-flying-box'),
				'default' => '',
				'desc_tip' => true,
			),
			'max_weight' => array(
				'title' => __('Maximum weight', 'my-flying-box'),
				'type' => 'number',
				'description' => __('If total weight of the cart is above this value, the service will not be proposed.', 'my-flying-box'),
				'default' => '',
				'desc_tip' => true,
			),
			'min_order_price' => array(
				'title' => __('Minimum order price', 'my-flying-box'),
				'type' => 'number',
				'description' => __('If total price of the cart is below this value, the service will not be proposed.', 'my-flying-box'),
				'default' => '',
				'desc_tip' => true,
			),
			'max_order_price' => array(
				'title' => __('Maximum order price', 'my-flying-box'),
				'type' => 'number',
				'description' => __('If total price of the cart is above this value, the service will not be proposed.', 'my-flying-box'),
				'default' => '',
				'desc_tip' => true,
			),
			'shipping_price_modifier_static' => array(
				'title' => __('Price modifier (in cents)', 'my-flying-box'),
				'type' => 'number',
				'description' => __('IN CENTS! If set, will increment the shipping price by the specified amount. Put a negative value to decrease the price.', 'my-flying-box'),
				'default' => '',
				'desc_tip' => true,
			),
			'shipping_price_modifier_percent' => array(
				'title' => __('Price modifier (percentage)', 'my-flying-box'),
				'type' => 'number',
				'description' => __('A value of 20 will increase the price by 20%, a value value of -30 will decrease the price by 30%, a value of 100 will double the price.', 'my-flying-box'),
				'default' => '',
				'desc_tip' => true,
			),
			'shipping_price_rounding_increment' => array(
				'title' => __('Price rounding increment (in cents)', 'my-flying-box'),
				'type' => 'number',
				'description' => __('IN CENTS! e.g. 20 will round 13.23 to 13.40, 100 will round 15.13 to 16.00.', 'my-flying-box'),
				'default' => '',
				'desc_tip' => true,
			),
			'included_postcodes' => array(
				'title' => __('Included postcodes', 'my-flying-box'),
				'type' => 'textarea',
				'description' => __('Limits the service only to these postcodes. One rule per line (or same line and separated by a comma), using the following format: XX-YYYYY (XX = alpha2 country code, YYYYY = full postcode or prefix). Whitespaces will be ignored. You can also specify country codes without postcodes (just "XX"). Leave blank to apply no limitation.', 'my-flying-box'),
				'default' => '',
				'placeholder' => 'FR, ES-28...',
				'desc_tip' => true,
			),
			'excluded_postcodes' => array(
				'title' => __('Excluded postcodes', 'my-flying-box'),
				'type' => 'textarea',
				'description' => __('Excludes postcodes matching this list. One rule per line, using the following format: XX-YYYYY (XX = alpha2 country code, YYYYY = full postcode or just beginning). Whitespaces will be ignored. You can also specify country codes without postcodes (just "XX").', 'my-flying-box'),
				'default' => '',
				'placeholder' => 'FR-75, FR-974...',
				'desc_tip' => true,
			),
			'flatrate_pricing' => array(
				'title' => __('Flat-rate prices', 'my-flying-box'),
				'type' => 'checkbox',
				'label' => __('Check to enable pricing based on flat-rate pricing table, as defined below. When enabled, the prices returned by the API will not be used.', 'my-flying-box'),
				'default' => 'no'
			),
			// 'flatrate_prices' => array(
			// 	'title'       => __( 'Flat-rate prices', 'my-flying-box' ),
			// 	'type'        => 'hidden',
			// 	'description' => __( 'Prices are based on weight, so you can define as many rules as you want in the following format: Weight (up to, in kg, e.g. 6 or 7.5) | Price (float with dot as separator, e.g. 3.54). One rule per line.', 'my-flying-box' ),
			// 	'default'     => '',
			// 	'placeholder' => '6 | 3.5',
			// 	'desc_tip'    => true,
			// ),
			'flatrate_prices' => array(
				'title' => __('Flat-rate editor', 'my-flying-box'),
				'type' => 'flatrate_editor_html',
				'description' => __('Prices are based on weight, so you can define as many rules as you want in the following format: Weight (up to, in kg, e.g. 6 or 7.5) | Price (float with dot as separator, e.g. 3.54). One rule per line.', 'my-flying-box'),
				'desc_tip' => true,
			),
			// Règle d'exclusion
			'excluding_classes' => array(
				'title' => __('Exclusion rule', 'my-flying-box'),
				'type' => 'multiple_select',
				'description' => __('Multiple selection of shipping classes; if present in the order the delivery method will not be displayed', 'my-flying-box'),
				'desc_tip' => true,
			),
			'including_classes' => array(
				'title' => __('Inclusion rule', 'my-flying-box'),
				'type' => 'multiple_select',
				'description' => __('Multiple selection of shipping classes; the delivery method will only be shown if all items match at least one of the selected shipping classes', 'my-flying-box'),
				'desc_tip' => true,
			),
			'reduce_api_calls' => array(
				'title' => __('Reduce API calls', 'my-flying-box'),
				'type' => 'checkbox',
				'label' => __('Check to limit the number of API calls, improving performance of checkout process. WARNING: use only in conjunction with flatrate pricing (internal or from other extension) and an external method to determine whether this service should be available or not. As no request will be sent to the API, the module cannot determine whether the service is available or not for the destination, so you must use another mechanism for this!', 'my-flying-box'),
				'default' => 'no'
			),
			'force_dimensions_table' => array(
				'title' => __('Force dimensions table', 'my-flying-box'),
				'type' => 'checkbox',
				'label' => __('Check to force the use of the weight/dimensions table (defined in module settings) to determine the packlist, instead of using product dimensions even when available.', 'my-flying-box'),
				'default' => 'no'
			),
			'extended_cover' => array(
				'title' => __('Extended cover', 'my-flying-box'),
				'type' => 'checkbox',
				'label' => __('When checked, the price presented for shipping service will include the Extended cover option, if available.', 'my-flying-box'),
				'default' => 'no'
			),
		);
		$this->instance_form_fields = apply_filters('mfb_shipping_method_form_fields', $fields);
		$this->form_fields = apply_filters('mfb_shipping_method_form_fields', $fields);
	}

	/**
	 * calculate_shipping function.
	 */
	public function calculate_shipping($package = array())
	{
		// If this shipping method is not enabled, no need to proceed any further
		if (!$this->enabled)
			return false;

		// First exclusion test: only proceed if the total price of the cart is within
		// defined limitations.
		if ($this->max_order_price && WC()->cart->get_subtotal() > $this->max_order_price)
			return false;
		if ($this->min_order_price && WC()->cart->get_subtotal() < $this->min_order_price)
			return false;


		$recipient_city = (isset($package['destination']['city']) && !empty($package['destination']['city']) ? $package['destination']['city'] : '');
		$recipient_postal_code = (isset($package['destination']['postcode']) ? $package['destination']['postcode'] : '');
		$recipient_country = (isset($package['destination']['country']) ? $package['destination']['country'] : '');


		// We extract the list of shipping classes present in the cart. This may be needed when
		// using static rate definitions.
		$shipping_classes = array();
		foreach ($package['contents'] as $item) {
			$product = $item['data'];
			$shipping_class_id = $product->get_shipping_class();
			if ($shipping_class_id != 0) {
				$shipping_classes[] = $shipping_class_id;
			} else {
				$shipping_classes[] = null;
			}
		}
		$shipping_classes = array_unique($shipping_classes);

		// If this destination is not supported by this method, no need to go further either
		if (!$this->destination_supported($recipient_postal_code, $recipient_country))
			return false;

		if ($this->get_option('flatrate_pricing') == 'yes')
			$this->load_flat_rates($recipient_country);

		// Extracting total weight from the WC CART
		$total_weight = 0;
		$total_value = wc_format_decimal(0);
		$products = [];
		$cart_content = [];
		foreach (WC()->cart->get_cart() as $item_id => $values) {
			$product = $values['data'];
			if ($product->needs_shipping()) {
				// We will store the quantity for each product ID, so we can check later if
				// the quote is still valid for a given cart
				$cart_content[$product->get_id()] = $values['quantity'];

				$product_weight = $product->get_weight() ? wc_format_decimal(wc_get_weight($product->get_weight(), 'kg'), 2) : 0;
				$total_weight += ($product_weight * $values['quantity']);
				$total_value += (wc_format_decimal($product->get_price() * $values['quantity']));

				$products[] = ['id' => $product->get_id(), 'name' => $product->get_title(), 'price' => wc_format_decimal($product->get_price()), 'quantity' => $values['quantity'], 'weight' => $product->get_weight(), 'length' => $product->get_length(), 'width' => $product->get_width(), 'height' => $product->get_height()];
			}
		}

		// Now that we have the weight, we'll check that we can use this service
		if ($this->min_weight && $total_weight < $this->min_weight)
			return false;
		if ($this->max_weight && $total_weight > $this->max_weight)
			return false;

		// We force a minimum of 0.2 for the rest of the processing. Sending a parcel
		// with weight = 0 is simply not physically possible.
		if (0 == $total_weight)
			$total_weight = 0.2;

		// Loading existing quote, if available, so as not to send useless requests to the API
		$saved_quote_id = WC()->session->get('myflyingbox_shipment_quote_id');
		$quote_request_time = WC()->session->get('myflyingbox_shipment_quote_timestamp');

		if (is_numeric($saved_quote_id) && $quote_request_time && $quote_request_time == $_SERVER['REQUEST_TIME']) {
			// A quote was already requested in the same server request, we just load it
			$quote = MFB_Quote::get($saved_quote_id);
		} else if (
			is_numeric($saved_quote_id) &&
			$quote_request_time &&
			$quote_request_time != $_SERVER['REQUEST_TIME'] &&
			time() - $_SERVER['REQUEST_TIME'] < 600 &&
			$this->quote_still_valid($saved_quote_id, $package)
		) {
			// A quote was requested in a recent previous request, with the same parameters. We can use it as is.
			$quote = MFB_Quote::get($saved_quote_id);
		} else {
			// We don't have any existing valid quote

			// Removing any remains of old quote references
			WC()->session->set('myflyingbox_shipment_quote_id', null);
			WC()->session->set('myflyingbox_shipment_quote_timestamp', null);

			// In some cases, we can avoid calling the API altogether, improving performances.
			if ($this->get_option('reduce_api_calls') == 'yes' && $this->get_option('flatrate_pricing') == 'yes') {

				$price = $this->get_flatrate_price($total_weight, $shipping_classes);
				$price = $this->apply_price_modifiers($price);
				$rate = array(
					'id' => $this->get_rate_id(),
					'label' => $this->get_title(),
					'cost' => apply_filters('mfb_shipping_rate_price', $price)
				);
				$this->add_rate($rate);
				return true;
			}
			// If we continue to run here, that means we must get a quote from the API.

			// We prepare the parcels data based on products inside this shipment

			// OBSOLETE, see below
			// $include_insured_value = My_Flying_Box_Settings::get_option('mfb_insure_by_default') == 'yes';
			
			// Always include insured value in quote request, as it may impact the price even if we don't want
			// to insure by default, for instance when using extended cover.
			$include_insured_value = true;
			$parcels = MFB_Shipment::parcel_data_from_items($products, $include_insured_value);

			// We need destination info to be able to send a quote
			if (empty($recipient_postal_code) || empty($recipient_country))
				return false;

			// However, when having a postal code but no city, this should not block the quote request. In most cases the quote is based on postal code only anyway!
			if (empty($recipient_city) && !empty($recipient_postal_code))
				$recipient_city = 'N/A';

			// And then we build the quote request params' array
			$params = array(
				'shipper' => array(
					'city' => My_Flying_Box_Settings::get_option('mfb_shipper_city'),
					'postal_code' => My_Flying_Box_Settings::get_option('mfb_shipper_postal_code'),
					'country' => My_Flying_Box_Settings::get_option('mfb_shipper_country_code')
				),
				'recipient' => array(
					'city' => $recipient_city,
					'postal_code' => $recipient_postal_code,
					'country' => $recipient_country,
					'is_a_company' => false
				),
				'parcels' => $parcels
			);

			$api_quote = Lce\Resource\Quote::request($params);

			$quote = new MFB_Quote();
			$quote->api_quote_uuid = $api_quote->id;
			$quote->params = $params;
			$quote->cart_content = $cart_content;

			if ($quote->save()) {
				// Now we create the offers

				foreach ($api_quote->offers as $k => $api_offer) {
					$offer = new MFB_Offer();
					$offer->quote_id = $quote->id;
					$offer->api_offer_uuid = $api_offer->id;
					$offer->product_code = $api_offer->product->code;
					$offer->base_price_in_cents = $api_offer->price->amount_in_cents;
					$offer->total_price_in_cents = $api_offer->total_price->amount_in_cents;
					if ($api_offer->insurance_price) {
						$offer->insurance_price_in_cents = $api_offer->insurance_price->amount_in_cents;
					}
					$offer->currency = $api_offer->total_price->currency;
					//extended_cover
					$offer->extended_cover_available = $api_offer->extended_cover_available;
					if ($api_offer->extended_cover_available) {
						$offer->price_with_extended_cover = $api_offer->price_with_extended_cover->amount_in_cents;
						$offer->price_vat_with_extended_cover = $api_offer->price_vat_with_extended_cover->amount_in_cents;
						$offer->total_price_with_extended_cover = $api_offer->total_price_with_extended_cover->amount_in_cents;
						$offer->extended_cover_max_liability = $api_offer->extended_cover_max_liability->amount_in_cents;
					}
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
		WC()->session->set('myflyingbox_shipment_quote_id', $quote->id);
		WC()->session->set('myflyingbox_shipment_quote_timestamp', $_SERVER['REQUEST_TIME']);

		if (isset($quote->offers[$this->id])) {
			if (My_Flying_Box_Settings::get_option('mfb_use_total_price_with_vat') == 'yes') {
				$price = $quote->offers[$this->id]->total_price_in_cents / 100;
			} else {
				$price = $quote->offers[$this->id]->base_price_in_cents / 100;
			}
			//extended_cover
			if ($this->extended_cover == 'yes') {
				if (My_Flying_Box_Settings::get_option('mfb_use_total_price_with_vat') == 'yes') {
					$price = $quote->offers[$this->id]->total_price_with_extended_cover / 100;
				} else {
					$price = $quote->offers[$this->id]->price_with_extended_cover / 100;
				}
			}
			// Applying insurance cost if applicable
			if ($quote->offers[$this->id]->is_insurable() && My_Flying_Box_Settings::get_option('mfb_insure_by_default') == 'yes') {
				$price += ($quote->offers[$this->id]->insurance_price_in_cents / 100);
			}

			// Overriding the API price is we use flatrate pricing
			if ($this->get_option('flatrate_pricing') == 'yes') {
				$price = $this->get_flatrate_price($total_weight, $shipping_classes);
			}

			$price = $this->apply_price_modifiers($price);
			$price = apply_filters('mfb_shipping_rate_price', $price, $this->id);
			$rate_id_suffix = $this->extended_cover === 'yes' ? '_cover' : '_no_cover';
			$rate = array(
				'id' => $this->id . $rate_id_suffix,
				'label' => $this->get_title(),
				'cost' => $price,
				'taxes' => apply_filters('mfb_shipping_rate_taxes', '', $this->id, $price),
			);

			// A rate is valid if it is more than 0, unless we are using flatrate pricing,
			// where the merchant can specify a free shipping rate manually.
			$valid_rate = false;
			if (is_numeric($rate['cost'])) {
				if ($this->get_option('flatrate_pricing') == 'yes' && $rate['cost'] >= 0) {
					$valid_rate = true;
				} elseif ($rate['cost'] > 0) {
					$valid_rate = true;
				}
			}

			if ($valid_rate) {
				$this->add_rate($rate);
			}
		}
	}

	// Checking whether a quote is still valid for the given params
	private function quote_still_valid($quote_id, $params)
	{
		$quote = MFB_Quote::get($quote_id);
		# First, we check the destination
		$same_destination = false;
		if (
			$quote &&
			$quote->params['recipient']['city'] == $params['destination']['city'] &&
			$quote->params['recipient']['postal_code'] == $params['destination']['postcode'] &&
			$quote->params['recipient']['country'] == $params['destination']['country']
		) {
			$same_destination = true;
		}

		$same_content = true;
		if ($quote && $quote->cart_content) {
			$cart_content = array();
			foreach ($params['contents'] as $content) {
				if (!array_key_exists($content['product_id'], $quote->cart_content) || $quote->cart_content[$content['product_id']] !== $content['quantity']) {
					// We don't have a matching quantity for a product in the cart
					$same_content = false;
				}
			}
			// We don't have the same number of articles in the cart? Not valid for reuse.
			if (count($quote->cart_content) !== count($params['contents'])) {
				$same_content = false;
			}
		} else {
			// No cart data, quote is not considered valid for reuse.
			$same_content = false;
		}

		if ($same_destination && $same_content) {
			return true;
		} else {
			return false;
		}
	}

	// Modify the provided price value in accordance to the three price modifier settings
	// defined in the module settings by the merchant.
	private function apply_price_modifiers($price)
	{

		if (!is_numeric($price))
			return $price;

		$modifier_amount = (int) $this->get_option('shipping_price_modifier_static');
		$modifier_percent = (int) $this->get_option('shipping_price_modifier_percent');
		$rounding_increment = (int) $this->get_option('shipping_price_rounding_increment');

		// First, we apply the static surcharge
		if (is_int($modifier_amount) && $modifier_amount != 0) {
			$price += ($modifier_amount / 100); // $modifier_amount is in cents
		}

		// Then we apply the percentage surcharge
		if (is_int($modifier_percent) && $modifier_percent != 0) {
			$price *= (1 + ($modifier_percent / 100));
		}

		// Finally, we round the price to the nearest increment
		if (is_int($rounding_increment) && $rounding_increment > 0) {
			$rounding_increment = 1 / ($rounding_increment / 100);
			$price = ceil($price * $rounding_increment) / $rounding_increment;
		}

		return $price;
	}


	// Returns the matching rate for a given cart weight and all applicable shipping classes.
	// Only one rate will be returned: the most expensive.
	// Note: if more than one shipping class is passed, a rate must be found for every
	// shipping class provided. If that is not the case, no rate will be returned.
	// In case several rates match (default rules, distinct rates for different classes),
	// only the most expensive will be returned.
	private function get_flatrate_price($weight, $shipping_classes)
	{
		$matching_rates = array();

		if (empty($shipping_classes) || count($shipping_classes) == 0) {
			// When having no shipping classes, we will only consider rates without shipping class definition.
			foreach ($this->flat_rates as $rate) {
				if ($weight >= $rate['min_weight'] && $weight < $rate['max_weight'] && $rate['shipping_classes'] == null) {
					$matching_rates[] = $rate['price'];
				}
			}
		} else {
			// When having shipping classes, we must find at least one rate per shipping class.
			// If we have at least one rate per shipping class, we will return the highest one.
			foreach ($shipping_classes as $shipping_class) {
				$rate_found = false;
				foreach ($this->flat_rates as $rate) {
					if (($rate['shipping_classes'] == null || in_array($shipping_class, $rate['shipping_classes'])) && $weight >= $rate['min_weight'] && $weight < $rate['max_weight']) {
						$rate_found = true;
						$matching_rates[] = $rate['price'];
					}
				}
				// If the current shipping class has no matching rate, we will no return any price.
				if ($rate_found === false)
					return false;
			}
		}
		// Now we have an array of matching rates (can be empty).
		// We will return the highest rate registered:
		if (count($matching_rates) == 0) {
			return false;
		} else {
			return max($matching_rates);
		}
	}

	// Controls whether this method should be proposed or not
	public function is_available($package)
	{
		$excluded_classes = array_filter(explode(',', $this->get_option('excluding_classes', '')));
		$included_classes = array_filter(explode(',', $this->get_option('including_classes', '')));

		// Retrieve all shipping classes of products in the cart
		$cart_shipping_classes = [];
		foreach ($package['contents'] as $item) {
			$product = $item['data'];
			$class_id = $product->get_shipping_class_id();
			if ($class_id) {
				$cart_shipping_classes[] = $product->get_shipping_class();
			}
		}
		$cart_shipping_classes = array_unique($cart_shipping_classes);

		// --- Application of the rules ---

		// 1) Exclusion : si un produit a une classe exclue, méthode non disponible
		if (!empty($cart_shipping_classes)) {
			foreach ($cart_shipping_classes as $class) {
				if (in_array($class, $excluded_classes, true)) {
					return false; // Méthode non disponible
				}
			}
		}

		// 2) Inclusion : si la liste d'inclusion est vide, on ne filtre pas
		if (!empty($included_classes)) {
			// Ici, on veut que *tous* les produits aient une classe dans la liste d'inclusion
			if (!empty($cart_shipping_classes)) {
				foreach ($cart_shipping_classes as $class) {
					if (!in_array($class, $included_classes, true)) {
						return false; // Un produit n'est pas dans les classes incluses → méthode non dispo
					}
				}
			} else {
				return false;
			}
		}

		return apply_filters('mfb_shipping_method_available', $this->enabled, $package, $this->id);
	}

	public function destination_supported($postal_code, $country)
	{
		// Result tag; by default, all destinations are supported.
		$supported = true;

		// First, checking if inclusion restrictions apply
		if (count($this->included_destinations) > 0) {
			if (!array_key_exists($country, $this->included_destinations)) {
				// The country is not included, we can get out now.
				$supported = false;
			} else {
				// Country is included, let's check the postcodes, if necessary
				if (count($this->included_destinations[$country]) > 0) {
					$included = false;
					foreach ($this->included_destinations[$country] as $included_postcode) {
						if (strrpos($postal_code, $included_postcode, -strlen($postal_code)) !== FALSE) { // This code checks whether postal code starts with the characters from excluded_postcode
							$included = true;
						}
					}
					if (!$included)
						$supported = false; // The postal code was not included in any way, so we just get out.
				}
			}
		}

		// If arrive here, it means that either there is no inclusion restriction, or we have passed
		// all inclusion rules.
		// We now check that there is no applicable exclusion rule.
		// If any exclusion rule matches, we return false.
		if (count($this->excluded_destinations) > 0) {
			if (array_key_exists($country, $this->excluded_destinations)) {
				// The country has some exclusion rules, let's check the postcodes, if necessary
				if (count($this->excluded_destinations[$country]) > 0) {
					foreach ($this->excluded_destinations[$country] as $excluded_postcode) {
						// Checks whether postal code starts with the characters from excluded_postcode
						// If any match, we return false.
						if (strrpos($postal_code, $excluded_postcode, -strlen($postal_code)) !== FALSE) {
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
		return apply_filters('mfb_shipping_method_destination_supported', $supported);
	}

	public function generate_multiple_select_html($key, $field)
	{
		ob_start();

		$value = $this->get_option($key, isset($field['default']) ? $field['default'] : '');
		$selected_values = array_map('trim', explode(',', $value));

		$shipping_classes = WC()->shipping()->get_shipping_classes();

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html($field['title']); ?></label>
				<?php if (trim($field['description'])): ?>
					<div class="wc-shipping-zone-method-fields-help-text"><?php echo $field['description']; ?></div>
				<?php endif; ?>
			</th>
			<td class="forminp forminp-select">
				<select multiple name="<?php echo esc_attr($this->get_field_key($key)); ?>[]"
					style="min-width: 300px; height: 150px;display:block;clear:both;width:100%;margin-bottom:25px;">
					<?php foreach ($shipping_classes as $class): ?>
						<option value="<?php echo esc_attr($class->slug); ?>" <?php selected(in_array($class->slug, $selected_values), true); ?>>
							<?php echo esc_html($class->name); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	public function validate_multiple_select_field($key, $value)
	{
		if (is_array($value)) {
			$value = implode(',', array_map('sanitize_text_field', $value));
		} else {
			$value = sanitize_text_field($value);
		}
		return $value;
	}

	public function generate_flatrate_editor_html_html($key, $field)
	{
		ob_start();
		$input_name = esc_attr($this->get_field_key($key));
		$value = $this->get_option($key, isset($field['default']) ? $field['default'] : '');

		if ((trim($value) == "|") || !trim($value)) {
			$existing_rules = [];
			$value = "";
		} else {
			$existing_rules = explode(PHP_EOL, str_replace(',', PHP_EOL, $value));
		}
		$shipping_classes = WC()->shipping()->get_shipping_classes();
		?>

		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html($field['title']); ?></label>
				<div class="wc-shipping-zone-method-fields-help-text">
					<?php if (trim($field['description']))
						echo $field['description']; ?>
				</div>
			</th>
			<td class="forminp">
				<?php echo sprintf('<textarea name="%s" id="flat-rate-data" style="display:none;">%s</textarea>', $input_name, esc_textarea($value)); ?>

				<table id="flat-rate-editor-table" class="widefat striped" style="width:auto;">
					<thead>
						<tr>
							<th><?php _e('Country', 'my-flying-box'); ?></th>
							<th><?php _e('Min weight', 'my-flying-box'); ?></th>
							<th><?php _e('Max weight', 'my-flying-box'); ?></th>
							<th><?php _e('Price', 'my-flying-box'); ?></th>
							<th><?php _e('Shipping classes', 'my-flying-box'); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="flat-rate-rows">
						<?php if (empty(array_filter($existing_rules))): ?>
							<tr class="flat-rate-row">
								<td><input type="text" class="country" value="" style="width:60px;"></td>
								<td><input type="text" class="min-weight" value="" style="width:60px;"></td>
								<td><input type="text" class="max-weight" value="" style="width:60px;"></td>
								<td><input type="text" class="price" value="" style="width:60px;"></td>
								<td>
									<select class="shipping-class" multiple style="min-width:150px;">
										<?php foreach ($shipping_classes as $class): ?>
											<option value="<?php echo esc_attr($class->term_id); ?>">
												<?php echo esc_html($class->name); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><button type="button" class="button remove">×</button></td>
							</tr>
						<?php else: ?>
							<?php foreach ($existing_rules as $line): ?>
								<?php
								if (empty($line))
									continue;
								if (trim($line) == "|" || trim($line) == "||")
									continue;
								if (!trim($line))
									continue;
								$parts = explode('||', $line);
								$main = $parts[0];
								$shipping_ids = isset($parts[1]) ? explode(';', $parts[1]) : [];
								$fields = explode('|', $main);
								if (count($fields) == 2) {
									$country = '';
									$min_weight = '';
									$max_weight = (float) trim($fields[0]);
									$price = (float) trim($fields[1]);
								} elseif (count($fields) == 3) {
									if(preg_match('/^[a-zA-Z]{2}$/', $fields[0])) {
										$country = $fields[0];
										$min_weight = '';
									} else {
										$country = '';
										$min_weight = (float) trim($fields[0]);
									}
									$max_weight = (float) trim($fields[1]);
									$price = (float) trim($fields[2]);
								} else {
									$country = $fields[0] ?? '';
									$min_weight = $fields[1] ?? '';
									$max_weight = $fields[2] ?? '';
									$price = $fields[3] ?? '';
								}
								?>
								<tr class="flat-rate-row">
									<td><input type="text" class="country" value="<?php echo esc_attr($country); ?>"
											style="width:60px;"></td>
									<td><input type="text" class="min-weight" value="<?php echo esc_attr($min_weight); ?>"
											style="width:60px;"></td>
									<td><input type="text" class="max-weight" value="<?php echo esc_attr($max_weight); ?>"
											style="width:60px;"></td>
									<td><input type="text" class="price" value="<?php echo esc_attr($price); ?>" style="width:60px;">
									</td>
									<td>
										<select class="shipping-class" multiple style="min-width:150px;">
											<?php foreach ($shipping_classes as $class): ?>
												<option value="<?php echo esc_attr($class->term_id); ?>" <?php echo (in_array($class->term_id, $shipping_ids)) ? 'selected' : ''; ?>>
													<?php echo esc_html($class->name); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><button type="button" class="button remove">×</button></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<p><button type="button" class="button" id="add-rate"><?php _e('Add new rate', 'my-flying-box'); ?></button></p>

				<script>
					jQuery(function ($) {
						function validateFields(__this) {
							const codePattern = /^[A-Z]{0,2}$/; // vide autorisé ou jusqu'à 2 lettres majuscules
							const decimalPattern = /^[0-9]*\.?[0-9]*$/; // nombre décimal autorisé

							// --- Pays ---
							jQuery(__this).find('.country').on('input', function () {
								let val = jQuery(this).val().toUpperCase();
								if (!codePattern.test(val)) {
									val = val.replace(/[^A-Z]/g, '').substr(0, 2);
								}
								jQuery(this).val(val);
							});

							// --- Poids min, max et prix ---
							jQuery(__this).find('.min-weight, .max-weight, .price').on('input', function () {
								let val = jQuery(this).val();
								if (!decimalPattern.test(val)) {
									val = val.replace(/[^0-9.]/g, ''); // autoriser uniquement chiffres et point
								}
								jQuery(this).val(val);
							});

							// Optionnel : à la sortie du champ, vérifier si c'est vide ou correct
							jQuery(__this).find('.country').on('blur', function () {
								if (jQuery(this).val() && jQuery(this).val().length !== 2) {
									alert("Code pays invalide (2 lettres majuscules).");
									jQuery(this).val('');
								}
							});
							jQuery(__this).find('.min-weight, .max-weight, .price').on('blur', function () {
								if (jQuery(this).val() && isNaN(parseFloat(jQuery(this).val()))) {
									alert("Valeur invalide (doit être un nombre).");
									jQuery(this).val('');
								}
							});
						}
						function updateEncoded() {
							let rules = [];
							const codePattern = /^[A-Z]{2}$/;
							if (jQuery('#flat-rate-rows .flat-rate-row').length) {
								jQuery('#flat-rate-rows .flat-rate-row').each(function () {
									validateFields(this);
									let country = jQuery(this).find('.country').val().trim();
									let min = jQuery(this).find('.min-weight').val().trim();
									let max = jQuery(this).find('.max-weight').val().trim();
									let price = jQuery(this).find('.price').val().trim();
									let classes = jQuery(this).find('.shipping-class').val() || [];

									let rule = '';

									if (country) {
										rule += country + '|';
									} else {
										rule += '';
									}

									if (min) {
										rule += min + '|';
									} else {
										rule += '';
									}

									rule += max + '|' + price;

									if (classes.length > 0) {
										rule += '||' + classes.join(';');
									}

									rules.push(rule);
								});
							}
							let _val = rules.join("\n");
							if (_val == "|" || _val == "||") {
								_val = "";
							}
							jQuery('#flat-rate-data').val(_val);
						}

						$('#add-rate').on('click', function () {
							let newRow = $('#flat-rate-rows tr:first').clone();
							$('input, select', newRow).val('');
							$('#flat-rate-rows').append(newRow);
						});

						$('#flat-rate-rows').on('click', '.remove', function () {
							let $rows = $('#flat-rate-rows tr');
							let $currentRow = $(this).closest('tr');

							if ($rows.length > 1) {
								$currentRow.remove();
							} else {
								$currentRow.find('input').val('');
								$currentRow.find('select').val([]);
							}

							updateEncoded();
						});

						$('#flat-rate-rows').on('change', 'input, select', updateEncoded);
						updateEncoded();
					});
				</script>
			</td>
		</tr>

		<?php
		return ob_get_clean();
	}

	public function get_logo()
	{
		return $this->get_option("carrier_logo");
	}

	public function generate_image_logo_html($key, $field)
	{
		ob_start();

		$option_key = $this->get_field_key($key);
		$value = $this->get_option($key);
		$image_src = $value ? esc_url($value) : wc_placeholder_img_src();

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr($option_key); ?>"><?php echo esc_html($field['title']); ?></label>
				<?php if (!empty($field['desc_tip']))
					echo wc_help_tip($field['description']); ?>
			</th>
			<td class="forminp forminp-image_logo">
				<img id="<?php echo esc_attr($option_key); ?>_preview" src="<?php echo $image_src; ?>"
					style="max-width:100px; height:auto; display:block; margin-bottom:10px;" />
				<input type="hidden" name="<?php echo esc_attr($option_key); ?>" id="<?php echo esc_attr($option_key); ?>"
					value="<?php echo esc_attr($value); ?>" />
				<button type="button" class="button upload_image_button"
					data-target="<?php echo esc_attr($option_key); ?>"><?php _e('Select image', 'my-flying-box'); ?></button>
				<div style="width:100%;display:block;margin-bottom:25px;"></div>
			</td>
		</tr>

		<script>
			jQuery(function ($) {
				$('.upload_image_button').off('click').on('click', function (e) {
					e.preventDefault();
					var button = $(this);
					var target = $('#' + button.data('target'));
					var preview = $('#' + button.data('target') + '_preview');

					var frame = wp.media({
						title: '<?php _e('Select or upload a logo', 'my-flying-box'); ?>',
						button: {
							text: '<?php _e('Use this image', 'my-flying-box'); ?>'
						},
						multiple: false
					});

					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						target.val(attachment.url).change();
						preview.attr('src', attachment.url);
					});

					frame.open();
				});
			});
		</script>
		<?php

		return ob_get_clean();
	}

	// public function get_title() {
	// 	if (!is_admin()) {
	// 		$logo = $this->get_option("carrier_logo");
	// 		if (!trim($logo) || !filter_var($logo, FILTER_VALIDATE_URL)) {
	// 			return $this->title;
	// 		}
	// 		return '<span class="mfb-shipping-title" data-logo="' . esc_url($logo) . '"></span>' . esc_html($this->title);
	// 	}
	// 	return apply_filters( 'woocommerce_shipping_method_title', $this->title, $this->id );
	// }
}
