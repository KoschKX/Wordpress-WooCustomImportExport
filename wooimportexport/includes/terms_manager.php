<?php

	/*	types
		
		id,	comma, float, int, bool, date, description, published, relative,relative_comma, images, categories, tags, tags_spaces, stock_quantity, shipping_class, backorders, tax_status, download_file, skip, intval, esc_url_raw

	*/

	/* slug, name, type, parse? */

class WC_Terms_Manager{

	/* Functions */


		private function remove_empty($terms){
			$out=array();
			foreach($terms as $term){
				if($term['name']!=''){
					array_push($out,$term);
				}
			}
			return $out;
		}


	/* REGISTERED IMPORTERS & EXPORTERS */

		public function get_importers($remove_empty=true){
	
				$_importers=array(

							array('slug' => 'product', 		'name' => 'Product', 	'menu' => 	'Product Import', 		'page' => 'edit.php?post_type=product',	'use_meta' => true	),

							//array('slug' => 'category', 	'name' => 'Category', 	'menu' => 	'Categories Import', 	'page' => 'edit.php?post_type=product'),

							array('slug' => 'user', 		'name' => 'User', 		'menu' => 	'User Import', 			'page' => 'users.php'),

							);

			return $_importers; 
		}	
		public function get_exporters($remove_empty=true){
			
				$_exporters=array(

							//array('slug' => 'product', 		'name' => 'Product', 	'menu' => 	'Product Export', 		'page' => 'edit.php?post_type=product',	'use_meta' => true),

							array('slug' => 'category', 	'name' => 'Category', 	'menu' => 	'Categories Export', 	'page' => 'edit.php?post_type=product',	'use_meta' => false),

							array('slug' => 'user', 		'name' => 'User', 		'menu' => 	'User Export', 			'page' => 'users.php',	'use_meta' => true),

							//array('slug' => 'image_data', 	'name' => 'Image Data', 'menu' => 	'Image Data Export', 	'page' => 'edit.php?post_type=product',	'use_meta' => false),

							);

			return $_exporters; 
		}	

	/* TERMS */

		public function get_user_terms($remove_empty=true){

			$_terms=array(

						array('slug' => 'id',         	 			'name' => 'ID', 					'type' => 'id', 		 		'parse' => true),

						array('slug' => 'user_login', 	 			'name' => 'Login', 					'type' => 'string', 	 		'parse' => true),

						array('slug' => 'user_email', 	 			'name' => 'Email', 					'type' => 'string', 	 		'parse' => true),

						array('slug' => 'user_pass',  	 			'name' => 'Password', 				'type' => 'string', 	 		'parse' => true),

						array('slug' => 'user_nicename', 			'name' => 'Nice Name', 				'type' => 'string', 	 		'parse' => true),

						array('slug' => 'user_url', 	 			'name' => 'URL', 					'type' => 'esc_url_raw', 		'parse' => false),

						array('slug' => 'user_registered', 			'name' => 'Is Registered?', 		'type' => 'date', 				'parse' => true),

						array('slug' => 'user_activation_key', 		'name' => 'Activation Key', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'user_status', 				'name' => 'Status', 				'type' => 'int', 				'parse' => true),

						array('slug' => 'display_name', 			'name' => 'Username', 				'type' => 'string', 			'parse' => true),

						array('slug' => 'role', 					'name' => 'Role', 					'type' => 'string', 			'parse' => true),

						// array('slug' => 'wp_user_level', 			'name' => 'Level', 					'type' => 'int', 				'parse' => true),

						// array('slug' => 'wp_capabilities', 			'name' => 'Capabilities', 			'type' => 'string', 			'parse' => true),

						
						array('slug' => 'first_name', 				'name' => 'First Name', 			'type' => 'string', 			'parse' => true),

						array('slug' => 'last_name', 				'name' => 'Last Name', 				'type' => 'string', 			'parse' => true),

						array('slug' => 'registration_field_12', 	'name' => 'Tax ID', 				'type' => 'string', 			'parse' => true),
						
						array('slug' => 'billing_first_name', 		'name' => 'Billing First Name', 	'type' => 'string', 			'parse' => true),

						array('slug' => 'billing_last_name', 		'name' => 'Billing Last Name', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'billing_company', 			'name' => 'Billing Company', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'billing_address_1', 		'name' => 'Billing Address 1', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'billing_address_2', 		'name' => 'Billing Address 2', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'billing_city', 			'name' => 'Billing City', 			'type' => 'string', 			'parse' => true),

						array('slug' => 'billing_postcode', 		'name' => 'Billing Postcode', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'billing_state', 			'name' => 'Billing State', 			'type' => 'string', 			'parse' => true),

						array('slug' => 'billing_country', 			'name' => 'Billing Country', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'billing_email', 			'name' => 'Billing Email', 			'type' => 'string', 			'parse' => true),

						array('slug' => 'shipping_first_name', 		'name' => 'Billing First Name', 	'type' => 'string', 			'parse' => true),

						array('slug' => 'shipping_last_name', 		'name' => 'Billing Last Name', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'shipping_company', 		'name' => 'Shipping Company', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'shippingaddress_1', 		'name' => 'Shipping Address 1', 	'type' => 'string', 			'parse' => true),

						array('slug' => 'shipping_address_2', 		'name' => 'Shipping Address 2', 	'type' => 'string', 			'parse' => true),

						array('slug' => 'shipping_city', 			'name' => 'Shipping City', 			'type' => 'string', 			'parse' => true),

						array('slug' => 'shipping_postcode', 		'name' => 'Shipping Postcode', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'shipping_state', 			'name' => 'Shipping State', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'shipping_country', 		'name' => 'Shipping Country', 		'type' => 'string', 			'parse' => true),

						array('slug' => 'shipping_email', 			'name' => 'Shipping Email', 		'type' => 'string', 			'parse' => true),	

						array('slug' => '_order_count', 			'name' => 'Order Count', 			'type' => 'int', 				'parse' => true),	

						array('slug' => 'last_update', 				'name' => 'Last Update', 			'type' => 'date', 				'parse' => true),	
						
						);


			return $_terms;
		}
		

