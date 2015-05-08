jQuery( function ( $ ) {

	/**
	 * My Flying Box meta boxes functions
	 * 
	 * We can rely on woocommerce_admin_meta_boxes variable to extract
	 * some useful data.
	 */
	var mfb_meta_boxes_order = {
		states: null,
		init: function() {
			$( '#myflyingbox-order-shipping' )
				.on( 'click', 'button.add-shipment',	this.add_shipment )
				.on( 'click', 'button.book-offer',		this.book_offer )
				.on( 'click', 'a.delete-shipment',		this.delete_shipment );
    },

    add_shipment: function() {
			var data = {
				action:   'mfb_create_shipment',
				order_id: woocommerce_admin_meta_boxes.post_id
			};

			$.ajax({
				url:  woocommerce_admin_meta_boxes.ajax_url,
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
			var data = {
				action:   'mfb_book_offer',
				offer_id: $( this ).closest('div.offer-details').data('offer_id'),
				shipment_id: $( this ).closest('tr').data('shipment_id')
			};

			$.ajax({
				url:  woocommerce_admin_meta_boxes.ajax_url,
				data: data,
				type: 'POST',
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
				url:  woocommerce_admin_meta_boxes.ajax_url,
				data: data,
				type: 'POST',
				success: function( response ) {
					window.location.reload();
				}
			});
			return false;
		}
		
		
  };
  
  mfb_meta_boxes_order.init();
  
});
