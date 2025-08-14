<?php
/**
 * Init WooCommerce data importers.
 *
 * @package WooCommerce\Admin
 *
 */

use Automattic\Jetpack\Constants;

defined( 'ABSPATH' ) || exit;

class WC_Admin_Extended_Importers {

	protected $importers = array();

	public function __construct() {

		global $terms_manager;

		$r_importers=$terms_manager->get_importers();

			$allowed=true;
			foreach($r_importers as $r) {
				if ( ! $this->import_allowed($r['slug']) ) {
					$allowed=false;
				}
			}
			if(!$allowed){
				//return;
			}

			add_action( 'admin_menu', array( $this, 'remove_menus' ),11);
			add_action( 'admin_menu', array( $this, 'add_to_menus' ),10);
			add_action( 'admin_init', array( $this, 'register_importers' ) );
			add_action( 'admin_head', array( $this, 'hide_from_menus' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

			foreach ($r_importers as $r) {
				$this->importers[$r['slug'].'_extended_importer'] = array(
					'menu'       => $r['page'],
					'name'       => __( $r['menu'], 'woocommerce' ),
					'capability' => 'import',
					'callback'   => function() use ($r) { $this->load_extended_importer($r); },
					'slug'	     => $r['slug'],
				);
				add_action( 'wp_ajax_woocommerce_do_ajax_'.$r['slug'].'_import', 
					function() use ($r) { $this->do_ajax_import($r); },
				);

			}
			foreach ( $this->importers as $id => $importer ) {

			}
	}

	protected function import_allowed($slug) {
		return current_user_can( 'edit_products' ) && current_user_can( 'import' );
	}

	public function remove_menus() {
		foreach ( $this->importers as $id => $importer ) {
			remove_submenu_page($importer['menu'], $importer['slug'].'_importer' );
		}
	}

	public function add_to_menus() {
		foreach ( $this->importers as $id => $importer ) {
			add_submenu_page( $importer['menu'], $importer['name'], $importer['name'], $importer['capability'], $id, $importer['callback'] );
		}
	}

	public function hide_from_menus() {
		global $submenu;

		foreach ( $this->importers as $id => $importer ) {
			if ( isset( $submenu[ $importer['menu'] ] ) ) {
				foreach ( $submenu[ $importer['menu'] ] as $key => $menu ) {
					if ( $id === $menu[2] ) {
						unset( $submenu[ $importer['menu'] ][ $key ] );
					}
				}
			}
		}
	}

	public function admin_scripts() {
		$suffix  = Constants::is_true( 'SCRIPT_DEBUG' ) ? '' : '.min';
		$version = Constants::get_constant( 'WC_VERSION' );
		$suffix  = '';

		foreach ( $this->importers as $id => $importer ) {
			if($importer['slug']=='product'){
				wp_register_script( 'wc-product-import', WC()->plugin_url() . '/assets/js/admin/wc-'.$importer['slug'].'-import' . $suffix . '.js', array( 'jquery' ), $version, true );
			}else{
				wp_register_script( 'wc-'.$importer['slug'].'-import', WOOIMPORTEXPORT_URI . '/assets/js/admin/wc-import' . $suffix . '.js', array( 'jquery' ), $version, true );
			}
		}
	}

	public function load_extended_importer($ext) {
		if ( Constants::is_defined( 'WP_LOAD_IMPORTERS' ) ) {
			if($ext['slug']=='product'){
				wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=product_extended_importer' ) );
			}else{
				wp_safe_redirect( admin_url( $ext['menu'].'?page='.$ext['slug'].'_extended_importer' ) );
			}
			exit;
		}
		$e_class='';
		if($ext['slug']=='product'){
			include_once WOOIMPORTEXPORT_DIR . '/includes/import/class-wc-product-csv-importer.php';
			include_once WOOIMPORTEXPORT_DIR . '/includes/admin/importers/class-wc-product-csv-importer-controller.php';
			$e_class='WC_Product_CSV_Importer_Controller';
			$importer = new $e_class();
			$importer->dispatch();
		}else{
			include_once WOOIMPORTEXPORT_DIR . '/includes/import/class-wc-'.$ext['slug'].'-csv-importer.php';
			include_once WOOIMPORTEXPORT_DIR . '/includes/admin/importers/class-wc-custom-csv-importer-controller.php';
			$e_class='WC_Custom_CSV_Importer_Controller';
			$importer = new $e_class($ext);
			$importer->dispatch();
		}
	}

	public function register_importers() {
		foreach ( $this->importers as $id => $importer ) {
			if ( Constants::is_defined( 'WP_LOAD_IMPORTERS' ) ) {
				if($importer['slug']=='product'){
					add_action( 'import_start', array( $this, 'post_importer_compatibility' ) );
					register_importer( 'woocommerce_product_csv', __( 'WooCommerce products (CSV)', 'woocommerce' ), __( 'Import <strong>products</strong> to your store via a csv file.', 'woocommerce' ), array( $this, 'product_extended_importer' ) );
					register_importer( 'woocommerce_tax_rate_csv', __( 'WooCommerce tax rates (CSV)', 'woocommerce' ), __( 'Import <strong>tax rates</strong> to your store via a csv file.', 'woocommerce' ), array( $this, 'tax_rates_importer' ) );
				}else{
					//add_action( $importer['slug'].'import_start', array( $this, 'post_custom_importer_compatibility' ) );
					register_importer( 'woocommerce_'.$importer['slug'].'_csv', __( 'WooCommerce '.ucfirst($importer['slug']).' (CSV)', 'woocommerce' ), __( 'Import to your store via a csv file.', 'woocommerce' ), function() use ($importer) { $this->load_extended_importer($importer); } );
				}
			}
		}
	}

	public function post_custom_importer_compatibility() {
		error_log('test');
		$id          = absint( $_POST['import_id'] ); // PHPCS: input var ok.
		$file        = get_attached_file( $id );
		$parser      = new WXR_Parser();
		$import_data = $parser->parse( $file );
	}

	public function post_importer_compatibility() {
		global $wpdb;

		if ( empty( $_POST['import_id'] ) || ! class_exists( 'WXR_Parser' ) ) { // PHPCS: input var ok, CSRF ok.
			return;
		}

		$id          = absint( $_POST['import_id'] ); // PHPCS: input var ok.
		$file        = get_attached_file( $id );
		$parser      = new WXR_Parser();
		$import_data = $parser->parse( $file );

		if ( isset( $import_data['posts'] ) && ! empty( $import_data['posts'] ) ) {
			foreach ( $import_data['posts'] as $post ) {
				if ( 'product' === $post['post_type'] && ! empty( $post['terms'] ) ) {
					foreach ( $post['terms'] as $term ) {
						if ( strstr( $term['domain'], 'pa_' ) ) {
							if ( ! taxonomy_exists( $term['domain'] ) ) {
								$attribute_name = wc_attribute_taxonomy_slug( $term['domain'] );

								// Create the taxonomy.
								if ( ! in_array( $attribute_name, wc_get_attribute_taxonomies(), true ) ) {
									wc_create_attribute(
										array(
											'name'         => $attribute_name,
											'slug'         => $attribute_name,
											'type'         => 'select',
											'order_by'     => 'menu_order',
											'has_archives' => false,
										)
									);
								}

								// Register the taxonomy now so that the import works!
								register_taxonomy(
									$term['domain'],
									apply_filters( 'woocommerce_taxonomy_objects_' . $term['domain'], array( 'product' ) ),
									apply_filters(
										'woocommerce_taxonomy_args_' . $term['domain'],
										array(
											'hierarchical' => true,
											'show_ui'      => false,
											'query_var'    => true,
											'rewrite'      => false,
										)
									)
								);
							}
						}
					}
				}
			}
		}
	}

	public function do_ajax_import($ext) {
		global $wpdb;

		if($ext['slug']=='product'){ $this->do_ajax_product_import(); return;}

		check_ajax_referer( 'wc-import', 'security' );

		if ( ! $this->import_allowed($ext['slug']) || ! isset( $_POST['file'] ) ) { // PHPCS: input var ok.
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to import.', 'woocommerce' ) ) );
		}

		include_once WOOIMPORTEXPORT_DIR . '/includes/admin/importers/class-wc-custom-csv-importer-controller.php';
		include_once WOOIMPORTEXPORT_DIR . '/includes/import/class-wc-'.$ext['slug'].'-csv-importer.php';

		$file   = wc_clean( wp_unslash( $_POST['file'] ) ); // PHPCS: input var ok.
		$params = array(
			'import_type'     => $ext['slug'], // PHPCS: input var ok.
			'import_page'     => $ext['page'], // PHPCS: input var ok.
			'delimiter'       => ! empty( $_POST['delimiter'] ) ? wc_clean( wp_unslash( $_POST['delimiter'] ) ) : ',', // PHPCS: input var ok.
			'start_pos'       => isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0, // PHPCS: input var ok.
			'mapping'         => isset( $_POST['mapping'] ) ? (array) wc_clean( wp_unslash( $_POST['mapping'] ) ) : array(), // PHPCS: input var ok.
			'update_existing' => isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false, // PHPCS: input var ok.
			'lines'           => apply_filters( 'woocommerce_'.$ext['slug'].'_import_batch_size', 30 ),
			'parse'           => true,
		);

		// Log failures.
		if ( 0 !== $params['start_pos'] ) {
			$error_log = array_filter( (array) get_user_option( 'custom_import_error_log' ) );
		} else {
			$error_log = array();
		}
		
		$e_class='WC_Custom_CSV_Importer_Controller';

		$importer         = $e_class::get_importer( $file, $params );
		$results          = $importer->import();
		$percent_complete = $importer->get_percent_complete();
		$error_log        = array_merge( $error_log, $results['failed'], $results['skipped'] );

		update_user_option( get_current_user_id(), 'custom_import_error_log', $error_log );

		/* DO CLEANUP HERE */

		if ( 100 === $percent_complete ) {
			wp_send_json_success(
				array(
					'position'   => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( array( '_wpnonce' => wp_create_nonce( 'woocommerce-csv-importer' ) ), admin_url( $ext['page'].'?page='.$ext['slug'].'_extended_importer&step=done' ) ),
					'imported'   => count( $results['imported'] ),
					'failed'     => count( $results['failed'] ),
					'updated'    => count( $results['updated'] ),
					'skipped'    => count( $results['skipped'] ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'position'   => $importer->get_file_position(),
					'percentage' => $percent_complete,
					'imported'   => count( $results['imported'] ),
					'failed'     => count( $results['failed'] ),
					'updated'    => count( $results['updated'] ),
					'skipped'    => count( $results['skipped'] ),
				)
			);
		}

	}

	public function do_ajax_product_import() {
		global $wpdb;

		check_ajax_referer( 'wc-product-import', 'security' );

		if ( ! $this->import_allowed('product') || ! isset( $_POST['file'] ) ) { // PHPCS: input var ok.
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to import products.', 'woocommerce' ) ) );
		}

		include_once WOOIMPORTEXPORT_DIR . '/includes/admin/importers/class-wc-product-csv-importer-controller.php';
		include_once WOOIMPORTEXPORT_DIR . '/includes/import/class-wc-product-csv-importer.php';

		$file   = wc_clean( wp_unslash( $_POST['file'] ) ); // PHPCS: input var ok.
		$params = array(
			'delimiter'       => ! empty( $_POST['delimiter'] ) ? wc_clean( wp_unslash( $_POST['delimiter'] ) ) : ',', // PHPCS: input var ok.
			'start_pos'       => isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0, // PHPCS: input var ok.
			'mapping'         => isset( $_POST['mapping'] ) ? (array) wc_clean( wp_unslash( $_POST['mapping'] ) ) : array(), // PHPCS: input var ok.
			'update_existing' => isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false, // PHPCS: input var ok.
			'lines'           => apply_filters( 'woocommerce_product_import_batch_size', 30 ),
			'parse'           => true,
		);

		// Log failures.
		if ( 0 !== $params['start_pos'] ) {
			$error_log = array_filter( (array) get_user_option( 'product_import_error_log' ) );
		} else {
			$error_log = array();
		}

		$importer         = WC_Product_CSV_Importer_Controller::get_importer( $file, $params );
		$results          = $importer->import();
		$percent_complete = $importer->get_percent_complete();
		$error_log        = array_merge( $error_log, $results['failed'], $results['skipped'] );

		update_user_option( get_current_user_id(), 'product_import_error_log', $error_log );

		if ( 100 === $percent_complete ) {
			// @codingStandardsIgnoreStart.
			$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_original_id' ) );
			$wpdb->delete( $wpdb->posts, array(
				'post_type'   => 'product',
				'post_status' => 'importing',
			) );
			$wpdb->delete( $wpdb->posts, array(
				'post_type'   => 'product_variation',
				'post_status' => 'importing',
			) );
			// @codingStandardsIgnoreEnd.

			// Clean up orphaned data.
			$wpdb->query(
				"
				DELETE {$wpdb->posts}.* FROM {$wpdb->posts}
				LEFT JOIN {$wpdb->posts} wp ON wp.ID = {$wpdb->posts}.post_parent
				WHERE wp.ID IS NULL AND {$wpdb->posts}.post_type = 'product_variation'
			"
			);
			$wpdb->query(
				"
				DELETE {$wpdb->postmeta}.* FROM {$wpdb->postmeta}
				LEFT JOIN {$wpdb->posts} wp ON wp.ID = {$wpdb->postmeta}.post_id
				WHERE wp.ID IS NULL
			"
			);
			// @codingStandardsIgnoreStart.
			$wpdb->query( "
				DELETE tr.* FROM {$wpdb->term_relationships} tr
				LEFT JOIN {$wpdb->posts} wp ON wp.ID = tr.object_id
				LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE wp.ID IS NULL
				AND tt.taxonomy IN ( '" . implode( "','", array_map( 'esc_sql', get_object_taxonomies( 'product' ) ) ) . "' )
			" );
			// @codingStandardsIgnoreEnd.

			// Send success.
			wp_send_json_success(
				array(
					'position'   => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( array( '_wpnonce' => wp_create_nonce( 'woocommerce-csv-importer' ) ), admin_url( 'edit.php?post_type=product&page=product_extended_importer&step=done' ) ),
					'imported'   => count( $results['imported'] ),
					'failed'     => count( $results['failed'] ),
					'updated'    => count( $results['updated'] ),
					'skipped'    => count( $results['skipped'] ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'position'   => $importer->get_file_position(),
					'percentage' => $percent_complete,
					'imported'   => count( $results['imported'] ),
					'failed'     => count( $results['failed'] ),
					'updated'    => count( $results['updated'] ),
					'skipped'    => count( $results['skipped'] ),
				)
			);
		}
	}

	public function console_log($message){
		echo "<script>console.log('".$message."');</script>";
	}
}

new WC_Admin_Extended_Importers();
