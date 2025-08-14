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

	if ( ! class_exists( 'WC_Custom_Importer', false ) ) {
		include_once dirname( __FILE__ ) . '/abstract/abstract-wc-custom-importer.php';
	}

	if ( ! class_exists( 'WC_Custom_CSV_Importer_Controller', false ) ) {
		include_once WOOIMPORTEXPORT_DIR . '/includes/admin/importers/class-wc-custom-csv-importer-controller.php';
	}

class WC_CSV_Custom_Importer extends WC_Custom_Importer {
	protected $parsing_raw_data_index = 0;
	public function __construct( $file, $params = array() ) {
		$default_args = array(
			'start_pos'        => 0, // File pointer start.
			'end_pos'          => -1, // File pointer end.
			'lines'            => -1, // Max lines to read.
			'mapping'          => array(), // Column mapping. csv_heading => schema_heading.
			'parse'            => false, // Whether to sanitize and format data.
			'update_existing'  => false, // Whether to update existing items.
			'delimiter'        => ',', // CSV delimiter.
			'prevent_timeouts' => true, // Check memory and time usage and abort if reaching limit.
			'enclosure'        => '"', // The character used to wrap text in the CSV.
			'escape'           => "\0", // PHP uses '\' as the default escape character. This is not RFC-4180 compliant. This disables the escape character.
		);

		$this->params = wp_parse_args( $params, $default_args );
		$this->file   = $file;

		if ( isset( $this->params['mapping']['from'], $this->params['mapping']['to'] ) ) {
			$this->params['mapping'] = array_combine( $this->params['mapping']['from'], $this->params['mapping']['to'] );
		}
		// Import mappings for CSV data.
		//include_once dirname( dirname( __FILE__ ) ) . '/admin/importers/mappings/mappings.php';
		$this->read_file();
	}

	protected function read_file() {
		if ( ! WC_Custom_CSV_Importer_Controller::is_file_valid_csv( $this->file ) ) {
			wp_die( esc_html__( 'Invalid file type. The importer supports CSV and TXT file formats.', 'woocommerce' ) );
		}
		$handle = fopen( $this->file, 'r' ); // @codingStandardsIgnoreLine.
		if ( false !== $handle ) {
			$this->raw_keys = version_compare( PHP_VERSION, '5.3', '>=' ) ? array_map( 'trim', fgetcsv( $handle, 0, $this->params['delimiter'], $this->params['enclosure'], $this->params['escape'] ) ) : array_map( 'trim', fgetcsv( $handle, 0, $this->params['delimiter'], $this->params['enclosure'] ) ); // @codingStandardsIgnoreLine
			if ( isset( $this->raw_keys[0] ) ) {
				$this->raw_keys[0] = $this->remove_utf8_bom( $this->raw_keys[0] );
			}
			if ( 0 !== $this->params['start_pos'] ) {
				fseek( $handle, (int) $this->params['start_pos'] );
			}
			while ( 1 ) {
				$row = version_compare( PHP_VERSION, '5.3', '>=' ) ? fgetcsv( $handle, 0, $this->params['delimiter'], $this->params['enclosure'], $this->params['escape'] ) : fgetcsv( $handle, 0, $this->params['delimiter'], $this->params['enclosure'] ); // @codingStandardsIgnoreLine

				if ( false !== $row ) {
					$this->raw_data[]                                 = $row;
					$this->file_positions[ count( $this->raw_data ) ] = ftell( $handle );

					if ( ( $this->params['end_pos'] > 0 && ftell( $handle ) >= $this->params['end_pos'] ) || 0 === --$this->params['lines'] ) {
						break;
					}
				} else {
					break;
				}
			}
			$this->file_position = ftell( $handle );
		}
		if ( ! empty( $this->params['mapping'] ) ) {
			$this->set_mapped_keys();
		}

		if ( $this->params['parse'] ) {
			$this->set_parsed_data();
		}
	}

	protected function remove_utf8_bom( $string ) {
		if ( 'efbbbf' === substr( bin2hex( $string ), 0, 6 ) ) {
			$string = substr( $string, 3 );
		}
		return $string;
	}

	protected function set_mapped_keys() {
		$mapping = $this->params['mapping'];
		foreach ( $this->raw_keys as $key ) {
			$this->mapped_keys[] = isset( $mapping[ $key ] ) ? $mapping[ $key ] : $key;
		}
	}

