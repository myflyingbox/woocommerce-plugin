<?php

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * AJAX Event Handler
 */

class MFB_AJAX
{

	public static function init()
	{

		// mfb_EVENT => nopriv
		// nopriv = true => user-facing request
		$ajax_events = array(
			'get_delivery_locations'                          => true,
			'create_shipment'                                 => false,
			'create_return_shipment'                          => false,
			'book_offer'                                      => false,
			'delete_shipment'                                 => false,
			'delete_bulk_order'                               => false,
			'update_shipper'                                  => false,
			'update_recipient'                                => false,
			'add_parcel'                                      => false,
			'edit_parcel'                                     => false,
			'update_parcel'                                   => false,
			'delete_parcel'                                   => false,
			'update_selected_offer'                           => false,
			'download_labels'                                 => false,
			'check_status'                                 => false,
			'update_extended_cover_offer'                     => false
		); //extended_cover
		foreach ($ajax_events as $ajax_event => $nopriv) {
			add_action('wp_ajax_mfb_' . $ajax_event, array(__CLASS__, $ajax_event));

			if ($nopriv) {
				add_action('wp_ajax_nopriv_mfb_' . $ajax_event, array(__CLASS__, $ajax_event));
			}
		}
	}

	public static function get_delivery_locations()
	{

		if (empty($k)) {
			//
			// We have no offer uuid. We need to request a quote to get the delivery locations!

			$method_name = $_REQUEST['s'];
			$instance_id = $_REQUEST['i'];

			if (! class_exists($method_name)) {
				eval("class $method_name extends MFB_Shipping_Method{}");
			}
			$shipping_method = new $method_name($instance_id);

			// Extracting total weight from the WC CART
			$weight = 0;
			$dimensions_available = true;
			$products = [];
			foreach (WC()->cart->get_cart() as $item_id => $values) {
				$product = $values['data'];
				if ($product->needs_shipping()) {
					$product_weight = $product->get_weight() ? wc_format_decimal(wc_get_weight($product->get_weight(), 'kg'), 2) : 0;
					$weight += ($product_weight * $values['quantity']);
					if ($product->get_length() > 0 && $product->get_width() > 0 && $product->get_height() > 0) {
						$products[] = ['name' => $product->get_title(), 'price' => wc_format_decimal($product->get_price()), 'quantity' => $values['quantity'], 'weight' => $product->get_weight(), 'length' => $product->get_length(), 'width' => $product->get_width(), 'height' => $product->get_height()];
					} else {
						$dimensions_available = false;
					}
				}
			}

			if (0 == $weight)
				$weight = 0.2;


			// We prepare the parcels data depending on whether or not we have product dimensions
			$parcels = [];
			if ($dimensions_available && !$shipping_method->force_dimensions_table) {
				foreach ($products as $product) {
					for ($i = 1; $i <= $product['quantity']; $i++) {
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
				$dims = MFB_Dimension::get_for_weight($weight);

				# We should use weight/dimensions correspondance; if we don't have any, we can't get a tariff...
				if (!$dims) return false;

				$parcel['length']            = $dims->length;
				$parcel['width']             = $dims->width;
				$parcel['height']            = $dims->height;
				$parcel['weight']            = $weight;
				$parcels[] = $parcel;
			}

			// And then we build the quote request params' array
			if (isset($_REQUEST['address'])) {
				$address = $_REQUEST['address'];
				$recipient_city = sanitize_text_field($address['city']);
				$recipient_postal_code = sanitize_text_field($address['postcode']);
				$recipient_country = sanitize_text_field($address['country']);
				$recipient_company_name = "";
			} else {
				$recipient_city = (isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1) ? $_REQUEST['shipping_city'] : $_REQUEST['billing_city'];
				$recipient_postal_code = (isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1) ? $_REQUEST['shipping_postcode'] : $_REQUEST['billing_postcode'];
				$recipient_country = (isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1) ? $_REQUEST['shipping_country'] : $_REQUEST['billing_country'];
				$recipient_company_name = (isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1) ? $_REQUEST['shipping_company'] : $_REQUEST['billing_company'];
			}
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
					'is_a_company' => !empty($recipient_company_name)
				),
				'parcels' => $parcels
			);

			if (
				empty($params['recipient']['city']) ||
				empty($params['recipient']['country'])
			) {

				$response['data'] = 'error';
				$response['message'] = 'Please fill in all address fields before loading the delivery location selector';

				wp_send_json($response);
				die();
			}

			// Loading existing quote, if available, so as not to send useless requests to the API
			$saved_quote_id = WC()->session->get('myflyingbox_shipment_quote_id');
			$quote_request_time = WC()->session->get('myflyingbox_shipment_quote_timestamp');

			if (
				is_numeric($saved_quote_id) &&
				$quote_request_time &&
				time() - $_SERVER['REQUEST_TIME'] < 600
			) {

				$quote = MFB_Quote::get($saved_quote_id);

				if (
					$quote->params['recipient']['city']        !=  $params['recipient']['city'] ||
					$quote->params['recipient']['postal_code'] !=  $params['recipient']['postal_code'] ||
					$quote->params['recipient']['country']     !=  $params['recipient']['country']
				) {
					$quote = null;
				}
			}

			if (! $quote) {

				$api_quote = Lce\Resource\Quote::request($params);

				$quote = new MFB_Quote();
				$quote->api_quote_uuid = $api_quote->id;
				$quote->params         = $params;

				if ($quote->save()) {
					// Now we create the offers

					foreach ($api_quote->offers as $k => $api_offer) {
						$offer = new MFB_Offer();
						$offer->quote_id = $quote->id;
						$offer->api_offer_uuid = $api_offer->id;
						$offer->product_code = $api_offer->product->code;
						$offer->base_price_in_cents = $api_offer->price->amount_in_cents;
						$offer->total_price_in_cents = $api_offer->total_price->amount_in_cents;
						$offer->currency = $api_offer->total_price->currency;
						//extended_cover
						$offer->extended_cover_available = $api_offer->extended_cover_available;
						$offer->price_with_extended_cover = $api_offer->price_with_extended_cover->amount_in_cents;
						$offer->price_vat_with_extended_cover = $api_offer->price_vat_with_extended_cover->amount_in_cents;
						$offer->total_price_with_extended_cover = $api_offer->total_price_with_extended_cover->amount_in_cents;
						$offer->extended_cover_max_liability = $api_offer->extended_cover_max_liability->amount_in_cents;

						$offer->save();
					}
				}
				// Refreshing the quote, to get the offers loaded properly
				$quote->populate();

				WC()->session->set('myflyingbox_shipment_quote_id', $quote->id);
				WC()->session->set('myflyingbox_shipment_quote_timestamp', $_SERVER['REQUEST_TIME']);
			}
			$offer = $quote->offers[$_REQUEST['s']];
		} else {
			// Getting Offer from transmitted uuid:
			$offer = MFB_Offer::get_by_uuid($_REQUEST['k']);
		}
		if (isset($_REQUEST['address'])) {
			$address = $_REQUEST['address'];
			$street = sanitize_text_field($address['address_1']);
			$street_line_2 = sanitize_text_field($address['address_2']);
		} else {
			$street = isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1 ? $_REQUEST['shipping_address_1'] : $_REQUEST['billing_address_1'];
			$street_line_2 = isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1 ? $_REQUEST['shipping_address_2'] : $_REQUEST['billing_address_2'];
		}
		if (! empty($street_line_2)) {
			$street .= "\n" . $street_line_2;
		}

		$params = array(
			'street' => $street,
			'city' => $quote->params['recipient']['city']
		);

		// Building the response
		$response = array();

		$locations = $offer->get_delivery_locations($params);

		if (! empty($locations)) {

			$response['data'] = 'success';
			$response['locations'] = $locations;
		} else {
			$response['data'] = 'error';
			$response['message'] = 'Failed to load locations';
		}

		// Whatever the outcome, send the Response back
		wp_send_json($response);
	}

