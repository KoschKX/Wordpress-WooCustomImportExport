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

	if ( ! class_exists( 'WC_CSV_Custom_Exporter', false ) ) {
		include_once WOOIMPORTEXPORT_DIR . '/includes/export/wc-csv-custom-exporter.php';
	}

class WC_User_CSV_Exporter extends WC_CSV_Custom_Exporter {

	public $export_type = 'user';
	public $export_args = array();
	//public $enable_meta_export = false;

	public $column_names = array();

	public $total_users = -1;

	public function extend_default_column_names($column_names) {
		
			/*
			if ( $this->enable_meta_export ) {
				$meta_keys = array();
				if($this->is_callable("get_meta_keys")){
					$meta_keys = $this->get_meta_keys();
				}
				foreach ( $meta_keys as $key ) {
					$column_names[$key] = 'Meta: '.$key;
				}
			}
			*/

			// EXTENDONS REGISTRATION FIELDS
				/*
				if(class_exists("EXT_Registration_Fields_Front"))
				{
					$ext=new EXT_Registration_Fields_Front();
					$custom_fields=$ext->get_custom_fields();
					foreach ($custom_fields as $custom_field) {
						$column_names[$custom_field->field_name] = $custom_field->field_label;
					}
					unset($ext);
				}
				*/

		return $column_names;
	}

	public function set_types() {
		global $wp_roles;

		$wp_user_roles = $wp_roles->get_names();
		$user_roles = array();
		$idx=0;
		foreach($wp_user_roles as $user_role){
			$skip_role=false;
			if (strpos($user_role, 'BackWPup') !== false) {
				$skip_role=true;
			}
			if(!$skip_role){
				$user_roles[$user_role]=$user_role;
			}
			$idx+=1;
		}
		return $user_roles;
	}

	public function prepare_data($types) {

		$this->limit=300;
		
		//if($total_users==-1){
		/*
			$user_query = new WP_User_Query( array( 'role__in' => $types ) );
			$users_count = $user_query->get_total();
			$total_users = $users_count;
		*/

		/*
		if($this->total_users==-1){
			$this->total_users = count( get_users( array( 'fields' => array( 'ID' ), 'role__in' => $types ) ) );
		}
		*/

		//$this->total_rows = $this->total_users;

		$args = array(
			'role__in' => $types,
			'number'    => $this->get_limit(),
			//'offset'    => ($this->get_page()-1)*$this->get_limit(),
			'paged'     => $this->get_page(),
			//'count_total' => false,
		);
	
		$all_users = new WP_User_Query($args);
		$users = $all_users->get_results();
		$this->total_rows = $all_users->get_total();
		
		//error_log($this->get_page(). ' : '.$this->get_limit(). ' : '.$this->total_rows);
		
		return $users;
	}

	public function get_meta_keys(){
		global $wpdb;

	    $select = "SELECT distinct $wpdb->usermeta.meta_key FROM $wpdb->usermeta";
	    $metakeys = $wpdb->get_results($select);
  		
  		$meta_keys=array();
		foreach ( $metakeys as $key ) {
			if($key==''){continue;}
			array_push($meta_keys,$key->meta_key);
		}

		$meta_keys = apply_filters( 'woocommerce_'.$this->export_type.'_export_allow_meta_keys', $meta_keys);

		return $meta_keys;
	}

	public function get_meta_data($obj){
		$meta_data = get_user_meta($obj->ID);
		return $meta_data;
	}
	

	/* GETTERS */

			public function get_meta_value($obj, $meta_key){
				global $wpdb;
				$value=get_user_meta($obj->ID,$meta_key, true);
				return $value; 
			}

			/*
			public function get_column_special( $obj, $column_id ) {
				$value='';
				if(strpos($column_id, 'registration_field_') !== false){

					// EXTENDONS REGISTRATION FIELDS
					$meta_value=get_user_meta($obj->ID, $column_id, true );
					$value=$obj->{"{$column_id}"};

				}
				return $value;
			}
			*/

			public function get_column_id($obj) {
				$value=$obj->ID;
				return $value;
			}

			public function get_column_first_name($obj) {
				$value=$obj->first_name;
				return $value;
			}

			public function get_column_role($obj) {
				$id = $obj->ID;
				//error_log($id);
				//$user_data = get_userdata( $id );
			    $user_meta = get_userdata($id);
			    $user_roles = $user_meta->roles;
				$value = $this->implode_recursive(',',$user_roles);
				return $value;
			}


}
