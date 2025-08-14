<?php
/**
 * Handles category CSV export.
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

class WC_Category_CSV_Exporter extends WC_CSV_Custom_Exporter {

	public $export_type = 'category';

	public function set_column_names($columns) {
		return $columns;
	}

	public function set_types() {
		
		$types=$this->get_sorted_categories();
		
		return $types;
	}

	public function prepare_data($types) {

		/* SORT THEM */

			$categories=array();

			$cats=$types;
			
			foreach ($cats as $cat){
				$args = array(
				     'taxonomy'     => 'product_cat',
				     'page'     => $this->get_page(),
				     'posts_per_page'    => $this->get_limit(),
				     'hide_empty' => 0,
				     'limit '	=> 1,
				     'slug'	=> $cat
				);
				$s_cat=get_categories( $args )[0];
				if($s_cat!=null){
					array_push($categories,$s_cat);
				}
			}

			//$this->limit=round(count($categories)/2);
			$this->limit=500;
			
			$l=$this->get_limit();
			$p=($this->get_page()-1)*$l;

			if($p>count($categories)){
				$p=count($categories);
			}
			if($l>count($categories)){
				$l=count($categories);
			}

			$this->total_rows = count($categories);

			//error_log($l. " : ".$p. " : ".count($categories));

			$categories=array_slice($categories,$p, $l);
		
		return $categories;

	}

	public function set_meta_keys(){
		$meta_keys = array();
		return $meta_keys;
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


	/* GETTERS */

			public function get_meta_value($obj, $meta_key){
				$value='';
				return $value; 
			}

			public function get_column_term_id($obj) {
				$value=$obj->term_id;
				return $value;
			}

			public function get_column_name($obj) {
				$value=$obj->name;
				return $value;
			}

			public function get_column_count($obj) {
				$id=$obj->term_id;
				$term=get_term($obj->$id ,'product_cat');
				$value=$obj->count;
				return $value;
			}

			public function get_column_parents($obj) {
				$id=$obj->term_id;
				$parents=get_ancestors( $id ,'product_cat');
				if($parents==null){
					return '';
				}
				$parent_values=array();
				foreach ($parents as $parent) {
					$pt=get_term($parent);
					array_push($parent_values,"[".$parent."] ".$pt->name);
				}
				$value=implode(',',$parent_values);
				return $value;
			}

			public function get_column_children($obj) {
				$id=$obj->term_id;
				$children=get_term_children( $id, 'product_cat' );
				if($children==null){
					return '';
				}
				$child_values=array();
				foreach ($children as $child) {
					$ch=get_term($child);
					array_push($child_values,"[".$child."] ".$ch->name);
				}
				$value=implode("\r\n",$child_values);
				return $value;
			}


			public function get_column_hierarchy($obj) {
				$id=$obj->term_id;
				$parents=get_ancestors( $id ,'product_cat');
				if($parents==null){
					return $obj->name;
				}
				$parent_values=array();
				foreach ($parents as $parent) {
					$pt=get_term($parent);
					array_push($parent_values,$pt->name);
				}
				$value=implode(' > ',$parent_values).' > '.$obj->name;
				return $value;
			}

			public function get_column_link($obj) {
				$id=$obj->term_id;
				$value=get_term_link( $id, 'product_cat');
				return $value;
			}

			public function get_column_thumbnail($obj) {
				$id=$obj->term_id;
				$img=get_term_meta( $id, 'thumbnail_id', true );
				$value=wp_get_attachment_url($img);
				if(!is_string($value)){
					$value='';
				}
				return $value;
			}	

			public function get_column_display_type($obj) {
				$id=$obj->term_id;
				$value=get_term_meta( $id, 'display_type', true );
				if($value==''){
					$value='default';
				}
				return $value;
			}	

}

