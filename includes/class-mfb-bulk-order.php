<?php
/**
 * MFB_Bulk_Order
 * Used to gather shipments for a set of orders, automatically
 */

class MFB_Bulk_Order {

  public $id = 0;

  public $wc_order_ids = array(); // All orders that are part of this bulk shipping order
  public $orders = array();

  public $status = null;
  public $return_shipments = false;



  public function __construct() {

  }

  public static function get( $bulk_id ) {

    if ( is_numeric( $bulk_id ) ) {
      $instance = new self();
      $instance->id             = absint( $bulk_id );
      $instance->post           = get_post( $instance->id );
      $instance->status         = $instance->post->post_status;

      $instance->populate();
    }
    return $instance;
  }


  public function populate() {
    $this->wc_order_ids = get_post_meta( $this->id, '_wc_order_ids', true);

    foreach( $this->wc_order_ids as $wc_order_id ){
      $this->orders[] = wc_get_order( $wc_order_id );
    }

		$this->return_shipments = get_post_meta( $this->id, '_return_shipments', true );
  }

  public function save() {
    // ID equal to zero, this is a new record
    if ($this->id == 0) {
      $bulk = array(
        'post_type' => 'mfb_bulk_order',
        'post_status' => 'draft',
        'ping_status' => 'closed',
        'comment_status' => 'closed',
        'post_author' => 1,
        'post_password' => uniqid( 'bulk_' ),
        'post_title' => 'MyFlyingBox Bulk Shipment'
      );

      $this->id = wp_insert_post( $bulk, true );
      $this->post = get_post( $this->id );
      $this->status = 'draft'; // New bulk shipments should always be created as drafts.
    }
    wp_update_post(array(
      'ID' => $this->id,
      'post_status' => $this->status
    ));

    update_post_meta( $this->id, '_wc_order_ids', $this->wc_order_ids );
    update_post_meta( $this->id, '_return_shipments', $this->return_shipments );

    foreach( $this->wc_order_ids as $wc_order_id ) {
        update_post_meta( $wc_order_id, '_mfb_bulk_order_id', $this->id );
    }

    // Reloading object
    $this->populate();
    return true;
  }

  public function update_status( $status ) {
    $this->status = $status;
    wp_update_post(array(
      'ID' => $this->id,
      'post_status' => $this->status
    ));
  }

  // Mark this bulk order as processed if all shipments have been dealt with
  public function mark_processed() {

    $args = array(
      'post_type'  => 'mfb_shipment',
      'post_status' => array('mfb-draft','mfb-booked'),
      'meta_query' => array(
        array(
          'key'     => '_bulk_order_id',
          'value'   => $this->id,
          'compare' => '=',
          'type'    => 'numeric'
        ),
      ),
    );

    $query = new WP_Query( $args );

    // If we have the same count of shipments (booked or draft, so not processing) as
    // we have associated orders, we're good.
    if ($query->post_count == count($this->wc_order_ids)) {
      $notices = array();
      $notices[] = sprintf( __('Bulk shipment #%s has been processed.', 'my-flying-box'), $this->id);
      set_transient('mfb_bulk_notices', $notices, 300);
      return $this->update_status('mfb-processed');
    } else {
      return true;
    }
  }


  /**
   * Returns all existing bulk orders.
   * If none exist, then we initialize the default values.
   */
  public static function get_all() {

    $all_bulk_orders = get_posts( array(
      'posts_per_page'=> -1,
      'post_type'   => 'mfb_bulk_order',
      'field' => 'ids',
      'orderby'  => array( 'date' => 'DESC' )
    ));

    $bulk_orders = array();

    foreach($all_bulk_orders as $bulk) {
      $bulk_orders[] = self::get($bulk->ID);
    }

    return $bulk_orders;
  }

  // Add an order to this bulk order. This does NOT save the bulk order.
  public function add_order( $order ) {

    // Order already included in this bulk order
    if ( in_array( $order->get_id(), $this->wc_order_ids ) ) {
      return array('success' => false, 'error' => 'already_included');
    }

    if ( $this->return_shipments ) {
      // For return shipments, at least one booked shipment must be present and it must
      // not be a return shipment.
      $latest = MFB_Shipment::get_last_booked_for_order( $order->get_id() );
      if ( $latest == null) {
        return array('success' => false, 'error' => 'no_initial_shipment');
      } else if ( $latest->is_return ) {
        return array('success' => false, 'error' => 'already_returned');
      }
    } else {
      // Order already has a booked MFB shipment, so can't be included in a bulk order to avoid doubles
      if ( MFB_Shipment::get_last_booked_for_order( $order->get_id() ) != null ) {
        return array('success' => false, 'error' => 'already_shipped');
      }
    }

    // All good, adding the order to the list.
    $this->wc_order_ids[] = $order->get_id();
    $this->orders[] = $order;

    // try {
    //   $shipment = MFB_Shipment::create_from_order( $order );
    //   $shipment->place_booking();
    //   $booked[] = $order->get_order_number();
    // } catch (Exception $e) {
    //   $errors[] = sprintf( __('Error while booking shipment for Order %s: %s', 'my-flying-box'), $order->get_order_number(), $e->getMessage());
    // }

    return array('success' => true);
  }


  public function get_shipment_for_order( $order_id ) {
    $all_shipments = get_children( array(
      'post_type'     => 'mfb_shipment',
      'post_status'   => array('private','mfb-draft','mfb-booked','mfb-processing'),
      'post_parent'   => $order_id,
      'field'         => 'ids',
      'orderby'       => array( 'date' => 'DESC' )
    ));

    $shipments = array();



    $args = array(
      'post_type'  => 'mfb_shipment',
      'post_status' => array('private','mfb-draft','mfb-booked','mfb-processing'),
      'post_parent'   => $order_id,
      'orderby'       => array( 'date' => 'DESC' ),
      'meta_query' => array(
        array(
          'key'     => '_bulk_order_id',
          'value'   => $this->id,
          'compare' => '=',
          'type'    => 'numeric'
        ),
      ),
    );
    $query = new WP_Query( $args );
    if ($query->post_count == 1) {
      return MFB_Shipment::get($query->post->ID);
    } else {
      return false;
    }
  }

}