		public function get_product_terms($remove_empty=true){


			$weight_unit = get_option( 'woocommerce_weight_unit' );
			$dimension_unit = get_option( 'woocommerce_dimension_unit' );

			$_terms=array(

					   	array('slug' => 'id',         	 		'name' => 'ID', 							'type' => 'id', 		 			'parse' => true),

						array('slug' => 'type',       	 		'name' => 'Type', 							'type' => 'int', 		 			'parse' => true),

					 	array('slug' => 'sku',       	 		'name' => 'SKU', 							'type' => 'skip', 		 			'parse' => true),

						array('slug' => 'name',       	 		'name' => 'Name', 							'type' => 'skip', 		 			'parse' => true),

					 	array('slug' => 'published',       		'name' => 'Published', 						'type' => 'published', 		 		'parse' => true),

					 	array('slug' => 'featured',       		'name' => 'Is Featured?', 					'type' => 'bool', 		 			'parse' => true),

					 	array('slug' => 'catalog_visibility', 	'name' => 'Visibility in catalog', 			'type' => 'bool', 		 			'parse' => true),

					 	array('slug' => 'short_description', 	'name' => 'Short description', 				'type' => 'description', 			'parse' => true),

					 	array('slug' => 'description', 			'name' => 'Description', 					'type' => 'description', 			'parse' => true),

					 	array('slug' => 'date_on_sale_from', 	'name' => 'Date sale price starts', 		'type' => 'date', 					'parse' => true),

					 	array('slug' => 'date_on_sale_to', 		'name' => 'Date sale price ends', 			'type' => 'date', 					'parse' => true),

					 	array('slug' => 'tax_status', 			'name' => 'Tax status', 					'type' => 'tax_status', 			'parse' => true),

					 	array('slug' => 'tax_class', 			'name' => 'Tax class', 						'type' => 'string', 				'parse' => true),

					 	array('slug' => 'stock_status', 		'name' => 'In stock?', 						'type' => 'bool', 					'parse' => true),

					 	array('slug' => 'stock_quantity', 		'name' => 'Stock', 							'type' => 'stock_quantity', 		'parse' => true),

					 	array('slug' => 'backorders', 			'name' => 'Backorders allowed?', 			'type' => 'backorders', 			'parse' => true),

					 	array('slug' => 'low_stock_amount', 	'name' => 'Low stock amount', 				'type' => 'int', 					'parse' => true),

					 	array('slug' => 'sold_individually', 	'name' => 'Sold individually?', 			'type' => 'bool', 					'parse' => true),

					 	array('slug' => 'weight', 				'name' => 'Weight ('.$weight_unit.')', 		'type' => 'float', 					'parse' => true),

					 	array('slug' => 'length', 				'name' => 'Length ('.$dimension_unit.')', 	'type' => 'float', 					'parse' => true),

					 	array('slug' => 'width', 				'name' => 'Width ('.$dimension_unit.')', 	'type' => 'float', 					'parse' => true),

					 	array('slug' => 'height', 				'name' => 'Height ('.$dimension_unit.')', 	'type' => 'float', 					'parse' => true),

						array('slug' => 'reviews_allowed', 		'name' => 'Allow customer reviews?', 		'type' => 'bool', 					'parse' => true),

						array('slug' => 'purchase_note', 		'name' => 'Purchase note', 					'type' => 'wp_filter_post_kses', 	'parse' => false),

						array('slug' => 'sale_price', 			'name' => 'Sale price', 					'type' => 'wc_format_decimal', 		'parse' => false),

						array('slug' => 'regular_price', 		'name' => 'Regular price', 					'type' => 'wc_format_decimal', 		'parse' => false),

						array('slug' => 'category_ids', 		'name' => 'Categories', 					'type' => 'relative_comma', 		'parse' => true),

						array('slug' => 'tag_ids', 				'name' => 'Tags', 							'type' => 'tags', 					'parse' => true),

						array('slug' => 'shipping_class_id', 	'name' => 'Shipping class', 				'type' => 'shipping_class', 		'parse' => true),

						array('slug' => 'images', 				'name' => 'Images', 						'type' => 'images', 				'parse' => true),

						array('slug' => 'download_limit', 		'name' => 'Download limit', 				'type' => 'int', 					'parse' => true),

						array('slug' => 'download_expiry', 		'name' => 'Download expiry days', 			'type' => 'int', 					'parse' => true),

						array('slug' => 'parent_id', 			'name' => 'Parent', 						'type' => 'relative', 				'parse' => true),

						array('slug' => 'upsell_ids', 			'name' => 'Upsells', 						'type' => 'relative_comma', 		'parse' => true),

						array('slug' => 'cross_sell_ids', 		'name' => 'Cross-sells', 					'type' => 'relative_comma', 		'parse' => true),

						array('slug' => 'grouped_products', 	'name' => 'Grouped products', 				'type' => 'relative_comma', 		'parse' => true),

						array('slug' => 'product_url', 			'name' => 'External URL', 					'type' => 'esc_url_raw', 			'parse' => false),

						array('slug' => 'button_text', 			'name' => 'Button text', 					'type' => 'string', 				'parse' => true),

						array('slug' => 'menu_order', 			'name' => 'Position', 						'type' => 'intval', 				'parse' => false),

						array('slug' => 'date_created', 		'name' => 'Date Created', 					'type' => 'date', 					'parse' => true),



						//array('slug' => 'price', 				'name' => 'Price', 							'type' => 'wc_format_decimal', 		'parse' => false),

						//array('slug' => 'tag_ids_spaces', 		'name' => '', 							'type' => 'tags', 					'parse' => false),
			
						);


			return $_terms;
		}

