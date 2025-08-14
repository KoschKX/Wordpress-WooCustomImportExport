<?php
/**
 * Abstract Product importer
 *
 * @package  WooCommerce\Import
 * @version  3.1.0
 */

use Automattic\WooCommerce\Utilities\NumberUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/* DEPENDENCIES */

	if ( ! class_exists( 'WC_Importer_Interface', false ) ) {
		include_once WC_ABSPATH . 'includes/interfaces/class-wc-importer-interface.php';
	}

abstract class WC_Custom_Importer implements WC_Importer_Interface {

	protected $file = '';
	protected $file_position = 0;
	protected $params = array();
	protected $raw_keys = array();
	protected $mapped_keys = array();
	protected $raw_data = array();
	protected $file_positions = array();
	protected $parsed_data = array();
	protected $start_time = 0;

	public function get_raw_keys() {
		return $this->raw_keys;
	}

	public function get_mapped_keys() {
		return ! empty( $this->mapped_keys ) ? $this->mapped_keys : $this->raw_keys;
	}

	public function get_raw_data() {
		return $this->raw_data;
	}

	public function get_parsed_data() {
		return apply_filters( 'woocommerce_custom_importer_parsed_data', $this->parsed_data, $this );
	}

	public function get_params() {
		return $this->params;
	}

	public function get_file_position() {
		return $this->file_position;
	}

	public function get_percent_complete() {
		$size = filesize( $this->file );
		if ( ! $size ) {
			return 0;
		}
		return absint( min( NumberUtil::round( ( $this->file_position / $size ) * 100 ), 100 ) );
	}

	public function implode_recursive(string $separator, array $array): string{
	    $string = '';
	    foreach ($array as $i => $a) {
	        if (is_array($a)) {
	            $string .= $this->implode_recursive($separator, $a);
	        } else {
	            $string .= $a;
	            if ($i < count($array) - 1) {
	                $string .= $separator;
	            }
	        }
	    }
	    return $string;
	}

	protected function get_data_object( $data ) {
		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$object=(object)[];
		if($this->is_callable("get_object")&&isset($id)){
			$object=$this->get_object($id);
		}
		return apply_filters( 'woocommerce_custom_import_get_custom_object', $object, $data );
	}

