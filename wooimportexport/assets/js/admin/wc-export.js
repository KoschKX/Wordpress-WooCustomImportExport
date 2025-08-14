/*global ajaxurl, wc_export_params */
;(function ( $, window ) {
	/**
	 * ExportForm handles the export process.
	 */

	var slug = wc_export_params.slug;

	var ExportForm = function( $form ) {
		this.$form = $form;
		this.xhr   = false;

		// Initial state.
		this.$form.find('.woocommerce-exporter-progress').val( 0 );

		// Methods.
		this.processStep = this.processStep.bind( this );

		// Events.
		$form.on( 'submit', { ExportForm: this }, this.onSubmit );
		$form.find( '.woocommerce-exporter-types' ).on( 'change', { ExportForm: this }, this.exportTypeFields );
	};

	/**
	 * Handle export form submission.
	 */
	ExportForm.prototype.onSubmit = function( event ) {
		event.preventDefault();

		var currentDate    = new Date(),
			day            = currentDate.getDate(),
			month          = currentDate.getMonth() + 1,
			year           = currentDate.getFullYear(),
			timestamp      = currentDate.getTime(),
			filename       = 'wc-'+slug+'-export-' + day + '-' + month + '-' + year + '-' + timestamp + '.csv';

		event.data.ExportForm.$form.addClass( 'woocommerce-exporter__exporting' );
		event.data.ExportForm.$form.find('.woocommerce-exporter-progress').val( 0 );
		event.data.ExportForm.$form.find('.woocommerce-exporter-button').prop( 'disabled', true );
		event.data.ExportForm.processStep( 1, $( this ).serialize(), '', filename );
	};

	/**
	 * Process the current export step.
	 */
	ExportForm.prototype.processStep = function( step, data, columns, filename ) {
		
		var $this         = this,
			selected_columns = $( '.woocommerce-exporter-columns' ).val(),
			export_meta      = $( '#woocommerce-exporter-meta:checked' ).length ? 1: 0,
			export_types     = $( '.woocommerce-exporter-types' ).val()

		$.ajax( {
			type: 'POST',
			url: ajaxurl,
			data: {
				form             : data,
				action           : 'woocommerce_do_ajax_'+slug+'_export',
				step             : step,
				columns          : columns,
				selected_columns : selected_columns,
				export_meta      : export_meta,
				export_types     : export_types,
				filename         : filename,
				security         : wc_export_params.export_nonce
			},
			dataType: 'json',
			success: function( response ) {

				console.log(response.data.percentage);
				if ( response.success ) {

					if ( 'done' === response.data.step ) {
						$this.$form.find('.woocommerce-exporter-progress').val( response.data.percentage );
						window.location = response.data.url;
						setTimeout( function() {
							$this.$form.removeClass( 'woocommerce-exporter__exporting' );
							$this.$form.find('.woocommerce-exporter-button').prop( 'disabled', false );
						}, 2000 );
					} else {
						$this.$form.find('.woocommerce-exporter-progress').val( response.data.percentage );
						$this.processStep( parseInt( response.data.step, 10 ), data, response.data.columns, filename );
					}
				}


			}
		} ).fail( function( response ) {
			window.console.log( response );
		} );
	};

	/**
	 * Handle fields per export type.
	 */
	ExportForm.prototype.exportTypeFields = function() {
		var exportCategory = $( '.woocommerce-exporter-'+slug );

		if ( -1 !== $.inArray( 'variation', $( this ).val() ) ) {
			exportCategory.closest( 'tr' ).hide();
			exportCategory.val( '' ).trigger( 'change' ); // Reset WooSelect selected value.
		} else {
			exportCategory.closest( 'tr' ).show();
		}
	};

	/**
	 * Function to call ExportForm on jquery selector.
	 */
	$.fn.wc_export_form = function() {
		new ExportForm( this );
		return this;
	};

	$( '.woocommerce-exporter' ).wc_export_form();

})( jQuery, window );
