jQuery( function ( $ ) {

	/**
	 * My Flying Box meta boxes functions
	 *
	 * We can rely on mfb_js_resources variable to extract
	 * some useful data.
	 */
	var mfb_meta_boxes_order = {
		states: null,
		init: function() {
			$( '#myflyingbox-order-shipping' )
				.on( 'click',  'button.add-shipment',                                       	this.add_shipment )
				.on( 'click',  'div.mfb-available-offers button.book-offer',									this.book_offer )
				.on( 'click',  'div.display_recipient_address a.edit_recipient_address',			this.load_recipient_form )
				.on( 'click',  'div.display_shipper_address a.edit_shipper_address',					this.load_shipper_form )
				.on( 'click',  'div.parcel-data a.edit_parcel',																this.load_parcel_form )
				.on( 'click',  'div.parcel-data a.delete_parcel',															this.delete_parcel )
				.on( 'click',  'div.parcel-data button.new_parcel', 													this.load_parcel_form )
				.on( 'click',  'div.recipient_form_container button.cancel_recipient_form',		this.hide_recipient_form )
				.on( 'click',  'div.shipper_form_container button.cancel_shipper_form',				this.hide_shipper_form )
				.on( 'click',  'div.parcel div.parcel-form button.cancel_parcel_form',				this.hide_parcel_form )
				.on( 'click',  'div.recipient_form_container button.submit_recipient_form',		this.submit_recipient_form )
				.on( 'click',  'div.shipper_form_container button.submit_shipper_form',				this.submit_shipper_form )
				.on( 'click',  'div.mfb-booked-offer button.download-labels',									this.download_labels )
				.on( 'click',  'div.parcel div.parcel-form button.submit_parcel_form',				this.submit_parcel_form )
				.on( 'change', 'div.mfb-available-offers select.offer-selector',							this.update_selected_offer )
				.on( 'click',  'a.delete-shipment',						this.delete_shipment )
				.on( 'click',  'button.add-return-shipment',	this.add_return_shipment );


			$( '#myflyingbox-bulk-shipments' )
				.on( 'click',  'div.mfb-available-offers button.book-offer',                  this.book_offer )
				.on( 'click',  'div.mfb-booked-offer button.download-labels',                 this.download_labels )
				.on( 'change', 'div.mfb-available-offers select.offer-selector',              this.update_selected_offer );
		},

		add_shipment: function() {
			var data = {
				action:   'mfb_create_shipment',
				order_id: mfb_js_resources.post_id
			};

			$.ajax({
				url:  mfb_js_resources.ajax_url,
				data: data,
				type: 'POST',
				success: function( response ) {
					// At some point it would be nice to have a clean element replace...
					window.location.reload();
				}
			});
			return false;
		},

		book_offer: function() {

			var offer_id = $( this ).closest('div.mfb-available-offers').find('select.offer-selector option:selected').data('offer_id');
			var date_selector = $( this ).closest('div.mfb-available-offers').find('select.pickup-date-selector');
			var relay_selector = $( this ).closest('div.mfb-available-offers').find('select.delivery-location-selector');
			var insurance_checkbox = $( this ).closest('div.mfb-available-offers').find('input#mfb_ad_valorem_insurance');
			var pickup_date = '';
			var relay_code = '';
			var insurance = '0';

			$( this ).find( '.spinner' ).addClass( 'is-active' );
			if ( date_selector ) {
				pickup_date = date_selector.val();
			}
			if ( relay_selector ) {
				relay_code = relay_selector.val();
			}
			if ( insurance_checkbox.is(':checked') ) {
				insurance = '1';
			}

			var data = {
				action:   'mfb_book_offer',
				offer_id: offer_id,
				pickup_date: pickup_date,
				relay_code: relay_code,
				insurance: insurance,
				shipment_id: $( this ).closest('tr').data('shipment_id')
			};

			$.ajax({
				url:  mfb_js_resources.ajax_url,
				data: data,
				type: 'POST',
				dataType: 'json',
				success: function( response ) {
					if (true === response.success) {
						window.location.reload();
					} else {
						alert(response.data.message);
					}
				}
			});
			return false;
		},

		update_selected_offer: function() {
			var offer_id = $( this ).find('option:selected').data('offer_id');
			var data = {
				action:   'mfb_update_selected_offer',
				offer_id: offer_id,
				shipment_id: $( this ).closest('tr').data('shipment_id')
			};

			$.ajax({
				url:  mfb_js_resources.ajax_url,
				data: data,
				type: 'POST',
				dataType: 'json',
				success: function( response ) {
					window.location.reload();
				}
			});
			return false;
		},

		delete_shipment: function() {
			var data = {
				action:   'mfb_delete_shipment',
				shipment_id: $( this ).closest('tr').data('shipment_id')
			};

			$.ajax({
				url:  mfb_js_resources.ajax_url,
				data: data,
				type: 'POST',
				success: function( response ) {
					window.location.reload();
				}
			});
			return false;
		},

		add_return_shipment: function() {
			var data = {
				action:   'mfb_create_return_shipment',
				shipment_id: $( this ).closest('tr').data('shipment_id')
			};

			$.ajax({
				url:  mfb_js_resources.ajax_url,
				data: data,
				type: 'POST',
				success: function( response ) {
					window.location.reload();
				}
			});
			return false;
		},

		load_recipient_form: function( event ) {
			event.preventDefault();
			// Initializing form dialogs
			$( this ).closest('div.display_recipient_address').hide();
			$( this ).closest('td').find('div.recipient_form_container').show();
		},

		load_shipper_form: function( event ) {
			event.preventDefault();
			// Initializing form dialogs
			$( this ).closest('div.display_shipper_address').hide();
			$( this ).closest('td').find('div.shipper_form_container').show();
		},

		load_parcel_form: function( event ) {
			event.preventDefault();
			// Initializing form dialogs
			$( this ).closest('div.parcel-data').hide();
			$( this ).closest('div.parcel').find('div.parcel-form').show();
		},


		hide_recipient_form: function( event ) {
			event.preventDefault();
			$( this ).closest('td').find('div.recipient_form_container').hide();
			$( this ).closest('td').find('div.display_recipient_address').show();
		},

		hide_shipper_form: function( event ) {
			event.preventDefault();
			$( this ).closest('td').find('div.shipper_form_container').hide();
			$( this ).closest('td').find('div.display_shipper_address').show();
		},

		hide_parcel_form: function( event ) {
			event.preventDefault();
			$( this ).closest('div.parcel').find('div.parcel-form').hide();
			$( this ).closest('div.parcel').find('div.parcel-data').show();
		},

		submit_recipient_form: function( event ) {
			event.preventDefault();
			var action = 'mfb_update_recipient';
			var shipment_id = $( this ).closest('tr').data('shipment_id');
			$( this ).find( '.spinner' ).addClass( 'is-active' );
			$.ajax({
				url:  mfb_js_resources.ajax_url,
				data: $( this ).closest('div.recipient_form_container').find('input, textarea, select').serialize()+'&action='+action+'&shipment_id='+shipment_id,
				type: 'POST',
				success: function( response ) {
					window.location.reload();
				}
			});
			return false;
		},
		submit_shipper_form: function( event ) {
			event.preventDefault();
			var action = 'mfb_update_shipper';
			var shipment_id = $( this ).closest('tr').data('shipment_id');
			$( this ).find( '.spinner' ).addClass( 'is-active' );
			$.ajax({
				url:  mfb_js_resources.ajax_url,
				data: $( this ).closest('div.shipper_form_container').find('input, textarea, select').serialize()+'&action='+action+'&shipment_id='+shipment_id,
				type: 'POST',
				success: function( response ) {
					window.location.reload();
				}
			});
			return false;
		},
		submit_parcel_form: function( event ) {
			event.preventDefault();
			var action = 'mfb_update_parcel';
			var shipment_id = $( this ).closest('tr').data('shipment_id');
			var parcel_index = $( this ).closest('div.parcel').data('parcel_index');
			$( this ).find( '.spinner' ).addClass( 'is-active' );
			$.ajax({
				url:  mfb_js_resources.ajax_url,
				data: $( this ).closest('div.parcel-form').find('input, select').serialize()+'&action='+action+'&shipment_id='+shipment_id+"&parcel_index="+parcel_index,
				type: 'POST',
				success: function( response ) {
					window.location.reload();
				}
			});
			return false;
		},

		delete_parcel: function( event ) {
			event.preventDefault();
			var action = 'mfb_delete_parcel';
			var shipment_id = $( this ).closest('tr').data('shipment_id');
			var parcel_index = $( this ).closest('div.parcel').data('parcel_index');
			$( this ).closest('div.parcel').find( '.spinner.delete-parcel-spinner' ).addClass( 'is-active' );
			$.ajax({
				url:  mfb_js_resources.ajax_url,
				data: 'action='+action+'&shipment_id='+shipment_id+"&parcel_index="+parcel_index,
				type: 'POST',
				success: function( response ) {
					window.location.reload();
				}
			});
			return false;
		},

		download_labels: function() {
			var shipment_id = $( this ).closest('tr').data('shipment_id');
			window.open( mfb_js_resources.labels_url+'&shipment_id='+shipment_id );
			return false;
		},

	};

	mfb_meta_boxes_order.init();

});
