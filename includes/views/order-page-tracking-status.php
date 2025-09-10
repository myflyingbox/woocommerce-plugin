<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div id="last-status" style="margin-top:25px;display:inline-block;">
<h2><?php _e("Shipment Status", 'my-flying-box'); ?></h2>
<?php
$last_event = "";
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
    $last_event .= '<label>'.__('Code :', 'my-flying-box') . '</label> ' . esc_html($latest_event->code) . '<br>';
    $last_event .= '<label>'.__('Label :', 'my-flying-box') . '</label> ' . esc_html($latest_event->label->{$lang}) . '<br>';
    $last_event .= '<label>'.__('Date :', 'my-flying-box') . '</label> ' . date('d/m/Y H:i', strtotime($latest_event->happened_at)) . '<br>';

    if (!empty($latest_event->location)) {
        $loc = $latest_event->location;
        $last_event .= '<label>'.__('Place :', 'my-flying-box') . '</label>';
        $last_event .= " ";
        if (!empty($loc->name)) $last_event .= esc_html($loc->name) . ', ';
        if (!empty($loc->street)) $last_event .= esc_html($loc->street) . ', ';
        if (!empty($loc->postal_code)) $last_event .= esc_html($loc->postal_code) . ' ';
        if (!empty($loc->city)) $last_event .= esc_html($loc->city) . ', ';
        $last_event .= esc_html($loc->country ?? '');
    }
}
echo $last_event;
?>
</div>
<div class="mfb-order-check-status-button" style="display: inline-block;margin-top: 25px;">
    <button id="trackthis" type="button" class="button wp-element-button" data-order_id="<?php echo $order_id; ?>" data-api_uuid="<?php echo $api_order_uuid; ?>">
        <?php _e( 'Check Status', 'my-flying-box' ); ?>
    </button>
</div>
