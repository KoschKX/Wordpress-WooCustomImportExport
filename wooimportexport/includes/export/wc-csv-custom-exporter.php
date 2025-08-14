<?php
/**
 * Handles user CSV export.
 *
 * @package WooCommerce\Export
 * @version 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/* DEPENDENCIES */

	if ( ! class_exists( 'WC_CSV_Batch_Exporter', false ) ) {
		include_once WC_ABSPATH . 'includes/export/abstract-wc-csv-batch-exporter.php';
	}


class WC_CSV_Custom_Exporter extends WC_CSV_Batch_Exporter {

	public $export_type = '';

	public $export_args = array();

	public $enable_meta_export = false;

	public $types_to_export = array();

		public function __construct($opts=array()) {		
			parent::__construct();

			$this->set_options($opts);



			$this->set_types_to_export( array_keys( $this->get_default_types() ) );
		}

		public function enable_meta_export( $enable_meta_export ) {
			$this->enable_meta_export = (bool) $enable_meta_export;
		}

		public function set_types_to_export( $types_to_export ) {
			$this->types_to_export = array_map( 'wc_clean', $types_to_export );
		}

	public function set_options($opts) {
		if(!empty($opts)){
			$this->$export_type=$opts['type'];
			$this->$export_args=$opts['args'];
		}
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

	public function set_default_column_names($include_meta=false){
		$this->column_names=$this->get_default_column_names($include_meta);
	}

	public function get_default_column_names($include_meta=false) {
		
		global $terms_manager;

		$columns=array();
		
		$names = array();
		foreach ($terms_manager->{"get_{$this->export_type}_terms"}() as $name) {
			$names[$name['slug']] = __($name['name'],'woocommerce');
		}
		$columns= apply_filters(
			"woocommerce_{$this->export_type}_export_{$this->export_type}_default_columns",
			$names,
		);	
		
		if($this->is_callable("extend_default_column_names")){$columns=$this->extend_default_column_names($columns);}


		//error_log('test: '.$_POST['step']);

		/* META */
			if($include_meta){
				$meta_keys = array();
				if($this->is_callable("get_meta_keys")){$meta_keys=$this->get_meta_keys();}
			    if(count($meta_keys)){
				    									
				    $meta_keys_to_allow = apply_filters( 'woocommerce_'.$this->export_type.'_export_allow_meta_keys', array());
				    //$meta_keys_to_skip = apply_filters( 'woocommerce_user_export_skip_meta_keys', array());
				    
				    foreach ( $meta_keys as $meta_key ) {
				    	if(!in_array($meta_key,$meta_keys_to_allow, true)){continue;}
			
				    	$meta_column_key = 'Meta:' . esc_attr( $meta_key );
				    	$meta_column_name = sprintf( __( 'Meta: %s', 'woocommerce' ), $meta_key );
				    	$columns[$meta_column_key] = $meta_column_name;
				    }
				}
			}

		unset($terms_manager);

		return $columns;
	}

	public function get_default_types() {
		$types=array();

		if($this->is_callable( "set_types")){$types=$this->set_types();}

		return apply_filters( 'woocommerce_exporter_{$this->export_type}_types', $types );
	}

	public function prepare_data_to_export() {

		if (empty( $this->types_to_export ) ) {
			$this->types_to_export=$this->get_default_types();
		}

		if($this->is_callable("prepare_data")){
			$objs=$this->prepare_data($this->types_to_export);
		}else{
			$query = new WP_User_Query($export_args);
			$objs = $query->get_results();
			$this->total_rows =$query->found_posts;
		}
		
		$this->row_data = array();
		foreach ( $objs as $obj ) {
			$this->row_data[] = $this->generate_row_data( $obj );
		}
	}
	public function is_callable($func){
		return is_callable( array( $this, $func ));
	}

	protected function generate_row_data( $obj ) {
	
			$columns = $this->get_column_names();
			$row     = array();

			foreach ( $columns as $column_id => $column_name ) {

				$column_id = strstr( $column_id, ':' ) ? current( explode( ':', $column_id ) ) : $column_id;
				$value     = '';

				if ( in_array( $column_id, array( 'downloads', 'attributes', 'meta' ), true ) || ! $this->is_column_exporting( $column_id ) ) {
					continue;
				}

				//if($column_id=="wp_custom_import_error_log"||"wp_yoast_notification"){continue;}

				if ( has_filter( "woocommerce_{$this->export_type}_export_{$this->export_type}_column_{$column_id}" ) ) {
					$value = apply_filters( "woocommerce_{$this->export_type}_export_{$this->export_type}_column_{$column_id}", '', $obj, $column_id );
				}else if ( $this->is_callable( "get_column_{$column_id}" ) ) {
					$value = $this->{"get_column_{$column_id}"}($obj);
				}else{
					if($this->is_callable("set_column_special")&&$this->get_column_special($obj,$column_id)!=''){
						$value=$this->get_column_special($column_id);
					}else if($obj->{"{$column_id}"}!=null){
						$value = $obj->{"{$column_id}"};
						if(is_array($value)){
							if(is_string(array_keys($value)[0])){
								$value = implode(',', array_keys($value));
							}
						}
						if(!is_numeric($value)&&!is_string($value)){
							$value = '';
						}
					}else{
						$value = '';
					}
				}

				if ( 'description' === $column_id || 'short_description' === $column_id ) {
					$value = $this->filter_description_field( $value );
				}

				$row[ $column_id ] = $value;
			}

			$this->prepare_meta_for_export( $obj, $row );
			

		return apply_filters( 'woocommerce_user_export_row_data', $row, $obj );
	}

	protected function filter_description_field( $description ) {
		$description = str_replace( '\n', "\\\\n", $description );
		$description = str_replace( "\n", '\n', $description );
		return $description;
	}

	protected function prepare_meta_for_export( $obj, &$row ) {
		if ( $this->enable_meta_export ) {

		    $meta_keys = array();

		    if($this->is_callable("get_meta_keys")&&$this->is_callable("get_meta_value")){

		    	$meta_keys=$this->get_meta_keys();

				if (count( $meta_keys ) ) {

					$meta_keys_to_allow = apply_filters( 'woocommerce_'.$this->export_type.'_export_allow_meta_keys', array());
					//$meta_keys_to_skip = apply_filters( 'woocommerce_user_export_skip_meta_keys', array(), $user );

					$i = 1;
					foreach ( $meta_keys as $meta_key ) {
						if(!in_array($meta_key,$meta_keys_to_allow, true)){continue;}

						$meta_value=$this->get_meta_value($obj,$meta_key);
						
						if(is_array($meta_value)){					
							$meta_value = $this->implode_recursive(",",$meta_value);
							if($meta_value=="Array"){
								$meta_value='';
							}
						}

						if ( ! is_scalar( $meta_value ) ) {
							continue;
						}

						error_log("test");

						$column_key = 'Meta:' . esc_attr( $meta_key );

						$this->column_names[ $column_key ] = sprintf( __( 'Meta: %s', 'woocommerce' ), $meta_key );

						$row[ $column_key ]                = $meta_value;
						$i ++;
					}
				}
			}			
		}
	}

}
