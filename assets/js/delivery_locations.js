var mfb_locations = null;
var infowindow = null;
var service_code = '';
var gmap = null;
var geocoder = null;
var markers = [];
var locations_data = [];

jQuery(window).load(function(){

	jQuery('body').append(map);

	jQuery('body').delegate('[data-mfb-action="select-location"]','click',function(){
		var key = jQuery(this).data('mfb-offer-uuid')
		var instance_id = jQuery(this).data('mfb-instance-id');

		service_code = jQuery(this).data('mfb-method-id');

		// Show a spinner
		var td = jQuery(this).closest('td');
		td.addClass( 'processing' ).block({
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});

		jQuery.ajax({
			url:      mfb_params.ajaxurl,
			data: jQuery('form.checkout.woocommerce-checkout').serialize()+'&action='+mfb_params.action+'&k='+key+'&s='+service_code+'&i='+instance_id,
			dataType: 'json',
			timeout:  15000,
			error:    error_loading_locations,
			success:  show_locations,
			complete: function() {
				td.removeClass( 'processing' ).unblock();
			}
		});
	});

	jQuery('#map-canvas').delegate('.mfb-select-location','click', select_location);

	jQuery('.mfb-close-map').click(close_gmap);
});

/*
 * Initialize the google map for a new display
 */
function init_gmap() {
	jQuery('#map-container').css('display','block');
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
	jQuery('#map-container').css('display','none');
	jQuery('#map-canvas').html('');
	for (var i = 0; i < markers.length; i++) {
		if ( markers[i] != undefined) {
			markers[i].setMap(null);
		}
	}
	markers = [];
	locations_data = [];
}

function update_zoom_gmap() {

	if (mfb_locations.length == 0 ||  (mfb_locations.length != 0 && markers.length < mfb_locations.length))
	{
		return;
	}
	var bounds = new google.maps.LatLngBounds();

	for(var i = 0;i<markers.length;i++) {
		if (typeof markers[i] != 'undefined')
			bounds.extend(markers[i].getPosition());
	}
	gmap.setCenter(bounds.getCenter());
	gmap.fitBounds(bounds);
	gmap.setZoom(gmap.getZoom()-1);
	if(gmap.getZoom()> 15){
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
	for (i in mfb_locations){
		loc = mfb_locations[i];
		(function(i) {
			var name = loc.company;
			var address = loc.street;
			var city = loc.city;
			var postal_code = loc.postal_code;
			info ='<b>'+name+'</b><br/>'+
						'<u class="mfb-select-location" data="'+i+'">'+lang['Select this location']+'</u><br/>'+
						'<span>'+address+', '+postal_code+' '+city+'</span><br/>'+
						'<div class="mfb-opening-hours"><table>';
			var opening_hours = loc.opening_hours.sort(function(a,b){ return a.day-b.day});

			for (j in opening_hours){
				day = opening_hours[j];
					info += '<tr>';
					info += '<td><b>'+lang['day_'+day.day]+'</b> : </td>';
					info += '<td>';
					info += day.hours ;
					info += '</td></tr>';
			}
			info += '</table></div>';

			locations_data[i] = info;

			if(geocoder)
			{
				geocoder.geocode({ 'address': address + ', ' + postal_code + ' ' + city }, function(results, status) {
					if(status == google.maps.GeocoderStatus.OK)
					{
						if (i == 0) {
							gmap.setCenter(results[0].geometry.location);
						}
						var marker = new google.maps.Marker({
							map: gmap,
							position: results[0].geometry.location,
							title : name
						});
						marker.set("content", locations_data[i]);
						google.maps.event.addListener(marker,"click",function() {
							infowindow.close();
							infowindow.setContent(this.get("content"));
							infowindow.open(gmap,marker);
						});
						markers[i] = marker;
						update_zoom_gmap();
					}
				});
			}
		})(i);
	}


	// remove info if we click on the map
	google.maps.event.addListener(gmap,"click",function() {
		infowindow.close();
	});
}

function select_location(source){
	code = mfb_locations[jQuery(source.target).attr('data')].code;
	name = mfb_locations[jQuery(source.target).attr('data')].company;
	jQuery('#input_'+service_code).html('<input type="hidden" name="_delivery_location" value="'+code+'"/>');
	jQuery('#mfb-location-client').html(name);
	jQuery('#map-container').css('display','none');
	close_gmap();
}

function error_loading_locations(jqXHR, textStatus, errorThrown ) {
	alert(lang['Unable to load delivery locations']+' : '+errorThrown);
}