	public static function create_shipment()
	{

		// We create a ready-to-confirm shipment object based on existing order
		$order_id = intval($_POST['order_id']);
		$order = new WC_Order($order_id);
		$shipment = MFB_Shipment::create_from_order($order);
		$response['data'] = 'success';
		$response['shipment'] = $shipment;

		// Whatever the outcome, send the Response back
		wp_send_json($response);
	}

	public static function create_return_shipment()
	{
		// We create a ready-to-confirm shipment object based on existing order
		$origin_shipment_id = intval($_POST['shipment_id']);

		// Second parameter means we want a return shipment
		$shipment = MFB_Shipment::create_from_shipment($origin_shipment_id, true);
		$response['data'] = 'success';
		$response['shipment'] = $shipment;

		// Whatever the outcome, send the Response back
		wp_send_json($response);
	}

	public static function book_offer()
	{

		// We create a ready-to-confirm shipment object based on existing order
		$shipment_id = intval($_POST['shipment_id']);
		$shipment = MFB_Shipment::get($shipment_id);

		if ($shipment->status != 'mfb-draft') die();

		$offer_id = intval($_POST['offer_id']);
		$offer = MFB_Offer::get($offer_id);

		$error = false;

		if ($offer && $shipment->quote->offers[$offer->product_code]) {
			$shipment->offer = $offer;
			if ($offer->pickup) {
				$shipment->collection_date = $_POST['pickup_date'];
			}
			if ($offer->relay) {
				$shipment->delivery_location_code = $_POST['relay_code'];
			}
			if ($_POST['insurance'] == '1') {
				$shipment->insured = true;
			} else {
				$shipment->insured = false;
			}
			if ($_POST['extended_cover'] == '1') {
				$shipment->extended_covered = true;
			} else {
				$shipment->extended_covered = false;
			}
			$shipment->save();
			try {
				$shipment->place_booking();
			} catch (Lce\Exception\LceException $e) {
				$error = true;
				$error_message = $e->getMessage();
			}
		} else {
			$error = true;
			$error_message = 'Selected offer does not match any available offer in the quotation';
		}

		if (! $error) {
			$response['data'] = 'success';
		} else {
			$response['data'] = 'error';
			$response['message'] = $error_message;
		}

		// Whatever the outcome, send the Response back
		if ($error) {
			wp_send_json_error($response);
		} else {
			wp_send_json_success($response);
		}
	}


