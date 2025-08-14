<?php
/**
 * WooCommerce user CSV importer
 *
 * @package WooCommerce\Import
 * @version 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* DEPENDENCIES */

	if ( ! class_exists( 'WC_Custom_Extended_Importer', false ) ) {
		include_once WOOIMPORTEXPORT_DIR . '/includes/import/wc-csv-custom-importer.php';
	}

class WC_User_CSV_Extended_Importer extends WC_CSV_Custom_Importer {

	public $import_type = 'user';


	public function check_exists($id){
		//error_log('test '.$id);
 		global $wpdb;
		$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->users WHERE ID = %d", $id));
		if($count == 1){ return true; }else{ return false; }
	}

	public function get_id($data){
		/* DOUBLE CHECK ID */
		if(array_key_exists('user_email',$data)){
			$double_check=get_user_by( 'email', $data['user_email'] );
			if($double_check!=null){
				return $double_check->ID;
			}
		}else if(array_key_exists('id',$data)){
			return $data['id'];
		}
		return '';
	}

	public function get_object($id){
		$obj=get_user_by('id',$id);
		return $obj;
	}

	public function set_row_data(&$object,$data){
		$user_data=array();
		if(array_key_exists('id', $data)){
			$user_data['ID']=$data['id'];
		}

		$keys=array(
			'user_login',
			'user_pass',
			'user_email',
			'user_nicename',
			'display_name',
			'user_status',
			'user_url',
			'nickname',
			'first_name',
			'last_name',
			'description',
			'rich_editing',
			'user_registered',
			'role',
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_postcode',
			'billing_state',
			'billing_email',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_postcode',
			'shipping_state',
			'shipping_email',
			'_order_count'
		);

		$implode_keys=array(
			'role'
		);

		foreach($keys as $key) { 
			if(array_key_exists($key, $data)){
				$value='';

				if(is_array($data[$key])){
					if(in_array($key,$implode_keys)){
						$value=$this->implode_recursive(',',$data[$key]);
						//error_log('in_array: '.$value);
					}else{
						$value=$data[$key];
					}
				}else{
					$value=$data[$key];
				}
				//error_log($key. ' : '.$value);

				$user_data[$key]=$value;
			}
		}

		// FROM XCART	
		if(array_key_exists('role',$user_data)&&$user_data['role']=="C"){
			$user_data['role']="customer";
		}
		// FROM XCART	
		if(array_key_exists('status',$user_data)){
			$user_data['status']="0";
		}

		$check_user=$this->check_user($user_data);
		if($check_user!=''){
			return new WP_Error( 'woocommerce_custom_importer_error', $check_user.' matches an Administrator.');
		}

		//error_log('updating user');

		$result = wp_update_user($user_data);
		return $result;
	}

	public function new_row_data($data){
		$user_data=array();
		if(array_key_exists('id', $data)){
			$user_data['ID']='';
		}
		$keys=array(
			'user_login',
			'user_pass',
			'user_email',
			'user_nicename',
			'display_name',
			'user_status',
			'user_url',
			'nickname',
			'first_name',
			'last_name',
			'description',
			'rich_editing',
			'user_registered',
			'role',
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_postcode',
			'billing_state',
			'billing_email',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_postcode',
			'shipping_state',
			'shipping_email',
			'_order_count'
		);
		$implode_keys=array(
			'role'
		);
		foreach($keys as $key) { 
			if(array_key_exists($key, $data)){
				$value='';
				if(is_array($data[$key])){
					if(in_array($key,$implode_keys)){
						$value=$this->implode_recursive(',',$data[$key]);
					}else{
						$value=$data[$key];
					}
				}else{
					$value=$data[$key];
				}
				$user_data[$key]=$value;
			}
		}

		// FROM XCART	
		if(array_key_exists('role',$user_data)&&$user_data['role']=="C"){
			$user_data['role']="customer";
		}
		// FROM XCART	
		if(array_key_exists('status',$user_data)){
			$user_data['status']="0";
		}
		if(!array_key_exists('user_pass',$user_data)){
			$user_data['user_pass']="";
		}

		$check_user=$this->check_user($user_data);
		if($check_user!=''){
			return new WP_Error( 'woocommerce_custom_importer_error', $check_user.' matches an Administrator.');
		}

		$result = wp_insert_user($user_data);
		return $result;
	}

	public function check_user($user_data){
		$args = array(
		    'role'    => 'Administrator'
		);
		$uchecks=get_users($args);

		$current_check='';
		foreach($uchecks as $ucheck){
			if(in_array('administrator',$ucheck->roles)){
				if($user_data['ID']==$ucheck->ID){	
					$current_check="ID";
				}else if($user_data['user_login']==$ucheck->user_login){	
					$current_check="Login";
				}else if($user_data['user_email']==$ucheck->user_email){	
					$current_check="Email";
				}
				if($current_check!=''){
					return $current_check;
					break;
				}
			}
		}
	}

	public function update_meta_data($data,$meta_key,$meta_value){
		$user_id=$this->get_id($data);
		if (get_user_meta( $user_id, $meta_key)) {
			update_user_meta( $user_id, $meta_key, $meta_value );
		}else{
			add_user_meta( $user_id, $meta_key, $meta_value, $unique = true );
		}
	}

	public function clean_up(){
		global $wpdb;
		$wpdb->query(
			"
			DELETE {$wpdb->usermeta}.* FROM {$wpdb->usermeta}
			LEFT JOIN {$wpdb->users} wp ON wp.ID = {$wpdb->usermeta}.user_id
			WHERE wp.ID IS NULL
		"
		);
	}

	/* 
		public function set_object_data($key,$value){
		}
	*/

	/* 
		public function new_object_data($key,$value){
		}
	*/

	/* 
		public function set_column_name($key,$value){
		}
	*/

	/*
	public function set_column_image_data_external(&$object, $data){
		if ( isset( $data['raw_image_id'] ) ) {
			$url=$data['raw_image_id'];
		}
		if ( empty( $url ) ) {
			return;
		}
		$object->update_meta_data( '_knawatfibu_url', $url );
	}
	*/
	/*
	protected function set_column_image_data( &$object, $data ) {
		$id=$this->get_object($data[$id];
		if ( isset( $data['raw_image_id'] ) ) {
			$object->set_image_id( $this->get_attachment_id_from_url( $data['raw_image_id'], $id) ) );
		}
		if ( isset( $data['raw_gallery_image_ids'] ) ) {
			$gallery_image_ids = array();
			foreach ( $data['raw_gallery_image_ids'] as $image_id ) {
				$gallery_image_ids[] = $this->get_attachment_id_from_url( $image_id, $id );
			}
			$object->set_gallery_image_ids( $gallery_image_ids );
		}
	}
	*/


	/*
	public function get_column_relative_id($id, $mode){
		if($mode==0){
			$value=$wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_original_id' AND meta_value = %s;", $id ) );
		}else{
			$wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->user} WHERE post_type IN ( 'user', 'user_variation' ) AND ID = %d;", $id ) );
		}
		return $value;
	}
	*/

}
