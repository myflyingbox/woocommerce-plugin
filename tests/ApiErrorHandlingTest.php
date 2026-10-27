<?php

use Lce\Exception\LceException;
use PHPUnit\Framework\TestCase;

define('ABSPATH', '/unused/');

class MFB_Test_State
{
    public static $posts = [];
    public static $meta = [];
    public static $hooks = [];
    public static $notices = [];
    public static $orders = [];
    public static $woocommerce;
}

function __($message, $domain = '') { return $message; }
function esc_html__($message, $domain = '') { return htmlspecialchars($message); }
function esc_html($message) { return htmlspecialchars($message); }
function absint($value) { return abs((int) $value); }
function sanitize_text_field($value) { return trim($value); }
function sanitize_textarea_field($value) { return trim($value); }
function add_action($hook, $callback) { MFB_Test_State::$hooks[$hook] = $callback; }
function get_option($key, $default = '') { return $key === 'mfb_update_order_status_on_shipment' ? 'yes' : $default; }
function get_post($post_id) { return MFB_Test_State::$posts[$post_id] ?? null; }
function get_post_meta($post_id, $key, $single = true) { return MFB_Test_State::$meta[$post_id][$key] ?? ''; }
function update_post_meta($post_id, $key, $value) { MFB_Test_State::$meta[$post_id][$key] = $value; return true; }
function delete_post_meta($post_id, $key) { unset(MFB_Test_State::$meta[$post_id][$key]); }
function wp_insert_post($post, $return_error = false)
{
    $post_id = count(MFB_Test_State::$posts) + 1;
    MFB_Test_State::$posts[$post_id] = (object) array_merge(['ID' => $post_id, 'post_parent' => 0], $post);
    return $post_id;
}
function wp_update_post($post)
{
    foreach ($post as $key => $value) {
        MFB_Test_State::$posts[$post['ID']]->$key = $value;
    }
    return $post['ID'];
}
function get_posts($args)
{
    $posts = [];
    foreach (MFB_Test_State::$posts as $post) {
        if ($post->post_type !== $args['post_type']) { continue; }
        if (isset($args['meta_key']) && get_post_meta($post->ID, $args['meta_key']) !== $args['meta_value']) { continue; }
        $posts[] = ($args['fields'] ?? '') === 'ids' ? $post->ID : $post;
    }
    return $posts;
}
function get_children($args)
{
    return array_filter(get_posts($args), function ($post) use ($args) { return $post->post_parent === $args['post_parent']; });
}
function WC() { return MFB_Test_State::$woocommerce; }
function wc_get_order($order_id) { return MFB_Test_State::$orders[$order_id] ?? false; }
function wc_format_decimal($value, $decimals = false) { return (float) $value; }
function wc_has_notice($message, $type) { return in_array($message, MFB_Test_State::$notices, true); }
function wc_add_notice($message, $type) { MFB_Test_State::$notices[] = $message; }
function wp_send_json($response) { throw new MFB_Test_Json_Response($response); }
function wp_send_json_error($response) { throw new MFB_Test_Json_Response(['success' => false, 'data' => $response]); }
function wp_die($message, $title = '', $args = []) { throw new RuntimeException($message, $args['response'] ?? 500); }

class MFB_Test_Json_Response extends RuntimeException
{
    public $response;
    public function __construct($response) { $this->response = $response; }
}

class WC_Shipping_Method
{
    public function get_option($key) { return 'no'; }
}

class MFB_Dimension
{
    public static function get_for_weight($weight) { return (object) ['length' => 10, 'width' => 10, 'height' => 10]; }
}

class WC_Order
{
    public $id;
    public $status = 'processing';
    public $notes = [];
    public $saved = false;
    public function __construct($order_id) { $this->id = $order_id; }
    public function get_status() { return $this->status; }
    public function set_status($status) { $this->status = $status; }
    public function add_order_note($note) { $this->notes[] = $note; }
    public function save() { $this->saved = true; }
}

class WP_REST_Request
{
    public $data;
    public function get_json_params() { return $this->data; }
}

class WP_REST_Response
{
    public $data;
    public $status;
    public function __construct($data, $status) { $this->data = $data; $this->status = $status; }
}

