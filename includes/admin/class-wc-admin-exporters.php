<?php
/**
 * Init WooCommerce data exporters.
 *
 * @package     WooImportExport\Admin
 * @version     3.1.0
 */

use Automattic\Jetpack\Constants;

if (!defined( 'ABSPATH' )) { exit; }

class WC_Admin_Extended_Exporters {

	protected $exporters = array();

	public function __construct() {

		global $terms_manager;

		$r_exporters=$terms_manager->get_exporters();

			$allowed=true;
			foreach($r_exporters as $r) {
				if ( ! $this->export_allowed($r['slug']) ) {
					$allowed=false;
				}
			}
			if(!$allowed){
				return;
			}

			add_action( 'admin_menu', array( $this, 'remove_menus' ),999);
			add_action( 'admin_menu', array( $this, 'add_to_menus' ),999);
			add_action( 'admin_init', array( $this, 'download_extended_export_file' ) );
			add_action( 'admin_head', array( $this, 'hide_from_menus' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

			foreach ($r_exporters as $r) {
				$this->exporters[$r['slug'].'_exporter'] = array(
					'menu'       => $r['page'],
					'name'       => __( $r['menu'], 'woocommerce' ),
					'capability' => 'export',
					'callback'   => function() use ($r) { $this->custom_exporter($r); },
					'slug'	     => $r['slug'],
				);
			}

			foreach ( $this->exporters as $id => $exporter ) {
				add_action( 'wp_ajax_woocommerce_do_ajax_'.$exporter['slug'].'_export', 
					function() use ($exporter) { $this->do_ajax_export($exporter); },
				);
			}
	}


	protected function export_allowed($slug) {
		return current_user_can( 'edit_products' ) && current_user_can( 'export' );
	}

	public function remove_menus() {
		foreach ( $this->exporters as $id => $exporter ) {
			remove_submenu_page($exporter['menu'], $exporter['slug'].'_exporter' );
		}
	}

	public function add_to_menus() {
		foreach ( $this->exporters as $id => $exporter ) {
			add_submenu_page( $exporter['menu'], $exporter['name'], $exporter['name'], $exporter['capability'], $id, $exporter['callback'] );
		}
	}

	public function hide_from_menus() {
		global $submenu;

		foreach ( $this->exporters as $id => $exporter ) {
			if ( isset( $submenu[ $exporter['menu'] ] ) ) {
				foreach ( $submenu[ $exporter['menu'] ] as $key => $menu ) {
					if ( $id === $menu[2] ) {
						unset( $submenu[ $exporter['menu'] ][ $key ] );
					}
				}
			}
		}
	}

	public function admin_scripts() {
		$suffix = '';
		$version = Constants::get_constant( 'WC_VERSION' );
		foreach ( $this->exporters as $id => $exporter ) {
			wp_register_script( 'wc-'.$exporter['slug'].'-export', WOOIMPORTEXPORT_URI . '/assets/js/admin/wc-export' . $suffix . '.js', array( 'jquery' ), $version );
			wp_localize_script(
				'wc-'.$exporter['slug'].'-export',
				'wc_export_params',
				array(
					'slug' => $exporter['slug'],
					'export_nonce' => wp_create_nonce( 'wc-export' ),
				)
			);
		}
	}

	public function custom_exporter($ext) {
		include_once WOOIMPORTEXPORT_DIR . '/includes/export/class-wc-'.$ext['slug'].'-csv-exporter.php';
		include_once WOOIMPORTEXPORT_DIR . '/includes/admin/views/html-admin-page-export.php';

		$e_class='WC_'.ucfirst($ext['slug']).'_CSV_Exporter';
		$exporter = new $e_class();
	}

	public function download_extended_export_file() {
		foreach ( $this->exporters as $id => $exporter ) {
			if ( isset( $_GET['action'], $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), $exporter['slug'].'-csv' ) && 'download_'.$exporter['slug'].'_csv' === wp_unslash( $_GET['action'] ) ) { // WPCS: input var ok, sanitization ok.
				include_once WOOIMPORTEXPORT_DIR . '/includes/export/class-wc-'.$exporter['slug'].'-csv-exporter.php';

				$r_class='WC_'.ucfirst($exporter['slug']).'_CSV_Exporter';
				$exporter = new $r_class();

				if ( ! empty( $_GET['filename'] ) ) { // WPCS: input var ok.
					$exporter->set_filename( wp_unslash( $_GET['filename'] ) ); // WPCS: input var ok, sanitization ok.
				}

				$exporter->set_default_column_names( $_GET['export_meta']);

				//error_log("test: ".$exporter->enable_meta_export);

				$exporter->export();
			}
		}
		/*
		if ( isset( $_GET['action'], $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'category-csv' ) && 'download_category_csv' === wp_unslash( $_GET['action'] ) ) { // WPCS: input var ok, sanitization ok.
			include_once dirname( __FILE__ ) . '/../export/class-wc-category-csv-exporter.php';
			$exporter = new WC_Category_CSV_Exporter();

			if ( ! empty( $_GET['filename'] ) ) { // WPCS: input var ok.
				$exporter->set_filename( wp_unslash( $_GET['filename'] ) ); // WPCS: input var ok, sanitization ok.
			}

			$exporter->export();
		}
		*/

	}

	
	public function do_ajax_export($ext) {

		check_ajax_referer( 'wc-export', 'security' );

		if ( ! $this->export_allowed($ext['slug']) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to export.', 'woocommerce' ) ) );
		}

		include_once dirname( __FILE__ ) . '/../export/class-wc-'.$ext['slug'].'-csv-exporter.php';

		$step  = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1; // WPCS: input var ok, sanitization ok.

		$e_class='WC_'.ucfirst($ext['slug']).'_CSV_Exporter';
		$exporter = new $e_class();

		if ( ! empty( $_POST['columns'] ) ) { // WPCS: input var ok.
			$exporter->set_column_names( wp_unslash( $_POST['columns'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['selected_columns'] ) ) { // WPCS: input var ok.
			$exporter->set_columns_to_export( wp_unslash( $_POST['selected_columns'] ) ); // WPCS: input var ok, sanitization ok.
		}
		
		$export_meta=false;
		if ( ! empty( $_POST['export_meta'] ) ) { // WPCS: input var ok.
			$exporter->enable_meta_export( true );
			$export_meta=true;
		}
		
		if ( ! empty( $_POST['export_types'] ) ) { // WPCS: input var ok.
			$exporter->set_types_to_export( wp_unslash( $_POST['export_types'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['filename'] ) ) { // WPCS: input var ok.
			$exporter->set_filename( wp_unslash( $_POST['filename'] ) ); // WPCS: input var ok, sanitization ok.
		}

		$exporter->set_page( $step );
		$exporter->generate_file();

		$query_args = apply_filters(
			'woocommerce_export_get_ajax_query_args',
			array(
				'nonce'    => wp_create_nonce( $ext['slug'].'-csv' ),
				'action'   => 'download_'.$ext['slug'].'_csv',
				'export_meta'   => $export_meta,
				'filename' => $exporter->get_filename(),
			)
		);

		if ( $exporter->get_percent_complete() >= 100) {
		//if ( 100 === $exporter->get_percent_complete() ) {
			wp_send_json_success(
				array(
					'step'       => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( $query_args, admin_url( $ext['menu'].'?page='.$ext['slug'].'_exporter' ) ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'step'       => ++$step,
					'percentage' => $exporter->get_percent_complete(),
					'columns'    => $exporter->get_column_names(),
				)
			);
		}
	}
}

new WC_Admin_Extended_Exporters();
