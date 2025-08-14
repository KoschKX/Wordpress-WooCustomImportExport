/*global ajaxurl, wc_import_params */
;(function ( $, window ) {

	/**
	 * ImportForm handles the import process.
	 */
	var ImportForm = function( $form ) {
		this.$form           = $form;
		this.xhr             = false;
		this.mapping         = wc_import_params.mapping;
		this.position        = 0;
		this.file            = wc_import_params.file;
		this.update_existing = wc_import_params.update_existing;
		this.delimiter       = wc_import_params.delimiter;
		this.security        = wc_import_params.import_nonce;

		// Number of import successes/failures.
		this.imported = 0;
		this.failed   = 0;
		this.updated  = 0;
		this.skipped  = 0;

		this.import_type	 = wc_import_params.import_type;
		this.import_page	 = wc_import_params.import_page;

		// Initial state.
		this.$form.find('.woocommerce-importer-progress').val( 0 );

		this.run_import = this.run_import.bind( this );

		// Start importing.
		this.run_import();
	};

	/**
	 * Run the import in batches until finished.
	 */
	ImportForm.prototype.run_import = function() {
		var $this = this;
		$.ajax( {
			type: 'POST',
			url: ajaxurl,
			data: {
				action          : 'woocommerce_do_ajax_'+$this.import_type+'_import',
				import_type		: $this.import_type,
				import_page		: $this.import_page,
				position        : $this.position,
				mapping         : $this.mapping,
				file            : $this.file,
				update_existing : $this.update_existing,
				delimiter       : $this.delimiter,
				security        : $this.security
			},
			dataType: 'json',
			success: function( response ) {
				if ( response.success ) {
					$this.position  = response.data.position;
					$this.imported += response.data.imported;
					$this.failed   += response.data.failed;
					$this.updated  += response.data.updated;
					$this.skipped  += response.data.skipped;
					$this.$form.find('.woocommerce-importer-progress').val( response.data.percentage );

					if ( 'done' === response.data.position ) {
						var file_name = wc_import_params.file.split( '/' ).pop();
						window.location = response.data.url +
							'&terms-imported=' +
							parseInt( $this.imported, 10 ) +
							'&terms-failed=' +
							parseInt( $this.failed, 10 ) +
							'&terms-updated=' +
							parseInt( $this.updated, 10 ) +
							'&terms-skipped=' +
							parseInt( $this.skipped, 10 ) +
							'&file-name=' +
							file_name;
					} else {
						$this.run_import();
					}
				}
			}
		} ).fail( function( response ) {
			window.console.log( response );
		} );
	};

	/**
	 * Function to call ImportForm on jQuery selector.
	 */
	$.fn.wc_extended_importer = function() {
		new ImportForm( this );
		return this;
	};

	$( '.woocommerce-importer' ).wc_extended_importer();

})( jQuery, window );