	public static function delete_shipment()
	{

		// We create a ready-to-confirm shipment object based on existing order
		$shipment_id = intval($_POST['shipment_id']);
		$shipment = MFB_Shipment::get($shipment_id);

		if ($shipment->status != 'mfb-draft') die();

		$res = $shipment->destroy();

		if ($res) {
			$response['data'] = 'success';
			$response['shipment'] = $shipment;
		} else {
			$response['data'] = 'error';
		}

		// Whatever the outcome, send the Response back
		wp_send_json($response);
	}

	public static function update_extended_cover_offer()
	{
		$with_extended_cover = intval($_POST['with_extended_cover']);
		$offer_id = intval($_POST['offer_id']);
		$offer = MFB_Offer::get($offer_id);
		if ($with_extended_cover) {
			$updated_price = $offer->formatted_extended_cover_price();
		} else {
			$updated_price = "";
		}
		if ($offer) {
			$response['success'] = 'true';
			$response['new_label'] = $updated_price;
		} else {
			$response['success'] = 'false';
		}
		wp_send_json($response);
	}

	public static function check_status()
	{
		$order_id = intval($_POST['wc_order_id']);
		$shipment_id = intval($_POST['offer_id']);
		$api_order_uuid = get_post_meta($shipment_id, '_api_uuid', true);
		$reload = false;
		try {
			$response = Lce\Resource\Order::track($api_order_uuid);
			$last_event = "";
			if (!empty($response) && isset($response[0])) {
				$locale = get_locale();
				$lang = substr($locale, 0, 2);
				if (!trim($lang)) {
					$lang = "fr";
				}
				$tracking = $response[0];
				if (!empty($tracking->events)) {
					$latest_event = null;

					foreach ($tracking->events as $event) {
						if (
							is_null($latest_event) ||
							strtotime($event->happened_at) > strtotime($latest_event->happened_at)
						) {
							$latest_event = $event;
						}
					}

					if ($latest_event) {
						$last_event .= '<strong>' . __('Last status :', 'my-flying-box') . '</strong><br>';
						$last_event .= '<label>' . __('Code :', 'my-flying-box') . '</label> ' . esc_html($latest_event->code) . '<br>';
						$last_event .= '<label>' . __('Label :', 'my-flying-box') . '</label> ' . esc_html($latest_event->label->{$lang}) . '<br>';
						$last_event .= '<label>' . __('Date :', 'my-flying-box') . '</label> ' . date('d/m/Y H:i', strtotime($latest_event->happened_at)) . '<br>';

						if (!empty($latest_event->location)) {
							$loc = $latest_event->location;
							$last_event .= '<label>' . __('Place :', 'my-flying-box') . '</label>';
							$last_event .= " ";
							if (!empty($loc->name)) $last_event .= esc_html($loc->name) . ', ';
							if (!empty($loc->street)) $last_event .= esc_html($loc->street) . ', ';
							if (!empty($loc->postal_code)) $last_event .= esc_html($loc->postal_code) . ' ';
							if (!empty($loc->city)) $last_event .= esc_html($loc->city) . ', ';
							$last_event .= esc_html($loc->country ?? '');
						}

						if (isset($latest_event->code) &&  $latest_event->code == "delivered") {
							update_post_meta($shipment_id, '_delivered', 1);
						} else {
							if (metadata_exists('post', $shipment_id, '_delivered')) {
								delete_post_meta($shipment_id, '_delivered');
							}
						}
					}
				}
			}
			//set status order if all delivered
			if ($order_id) {
				$all_shipments = get_children(array(
					'post_type'			=> 'mfb_shipment',
					'post_status'		=> array('private', 'mfb-draft', 'mfb-booked'),
					'post_parent'		=> $order_id,
					'field'					=> 'ids',
					'orderby'				=> array('date' => 'DESC')
				));

				$all_delivered = true;
				if (!empty($all_shipments)) {
					foreach ($all_shipments as $shipment) {
						$event_data = get_post_meta($shipment->ID, '_delivered', true);
						if ($all_delivered && !$event_data) {
							$all_delivered = false;
							break;
						}
					}
				} else {
					$all_delivered = false;
				}
				if ($all_delivered) {
					$order = wc_get_order($order_id);
					if ( $order && $order->get_status() !== 'completed' ) {
						$order->set_status("completed");
						$order->save();
						$order->add_order_note(__('Status automatically updated by MFB shipment status.', 'my-flying-box'));
						$reload = true;
					}
				}
			}
			$status = $last_event;
		} catch (Lce\Exception\LceException $e) {
			error_log(print_r($e->getMessage(), true));
			$status = "";
		}
		if ($status) {
			$response['success'] = 'true';
			$response['status'] = $status;
			$response['all_delivered'] = $reload;
		} else {
			$response['success'] = 'false';
		}
		wp_send_json($response);
	}

