<?php

if ( ! defined( 'ABSPATH' ) ) exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

class My_Flying_Box  extends WC_Shipping_Method {

	/**
	 * The single instance of My_Flying_Box.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * Settings class object
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;

	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token;

	/**
	 * The main plugin file.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for Javascripts.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct ( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token = 'my_flying_box';

		// Load frontend JS & CSS
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

		$this->includes();

		// Custom post types
		add_action( 'init', array( $this, 'register_custom_post_types' ), 10, 0 );

		// Includes with dependencies are loaded after all plugins have been loaded already
		add_action( 'woocommerce_loaded', array( $this, 'includes_with_dependencies' ), 10, 0 );

		$this->register_custom_post_statuses();

		// Load plugin environment variables
		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// register_activation_hook( __FILE__, array( $this, 'install' ) );

		// Add delivery location selector for shipment methods supporting it
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( &$this,'add_delivery_location_selector_to_shipping_method_label'), 10, 2 );
		add_action( 'init',  array( &$this,'load_delivery_location_selector_tools')  );


		// Save selected delivery location during cart checkout
		// add_action( 'woocommerce_checkout_update_order_meta', array( &$this, 'reset_selected_delivery_location' ) );
		add_action( 'woocommerce_checkout_process', array( &$this,'hook_process_order_checkout'));
		add_action( 'woocommerce_checkout_order_processed', array( &$this,'hook_new_order'));
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( &$this,'new_hook_new_order'));


		// Adds MyFlyingBox meta box on order page
		add_action( 'add_meta_boxes', array( &$this, 'load_admin_order_metabox' ) );
		add_action( 'add_meta_boxes_mfb_bulk_order', array( &$this, 'load_admin_bulk_order_metabox' ) );

		add_filter( 'bulk_actions-edit-mfb_bulk_order', array( $this, 'bulk_order_bulk_actions' ) );
		add_filter( 'post_row_actions', array( $this, 'bulk_order_row_actions' ), 10, 2 );

		add_filter( 'pre_get_posts', array( $this, 'bulk_order_admin_filters' ), 10, 1 );


		// Inject tracking link to order confirmation notification
		add_action( 'woocommerce_email_customer_details', array( &$this, 'add_tracking_link_to_email_notification' ), 15, 3 );

		// Add tracking link(s) to order summary page
		add_action( 'woocommerce_order_details_after_order_table', array( &$this,'add_tracking_link_to_order_page'), 10, 1 );

		// Load API for generic admin functions
		if ( is_admin() ) {
			$this->admin = new My_Flying_Box_Admin_API();
			new My_Flying_Box_Multiple_Shipment();
		}

		// Load Background Process class, to make the hook available when cron is run
		if ( is_admin() ) {
			$queue = new MFB_Bulk_Order_Background_Process();
			$queue_returns = new MFB_Bulk_Order_Return_Background_Process();
		}

		$api_env = My_Flying_Box_Settings::get_option('mfb_api_env');
		$api_login = My_Flying_Box_Settings::get_option('mfb_api_login');
		$api_password = My_Flying_Box_Settings::get_option('mfb_api_password');

		if ($api_env != 'staging' && $api_env != 'production') $api_env = 'staging';
		$this->api = Lce\Lce::configure($api_login, $api_password, $api_env, '2');
		$this->api->application = "woocommerce-mfb";
		$this->api->application_version = $this->_version . " (WOO: " . WC()->version . ")";

		// Handle localisation
		$this->load_plugin_textdomain();

		add_action( 'init', array( $this, 'load_localisation' ), 0 );


		// Add shipping methods
		add_filter('woocommerce_shipping_methods', array(&$this, 'myflyingbox_filter_shipping_methods'));
		//order admin check status
		// add_action('woocommerce_admin_order_data_after_order_details', array(&$this, 'myflyingbox_check_order_status'));
		add_action('rest_api_init', array(&$this, 'myflyingbox_set_order_status'));

	} // End __construct ()

	/**
	 * Wrapper function to register a new post type
	 * @param  string $post_type   Post type name
	 * @param  string $plural      Post type item plural name
	 * @param  string $single      Post type item single name
	 * @param  string $description Description of post type
	 * @return object              Post type class object
	 */
	public function register_post_type ( $post_type = '', $plural = '', $single = '', $description = '', $in_menu = false ) {

		if ( ! $post_type || ! $plural || ! $single ) return;

		$post_type = new My_Flying_Box_Post_Type( $post_type, $plural, $single, $description, $in_menu );

		return $post_type;
	}

	/**
	 * Wrapper function to register a new taxonomy
	 * @param  string $taxonomy   Taxonomy name
	 * @param  string $plural     Taxonomy single name
	 * @param  string $single     Taxonomy plural name
	 * @param  array  $post_types Post types to which this taxonomy applies
	 * @return object             Taxonomy class object
	 */
	public function register_taxonomy ( $taxonomy = '', $plural = '', $single = '', $post_types = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) return;