		public function get_category_terms($remove_empty=true){
			

			$_terms=array(

					array('slug' => 'term_id',         	 			'name' => 'ID', 					'type' => 'id', 		 		'parse' => true),

					array('slug' => 'name',         	 			'name' => 'Name', 					'type' => 'string', 			'parse' => true),

					array('slug' => 'hierarchy',         	 		'name' => 'Hierarchy', 				'type' => 'relative_comma', 	'parse' => true),

					array('slug' => 'slug',         	 			'name' => 'Slug', 					'type' => 'string', 		  	'parse' => true),

					array('slug' => 'count',         	 			'name' => 'Count', 					'type' => 'int', 		 		'parse' => true),

					array('slug' => 'description',         	 		'name' => 'Description', 			'type' => 'description', 		'parse' => true),

					array('slug' => 'link',         	 			'name' => 'Link', 					'type' => 'esc_url_raw', 		'parse' => false),

					array('slug' => 'thumbnail',         	 		'name' => 'Thumbnail', 				'type' => 'esc_url_raw', 		'parse' => false),

					array('slug' => 'display_type',         	 	'name' => 'Display Type', 			'type' => 'string', 			'parse' => true),

					);

			return $_terms;
		}

		public function get_image_data_terms($remove_empty=true){
			
			$_terms=array(

					array('slug' => 'external_image',        		'name' => 'External Image', 		'type' => 'esc_url_raw', 		'parse' => false),

					);

			return $_terms;
		}


	/* OPTIONS */

		public function get_product_options(){
			$product_options=array();

			$product_options['weight_unit'] = get_option( 'woocommerce_weight_unit' );
			$product_options['dimension_unit'] = get_option( 'woocommerce_dimension_unit' );

			return $product_options;
		}

}

global $terms_manager;
$terms_manager=new WC_Terms_Manager();