	public function parse_relative_field( $value ) {
		global $wpdb;

		if ( empty( $value ) ) {
			return '';
		}

		if(!$this->is_callable("get_column_relative_id")){return '';}

		if ( preg_match( '/^id:(\d+)$/', $value, $matches ) ) {
			$id = intval( $matches[1] );

			$original_id = get_column_relative_id($id, 0); // WPCS: db call ok, cache ok.

			if ( $original_id ) {
				return absint( $original_id );
			}

			$existing_id = get_column_relative_id($id, 1); 

			if ( $existing_id ) {
				return absint( $existing_id );
			}
			if ( ! $this->params['update_existing'] ) {
				$user = wc_get_user_object( 'simple' );
				$user->set_name( 'Import placeholder for ' . $id );
				$user->set_status( 'importing' );
				$user->add_meta_data( '_original_id', $id, true );
				$id = $user->save();
			}
			return $id;
		}
		if ( $id ) {
			return $id;
		}
		try {
			$user = wc_get_user_object( 'simple' );
			$user->set_name( 'Import placeholder for ' . $value );
			$user->set_status( 'importing' );
			$id = $user->save();
			if ( $id && ! is_wp_error( $id ) ) {
				return $id;
			}
		} catch ( Exception $e ) {
			return '';
		}
		return '';
	}

	public function parse_id_field( $value ) {
		global $wpdb;
		$id = absint( $value );
		return $id && ! is_wp_error( $id ) ? $id : 0;
	}

	public function parse_string_field( $value ) {
		return $value;
	}

