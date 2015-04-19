<?php
/**
 * MFB_Offer
 * 
 * Offer saved from API response, with corresponding offers.
 * 
 */

class MFB_Offer {

	public $id = 0;
  public $quote_id = 0;
  public $api_offer_uuid = 0;
  public $product_code = null;
  public $base_price_in_cents = 0;
  public $total_price_in_cents = 0;
  public $currency = null;

  public $post = null;

	public function __construct() {
    
	}

	public static function get( $offer_id ) {
    
		if ( is_numeric( $offer_id ) ) {
      $instance = new self();
			$instance->id   = absint( $offer_id );
			$instance->post = get_post( $instance->id );
      $instance->populate();
    }
    return $instance;
	}

  public static function get_by_uuid( $uuid ) {

    $args = array(
      'post_type' => 'mfb_offer',
      'post_status' => 'private',
      'meta_key' => '_api_uuid',
      'meta_value' => $uuid
    );
    
    // The Query
    $query = get_posts( $args );
    
    if ( count($query) > 0 ) {
      return self::get($query[0]->ID);
    } else {
      return false;
    }
  }

  public function populate() {
    $this->quote_id       = $this->post->post_parent;
    
    $this->api_offer_uuid       = get_post_meta( $this->id, '_api_uuid', true );
    $this->product_code         = get_post_meta( $this->id, '_product_code', true );
    $this->base_price_in_cents  = get_post_meta( $this->id, '_base_price_in_cents', true );
    $this->total_price_in_cents = get_post_meta( $this->id, '_total_price_in_cents', true );
    $this->currency             = get_post_meta( $this->id, '_currency', true );
    
  }

  public static function get_all_for_quote( $quote_id ) {
    
    $all_offers = get_children( array(
			'post_type' 	  => 'mfb_offer',
      'post_status'   => 'any',
      'post_parent'   => $quote_id,
      'field'         => 'ids'
		));

    $offers = array();
    
    foreach($all_offers as $offer) {
      $offers[] = self::get($offer->ID);
    }
    
    uasort($offers, array('self', 'sort_offers_by_price'));
    
    return $offers;
	}

  public static function sort_offers_by_price ( $a, $b ) {
    if ($a->base_price_in_cents == $b->base_price_in_cents) {
      return 0;
    }
    return ($a->base_price_in_cents < $b->base_price_in_cents) ? -1 : 1;
  }

  public function save() {

    // ID equal to zero, this is a new record    
    if ($this->id == 0) {
      $offer = array(
        'post_type' => 'mfb_offer',
        'post_status' => 'private',
        'ping_status' => 'closed',
        'comment_status' => 'closed',
        'post_author' => 1,
        'post_password' => uniqid( 'offer_' ),
        'post_title' => $this->api_offer_uuid,
        'post_parent' => $this->quote_id
      );

      $this->id = wp_insert_post( $offer, true );

      update_post_meta( $this->id, '_api_uuid',             $this->api_offer_uuid );
      update_post_meta( $this->id, '_base_price_in_cents',  $this->base_price_in_cents );
      update_post_meta( $this->id, '_total_price_in_cents', $this->total_price_in_cents );
      update_post_meta( $this->id, '_product_code',         $this->product_code );
      update_post_meta( $this->id, '_currency',             $this->currency );
      
    }
  }

  /**
   * Get available delivery locations from API.
   * Expects an array containing 'street' and 'city', to pass to the request.
   */
  public function get_delivery_locations($params) {

    $api_offer = Lce\Resource\Offer::find($this->api_offer_uuid);
    
    return $api_offer->available_delivery_locations($params);
  }
}