class MFB_Test_Transport
{
    public $responses = [];
    public function get($resource = null, $id = null, $action = null)
    {
        $key = $resource . ($action ? '/' . $action : '');
        if (array_key_exists($key, $this->responses)) { return $this->responses[$key]; }
        throw new LceException('SECRET technical API error: ' . $key);
    }
    public function post($resource, $params) { throw new LceException('SECRET technical API error: ' . $resource); }
}

require_once __DIR__ . '/../includes/class-my-flying-box-settings.php';
require_once __DIR__ . '/../includes/class-mfb-offer.php';
require_once __DIR__ . '/../includes/class-mfb-quote.php';
require_once __DIR__ . '/../includes/class-mfb-shipment.php';
require_once __DIR__ . '/../includes/class-mfb-shipping-method.php';
require_once __DIR__ . '/../includes/class-mfb-carrier.php';
require_once __DIR__ . '/../includes/class-mfb-ajax.php';
require_once __DIR__ . '/../includes/class-mfb-rest-api.php';
require_once __DIR__ . '/../includes/class-mfb-download-labels.php';

class MFB_Test_Relay_Method extends MFB_Shipping_Method
{
    public function __construct() {}
}

class ApiErrorHandlingTest extends TestCase
{
    private $logFile;
    private $previousLog;

    protected function setUp(): void
    {
        MFB_Test_State::$posts = [];
        MFB_Test_State::$meta = [];
        MFB_Test_State::$notices = [];
        MFB_Test_State::$orders = [];
        Lce\Lce::$connection = new MFB_Test_Transport();
        $this->logFile = tempnam(sys_get_temp_dir(), 'mfb-errors-');
        $this->previousLog = ini_set('error_log', $this->logFile);
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        ini_set('error_log', $this->previousLog);
        unlink($this->logFile);
        $_GET = $_POST = $_REQUEST = [];
    }

    public function testRelayLookupFailuresAreOptional(): void
    {
        $offer = new MFB_Offer();
        self::assertSame([], $offer->get_delivery_locations([]));
        Lce\Lce::$connection->responses['offers'] = (object) ['id' => 'offer-id'];
        self::assertSame([], $offer->get_delivery_locations([]));
        Lce\Lce::$connection->responses['offers/available_delivery_locations'] = [(object) ['code' => 'relay-code']];
        self::assertSame('relay-code', $offer->get_delivery_locations([])[0]->code);
    }

    public function testTrackingFailureDoesNotBlockShipmentLoading(): void
    {
        update_post_meta(123, '_api_uuid', 'order-uuid');
        $shipment = new MFB_Shipment();
        $shipment->id = 123;
        $shipment->populate();
        self::assertNull($shipment->tracking);
        self::assertSame('order-uuid', $shipment->api_order_uuid);
    }

    public function testRestImportCompletesOrderDespiteRelayAndTrackingFailures(): void
    {
        MFB_Test_State::$orders[123] = new WC_Order(123);
        $request = new WP_REST_Request();
        $request->data = [
            'api_order_id' => 'order-uuid',
            'order_id' => 123,
            'delivery_location_code' => 'relay-code',
            'selected_offer' => ['api_offer_id' => 'offer-uuid', 'product_code' => 'relay-product', 'relay' => true],
            'parcels' => [['length' => 10, 'width' => 10, 'height' => 10, 'weight' => 1, 'carrier_reference' => 'TRACK123']],
        ];
        $response = MFB_Rest_Api::create_or_update_shipment($request);
        self::assertSame(201, $response->status);
        self::assertTrue($response->data['success']);
        self::assertSame('completed', MFB_Test_State::$orders[123]->status);
        self::assertTrue(MFB_Test_State::$orders[123]->saved);
        self::assertCount(1, MFB_Test_State::$orders[123]->notes);
        self::assertStringContainsString('TRACK123', MFB_Test_State::$orders[123]->notes[0]);
    }