	public function parse_relative_comma_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}
		return array_filter( array_map( array( $this, 'parse_relative_field' ), $this->explode_values( $value ) ) );
	}

	public function parse_comma_field( $value ) {
		if ( empty( $value ) && '0' !== $value ) {
			return array();
		}
		$value = $this->unescape_data( $value );
		return array_map( 'wc_clean', $this->explode_values( $value ) );
	}

	public function parse_bool_field( $value ) {
		if ( '0' === $value ) {
			return false;
		}
		if ( '1' === $value ) {
			return true;
		}
		return wc_clean( $value );
	}

	public function parse_float_field( $value ) {
		if ( '' === $value ) {
			return $value;
		}
		$value = $this->unescape_data( $value );
		return floatval( $value );
	}


	public function parse_stock_quantity_field( $value ) {
		if ( '' === $value ) {
			return $value;
		}
		$value = $this->unescape_data( $value );
		return wc_stock_amount( $value );
	}


	public function parse_tax_status_field( $value ) {
		if ( '' === $value ) {
			return $value;
		}
		$value = $this->unescape_data( $value );
		if ( 'true' === strtolower( $value ) || 'false' === strtolower( $value ) ) {
			$value = wc_string_to_bool( $value ) ? 'taxable' : 'none';
		}
		return wc_clean( $value );
	}


	public function parse_categories_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$row_terms  = $this->explode_values( $value );
		$categories = array();

		foreach ( $row_terms as $row_term ) {
			$parent = null;
			$_terms = array_map( 'trim', explode( '>', $row_term ) );
			$total  = count( $_terms );

			foreach ( $_terms as $index => $_term ) {
				// Don't allow users without capabilities to create new categories.
				if ( ! current_user_can( 'manage_'.$this->import_type.'_terms' ) ) {
					break;
				}

				$term = wp_insert_term( $_term, $this->import_type.'_cat', array( 'parent' => intval( $parent ) ) );

				if ( is_wp_error( $term ) ) {
					if ( $term->get_error_code() === 'term_exists' ) {
						// When term exists, error data should contain existing term id.
						$term_id = $term->get_error_data();
					} else {
						break; // We cannot continue on any other error.
					}
				} else {
					// New term.
					$term_id = $term['term_id'];
				}

				// Only requires assign the last category.
				if ( ( 1 + $index ) === $total ) {
					$categories[] = $term_id;
				} else {
					// Store parent to be able to insert or query categories based in parent ID.
					$parent = $term_id;
				}
			}
		}

		return $categories;
	}


	public function parse_tags_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$value = $this->unescape_data( $value );
		$names = $this->explode_values( $value );
		$tags  = array();

		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, $this->import_type.'_tag' );

			if ( ! $term || is_wp_error( $term ) ) {
				$term = (object) wp_insert_term( $name, $this->import_type.'_tag' );
			}

			if ( ! is_wp_error( $term ) ) {
				$tags[] = $term->term_id;
			}
		}

		return $tags;
	}


	public function parse_tags_spaces_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$value = $this->unescape_data( $value );
		$names = $this->explode_values( $value, ' ' );
		$tags  = array();

		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, $this->import_type.'user_tag' );

			if ( ! $term || is_wp_error( $term ) ) {
				$term = (object) wp_insert_term( $name, $this->import_type.'_tag' );
			}

			if ( ! is_wp_error( $term ) ) {
				$tags[] = $term->term_id;
			}
		}

		return $tags;
	}

	public function parse_images_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$images    = array();
		$separator = apply_filters( 'woocommerce_'.$this->import_type.'_import_image_separator', ',' );

		foreach ( $this->explode_values( $value, $separator ) as $image ) {
			$images[] = $image;
		}

		return $images;
	}


	public function parse_date_field( $value ) {
		if ( empty( $value ) ) {
			return null;
		}

		if ( preg_match( '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])([ 01-9:]*)$/', $value ) ) {
			// Don't include the time if the field had time in it.
			return current( explode( ' ', $value ) );
		}

		return null;
	}

	public function parse_backorders_field( $value ) {
		if ( empty( $value ) ) {
			return 'no';
		}

		$value = $this->parse_bool_field( $value );

		if ( 'notify' === $value ) {
			return 'notify';
		} elseif ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}

		return 'no';
	}

	public function parse_skip_field( $value ) {
		return $value;
	}

	public function parse_download_file_field( $value ) {
		// Absolute file paths.
		if ( 0 === strpos( $value, 'http' ) ) {
			return esc_url_raw( $value );
		}
		// Relative and shortcode paths.
		return wc_clean( $value );
	}

	public function parse_int_field( $value ) {
		// Remove the ' prepended to fields that start with - if needed.
		$value = $this->unescape_data( $value );

		return intval( $value );
	}


	public function parse_description_field( $description ) {
		$parts = explode( "\\\\n", $description );
		foreach ( $parts as $key => $part ) {
			$parts[ $key ] = str_replace( '\n', "\n", $part );
		}

		return implode( '\\\n', $parts );
	}

	public function parse_published_field( $value ) {
		if ( '' === $value ) {
			return $value;
		}

		// Remove the ' prepended to fields that start with - if needed.
		$value = $this->unescape_data( $value );

		if ( 'true' === strtolower( $value ) || 'false' === strtolower( $value ) ) {
			return wc_string_to_bool( $value ) ? 1 : -1;
		}

		return floatval( $value );
	}


	protected function get_formating_callback() {
		return $this->get_formatting_callback();
	}

	protected function get_formatting_callback() {

		global $terms_manager;

		$user_terms=$terms_manager->get_user_terms();
			
			$terms = array();
			foreach ($user_terms as $term) {
				if($term['type']!=''){
					if($term['parse']){
						$terms[$term['slug']] = array( $this, 'parse_'.$term['type'].'_field' );
					}else{
						$terms[$term['slug']] = $term['type'];
					}
				}
			}
			$data_formatting = $terms;

			/**
			 * Match special column names.
			 */
			$regex_match_data_formatting = array(
				'/attributes:value*/'    => array( $this, 'parse_comma_field' ),
				'/attributes:visible*/'  => array( $this, 'parse_bool_field' ),
				'/attributes:taxonomy*/' => array( $this, 'parse_bool_field' ),
				'/downloads:url*/'       => array( $this, 'parse_download_file_field' ),
				'/meta:*/'               => 'wp_kses_post', // Allow some HTML in meta fields.
			);

			$callbacks = array();

			// Figure out the parse function for each column.
			foreach ( $this->get_mapped_keys() as $index => $heading ) {
				$callback = 'wc_clean';

				if ( isset( $data_formatting[ $heading ] ) ) {
					$callback = $data_formatting[ $heading ];
				} else {
					foreach ( $regex_match_data_formatting as $regex => $callback ) {
						if ( preg_match( $regex, $heading ) ) {
							$callback = $callback;
							break;
						}
					}
				}

				$callbacks[] = $callback;
			}

		return apply_filters( 'woocommerce_'.$this->import_type.'_importer_formatting_callbacks', $callbacks, $this );
	}

	protected function starts_with( $haystack, $needle ) {
		return substr( $haystack, 0, strlen( $needle ) ) === $needle;
	}

	protected function expand_data( $data ) {
		$data = apply_filters( 'woocommerce_'.$this->import_type.'_importer_pre_expand_data', $data );

		// Images field maps to image and gallery id fields.
		if ( isset( $data['images'] ) ) {
			$images               = $data['images'];
			$data['raw_image_id'] = array_shift( $images );

			if ( ! empty( $images ) ) {
				$data['raw_gallery_image_ids'] = $images;
			}
			unset( $data['images'] );
		}

		// Type, virtual and downloadable are all stored in the same column.
		if ( isset( $data['type'] ) ) {
			$data['type']         = array_map( 'strtolower', $data['type'] );
			$data['virtual']      = in_array( 'virtual', $data['type'], true );
			$data['downloadable'] = in_array( 'downloadable', $data['type'], true );

			// Convert type to string.
			$data['type'] = current( array_diff( $data['type'], array( 'virtual', 'downloadable' ) ) );

			if ( ! $data['type'] ) {
				$data['type'] = 'simple';
			}
		}

		// Status is mapped from a special published field.
		if ( isset( $data['published'] ) ) {
			$statuses       = array(
				-1 => 'draft',
				0  => 'private',
				1  => 'publish',
			);
			$data['status'] = isset( $statuses[ $data['published'] ] ) ? $statuses[ $data['published'] ] : 'draft';

			// Fix draft status of variations.
			if ( isset( $data['type'] ) && 'variation' === $data['type'] && -1 === $data['published'] ) {
				$data['status'] = 'publish';
			}

			unset( $data['published'] );
		}

		if ( isset( $data['stock_quantity'] ) ) {
			if ( '' === $data['stock_quantity'] ) {
				$data['manage_stock'] = false;
				$data['stock_status'] = isset( $data['stock_status'] ) ? $data['stock_status'] : true;
			} else {
				$data['manage_stock'] = true;
			}
		}

		// Stock is bool or 'backorder'.
		if ( isset( $data['stock_status'] ) ) {
			if ( 'backorder' === $data['stock_status'] ) {
				$data['stock_status'] = 'onbackorder';
			} else {
				$data['stock_status'] = $data['stock_status'] ? 'instock' : 'outofstock';
			}
		}

		// Prepare grouped users.
		if ( isset( $data['grouped_users'] ) ) {
			$data['children'] = $data['grouped_users'];
			unset( $data['grouped_users'] );
		}

		// Tag ids.
		if ( isset( $data['tag_ids_spaces'] ) ) {
			$data['tag_ids'] = $data['tag_ids_spaces'];
			unset( $data['tag_ids_spaces'] );
		}

		// Handle special column names which span multiple columns.
		$downloads  = array();
		$meta_data  = array();


		foreach ( $data as $key => $value ) {
			if ( $this->starts_with( $key, 'downloads:name' ) ) {
				if ( ! empty( $value ) ) {
					$downloads[ str_replace( 'downloads:name', '', $key ) ]['name'] = $value;
				}
				unset( $data[ $key ] );

			} if ( $this->starts_with( $key, 'downloads:url' ) ) {
				if ( ! empty( $value ) ) {
					$downloads[ str_replace( 'downloads:url', '', $key ) ]['url'] = $value;
				}
				unset( $data[ $key ] );

			} elseif ( $this->starts_with( $key, 'meta:' ) ) {
				$meta_data[] = array(
					'key'   => str_replace( 'meta:', '', $key ),
					'value' => $value,
				);
				unset( $data[ $key ] );
			}
		}

		if ( ! empty( $downloads ) ) {
			$data['downloads'] = array();

			foreach ( $downloads as $key => $file ) {
				if ( empty( $file['url'] ) ) {
					continue;
				}

				$data['downloads'][] = array(
					'name' => $file['name'] ? $file['name'] : wc_get_filename_from_url( $file['url'] ),
					'file' => $file['url'],
				);
			}
		}

		if ( ! empty( $meta_data ) ) {
			$data['meta_data'] = $meta_data;
		}

		return $data;
	}

	protected function set_parsed_data() {
		$parse_functions = $this->get_formatting_callback();
		$mapped_keys     = $this->get_mapped_keys();
		$use_mb          = function_exists( 'mb_convert_encoding' );
		foreach ( $this->raw_data as $row_index => $row ) {
			if ( ! count( array_filter( $row ) ) ) {
				continue;
			}

			$this->parsing_raw_data_index = $row_index;

			$data = array();

			do_action( 'woocommerce_'.$this->import_type.'_importer_before_set_parsed_data', $row, $mapped_keys );
			
			foreach ( $row as $id => $value ) {
				if ( empty( $mapped_keys[ $id ] ) ) {
					continue;
				}
				if ( $use_mb ) {
					$encoding = mb_detect_encoding( $value, mb_detect_order(), true );
					if ( $encoding ) {
						$value = mb_convert_encoding( $value, 'UTF-8', $encoding );
					} else {
						$value = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
					}
				} else {
					$value = wp_check_invalid_utf8( $value, true );
				}
				$data[ $mapped_keys[ $id ] ] = call_user_func( $parse_functions[ $id ], $value );
			}
			$this->parsed_data[] = apply_filters( 'woocommerce_'.$this->import_type.'_importer_parsed_data', $this->expand_data( $data ), $this );
		}
	}

	protected function get_row_id( $parsed_data ) {
		$id       = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
		$name     = isset( $parsed_data['name'] ) ? esc_attr( $parsed_data['name'] ) : '';
		$row_data = array();

		if ( $name ) {
			$row_data[] = $name;
		}
		if ( $id ) {
			/* translators: %d: user ID */
			$row_data[] = sprintf( __( 'ID %d', 'woocommerce' ), $id );
		}

		return implode( ', ', $row_data );
	}

	public function import() {
		$this->start_time = time();
		$index            = 0;
		$update_existing  = $this->params['update_existing'];
		$data             = array(
			'imported' => array(),
			'failed'   => array(),
			'updated'  => array(),
			'skipped'  => array(),
		);


		foreach ( $this->parsed_data as $parsed_data_key => $parsed_data ) {
			if(!$this->is_callable("get_object")){continue;}
			if(!$this->is_callable("check_exists")){continue;}

			do_action( 'woocommerce_'.$this->import_type.'_import_before_import', $parsed_data );
			
			if($this->is_callable("get_id")){
				$parsed_data['id']=$this->get_id($parsed_data);
			}

			$id         = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
			$id_exists  = false;

			if ( $id ) {
				$obj   = $this->get_object( $id,$parsed_data);
				$id_exists = $obj && $this->check_exists($id);
			}

			if ( $id_exists && ! $update_existing ) {
				$data['skipped'][] = new WP_Error(
					'woocommerce_'.$this->import_type.'_importer_error',
					esc_html__( 'A '.$this->import_type.' with this ID already exists.', 'woocommerce' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && ( isset( $parsed_data['id'] ) ) && ! $id_exists) {
				$data['skipped'][] = new WP_Error(
					'woocommerce_'.$this->import_type.'_importer_error',
					esc_html__( 'No matching '.$this->import_type.' exists to update.', 'woocommerce' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			$result = $this->process_item( $parsed_data, $update_existing );

			if ( is_wp_error( $result ) ) {
				$result->add_data( array( 'row' => $this->get_row_id( $parsed_data ) ) );
				$data['failed'][] = $result;
			} elseif ( $result['updated'] ) {
				$data['updated'][] = $result['id'];
			} else {
				$data['imported'][] = $result['id'];
			}

			$index ++;

			if ( $this->params['prevent_timeouts'] && ( $this->time_exceeded() || $this->memory_exceeded() ) ) {
				$this->file_position = $this->file_positions[ $index ];
				break;
			}
		}

		if($this->is_callable("clean_up")){
			$this->clean_up();
		}

		return $data;
	}

	public function is_callable($func){
		return is_callable( array( $this, $func ));
	}

	public function console_log($message){
		echo "<script>console.log('".$message."');</script>";
	}

}
