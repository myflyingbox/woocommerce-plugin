<?php
/**
 * MFB_Dimension
 *
 * A dimension is a correspondance between a weight and a set of parcel
 * dimensions.
 * This is used to compute dimensions based on a the total weight of a cart.
 *
 */

class MFB_Dimension {

	public $id = 0;
	public $weight_from = 0;
	public $weight_to = 0;
	public $length = 0;
	public $width = 0;
	public $height = 0;

	public $post = null;

	public static $defaults = array(
		1 => array(1, 15), // = Up to 1kg: 15x15x15cm
		2 => array(2, 18),
		3 => array(3, 20),
		4 => array(4, 22),
		5 => array(5, 25),
		6 => array(6, 28),
		7 => array(7, 30),
		8 => array(8, 32),
		9 => array(9, 35),
		10 => array(10, 38),
		11 => array(15, 45),
		12 => array(20, 50),
		13 => array(30, 55),
		14 => array(40, 59),
		15 => array(50, 63)
	);

	public function __construct() {

	}


	public static function get( $dimension_id ) {

		if ( is_numeric( $dimension_id ) ) {
			$instance = new self();
			$instance->id   = absint( $dimension_id );
			$instance->post = get_post( $instance->id );
			$instance->populate();
		}
		return $instance;
	}

	public function populate() {
		$this->index        = get_post_meta( $this->id, '_index', true );
		$this->weight_from  = get_post_meta( $this->id, '_from',  true );
		$this->weight_to    = get_post_meta( $this->id, '_to',    true );
		$this->length       = get_post_meta( $this->id, '_length',true );
		$this->width        = get_post_meta( $this->id, '_width', true );
		$this->height       = get_post_meta( $this->id, '_height',true );
	}

	/**
	 * Returns all existing dimension objects.
	 * If none exist, then we initialize the default values.
	 */
	public static function get_all() {

		$all_dimensions = get_posts( array(
			'posts_per_page'=> -1,
			'post_type' 	=> 'mfb_dimension',
			'post_status' => 'private',
			'field' => 'ids',
			'orderby'  => array( 'meta_value_num' => 'ASC' ),
			'meta_key' => '_index'
		));

		$dimensions = array();

		foreach($all_dimensions as $dimension) {
			$dimensions[] = self::get($dimension->ID);
		}

		if ( count($dimensions) == 0 ) {
			return self::initialize_default_dimensions();
		} else {
			return $dimensions;
		}
	}

	public static function initialize_default_dimensions() {
		if ( count( get_posts( array(
			'post_type' 	=> 'mfb_dimension',
			'field' => 'ids'
		) ) ) > 0 ) return false;

		$from = 0;
		foreach( self::$defaults as $key => $dim ) {
			MFB_Dimension::create($key, $from, $dim[0], $dim[1], $dim[1], $dim[1]);
			$from = $dim[0];
		}

	}

	// Create a single dimension object
	public static function create($index, $from, $to, $length, $width, $height) {
		$dimension = array(
			'post_type' => 'mfb_dimension',
			'post_status' => 'private',
			'ping_status' => 'closed',
			'comment_status' => 'closed',
			'post_author' => 1,
			'post_password' => uniqid( 'dimension_' ),
			'post_title' => "$index - from $from to $to, $length x $width x $height"
		);

		$dimension_id = wp_insert_post( $dimension, true );

		if ( ! $dimension_id || is_wp_error( $dimension_id ) ) {
			return $dimension_id;
		} else {
			update_post_meta( $dimension_id, '_index',  $index );
			update_post_meta( $dimension_id, '_from',   $from );
			update_post_meta( $dimension_id, '_to',     $to );
			update_post_meta( $dimension_id, '_length', $length );
			update_post_meta( $dimension_id, '_width',  $width );
			update_post_meta( $dimension_id, '_height', $height );
		}
	}


	public function save() {

		wp_update_post( array(
			'ID'            => $this->id,
			'post_title'    => "$this->index - from $this->weight_from to $this->weight_to, $this->length x $this->width x $this->height"
		) );
		update_post_meta( $this->id, '_from',   $this->weight_from );
		update_post_meta( $this->id, '_to',     $this->weight_to );
		update_post_meta( $this->id, '_length', $this->length );
		update_post_meta( $this->id, '_width',  $this->width );
		update_post_meta( $this->id, '_height', $this->height );
	}

	// Return an MFB_Dimension based on a weight, or false if none could be found
	public static function get_for_weight( $weight ) {
		$args = array(
			'post_type'  => 'mfb_dimension',
			'post_status' => 'private',
			'meta_query' => array(
				array(
					'key'     => '_from',
					'value'   => $weight,
					'compare' => '<',
					'type'    => 'decimal'
				),
				array(
					'key'     => '_to',
					'value'   => $weight,
					'compare' => '>=',
					'type'    => 'decimal'
				)
			),
		);
		$query = new WP_Query( $args );
		if ($query->post_count == 1) {
			$res = self::get($query->post->ID);
			return $res;
		} else {
			return false;
		}
	}
}
