(function ($) {
	var mfb_locations = null;
	var infowindow = null;
	var service_code = '';
	var gmap = null;
	var geocoder = null;
	var markers = [];
	var locations_data = [];

	function addDeliveryLocation() {
		if ($('.wc-block-components-radio-control__option').length) {
			$('.wc-block-components-radio-control__option').each(function () {
				const $option = $(this);
				const $input = $option.find('input[type="radio"]');
				const wrap_label = $option.find("div.wc-block-components-radio-control__option-layout");

				if ($input.length === 0 || wrap_label.find('[data-mfb-action="select-location"]').length > 0) {
					return;
				}

				const methodId = $input.val();

				$.ajax({
					url: mfb_var.ajax_url,
					method: 'POST',
					data: {
						action: 'get_delivery_location',
						method_id: methodId
					},
					success: function (response) {
						if (response.success && response.data.location) {
							wrap_label.append(response.data.location);
							load_map();
						}
					}
				});
			});
		}
	}

	function getShippingAddressData() {
		return {
			first_name: $('#shipping-first_name').val(),
			last_name: $('#shipping-last_name').val(),
			address_1: $('#shipping-address_1').val(),
			address_2: $('#shipping-address_2').val(),
			postcode: $('#shipping-postcode').val(),
			city: $('#shipping-city').val(),
			country: $('#shipping-country').val(),
			phone: $('#shipping-phone').val()
		};
	}

	function getSelectedShippingMethodId() {
		const $shippingControl = $('.wc-block-components-shipping-rates-control');

		if ($shippingControl.length) {
			const $selectedInput = $shippingControl.find('.wc-block-components-radio-control__input:checked');

			if ($selectedInput.length) {
				return $selectedInput
					.closest('.wc-block-components-radio-control__option')
					.find('[data-mfb-method-input-id]')
					.data('mfb-method-input-id') || null;
			}
		}
		return null;
	}

	function getSelectedDeliveryLocation() {
		const $shippingControl = $('.wc-block-components-shipping-rates-control');

		if ($shippingControl.length) {
			const $selectedInput = $shippingControl.find('.wc-block-components-radio-control__input:checked');

			if ($selectedInput.length) {
				const $parentOption = $selectedInput.closest('.wc-block-components-radio-control__option');
				const $locationInput = $parentOption.find('input[name="_delivery_location"]');

				return $locationInput.val() || null;
			}
		}

		return null;
	}

	function load_map() {
		$('body').append(map);

		$('body').delegate('[data-mfb-action="select-location"]', 'click', function () {
			var key = $(this).data('mfb-offer-uuid')
			var instance_id = $(this).data('mfb-instance-id');

			service_code = $(this).data('mfb-method-id');

			// Show a spinner
			var td = $(this).closest('td');
			td.addClass('processing').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			if ($(".wc-block-checkout").length) {
				const addressData = getShippingAddressData();
				$.ajax({
					url: mfb_params.ajaxurl,
					method: 'POST',
					data: {
						action: mfb_params.action,
						k: key,
						s: service_code,
						i: instance_id,
						address: addressData
					},
					dataType: 'json',
					timeout: 15000,
					error: error_loading_locations,
					success: show_locations,
					complete: function () {
						td.removeClass('processing').unblock();
					}
				});
			} else {
				$.ajax({
					url: mfb_params.ajaxurl,
					data: $('form.checkout.woocommerce-checkout').serialize() + '&action=' + mfb_params.action + '&k=' + key + '&s=' + service_code + '&i=' + instance_id,
					dataType: 'json',
					timeout: 15000,
					error: error_loading_locations,
					success: show_locations,
					complete: function () {
						td.removeClass('processing').unblock();
					}
				});
			}
		});

		$('#map-canvas').delegate('.mfb-select-location', 'click', select_location);

		$('.mfb-close-map').click(close_gmap);
	}


	$(window).load(function () {
		if ($('body').hasClass('woocommerce-checkout')) {
			load_map();
			setTimeout(() => addDeliveryLocation(), 1000);

			if ($(".wc-block-checkout").length) {
				const originalFetch = window.fetch;

				window.fetch = function (...args) {
					const [url, options] = args;

					const isCheckoutPost = typeof url === 'string' &&
						url.includes('/wc/store/v1/checkout') &&
						options?.method?.toUpperCase() === 'POST';

					if (isCheckoutPost && options?.body) {
						try {
							const bodyObj = JSON.parse(options.body);
							bodyObj.extra_data = {
								...(bodyObj.extra_data || {}),
								shipping_address: getShippingAddressData(),
								shipping_method: getSelectedShippingMethodId(),
								delivery_location: getSelectedDeliveryLocation()
							};

							options.body = JSON.stringify(bodyObj);
						} catch (e) {
							console.warn('Erreur en injectant extra_data dans checkout body', e);
						}
					}

					return originalFetch.apply(this, args);
				};
			}
		}
	});

	/*
	 * Initialize the google map for a new display
	 */
	function init_gmap() {
		$('#map-container').css('display', 'block');
		var options = {
			zoom: 11,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		};
		gmap = new google.maps.Map(document.getElementById("map-canvas"), options);
		geocoder = new google.maps.Geocoder();
		infowindow = new google.maps.InfoWindow();
	}

	/*
	 * Close and clear the google map
	 */
	function close_gmap() {
		$('#map-container').css('display', 'none');
		$('#map-canvas').html('');
		for (var i = 0; i < markers.length; i++) {
			if (markers[i] != undefined) {
				markers[i].setMap(null);
			}
		}
		markers = [];
		locations_data = [];
	}

	function update_zoom_gmap() {

		if (mfb_locations.length == 0 || (mfb_locations.length != 0 && markers.length < mfb_locations.length)) {
			return;
		}
		var bounds = new google.maps.LatLngBounds();

		for (var i = 0; i < markers.length; i++) {
			if (typeof markers[i] != 'undefined')
				bounds.extend(markers[i].getPosition());
		}
		gmap.setCenter(bounds.getCenter());
		gmap.fitBounds(bounds);
		gmap.setZoom(gmap.getZoom() - 1);
		if (gmap.getZoom() > 15) {
			gmap.setZoom(15);
		}
	}

	/*
	 * Now that we have all the parcel points, we display them
	 */
	function show_locations(data) {
		mfb_locations = data.locations;

		init_gmap();

		// add parcel point markers
		for (i in mfb_locations) {
			loc = mfb_locations[i];
			(function (i) {
				var name = loc.company;
				var address = loc.street;
				var city = loc.city;
				var postal_code = loc.postal_code;
				info = '<b>' + name + '</b><br/>' +
					'<u class="mfb-select-location" data="' + i + '">' + lang['Select this location'] + '</u><br/>' +
					'<span>' + address + ', ' + postal_code + ' ' + city + '</span><br/>' +
					'<div class="mfb-opening-hours"><table>';
				var opening_hours = loc.opening_hours.sort(function (a, b) { return a.day - b.day });

				for (j in opening_hours) {
					day = opening_hours[j];
					info += '<tr>';
					info += '<td><b>' + lang['day_' + day.day] + '</b> : </td>';
					info += '<td>';
					info += day.hours;
					info += '</td></tr>';
				}
				info += '</table></div>';

				locations_data[i] = info;

				if (geocoder) {
					geocoder.geocode({ 'address': address + ', ' + postal_code + ' ' + city }, function (results, status) {
						if (status == google.maps.GeocoderStatus.OK) {
							if (i == 0) {
								gmap.setCenter(results[0].geometry.location);
							}
							var marker = new google.maps.Marker({
								map: gmap,
								position: results[0].geometry.location,
								title: name
							});
							marker.set("content", locations_data[i]);
							google.maps.event.addListener(marker, "click", function () {
								infowindow.close();
								infowindow.setContent(this.get("content"));
								infowindow.open(gmap, marker);
							});
							markers[i] = marker;
							update_zoom_gmap();
						}
					});
				}
			})(i);
		}


		// remove info if we click on the map
		google.maps.event.addListener(gmap, "click", function () {
			infowindow.close();
		});
	}

	function select_location(source) {
		code = mfb_locations[$(source.target).attr('data')].code;
		name = mfb_locations[$(source.target).attr('data')].company;
		$('#input_' + service_code).html('<input type="hidden" name="_delivery_location" value="' + code + '"/>');
		if ($(".wc-block-checkout").length) {
			$('#mfb-location-client_' + service_code).html(name);
			$('#ilbl_' + service_code).show();
		} else {
			$('#mfb-location-client').html(name);
		}
		$('#map-container').css('display', 'none');
		close_gmap();
	}

	function error_loading_locations(jqXHR, textStatus, errorThrown) {
		alert(lang['Unable to load delivery locations'] + ' : ' + errorThrown);
	}
})(jQuery);