	// public static function check_status()
	// {
	// 	$api_order_uuid = $_POST['apiuid'];
	// 	$wc_order_id = intval($_POST['wc_order_id']);
	// 	$lang = $_POST['lang'];
	// 	$front = (bool) $_POST['front'];
	// 	try {
	// 		$response = Lce\Resource\Order::track($api_order_uuid);
	// 		$last_event = "";
	// 		if(!empty($response) && isset($response[0])) {
	// 			$locale = get_locale();
	// 			$lang = substr($locale, 0, 2);
	// 			if(!trim($lang)) {
	// 				$lang = "fr";
	// 			}
	// 			$tracking = $response[0];
	// 			if (!empty($tracking->events)) {
	// 				$latest_event = null;

	// 				foreach ($tracking->events as $event) {
	// 					if (
	// 						is_null($latest_event) ||
	// 						strtotime($event->happened_at) > strtotime($latest_event->happened_at)
	// 					) {
	// 						$latest_event = $event;
	// 					}
	// 				}

	// 				if ($latest_event) {
	// 					if($front) {
	// 						$last_event .= '<h2>'.__("Shipment Status", 'my-flying-box').'</h2>';
	// 					} else {
	// 						$last_event .= '<strong>'.__('Last status :', 'my-flying-box').'</strong><br>';
	// 					}
	// 					$last_event .= '<label>'.__('Code :', 'my-flying-box') . '</label> ' . esc_html($latest_event->code) . '<br>';
	// 					$last_event .= '<label>'.__('Label :', 'my-flying-box') . '</label> ' . esc_html($latest_event->label->{$lang}) . '<br>';
	// 					$last_event .= '<label>'.__('Date :', 'my-flying-box') . '</label> ' . date('d/m/Y H:i', strtotime($latest_event->happened_at)) . '<br>';

