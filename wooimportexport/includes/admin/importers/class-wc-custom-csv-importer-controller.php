<?php
/**
 * Class WC_Extended_CSV_Importer_Controller file.
 *
 * @package WooCommerce\Admin\Importers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Importer' ) ) {
	return;
}

class WC_Custom_CSV_Importer_Controller {

	protected $import_type = '';
	protected $import_page = '';

	protected $file = '';

	protected $step = '';

	protected $steps = array();

	protected $errors = array();

	protected $delimiter = ',';

	protected $map_preferences = false;

	protected $update_existing = false;

	public static function get_importer( $file, $args = array() ) {
		$importer_class = 'WC_'.ucfirst($args['import_type']).'_CSV_Extended_Importer';
		$args           = apply_filters( 'woocommerce_{$this->import_type}_csv_importer_args', $args, $importer_class );
		return new $importer_class( $file, $args );
	}

	public static function is_file_valid_csv( $file, $check_path = true ) {
		if ( $check_path && apply_filters( 'woocommerce_custom_csv_importer_check_import_file_path', true ) && false !== stripos( $file, '://' ) ) {
			return false;
		}

		$valid_filetypes = self::get_valid_csv_filetypes();
		$filetype        = wp_check_filetype( $file, $valid_filetypes );
		if ( in_array( $filetype['type'], $valid_filetypes, true ) ) {
			return true;
		}

		return false;
	}

	protected static function get_valid_csv_filetypes() {
		return apply_filters(
			'woocommerce_csv_custom_import_valid_filetypes',
			array(
				'csv' => 'text/csv',
				'txt' => 'text/plain',
			)
		);
	}

	public function __construct($ext) {
		$default_steps = array(
			'upload'  => array(
				'name'    => __( 'Upload CSV file', 'woocommerce' ),
				'view'    => array( $this, 'upload_form' ),
				'handler' => array( $this, 'upload_form_handler' ),
			),
			'mapping' => array(
				'name'    => __( 'Column mapping', 'woocommerce' ),
				'view'    => array( $this, 'mapping_form' ),
				'handler' => '',
			),
			'import'  => array(
				'name'    => __( 'Import', 'woocommerce' ),
				'view'    => array( $this, 'import' ),
				'handler' => '',
			),
			'done'    => array(
				'name'    => __( 'Done!', 'woocommerce' ),
				'view'    => array( $this, 'done' ),
				'handler' => '',
			),
		);


		$this->steps = apply_filters( 'woocommerce_'.$this->import_type.'_csv_importer_steps', $default_steps );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended

		$this->import_type = $ext['slug'];
		$this->import_page = $ext['page'];

		$this->step            = isset( $_REQUEST['step'] ) ? sanitize_key( $_REQUEST['step'] ) : current( array_keys( $this->steps ) );
		$this->file            = isset( $_REQUEST['file'] ) ? wc_clean( wp_unslash( $_REQUEST['file'] ) ) : '';
		$this->update_existing = isset( $_REQUEST['update_existing'] ) ? (bool) $_REQUEST['update_existing'] : false;
		$this->delimiter       = ! empty( $_REQUEST['delimiter'] ) ? wc_clean( wp_unslash( $_REQUEST['delimiter'] ) ) : ',';
		$this->map_preferences = isset( $_REQUEST['map_preferences'] ) ? (bool) $_REQUEST['map_preferences'] : false;
		// phpcs:enable

		// Import mappings for CSV data.
		//include_once dirname( __FILE__ ) . '/mappings/mappings.php';

		if ( $this->map_preferences ) {
			add_filter( 'woocommerce_csv_'.$this->import_type.'_import_mapped_columns', array( $this, 'auto_map_user_preferences' ), 9999 );
		}
	}

	public function get_next_step_link( $step = '' ) {
		if ( ! $step ) {
			$step = $this->step;
		}

		$keys = array_keys( $this->steps );

		if ( end( $keys ) === $step ) {
			return admin_url();
		}

		$step_index = array_search( $step, $keys, true );

		if ( false === $step_index ) {
			return '';
		}

		$params = array(
			'import_type'	  => $this->import_type,
			'import_page'	  => $this->import_page,
			'step'            => $keys[ $step_index + 1 ],
			'file'            => str_replace( DIRECTORY_SEPARATOR, '/', $this->file ),
			'delimiter'       => $this->delimiter,
			'update_existing' => $this->update_existing,
			'map_preferences' => $this->map_preferences,
			'_wpnonce'        => wp_create_nonce( 'woocommerce-csv-importer' ), // wp_nonce_url() escapes & to &amp; breaking redirects.
		);

		return add_query_arg( $params );
	}

	protected function output_header() {
		include dirname( __FILE__ ) . '/views/html-csv-import-header.php';
	}

	protected function output_steps() {
		include dirname( __FILE__ ) . '/views/html-csv-import-steps.php';
	}

	protected function output_footer() {
		include dirname( __FILE__ ) . '/views/html-csv-import-footer.php';
	}

	protected function add_error( $message, $actions = array() ) {
		$this->errors[] = array(
			'message' => $message,
			'actions' => $actions,
		);
	}

	protected function output_errors() {
		if ( ! $this->errors ) {
			return;
		}

		foreach ( $this->errors as $error ) {
			echo '<div class="error inline">';
			echo '<p>' . esc_html( $error['message'] ) . '</p>';

			if ( ! empty( $error['actions'] ) ) {
				echo '<p>';
				foreach ( $error['actions'] as $action ) {
					echo '<a class="button button-primary" href="' . esc_url( $action['url'] ) . '">' . esc_html( $action['label'] ) . '</a> ';
				}
				echo '</p>';
			}
			echo '</div>';
		}
	}

	public function dispatch() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['save_step'] ) && ! empty( $this->steps[ $this->step ]['handler'] ) ) {
			call_user_func( $this->steps[ $this->step ]['handler'], $this );
		}
		$this->output_header();
		$this->output_steps();
		$this->output_errors();
		call_user_func( $this->steps[ $this->step ]['view'], $this );
		$this->output_footer();
	}

	protected function upload_form() {
		$bytes      = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size       = size_format( $bytes );
		$upload_dir = wp_upload_dir();

		include dirname( __FILE__ ) . '/views/html-custom-csv-import-form.php';
	}

	public function upload_form_handler() {
		check_admin_referer( 'woocommerce-csv-importer' );

		$file = $this->handle_upload();

		if ( is_wp_error( $file ) ) {
			$this->add_error( $file->get_error_message() );
			return;
		} else {
			$this->file = $file;
		}

		wp_redirect( esc_url_raw( $this->get_next_step_link() ) );
		exit;
	}


	public function handle_upload() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified in WC__CSV_Importer_Controller::upload_form_handler()
		$file_url = isset( $_POST['file_url'] ) ? wc_clean( wp_unslash( $_POST['file_url'] ) ) : '';

		if ( empty( $file_url ) ) {
			if ( ! isset( $_FILES['import'] ) ) {
				return new WP_Error( 'woocommerce_custom_csv_importer_upload_file_empty', __( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.', 'woocommerce' ) );
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( ! self::is_file_valid_csv( wc_clean( wp_unslash( $_FILES['import']['name'] ) ), false ) ) {
				return new WP_Error( 'woocommerce_custom_csv_importer_upload_file_invalid', __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'woocommerce' ) );
			}

			$overrides = array(
				'test_form' => false,
				'mimes'     => self::get_valid_csv_filetypes(),
			);
			$import    = $_FILES['import']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$upload    = wp_handle_upload( $import, $overrides );

			if ( isset( $upload['error'] ) ) {
				return new WP_Error( 'woocommerce_custom_csv_importer_upload_error', $upload['error'] );
			}

			// Construct the object array.
			$object = array(
				'post_title'     => basename( $upload['file'] ),
				'post_content'   => $upload['url'],
				'post_mime_type' => $upload['type'],
				'guid'           => $upload['url'],
				'context'        => 'import',
				'post_status'    => 'private',
			);

			// Save the data.
			$id = wp_insert_attachment( $object, $upload['file'] );

			wp_schedule_single_event( time() + DAY_IN_SECONDS, 'importer_scheduled_cleanup', array( $id ) );

			return $upload['file'];
		} elseif ( file_exists( ABSPATH . $file_url ) ) {
			if ( ! self::is_file_valid_csv( ABSPATH . $file_url ) ) {
				return new WP_Error( 'woocommerce_custom_csv_importer_upload_file_invalid', __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'woocommerce' ) );
			}

			return ABSPATH . $file_url;
		}
		// phpcs:enable

		return new WP_Error( 'woocommerce_custom_csv_importer_upload_invalid_file', __( 'Please upload or provide the link to a valid CSV file.', 'woocommerce' ) );
	}

	public function mapping_form() {
		check_admin_referer( 'woocommerce-csv-importer' );

		$args = array(
			'import_type'	=> $this->import_type,
			'import_page'	=> $this->import_page,
			'lines'     => 1,
			'delimiter' => $this->delimiter,
		);

		$importer     = self::get_importer( $this->file, $args );

		$headers      = $importer->get_raw_keys();
		$mapped_items = $this->auto_map_columns( $headers );
		$sample       = current( $importer->get_raw_data() );

		if ( empty( $sample ) ) {
			$this->add_error(
				__( 'The file is empty or using a different encoding than UTF-8, please try again with a new file.', 'woocommerce' ),
				array(
					array(
						'url'   => admin_url( $this->import_page ),
						'label' => __( 'Upload a new file', 'woocommerce' ),
					),
				)
			);

			// Force output the errors in the same page.
			$this->output_errors();
			return;
		}

		include_once dirname( __FILE__ ) . '/views/html-csv-import-mapping.php';
	}

	public function import() {
		// Displaying this page triggers Ajax action to run the import with a valid nonce,
		// therefore this page needs to be nonce protected as well.
		check_admin_referer( 'woocommerce-csv-importer' );

		if ( ! self::is_file_valid_csv( $this->file ) ) {
			$this->add_error( __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'woocommerce' ) );
			$this->output_errors();
			return;
		}

		if ( ! is_file( $this->file ) ) {
			$this->add_error( __( 'The file does not exist, please try again.', 'woocommerce' ) );
			$this->output_errors();
			return;
		}

		if ( ! empty( $_POST['map_from'] ) && ! empty( $_POST['map_to'] ) ) {
			$mapping_from = wc_clean( wp_unslash( $_POST['map_from'] ) );
			$mapping_to   = wc_clean( wp_unslash( $_POST['map_to'] ) );

			// Save mapping preferences for future imports.
			update_user_option( get_current_user_id(), 'woocommerce_custom_import_mapping', $mapping_to );
		} else {
			wp_redirect( esc_url_raw( $this->get_next_step_link( 'upload' ) ) );
			exit;
		}

		wp_localize_script(
			'wc-'.$this->import_type.'-import',
			'wc_import_params',
			array(
				'import_type'	=> $this->import_type,
				'import_page'	=> $this->import_page,
				'import_nonce'    => wp_create_nonce( 'wc-import' ),
				'mapping'         => array(
					'from' => $mapping_from,
					'to'   => $mapping_to,
				),
				'file'            => $this->file,
				'update_existing' => $this->update_existing,
				'delimiter'       => $this->delimiter,
			)
		);
		wp_enqueue_script( 'wc-'.$this->import_type.'-import' );

		include_once dirname( __FILE__ ) . '/views/html-csv-import-progress.php';
	}

	protected function done() {
		check_admin_referer( 'woocommerce-csv-importer' );
		$imported  = isset( $_GET['terms-imported'] ) ? absint( $_GET['terms-imported'] ) : 0;
		$updated   = isset( $_GET['terms-updated'] ) ? absint( $_GET['terms-updated'] ) : 0;
		$failed    = isset( $_GET['terms-failed'] ) ? absint( $_GET['terms-failed'] ) : 0;
		$skipped   = isset( $_GET['terms-skipped'] ) ? absint( $_GET['terms-skipped'] ) : 0;
		$file_name = isset( $_GET['file-name'] ) ? sanitize_text_field( wp_unslash( $_GET['file-name'] ) ) : '';
		$errors    = array_filter( (array) get_user_option( 'custom_import_error_log' ) );

		/* KOSCH HERE */
			do_action( 'woocommerce_import_finished', 0, 0);

		include_once dirname( __FILE__ ) . '/views/html-csv-import-done.php';
	}

	protected function normalize_columns_names( $columns ) {
		$normalized = array();

		foreach ( $columns as $key => $value ) {
			$normalized[ strtolower( $key ) ] = $value;
		}

		return $normalized;
	}

	protected function auto_map_columns( $raw_headers, $num_indexes = true ) {

		global $terms_manager;
		
			$terms = array();
			foreach ($terms_manager->{"get_{$this->import_type}_terms"}() as $term) {
				$terms[__( $term['name'] , 'woocommerce' )]=$term['slug'];
			}
			/* KOSCH HERE */
			
			$default_columns = $this->normalize_columns_names($terms);	
		
			$special_columns = $this->get_special_columns(
				$this->normalize_columns_names(
					apply_filters(
						'woocommerce_csv_custom_import_mapping_special_columns',
						array(
							/* translators: %d: Download number */
							__( 'Download %d ID', 'woocommerce' ) => 'downloads:id',
							/* translators: %d: Download number */
							__( 'Download %d name', 'woocommerce' ) => 'downloads:name',
							/* translators: %d: Download number */
							__( 'Download %d URL', 'woocommerce' ) => 'downloads:url',
							/* translators: %d: Meta number */
							__( 'Meta: %s', 'woocommerce' ) => 'meta:',
						),
						$raw_headers
					)
				)
			);

			$headers = array();
			foreach ( $raw_headers as $key => $field ) {
				$normalized_field  = strtolower( $field );
				$index             = $num_indexes ? $key : $field;
				$headers[ $index ] = $normalized_field;

				if ( isset( $default_columns[ $normalized_field ] ) ) {
					$headers[ $index ] = $default_columns[ $normalized_field ];
				} else {
					foreach ( $special_columns as $regex => $special_key ) {
						// Don't use the normalized field in the regex since meta might be case-sensitive.
						if ( preg_match( $regex, $field, $matches ) ) {
							$headers[ $index ] = $special_key . $matches[1];
							break;
						}
					}
				}
			}

		//return $headers;
		return apply_filters( 'woocommerce_csv_'.$this->import_type.'_import_mapped_columns', $headers, $raw_headers );
	}

	public function auto_map_user_preferences( $headers ) {
		$mapping_preferences = get_user_option( 'woocommerce_custom_import_mapping' );

		if ( ! empty( $mapping_preferences ) && is_array( $mapping_preferences ) ) {
			return $mapping_preferences;
		}

		return $headers;
	}

	protected function sanitize_special_column_name_regex( $value ) {
		return '/' . str_replace( array( '%d', '%s' ), '(.*)', trim( quotemeta( $value ) ) ) . '/i';
	}

	protected function get_special_columns( $columns ) {
		$formatted = array();

		foreach ( $columns as $key => $value ) {
			$regex = $this->sanitize_special_column_name_regex( $key );

			$formatted[ $regex ] = $value;
		}

		return $formatted;
	}

	protected function get_mapping_options( $item = '' ) {
		global $terms_manager;

		// Get index for special column names.
		$index = $item;

		if ( preg_match( '/\d+/', $item, $matches ) ) {
			$index = $matches[0];
		}

		// Properly format for meta field.
		$meta = str_replace( 'meta:', '', $item );

			$terms = array();
			foreach ($terms_manager->{"get_{$this->import_type}_terms"}() as $term) {
				$terms[$term['slug']]=__( $term['name'] , 'woocommerce' );
			}

			$terms['meta:' . $meta] = __( 'Import as meta data', 'woocommerce' );

			$options=$terms;

		return apply_filters( 'woocommerce_csv_'.$this->import_type.'_import_mapping_options', $options, $item );
	}

	public function console_log($message){
		echo "<script>console.log('".$message."');</script>";
	}

}