		$taxonomy = new My_Flying_Box_Taxonomy( $taxonomy, $plural, $single, $post_types );

		return $taxonomy;
	}

	/**
	 * Load frontend CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function enqueue_styles () {
		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function enqueue_scripts () {
      	$version_js = filemtime(plugin_dir_path(__DIR__) . 'assets/js/frontend' . $this->script_suffix . '.js');
		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), /*$version_js*/'0.0.1' );
		wp_enqueue_script( $this->_token . '-frontend' );
		$elements["ajax_url"] = admin_url('admin-ajax.php');
		$elements["site_url"] = get_site_url();
		$elements["extended_cover_checkbox_label"] = __( 'With Additional Guarantee', 'my-flying-box' );
		$elements["extended_cover_checkbox_value"] = WC()->session->get('myflyingbox_extended_cover');
		$locale = get_locale();
    	$lang = substr($locale, 0, 2);
		$lang = $lang ? $lang : "fr";
		$elements["lang"] = $lang;
		wp_localize_script($this->_token . '-frontend', 'mfb_var', $elements);
	} // End enqueue_scripts ()

	/**
	 * Load admin CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_styles ( $hook = '' ) {
		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_scripts ( $hook = '' ) {
		global $wp_query;

		$version_admin_js = filemtime(plugin_dir_path(__DIR__) . 'assets/js/admin' . $this->script_suffix . '.js');
		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), '0.0.1'/*$version_admin_js*/ );
		wp_localize_script( $this->_token . '-admin', 'plugin_url', plugins_url());
		$locale = get_locale();
    	$lang = substr($locale, 0, 2);
		$lang = $lang ? $lang : "fr";
		$extended_cover_cost_label = __('Total cost with extended cover :', 'my-flying-box' );
		$params = array(
			'ajax_url'                            => admin_url( 'admin-ajax.php' ),
			'labels_url'                          => admin_url( 'admin-post.php?action=mfb_download_labels' ),
			'lang'								  => $lang,
			'eclc'								  => $extended_cover_cost_label
		);
		wp_localize_script( $this->_token . '-admin', 'mfb_js_resources', $params );
		wp_enqueue_script( $this->_token . '-admin' );
		if ( isset($_GET['page']) && $_GET['page'] === 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'shipping' ) {
			wp_enqueue_media();
		}
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation () {
		load_plugin_textdomain( 'my-flying-box', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
			$domain = 'my-flying-box';

			$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

			load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
			load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()


	public function register_custom_post_types() {
		$this->register_post_type( 'mfb_shipment', __( 'Shipments', 'my-flying-box' ), __( 'Shipment', 'my-flying-box' ), '', true );
		$this->register_post_type( 'mfb_carrier', __( 'Carriers', 'my-flying-box' ), __( 'Carrier', 'my-flying-box' ), '', false );
		$this->register_post_type( 'mfb_dimension', __( 'Dimensions', 'my-flying-box' ), __( 'Carrier', 'my-flying-box', '', false ) );
		$this->register_post_type( 'mfb_offer', __( 'Offers', 'my-flying-box' ), __( 'Offer', 'my-flying-box', '', false ) );


		$labels = array(
				'name'                  => __( 'Bulk Orders', 'my-flying-box' ),
				'singular_name'         => __( 'Bulk Order', 'my-flying-box' ),
				'menu_name'             => __( 'Bulk Orders', 'my-flying-box' )
		);

		$args = array(
						'label'              => __( 'Bulk Orders', 'my-flying-box' ),
						'labels'             => $labels,
						'public'             => true,
						'exclude_from_search' => true,
						'publicly_queryable' => false,
						'show_ui'            => true,
						'show_in_menu'       => 'my-flying-box',
						'query_var'          => true,
						'capability_type'    => 'post',
						'capabilities'       => array('create_posts' => false, 'edit' => true),
						'map_meta_cap'       => true,
						'supports'           => array('title' => false),
				);

		register_post_type( 'mfb_bulk_order', $args );


		// Now we register filters to manage columns on index views
		add_filter( 'manage_mfb_bulk_order_posts_columns', array( $this, 'bulk_order_columns' ) );
		add_action( 'manage_mfb_bulk_order_posts_custom_column', array( $this, 'render_bulk_order_columns' ), 2 );

	}

	public function register_custom_post_statuses(){
		register_post_status( 'mfb-inactive', array(
			'label'                     => _x( 'Disabled', 'my-flying-box' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => false,
			'label_count'               => _n_noop( 'Disabled <span class="count">(%s)</span>', 'Disabled <span class="count">(%s)</span>' ),
		) );

		register_post_status( 'mfb-active', array(
			'label'                     => _x( 'Enabled', 'my-flying-box' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => false,
			'label_count'               => _n_noop( 'Enabled <span class="count">(%s)</span>', 'Enabled <span class="count">(%s)</span>' ),
		) );

		register_post_status( 'mfb-draft', array(
			'label'                     => _x( 'Draft shipment', 'my-flying-box' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => false,
			'label_count'               => _n_noop( 'Draft <span class="count">(%s)</span>', 'Draft <span class="count">(%s)</span>' ),
		) );

		register_post_status( 'mfb-booked', array(
			'label'                     => _x( 'Booked shipment', 'my-flying-box' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => false,
			'label_count'               => _n_noop( 'Booked <span class="count">(%s)</span>', 'Booked <span class="count">(%s)</span>' ),
		) );

		register_post_status( 'mfb-processing', array(
			'label'                     => _x( 'Processing', 'my-flying-box' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Processing <span class="count">(%s)</span>', 'Processing <span class="count">(%s)</span>' ),
		) );

		register_post_status( 'mfb-processed', array(
			'label'                     => _x( 'Processed', 'my-flying-box' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Processed <span class="count">(%s)</span>', 'Processed <span class="count">(%s)</span>' ),
		) );

	}


	/**
	 * Main My_Flying_Box Instance
	 *
	 * Ensures only one instance of My_Flying_Box is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see My_Flying_Box()
	 * @return Main My_Flying_Box instance
	 */
	public static function instance ( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __wakeup ()


	private function includes() {

		include_once( 'lib/php-lce/bootstrap.php' );
		include_once( 'class-mfb-carrier.php' );
		include_once( 'class-mfb-shipping-method.php' );
		include_once( 'class-mfb-quote.php' );
		include_once( 'class-mfb-offer.php' );
		include_once( 'class-mfb-dimension.php' );
		include_once( 'class-mfb-shipment.php' );
		include_once( 'class-mfb-bulk-order.php' );
		if ( is_admin() ) {
			include_once( 'class-mfb-admin-menus.php' );
			include_once( 'class-mfb-download-labels.php' );
		}

		if ( $this->is_request( 'ajax' ) ) {
			$this->ajax_includes();
		}
	}

	public function includes_with_dependencies() {
		// Checking if dependency is met
		if ( class_exists( 'WP_Background_Process' ) ) {
			include_once( 'class-mfb-bulk-order-background-process.php' );
			include_once( 'class-mfb-bulk-order-return-background-process.php' );
		}
	}

	/**
	 * Include required ajax files.
	 */
	public function ajax_includes() {
		include_once( 'class-mfb-ajax.php' ); // Ajax functions for admin and the front-end
	}


	/**
	 * Add MFB shipping methods, based on active services
	 */
	public function myflyingbox_filter_shipping_methods( $methods ) {

		$active_carriers = MFB_Carrier::get_all_active();

		foreach ( $active_carriers as $key => $carrier ) {
			$method_name = $carrier->code;
			if ( ! class_exists($method_name)){
				eval("class $method_name extends MFB_Shipping_Method{}");
			}
			if ( !in_array( $method_name, $methods ) ) {
				$methods[$method_name] = $method_name;
			}
		}

		return $methods;
	}

	/**
	 * Reset delivery location, in the case of shop delivery.
	 */
	public function reset_selected_delivery_location( $order_id ) {
		update_post_meta( $order_id, '_mfb_delivery_location', '' );
	}


	/**
	 * Validate the checkout form, so we can save the delivery location
	**/
	public function hook_process_order_checkout() {

		// Check if the parcel point is needed and if it has been chosen
		if (isset($_POST['shipping_method']))	{
			foreach($_POST['shipping_method'] as $shipping_method) {
				$carrier_code = explode(':',$shipping_method)[0];
				$carrier = MFB_Carrier::get_by_code( $carrier_code );
				if ($carrier && $carrier->shop_delivery) {
					if (!isset($_POST['_delivery_location'])) {
						wc_add_notice(__('Please select a delivery location','my-flying-box'),'error');
					}

				}
			}
		}
	}

	public function new_hook_new_order($order) {
		$order_id = $order->get_id();
		$json = file_get_contents('php://input');
    	$params = json_decode( $json, true );

		if(!isset($params['extra_data'])) {
			wc_add_notice(__('An error occured','my-flying-box'),'error');
		}

		$data = $params['extra_data'];

		if (isset($data['shipping_method']) && trim($data['shipping_method']))	{
			$carrier = MFB_Carrier::get_by_code( $data['shipping_method'] );
			if ($carrier && $carrier->shop_delivery) {
				if(!isset($data['delivery_location']) || (isset($data['delivery_location']) && !$data['delivery_location'])) {
					wc_add_notice(__('Please select a delivery location','my-flying-box'),'error');
				}
			}
		}

		// We save the latest quote associated to this cart
		update_post_meta( $order_id, '_mfb_last_quote_id', WC()->session->get('myflyingbox_shipment_quote_id') );

		if (isset($data['shipping_method']))	{
				$carrier_code = $data['shipping_method'];
				$carrier = MFB_Carrier::get_by_code( $carrier_code );
				update_post_meta( $order_id, '_mfb_carrier_code', $carrier_code );

				if ( $carrier && $carrier->shop_delivery ) {
					if ( isset($data['delivery_location']) ) {
						update_post_meta( $order_id, '_mfb_delivery_location', $data['delivery_location'] );

						// Trying to get the details of the location
						$quote = MFB_Quote::get( WC()->session->get('myflyingbox_shipment_quote_id') );

						$street = $data['shipping_address_1'];
						$street_line_2 = $data['shipping_address_2'];
						if ( ! empty( $street_line_2 ) ) {
							$street .= "\n".$street_line_2;
						}

						$params = array(
							'street' => $street,
							'city' => $quote->params['recipient']['city']
						);

						$locations = $quote->offers[$carrier_code]->get_delivery_locations($params);
						foreach( $locations as $loc ) {
							if ( $loc->code == $_POST['_delivery_location'] ) {
								update_post_meta( $order_id, '_mfb_delivery_location_name', $loc->company );
								update_post_meta( $order_id, '_mfb_delivery_location_street', $loc->street );
								update_post_meta( $order_id, '_mfb_delivery_location_city', $loc->city );
								update_post_meta( $order_id, '_mfb_delivery_location_postal_code', $loc->postal_code );
							}
						}
					}
				}
		}
	}

	public function hook_new_order($order_id) {
		// We save the latest quote associated to this cart
		update_post_meta( $order_id, '_mfb_last_quote_id', WC()->session->get('myflyingbox_shipment_quote_id') );

		if (isset($_POST['shipping_method']))	{
			foreach($_POST['shipping_method'] as $shipping_method) {
				$carrier_code = explode(':',$shipping_method)[0];
				$carrier = MFB_Carrier::get_by_code( $carrier_code );
				update_post_meta( $order_id, '_mfb_carrier_code', $carrier_code );

				if ( $carrier && $carrier->shop_delivery ) {
					if ( isset($_POST['_delivery_location']) ) {
						update_post_meta( $order_id, '_mfb_delivery_location', $_POST['_delivery_location'] );

						// Trying to get the details of the location
						$quote = MFB_Quote::get( WC()->session->get('myflyingbox_shipment_quote_id') );

						$street = isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1 ? $_REQUEST['shipping_address_1'] : $_REQUEST['billing_address_1'];
						$street_line_2 = isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1 ? $_REQUEST['shipping_address_2'] : $_REQUEST['billing_address_2'];
						if ( ! empty( $street_line_2 ) ) {
							$street .= "\n".$street_line_2;
						}

						$params = array(
							'street' => $street,
							'city' => $quote->params['recipient']['city']
						);

						$locations = $quote->offers[$carrier_code]->get_delivery_locations($params);
						foreach( $locations as $loc ) {
							if ( $loc->code == $_POST['_delivery_location'] ) {
								update_post_meta( $order_id, '_mfb_delivery_location_name', $loc->company );
								update_post_meta( $order_id, '_mfb_delivery_location_street', $loc->street );
								update_post_meta( $order_id, '_mfb_delivery_location_city', $loc->city );
								update_post_meta( $order_id, '_mfb_delivery_location_postal_code', $loc->postal_code );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Extending labels of shipping methods for MFB services:
	 *  - add description
	 *  - add selector of delivery location when applicable
	 *
	 */
	public function add_delivery_location_selector_to_shipping_method_label($full_label, $shipping_rate){
		global $pagename;

		$method_code = $shipping_rate->method_id;
		$service_id = $shipping_rate->id;
		$carrier = MFB_Carrier::get_by_code( $method_code );

		$instance_id = explode(':', $service_id)[1];

		// If this is not a MFB service, we do not do anything.
		if ( ! $carrier )
			return $full_label;

		// Relay selection must happen only during the checkout itself, not on the cart page
		if ( $pagename == 'cart' )
			return $full_label;

		if ( ! class_exists( $method_code ))
			eval("class $method_code extends MFB_Shipping_Method{}");

		$method = new $method_code();

		// Adding description, if present
		if ( ! empty($method->description) )
			$full_label .= '<br/><span class="description">'.$method->description.'</span>';

		// Add a selector of available delivery locations, pulled from the API
		if ( ( (stristr(wc_get_checkout_url(), $_SERVER['REQUEST_URI']) ||  (stristr(wc_get_checkout_url(), $_SERVER['HTTP_REFERER'] ) ) ) )
					&& in_array($service_id , WC()->session->get('chosen_shipping_methods') ) ) {

			if ($carrier->shop_delivery) {

				// Initializing currently valid quote; We need the offer's uuid to request the locations
				$quote = MFB_Quote::get( WC()->session->get('myflyingbox_shipment_quote_id') );

				$full_label .=  '<br/><span data-mfb-action="select-location" data-mfb-method-id="'.$method->id.'" data-mfb-instance-id="'.$instance_id.'" data-mfb-offer-uuid="'.$quote->offers[$method->id]->api_offer_uuid.'" id="locationselector">'.__( 'Choose a location', 'my-flying-box' ).'</span>';
				$full_label .=  '<br/><span>'.__( 'Selected ', 'my-flying-box' ).' : <span id="mfb-location-client"></span></span>';
				$full_label .=  '<span id="input_'.$method->id.'"></span>';
			}
			$full_label .=  '<span data-mfb-method-input-id="'.$method->id.'" ></span>';
		}
		return $full_label;
	}


	public function load_delivery_location_selector_tools( $checkout ) {
			$translations = array(
				'Unable to load parcel points' => __( 'Unable to load parcel points', 'my-flying-box' ),
				'Select this location' => __( 'Select this location', 'my-flying-box' ),
				'day_1' => __( 'Monday', 'my-flying-box' ),
				'day_2' => __( 'Tuesday', 'my-flying-box' ),
				'day_3' => __( 'Wednesday', 'my-flying-box' ),
				'day_4' => __( 'Thursday', 'my-flying-box' ),
				'day_5' => __( 'Friday', 'my-flying-box' ),
				'day_6' => __( 'Saturday', 'my-flying-box' ),
				'day_7' => __( 'Sunday', 'my-flying-box' )
			);

			wp_enqueue_script( 'jquery' );
			//wp_enqueue_script( 'gmap', '//maps.google.com/maps/api/js?sensor=false' );

			// Google APIs should not be loaded twice. We will make sure that it is only loaded once.
			global $wp_scripts;
			$google_apis = array();

			// First, we identify any registered script that corresponds to Google maps API
			foreach((array)$wp_scripts->registered as $script) {
				if(strpos($script->src, 'maps.googleapis.com/maps/api/js') !== false or strpos($script->src, 'maps.google.com/maps/api/js') !== false )
					$google_apis[] = $script;

			}

			// We will store the libraries called, to make sure that nothing is forgotten
			$libraries = array();
			$unregistered = array();

			foreach($google_apis as $g) {
				wp_dequeue_script($g->handle); // Temporarily deregistering the script
				$unregistered[] = $g->handle;

				// Extracting any specifically mentioned library
				$qs = parse_url($g->src);
				if ( array_key_exists('query', $qs) ) {
					$qs = $qs['query'];
				} else {
					$qs = '';
				}
				parse_str($qs, $params);
				if ( isset($params['libraries']) ) {
					$libraries = array_merge($libraries, explode(',', $params['libraries']) );
				}
			}

			// Updating deprecated dependency information that was based on old script handlers
			// We will use only one handler: google-api-grouped
			foreach($wp_scripts->registered as $i=>$script) {
				foreach($script->deps as $j => $dept) {
					if(in_array($dept, $unregistered)) {
						$script->deps[$j] = 'google-api-grouped';
					}
				}
			}

			$library = '';
			if(count($libraries))
				$library = 'libraries='.implode(',', $libraries).'&';

			$google_api_key = My_Flying_Box_Settings::get_option('mfb_google_api_key');

			// Finally, enqueuing the script again.
			wp_enqueue_script( 'google-api-grouped', '//maps.googleapis.com/maps/api/js?'.$library.'key='.$google_api_key, array(), '', true);


			wp_enqueue_script( 'mfb_delivery_locations', plugins_url().'/my-flying-box/assets/js/delivery_locations.js', array( 'jquery', 'google-api-grouped' ) );
			wp_localize_script( 'mfb_delivery_locations', 'plugin_url', plugins_url() );
			wp_localize_script( 'mfb_delivery_locations', 'lang', $translations );
			wp_localize_script( 'mfb_delivery_locations', 'map', My_Flying_Box::generate_google_map_html_container() );

			// Get the protocol of the current page
			$protocol = isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://';

			$params = array(
					// Get the url to the admin-ajax.php file using admin_url()
					// All Ajax requests in WP go to admin-ajax.php
					'ajaxurl' => admin_url( 'admin-ajax.php', $protocol ),
					'action'  => 'mfb_get_delivery_locations'
			);
			// Print the script to our page
			wp_localize_script( 'mfb_delivery_locations', 'mfb_params', $params );
	}

	public static function generate_google_map_html_container() {
		return  '<div id="map-container">
							 <p>
								 <a class="mfb-close-map">'.__( 'Hide map', 'my-flying-box' ).'</a>
							 </p>
							 <div id="map-canvas"></div>
						 </div>';
	}

	public function load_admin_order_metabox() {
		foreach ( wc_get_order_types( 'order-meta-boxes' ) as $type ) {

			$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: $type;

			//$order_type_object = get_post_type_object( $type );
			add_meta_box( 'myflyingbox-order-shipping', __( 'Shipping - My Flying Box', 'my-flying-box' ), 'MFB_Meta_Box_Order_Shipping::output', $screen, 'normal', 'high' );
		}
	}

	public function load_admin_bulk_order_metabox() {
			add_meta_box( 'myflyingbox-bulk-order-edit', __( 'List of shipments', 'my-flying-box' ), 'MFB_Meta_Box_Bulk_Order::output', 'mfb_bulk_order', 'normal', 'high' );
	}

	/**
	 * What type of request is this?
	 * string $type ajax, frontend or admin
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin' :
				return is_admin();
			case 'ajax' :
				return defined( 'DOING_AJAX' );
			case 'cron' :
				return defined( 'DOING_CRON' );
			case 'frontend' :
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}

	// Returns an array of tracking links and corresponding tracking number for a given order
	public function get_tracking_links( $order ) {
		$shipments = MFB_Shipment::get_all_for_order( $order->get_id() );
		$links = array();
		// We deal with each shipment
		foreach( $shipments as $shipment ) {
			// We only continue if we find the corresponding carrier and it has a tracking URL
			$carrier = MFB_Carrier::get_by_code( $shipment->offer->product_code );
			if ( $shipment->status == 'mfb-booked' && $carrier && $carrier->tracking_url ) {
				$tracking_numbers = array();
				foreach ( $shipment->parcels as $parcel ) {
					$tracking_numbers[] = $parcel->tracking_number;
				}
				$tracking_numbers = array_unique( $tracking_numbers );
				$tracking_numbers = array_filter( $tracking_numbers, function($v){ return !empty($v);});
				foreach( $tracking_numbers as $tracking_number ) {
					$links[] = array(	'link' => $carrier->tracking_url_for( $tracking_number, $shipment->receiver->postal_code ),
														'code' => $tracking_number);
				}
			}
		}
		return $links;
	}

	// Returns an array of relay delivery addresses for a given order
	public function get_relay_addresses( $order ) {
		$shipments = MFB_Shipment::get_all_for_order( $order->get_id() );
		$addresses = array();
		// We deal with each shipment
		foreach( $shipments as $shipment ) {
			// We only continue if the delivery is for a relay
			if ( $shipment->status == 'mfb-booked' && $shipment->delivery_location ) {
				$loc = $shipment->delivery_location;
				$addresses[$loc->code] = $loc;
			}
		}
		return $addresses;
	}


	public function add_tracking_link_to_email_notification( $order, $sent_to_admin, $plain_text ) {
		$links = MFB()->get_tracking_links( $order );
		if ( count($links) > 0) {
			include( dirname ( dirname( __FILE__ ) ) . '/includes/views/email-tracking.php');
		}
		$addresses = MFB()->get_relay_addresses( $order );
		if ( count($addresses) > 0) {
			include( dirname ( dirname( __FILE__ ) ) . '/includes/views/email-relay-address.php');
		}
	}
	public function add_tracking_link_to_order_page( $order ) {
		$shipments = MFB_Shipment::get_all_for_order( $order->get_id() );
		if(!empty($shipments) && isset($shipments[0]) && isset($shipments[0]->api_order_uuid)) {
			if(isset($shipments[0]->tracking) && !empty($shipments[0]->tracking) && isset($shipments[0]->tracking[0])) {
				$locale = get_locale();
    			$lang = substr($locale, 0, 2);
				if(!trim($lang)) {
					$lang = "fr";
				}
				$tracking = $shipments[0]->tracking[0];
				if (!empty($tracking->events)) {
					$api_order_uuid = $shipments[0]->api_order_uuid;
					$order_id = $order->get_id();
					include( dirname ( dirname( __FILE__ ) ) . '/includes/views/order-page-tracking-status.php');
				}
			}
		}
		$links = MFB()->get_tracking_links( $order );
		if ( count($links) > 0) {
			include( dirname ( dirname( __FILE__ ) ) . '/includes/views/order-page-tracking.php');
		}
		$addresses = MFB()->get_relay_addresses( $order );
		if ( count($addresses) > 0) {
			include( dirname ( dirname( __FILE__ ) ) . '/includes/views/order-page-relay-address.php');
		}
	}

	public function bulk_order_columns( $existing_columns ) {
		$columns                     = array();
		$columns['cb']               = $existing_columns['cb'];
		$columns['bulk_order_title']  =  __( 'Reference', 'my-flying-box' );
		$columns['bulk_order_date']  =  __( 'Date', 'my-flying-box' );
		$columns['bulk_order_status']  =  __( 'Status', 'my-flying-box' );
		$columns['orders']  =  __( 'Orders', 'my-flying-box' );
		$columns['bulk_order_actions']  =  __( 'Actions', 'my-flying-box' );

		return $columns;
	}

	public function render_bulk_order_columns( $column ) {
		global $post, $the_bulk_order;

		if ( empty( $the_bulk_order ) || $the_bulk_order->id != $post->ID ) {
			$the_bulk_order = MFB_Bulk_Order::get( $post->ID );
		}

		switch ( $column ) {

			case 'bulk_order_title' :
				echo '<a href="' . admin_url( 'post.php?post=' . absint( $the_bulk_order->id ) . '&action=edit' ) . '" class="row-title">';
				echo $the_bulk_order->id;
				echo '</a>';
			break;

			case 'bulk_order_status' :
				echo $the_bulk_order->status;

			break;


			case 'bulk_order_date' :

				$t_time = get_the_time( __( 'Y/m/d g:i:s A', 'my-flying-box' ), $post );
				$h_time = get_the_time( __( 'Y/m/d', 'my-flying-box' ), $post );

				echo '<abbr title="' . esc_attr( $t_time ) . '">' . esc_html( apply_filters( 'post_date_column_time', $h_time, $post ) ) . '</abbr>';

			break;

			case 'orders' :
				$res = implode(', ', $the_bulk_order->wc_order_ids);
				echo $res;
			break;

			case 'bulk_order_actions' :

				?><p>
					<?php

						$actions = array();

						if ( $the_bulk_order->status == 'draft' ) {
							$actions['delete'] = array(
								'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=wp_ajax_mfb_delete_bulk_order&bulk_order_id=' . $post->ID ), 'my-flying-box-delete-bulk-order' ),
								'name'      => __( 'Delete', 'my-flying-box' ),
								'action'    => "delete"
							);
						}

						$actions['view'] = array(
							'url'       => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
							'name'      => __( 'View', 'my-flying-box' ),
							'action'    => "view"
						);

						foreach ( $actions as $action ) {
							printf( '<a class="button tips %s" href="%s" data-tip="%s">%s</a>', esc_attr( $action['action'] ), esc_url( $action['url'] ), esc_attr( $action['name'] ), esc_attr( $action['name'] ) );
						}

					?>
				</p><?php

			break;
		}
	}

	public function bulk_order_row_actions( $actions, $post ) {
		global $pagenow;

		if ($pagenow == 'edit.php' && $post->post_type == 'mfb_bulk_order') {
			return array();
		} else {
			return $actions;
		}
	}

	public function bulk_order_admin_filters( $query ) {
		global $post_type, $pagenow;

		if ($pagenow == 'edit.php' && $post_type == 'mfb_bulk_order') {
			if( !isset($_GET['post_status']) || $_GET['post_status'] == 'all' ) {
				$query->query_vars['post_status'] = array('mfb-processing','draft','mfb-draft','mfb-processed');
			}
		}
		return $query;
	}


	public function bulk_order_bulk_actions( $actions ) {

		// if ( isset( $actions['edit'] ) ) {
		//   unset( $actions['edit'] );
		// }

		// return $actions;
		return array();
	}

	public function myflyingbox_check_order_status($order) {
		$shipments = MFB_Shipment::get_all_for_order( $order->get_id() );
		$last_event = "";
		if(!empty($shipments) && isset($shipments[0]) && isset($shipments[0]->api_order_uuid)) {
			if(isset($shipments[0]->tracking) && !empty($shipments[0]->tracking) && isset($shipments[0]->tracking[0])) {
				$locale = get_locale();
    			$lang = substr($locale, 0, 2);
				if(!trim($lang)) {
					$lang = "fr";
				}
				$tracking = $shipments[0]->tracking[0];
				if (!empty($tracking->events)) {
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
						$last_event .= '<strong>'.__('Last status :', 'my-flying-box').'</strong><br>';
						$last_event .= '<label>'.__('Code :', 'my-flying-box') . '</label> ' . esc_html($latest_event->code) . '<br>';
						$last_event .= '<label>'.__('Label :', 'my-flying-box') . '</label> ' . esc_html($latest_event->label->{$lang}) . '<br>';
						$last_event .= '<label>'.__('Date :', 'my-flying-box') . '</label> ' . date('d/m/Y H:i', strtotime($latest_event->happened_at)) . '<br>';

						if (!empty($latest_event->location)) {
							$loc = $latest_event->location;
							$last_event .= __('Place :', 'my-flying-box');
							$last_event .= " ";
							if (!empty($loc->name)) $last_event .= esc_html($loc->name) . ', ';
							if (!empty($loc->street)) $last_event .= esc_html($loc->street) . ', ';
							if (!empty($loc->postal_code)) $last_event .= esc_html($loc->postal_code) . ' ';
							if (!empty($loc->city)) $last_event .= esc_html($loc->city) . ', ';
							$last_event .= esc_html($loc->country ?? '');
						}
						$links = MFB()->get_tracking_links( $order );
						if ( count($links) > 0) {
							$last_event .= '<br><br><strong>'.__( "Track your shipment", 'my-flying-box' ).'</strong>';
							if ( count($links) > 1 ) {
								$last_event .= '<p>'.__( "Direct links to track your shipments:", 'my-flying-box' );
							} else {
								$last_event .= '<p>'.__( "Direct link to track your shipment:", 'my-flying-box' );
							}
							foreach ( $links as $link ) {
								$last_event .= '<br/><a href="'.$link['link'].'">'.$link['code'].'</a>';
							}
							$last_event .= '</p>';
						}
						if("delivered" == $latest_event->code) {
							if ( $order && $order instanceof WC_Order && $order->get_status() !== 'completed' ) {
								$order->set_status('completed');
								$order->save();
								$order->add_order_note(__('Status automatically updated by MFB shipment status.', 'my-flying-box'));
							}
						}
					}
				}
			}
		?>
		<div id="last-status" style="margin-top:25px;display:inline-block;"><?php echo $last_event; ?></div>
		<div class="mfb-order-check-status-button" style="display: inline-block;margin-top: 25px;">
			<button id="trackthis" type="button" class="button button-primary" data-order_id="<?php echo $order->get_id(); ?>" data-api_uuid="<?php echo $shipments[0]->api_order_uuid; ?>">
				<?php _e( 'Check Status', 'my-flying-box' ); ?>
			</button>
		</div>
    	<?php
		}
	}
	
	public function myflyingbox_set_order_status() {
		register_rest_route('myflyingbox/v1', '/update-order-status', [
			'methods' => 'POST',
			'callback' => [$this, 'mfb_update_order_status'],
            'permission_callback' => [$this, 'mfb_authorize_request'],
			'args' => [
				'event_type' => [
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'object_uuid' => [
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);
	}

	public function mfb_update_order_status(WP_REST_Request $request) {
		$status = $request->get_param('event_type');
		$api_order_uuid = $request->get_param('object_uuid');

		if(strtolower($status) == "order_delivered") {

			$order = MFB_Shipment::get_wc_order_by_api_uuid( $api_order_uuid );

			if (!$order) {
				return new WP_REST_Response(['error' => 'Commande introuvable.'], 404);
			}
			
			$shipment_id = (int) MFB_Shipment::get_shipment_by_api_uuid($api_order_uuid);
			if($shipment_id) {
				update_post_meta($shipment_id, '_delivered', 1);
			}

			try {
				$all_shipments = get_children(array(
					'post_type'			=> 'mfb_shipment',
					'post_status'		=> array('private', 'mfb-draft', 'mfb-booked'),
					'post_parent'		=> $order->id,
					'field'					=> 'ids',
					'orderby'				=> array('date' => 'DESC')
				));

				$all_delivered = true;
				if (!empty($all_shipments)) {
					foreach ($all_shipments as $shipment) {
						$event_data = get_post_meta($shipment->ID, '_delivered', true);
						if ($all_delivered && !$event_data) {
							$all_delivered = false;
							break;
						}
					}
				} else {
					$all_delivered = false;
				}
				if ($all_delivered) {
					$order = wc_get_order($order->id);
					if ( $order && $order instanceof WC_Order && $order->get_status() !== 'completed' ) {
						$order->set_status("completed");
						$order->save();
						$order->add_order_note(__('Status automatically updated by MFB shipment status.', 'my-flying-box'));
					}
				} else {
					return new WP_REST_Response([
						'success' => false,
						'message' => 'woocommerce order not yet in completed status.',
						'event_type' => $status
					], 202);
				}
				return new WP_REST_Response(['success' => true]);
			} catch (Exception $e) {
				return new WP_REST_Response(['error' => $e->getMessage()], 500);
			}
		} else {
			return new WP_REST_Response([
				'success' => false,
				'message' => 'No action required for this type of event.',
				'event_type' => $status
			], 202);
		}
	}

	public function mfb_authorize_request(WP_REST_Request $request) {
		$stored_token = My_Flying_Box_Settings::get_option('mfb_token_auth');

		if (!$stored_token) {
			return false;
		}

		$auth_header = $request->get_header('authorization');

		if ($auth_header && preg_match('/Token token="(.+)"/', $auth_header, $matches)) {
			$sent_token = $matches[1];
		} else {
			$sent_token = $request->get_header('mfb_token_auth') ?: $request->get_param('mfb_token_auth');
		}

		if (!$sent_token) {
			return false;
		}

		return hash_equals($stored_token, $sent_token);
	}

}
