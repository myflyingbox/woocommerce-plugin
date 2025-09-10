<?php

/**
 * MetaBox to display MyFlyingBox tools on Admin order page
 *
 */

class MFB_Meta_Box_Order_Shipping
{


	public static function output($post)
	{
		global $theorder, $extended_cover;

		if (!is_object($theorder)) {
			$theorder = wc_get_order($post->id);
		}

		if (!isset($extended_cover)) {
			$shipping_methods = $theorder->get_shipping_methods();
			if (!empty($shipping_methods)) {
				$shipping_method = reset($shipping_methods);
				$method_id = $shipping_method->get_method_id();
				$instance_id = $shipping_method->get_instance_id();
				$country = $theorder->get_shipping_country();
				$postcode = $theorder->get_shipping_postcode();
				$shipping_zone = WC_Shipping_Zones::get_zone_matching_package([
					'destination' => [
						'country' => $country,
						'state' => $theorder->get_shipping_state(),
						'postcode' => $postcode,
						'city' => $theorder->get_shipping_city(),
						'address' => $theorder->get_shipping_address_1(),
						'address_2' => $theorder->get_shipping_address_2(),
					]
				]);

				$methods = $shipping_zone->get_shipping_methods(true);

				$method_object = null;
				foreach ($methods as $method) {
					if ($method->id === $method_id && $method->instance_id == $instance_id) {
						$method_object = $method;
						break;
					}
				}

				if ($method_object) {
					$extended_cover = $method_object->get_option('extended_cover');
				}
			}
		}

		$delivery_location_code = get_post_meta($theorder->get_id(), '_mfb_delivery_location', true);
		if ($delivery_location_code && !empty($delivery_location_code)) {
			// We have a relay delivery. Extracting the details of the customer selection
			$relay = new stdClass();
			$relay->code = $delivery_location_code;
			$relay->name = get_post_meta($theorder->get_id(), '_mfb_delivery_location_name', true);
			$relay->street = get_post_meta($theorder->get_id(), '_mfb_delivery_location_street', true);
			$relay->postal_code = get_post_meta($theorder->get_id(), '_mfb_delivery_location_postal_code', true);
			$relay->city = get_post_meta($theorder->get_id(), '_mfb_delivery_location_city', true);
		} else {
			$relay = false;
		}
		$shipments = MFB_Shipment::get_all_for_order($theorder->get_id());

		include('views/html-shipments.php');
	}
}
