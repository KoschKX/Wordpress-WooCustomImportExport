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


class WC_Image_data_CSV_Exporter extends WC_CSV_Custom_Exporter {

	public $export_type = 'image_data';

	public function get_exporter_options() {
		$options=array();

		$name=$this->export_type;
		$name=str_replace("_", " ", $name);
		$name=ucfirst($name);

		$options['slug']=$type;
		$options['name']=$name;

		return $array;
	}

	public function set_column_names($columns) {
		return $columns;
	}

	public function set_types() {
		
		$types=$this->get_sorted_categories();

		return $types;
	}

	public function get_sorted_categories(){
		
		$args = array(
		     'taxonomy'     => 'product_cat',
		     'order'      => 'ASC',
		     'orderby'      => 'name',
		     'hide_empty'   => false
		);

		$sorted_cat_filters = array();

		$categories = get_categories( $args, 'product_cat' );
		$numeric = array();

		$sorted=[];
		$sort_arr=[];

		foreach ($categories as $cat) {
			$catname=$cat->name;
			$catid=$cat->term_id;
			$catslug=$cat->slug;

			$check_numeric=preg_replace("/[^A-Za-z0-9 ]/", '',$catname);
			$check_numeric=str_replace(" ","",$check_numeric);

			if(!is_numeric($check_numeric)){
				$sorted_cat_filters[$catid] = $catslug;
			}else{
				$numeric[$catid] = $catslug;
			}
		}

		// SORT ALPHA 
			sort($sorted_cat_filters);

		// SORT NUMERIC

			$restock = array();
			$new_arrivals = array();

			foreach ($numeric as $ncat) {
				//$pieces = explode(";", str_replace("/","-",str_replace(" - ",";",$catname)));
		        $pieces = str_split($ncat,11);
		        $pattern = "/\d{2}\-\d{2}-\d{4}/";
		        if (preg_match($pattern,$pieces[0])) {

		            $sort_date=$pieces[0];
		            if(count($pieces)>1){$sort_date=$pieces[1];}

		            $sort_str =preg_replace("([^0-9/])", "/", $sort_date);
		            $sort_stamp = strtotime($sort_str);
		            array_push($sort_arr, array($ncat,$sort_date,$sort_stamp));
		        }
		        usort($sort_arr, fn($b, $a) => $a[2] - $b[2]);
			}
			foreach ($sort_arr as $sort) {             
			 	array_push($sorted, $sort[0]);
			}
			foreach ($sorted as $ncat) {
				if($ncat==null){continue;}

				// if (strpos($catname, ' - ') !== false) {
				if (strlen($ncat)>10) {
					array_push($restock,$ncat);
				}else{
					array_push($new_arrivals,$ncat);
				}
			}

		$sorted_cat_filters=array_merge($sorted_cat_filters,$restock);
		$sorted_cat_filters=array_merge($sorted_cat_filters,$new_arrivals);

		// REAPPLY SLUGS 

		$cat_filters=array();
		foreach($sorted_cat_filters as $s_cat){
			foreach ($categories as $cat) {
				if($cat->slug==$s_cat){
					$cat_filters[$cat->slug]=$cat->name;
				}
			}	
		}
		
		return $cat_filters;
	}


	public function prepare_data($types) {

		global $wpdb;
   		
   		/*
   		$post_per_page = $this->get_limit();
    	$offset = ($this->get_page())*$post_per_page;
		$sql="	SELECT SQL_CALC_FOUND_ROWS post_id 
			 	FROM $wpdb->postmeta 
			 	WHERE meta_key = '_knawatfibu_url'
			 	LIMIT ".$offset.", ".$post_per_page."
			 ";
		$post_ids = $wpdb->get_col($sql);
		$posts=array();
		foreach($post_ids as $id){
			$post=get_post($id);
			array_push($posts,$post);
		}
		*/

		$args = array(
				'post_type'      => 'product',
				'posts_per_page'    => $this->get_limit(),
				'page'     => $this->get_page(),
		        'meta_key'       => '_knawatfibu_url',
		       	'category__in'    => $types,
		        'meta_value'     => '',
		        'meta_compare'   => '!=',
		        'paginate' => true,
		   );  

		$query= new WP_Query($args);
		$posts = $query->posts;
	
		$this->total_rows = $query->found_posts;

		//$this->total_rows = 10;

		return $posts;
	}
	
	public function set_meta_keys(){
		global $wpdb;

		$sql = "SELECT meta_key FROM $wpdb->postmeta";
		$meta_keys = $wpdb->get_col($sql);

		return $meta_keys;
	}

	/* GETTERS */

			public function get_meta_value($obj, $meta_key){
				$value=get_user_meta($obj->ID,$meta_key, false);
				return $value; 
			}

			public function get_column_external_image($obj){
				//error_log($obj->ID);
				$value=get_post_meta($obj->ID,'_knawatfibu_url', false);
				if(is_array($value)){
					$value=implode('',$value[0]);
				}
				//$value=get_site_url().$value;
				return $value; 
			}


}