	// 					if (!empty($latest_event->location)) {
	// 						$loc = $latest_event->location;
	// 						$last_event .= '<label>'.__('Place :', 'my-flying-box') . '</label>';
	// 						$last_event .= " ";
	// 						if (!empty($loc->name)) $last_event .= esc_html($loc->name) . ', ';
	// 						if (!empty($loc->street)) $last_event .= esc_html($loc->street) . ', ';
	// 						if (!empty($loc->postal_code)) $last_event .= esc_html($loc->postal_code) . ' ';
	// 						if (!empty($loc->city)) $last_event .= esc_html($loc->city) . ', ';
	// 						$last_event .= esc_html($loc->country ?? '');
	// 					}
	// 					$order = wc_get_order($wc_order_id);
	// 					if($wc_order_id) {
	// 						if(!$front) {
	// 						$links = MFB()->get_tracking_links( $order );
	// 							if ( count($links) > 0) {
	// 								$last_event .= '<br><br><strong>'.__( "Track your shipment", 'my-flying-box' ).'</strong>';
	// 								if ( count($links) > 1 ) {
	// 									$last_event .= '<p>'.__( "Direct links to track your shipments:", 'my-flying-box' );
	// 								} else {
	// 									$last_event .= '<p>'.__( "Direct link to track your shipment:", 'my-flying-box' );
	// 								}
	// 								foreach ( $links as $link ) {
	// 									$last_event .= '<br/><a href="'.$link['link'].'">'.$link['code'].'</a>';
	// 								}
	// 								$last_event .= '</p>';
	// 							}
	// 						}
	// 						if("delivered" == $latest_event->code) {
	// 							if ($order && $order instanceof WC_Order) {
	// 								$order->set_status('completed');
	// 								$order->save();
	// 								$order->add_order_note(__('Status automatically updated by MFB shipment status.', 'my-flying-box'));
	// 							}
	// 						}
	// 					}
	// 				}
	// 			}
	// 		}
	// 		$status = $last_event;
	// 	} catch (Lce\Exception\LceException $e) {
	// 		error_log(print_r($e->getMessage(), true));
	// 		$status = "";
	// 	}
	// 	if ($status) {
	// 		$response['success'] = 'true';
	// 		$response['status'] = $status;
	// 	} else {
	// 		$response['success'] = 'false';
	// 	}
	// 	wp_send_json($response);
	// }

	public static function update_selected_offer()
	{

		$shipment_id = intval($_POST['shipment_id']);
		$shipment = MFB_Shipment::get($shipment_id);

		if ($shipment->status != 'mfb-draft') die();

		$offer_id = intval($_POST['offer_id']);
		$offer = MFB_Offer::get($offer_id);

		if ($offer && $shipment->quote->offers[$offer->product_code]) {
			$shipment->offer = $offer;
			$shipment->save();
		} else {
			$shipment->offer = null;
			$shipment->save();
		}
	}

