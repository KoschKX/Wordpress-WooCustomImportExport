<?php

	$woo_extensions=array();
		array_push($woo_extensions,$terms_manager->get_exporters());
		array_push($woo_extensions,$terms_manager->get_importers());

		$is_woo=false;
		foreach($woo_extensions as $w_exs){
			foreach($w_exs as $e){

				if (strpos($woo_screen, $e['slug'].'_exporter') !== false
					||strpos($woo_screen, $e['slug'].'_extended_importer') !== false) {
					$is_woo=true;
				}
			}
		}

	if($is_woo){
		wp_register_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), $version );
		wp_register_style( 'jquery-ui-style', WC()->plugin_url() . '/assets/css/jquery-ui/jquery-ui.min.css', array(), $version );

		wp_style_add_data( 'woocommerce_admin_styles', 'rtl', 'replace' );

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'jquery-ui-style' );
		wp_enqueue_style( 'wp-color-picker' );
	}