	protected function process_item( $data , $update_existing) {

		try {

			if(!array_key_exists('id',$data)){
				if(array_key_exists('ID',$data))
				{
					$data['id']=$data['ID'];
					unset($data['ID']);
				}else{
					return new WP_Error( 'woocommerce_custom_importer_error', 'Object has no ID.');
				}
			}

			do_action( 'woocommerce_custom_import_before_process_item', $data );
			$data = apply_filters( 'woocommerce_custom_import_process_item_data', $data );

			$updating = false;
			if($this->is_callable("check_exist")){
				$updating = $this->check_exist($data['id']);
			}

			$object=null;
			if($update_existing||$updating){
				if($this->is_callable("get_data_object")){
					$object = $this->get_data_object($data);
				}

				if($this->is_callable("set_row_data")){
					$object = $this->set_row_data($object,$data);
				}
				/*
				else{
					foreach($data as $key=>$value){    
					    if($key!='meta_data'){
					    	if ( $this->is_callable( "set_column_{$key}" ) ) {
								$this->{"set_column_{$key}"}($key,$value);
							}else if($this->is_callable("set_data")){
								$this->set_object_data($key,$value,$updating);
							}
					    }
					}
				}
				*/
			}else{
				if($this->is_callable("new_row_data")){
					$object = $this->new_row_data($data);
				}
			}
	
			if ($object==null||is_wp_error( $object ) ) {
				return $object;
			}		

			if($this->is_callable("set_column_image_data_external")){
				$object = $this->set_column_image_data_external($object, $data);
			}

			$this->set_meta_data( $data );

			$object = apply_filters( 'woocommerce_custom_import_pre_insert_custom_object', $object, $data );

			do_action( 'woocommerce_custom_import_inserted_custom_object', $object, $data );

			return array(
				'id'      => $data['id'],
				'updated' => $updating,
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'woocommerce_custom_importer_error', $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

	protected function set_image_data( &$object, $data ) {
		if($this->is_callable("set_column_image_data")){
			$object = $this->set_column_image_data_external($object, $data);
		}
	}

	protected function set_meta_data( $data ) {
		if(!array_key_exists('id',$data)){return;}
		if ( isset( $data['meta_data'] ) ) {
			foreach ( $data['meta_data'] as $meta ) {
				if($this->is_callable("update_meta_data")){
					$this->update_meta_data($data, $meta['key'], $meta['value']);
				}
			}
		}
	}

	public function get_attachment_id_from_url( $url, $product_id ) {
		if ( empty( $url ) ) {
			return 0;
		}

		$id         = 0;
		$upload_dir = wp_upload_dir( null, false );
		$base_url   = $upload_dir['baseurl'] . '/';

		if ( false !== strpos( $url, $base_url ) || false === strpos( $url, '://' ) ) {
			$file = str_replace( $base_url, '', $url );
			$args = array(
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'fields'      => 'ids',
				'meta_query'  => array( // @codingStandardsIgnoreLine.
					'relation' => 'OR',
					array(
						'key'     => '_wp_attached_file',
						'value'   => '^' . $file,
						'compare' => 'REGEXP',
					),
					array(
						'key'     => '_wp_attached_file',
						'value'   => '/' . $file,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_wc_attachment_source',
						'value'   => '/' . $file,
						'compare' => 'LIKE',
					),
				),
			);
		} else {
			$args = array(
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'fields'      => 'ids',
				'meta_query'  => array( // @codingStandardsIgnoreLine.
					array(
						'value' => $url,
						'key'   => '_wc_attachment_source',
					),
				),
			);
		}
		$ids = get_posts( $args ); // @codingStandardsIgnoreLine.
		if ( $ids ) {
			$id = current( $ids );
		}
		if ( ! $id && stristr( $url, '://' ) ) {
			$upload = wc_rest_upload_image_from_url( $url );
			if ( is_wp_error( $upload ) ) {
				throw new Exception( $upload->get_error_message(), 400 );
			}
			$id = wc_rest_set_uploaded_image_as_attachment( $upload, $product_id );
			if ( ! wp_attachment_is_image( $id ) ) {
				throw new Exception( sprintf( __( 'Not able to attach "%s".', 'woocommerce' ), $url ), 400 );
			}
			update_post_meta( $id, '_wc_attachment_source', $url );
		}
		if ( ! $id ) {
			throw new Exception( sprintf( __( 'Unable to use image "%s".', 'woocommerce' ), $url ), 400 );
		}
		return $id;
	}

	public function get_attribute_taxonomy_id( $raw_name ) {
		global $wpdb, $wc_product_attributes;

		// These are exported as labels, so convert the label to a name if possible first.
		$attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
		$attribute_name   = array_search( $raw_name, $attribute_labels, true );

		if ( ! $attribute_name ) {
			$attribute_name = wc_sanitize_taxonomy_name( $raw_name );
		}

		$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

		if ( $attribute_id ) {
			return $attribute_id;
		}

		$attribute_id = wc_create_attribute(
			array(
				'name'         => $raw_name,
				'slug'         => $attribute_name,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);
		if ( is_wp_error( $attribute_id ) ) {
			throw new Exception( $attribute_id->get_error_message(), 400 );
		}
		$taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );
		register_taxonomy(
			$taxonomy_name,
			apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
			apply_filters(
				'woocommerce_taxonomy_args_' . $taxonomy_name,
				array(
					'labels'       => array(
						'name' => $raw_name,
					),
					'hierarchical' => true,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			)
		);
		$wc_product_attributes = array();
		foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
			$wc_product_attributes[ wc_attribute_taxonomy_name( $taxonomy->attribute_name ) ] = $taxonomy;
		}
		return $attribute_id;
	}

	protected function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;
		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}
		return apply_filters( 'woocommerce_custom_importer_memory_exceeded', $return );
	}

	protected function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}
		return intval( $memory_limit ) * 1024 * 1024;
	}

	protected function time_exceeded() {
		$finish = $this->start_time + apply_filters( 'woocommerce_custom_importer_default_time_limit', 20 ); // 20 seconds
		$return = false;
		if ( time() >= $finish ) {
			$return = true;
		}
		return apply_filters( 'woocommerce_custom_importer_time_exceeded', $return );
	}

	protected function explode_values( $value, $separator = ',' ) {
		$value  = str_replace( '\\,', '::separator::', $value );
		$values = explode( $separator, $value );
		$values = array_map( array( $this, 'explode_values_formatter' ), $values );

		return $values;
	}

	protected function explode_values_formatter( $value ) {
		return trim( str_replace( '::separator::', ',', $value ) );
	}

	protected function unescape_data( $value ) {
		$active_content_triggers = array( "'=", "'+", "'-", "'@" );

		if ( in_array( mb_substr( $value, 0, 2 ), $active_content_triggers, true ) ) {
			$value = mb_substr( $value, 1 );
		}
		return $value;
	}

	public function is_callable($func){
		return is_callable( array( $this, $func ));
	}

	public function console_log($message){
		echo "<script>console.log('".$message."');</script>";
	}

}