	public static function update_recipient()
	{

		$shipment_id = intval($_POST['shipment_id']);
		$shipment = MFB_Shipment::get($shipment_id);

		if ($shipment->status != 'mfb-draft') die();

		foreach (MFB_Shipment::$address_fields as $fieldname) {
			if (isset($_POST['_shipment_recipient_' . $fieldname])) $shipment->recipient->$fieldname = wp_kses_post($_POST['_shipment_recipient_' . $fieldname]);
		}

		$shipment->get_new_quote();
		$res = $shipment->save();

		if ($res) {
			$response['data'] = 'success';
			$response['shipment'] = $shipment;
		} else {
			$response['data'] = 'error';
		}

		// Whatever the outcome, send the Response back
		wp_send_json($response);
	}

	public static function update_shipper()
	{

		$shipment_id = intval($_POST['shipment_id']);
		$shipment = MFB_Shipment::get($shipment_id);

		if ($shipment->status != 'mfb-draft') die();

		foreach (MFB_Shipment::$address_fields as $fieldname) {
			if (isset($_POST['_shipment_shipper_' . $fieldname])) $shipment->shipper->$fieldname = wp_kses_post($_POST['_shipment_shipper_' . $fieldname]);
		}

		$shipment->get_new_quote();
		$res = $shipment->save();

		if ($res) {
			$response['data'] = 'success';
			$response['shipment'] = $shipment;
		} else {
			$response['data'] = 'error';
		}

		// Whatever the outcome, send the Response back
		wp_send_json($response);
	}
	public static function update_parcel()
	{

		$shipment_id = intval($_POST['shipment_id']);
		$shipment = MFB_Shipment::get($shipment_id);

		if ($shipment->status != 'mfb-draft') die();

		if ($_POST['parcel_index'] == 'new') {
			// we are adding a new parcel
			$parcel = new stdClass();
			foreach (MFB_Shipment::$parcel_fields as $fieldname) {
				if (isset($_POST['_parcel_new_' . $fieldname])) $parcel->$fieldname = wp_kses_post($_POST['_parcel_new_' . $fieldname]);
			}
			$shipment->parcels[] = $parcel;
		} else {
			// We are updating an existing parcel
			$parcel_index = intval($_POST['parcel_index']);
			foreach (MFB_Shipment::$parcel_fields as $fieldname) {
				if (isset($_POST['_parcel_' . $parcel_index . '_' . $fieldname])) $shipment->parcels[$parcel_index]->$fieldname = wp_kses_post($_POST['_parcel_' . $parcel_index . '_' . $fieldname]);
			}
		}

		$shipment->get_new_quote();
		$res = $shipment->save();

		if ($res) {
			$response['data'] = 'success';
			$response['shipment'] = $shipment;
		} else {
			$response['data'] = 'error';
		}

		// Whatever the outcome, send the Response back
		wp_send_json($response);
	}

	public static function delete_parcel()
	{

		$shipment_id = intval($_POST['shipment_id']);
		$shipment = MFB_Shipment::get($shipment_id);

		if ($shipment->status != 'mfb-draft') die();

		$parcel_index = intval($_POST['parcel_index']);

		unset($shipment->parcels[$parcel_index]);

		$shipment->save();

		$shipment->get_new_quote();
		$res = $shipment->save();

		if ($res) {
			$response['data'] = 'success';
			$response['shipment'] = $shipment;
		} else {
			$response['data'] = 'error';
		}

		// Whatever the outcome, send the Response back
		wp_send_json($response);
	}

	public static function download_labels()
	{

		// We create a ready-to-confirm shipment object based on existing order
		$shipment_id = intval($_GET['shipment_id']);
		$shipment = MFB_Shipment::get($shipment_id);

		$booking = Lce\Resource\Order::find($shipment->api_order_uuid);
		$labels_content = $booking->labels();
		$filename = 'labels_' . $booking->id . '.pdf';

		header('Content-type: application/pdf');
		header("Content-Transfer-Encoding: binary");
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		print($labels_content);
		die();
	}
}

MFB_AJAX::init();