    public function testAdminAjaxQuoteFailureReturnsExactApiError(): void
    {
        $_POST = ['shipment_id' => 123];
        MFB_Test_State::$posts[123] = (object) ['ID' => 123, 'post_status' => 'mfb-draft', 'post_parent' => 456];
        try {
            MFB_Test_State::$hooks['wp_ajax_mfb_update_recipient']();
            self::fail('Expected JSON error.');
        } catch (MFB_Test_Json_Response $response) {
            self::assertSame('error', $response->response['data']);
            self::assertSame('SECRET technical API error: quotes', $response->response['message']);
        }
    }

    public function testCarrierRefreshReturnsExactApiError(): void
    {
        $result = MFB_Carrier::refresh_from_api();
        self::assertSame('SECRET technical API error: products', $result);
    }

    public function testPublicRelayAjaxKeepsNeutralMessageForGuestsAndLoggedInCustomers(): void
    {
        MFB_Test_State::$posts[1] = (object) ['ID' => 1, 'post_type' => 'mfb_quote', 'post_parent' => 0];
        update_post_meta(1, '_params', ['recipient' => ['city' => 'Oldtown']]);
        MFB_Test_State::$woocommerce = (object) [
            'cart' => new class {
                public function get_cart() { return []; }
            },
            'session' => new class {
                public function get($key) { return $key === 'myflyingbox_shipment_quote_id' ? 1 : $_SERVER['REQUEST_TIME']; }
            },
        ];
        $_REQUEST = [
            's' => MFB_Test_Relay_Method::class,
            'i' => 1,
            'address' => ['city' => 'Paris', 'postcode' => '75001', 'country' => 'FR'],
        ];

        foreach (['wp_ajax_mfb_get_delivery_locations', 'wp_ajax_nopriv_mfb_get_delivery_locations'] as $hook) {
            try {
                MFB_Test_State::$hooks[$hook]();
                self::fail('Expected a frontend JSON error.');
            } catch (MFB_Test_Json_Response $response) {
                self::assertSame('error', $response->response['data']);
                self::assertStringNotContainsString('SECRET', $response->response['message']);
                self::assertStringContainsString('try again', $response->response['message']);
            }
        }
    }

    public function testAdminTrackingReturnsExactApiError(): void
    {
        $_POST = ['offer_id' => 123, 'wc_order_id' => 456];
        try {
            MFB_Test_State::$hooks['wp_ajax_mfb_check_status']();
            self::fail('Expected a tracking error.');
        } catch (MFB_Test_Json_Response $response) {
            self::assertSame('false', $response->response['success']);
            self::assertSame('SECRET technical API error: orders/tracking', $response->response['message']);
        }
    }

    public function testCheckoutFailureHidesRatesAndDeduplicatesNotice(): void
    {
        MFB_Test_State::$woocommerce = (object) [
            'cart' => new class {
                public function get_cart() { return []; }
            },
            'session' => new class {
                public function get($key) { return null; }
                public function set($key, $value) {}
            },
        ];
        $method = new class extends MFB_Shipping_Method {
            public $rates = [];
            public function __construct() { $this->enabled = true; }
            public function destination_supported($postal_code, $country) { return true; }
            public function add_rate($rate) { $this->rates[] = $rate; }
        };
        $package = ['destination' => ['city' => 'Paris', 'postcode' => '75001', 'country' => 'FR'], 'contents' => []];

        self::assertFalse($method->calculate_shipping($package));
        self::assertFalse($method->calculate_shipping($package));
        self::assertSame([], $method->rates);
        self::assertCount(1, MFB_Test_State::$notices);
        self::assertStringContainsString('choose another shipping method', MFB_Test_State::$notices[0]);
        self::assertStringNotContainsString('SECRET', MFB_Test_State::$notices[0]);
    }

    public function testLabelDownloadFailsBeforePdfOutput(): void
    {
        global $pagenow;
        $pagenow = 'admin-post.php';
        $_GET = ['action' => 'mfb_download_labels', 'shipment_id' => 123];
        MFB_Test_State::$posts[123] = (object) ['ID' => 123, 'post_status' => 'mfb-booked', 'post_parent' => 456];
        try {
            MFB_Download_Labels::download_labels();
            self::fail('Expected a download error.');
        } catch (RuntimeException $exception) {
            self::assertSame(503, $exception->getCode());
            self::assertSame('SECRET technical API error: orders', $exception->getMessage());
        }
    }
}