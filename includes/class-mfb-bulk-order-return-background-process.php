<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class MFB_Bulk_Order_Return_Background_Process extends WP_Background_Process {

    /**
     * @var string
     */
    protected $action = 'mfb_bulk_place_return_booking';

    /**
     * Task
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param mixed $item Queue item to iterate over
     *
     * @return mixed
     */
    protected function task( $wc_order_id ) {
        $wc_order = wc_get_order( $wc_order_id );

        $bulk_order_id = get_post_meta( $wc_order->get_id(), '_mfb_bulk_order_id', true);

        $bulk = MFB_Bulk_Order::get( $bulk_order_id );
        $latest = MFB_Shipment::get_last_booked_for_order( $wc_order->get_id() );

        if ( $latest && !$latest->is_return ) {
          try {
            $shipment = MFB_Shipment::create_from_shipment( $latest->id, true, $bulk_order_id );
            if ( !$shipment->place_booking()) {
              $error = __('The return shipment could not be booked (probably no offer could be automatically selected)');
            }
          } catch (Exception $e) {
            $error = sprintf( __('Error while booking return shipment for Order %s: %s', 'my-flying-box'), $wc_order->get_order_number(), $e->getMessage());
          }
          // In case of issues during the booking, we record the error to display it later.
          if ( is_object($shipment) && isset($error) ) {
            $shipment->status = 'mfb-draft';
            $shipment->last_booking_error = $error;
            $shipment->save();
          }
        }
        $bulk->mark_processed();
        return false;
    }

  public function handle_cron_healthcheck() {
    if ( $this->is_process_running() ) {
      // Background process already running.
      return;
    }

    if ( $this->is_queue_empty() ) {
      // No data to process.
      $this->clear_scheduled_event();
      return;
    }

    $this->handle();
  }


    /**
     * Complete
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete() {
        parent::complete();

        // Show notice to user or perform some other arbitrary task...
    }

}

?>
