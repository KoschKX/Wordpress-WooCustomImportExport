<?php

if ( ! function_exists( 'wooimportexport_bootstrap' ) ) {

	/**
	 * Initialize the plugin.
	 */

	function wooimportexport_bootstrap() {
		load_plugin_textdomain( 'wooimportexport', false, __DIR__ . '/languages' );
	}
}


if ( ! function_exists( 'wooimportexport_register_admin_page' ) ) {

	/**
	 * Register the admin page.
	 */

	function wooimportexport_register_admin_styles() {
		global $woo_screen;

		$suffix    = '';
		$version   = WC_VERSION;
		
		if (is_admin()) {

			include_once WOOIMPORTEXPORT_DIR . '/includes/terms_manager.php';
			
			$woo_screen=get_current_screen($suffix)->id;
			$terms_manager=new WC_Terms_Manager();

			include_once __DIR__ . '/styles.php';
		}
	}

	function wooimportexport_register_admin_page() {
		//require __DIR__ . '/php/user_export/admin/class-wc-admin-exporters.php';
		if (defined('WC_ABSPATH')&&is_admin()) {

			include_once WOOIMPORTEXPORT_DIR . '/includes/terms_manager.php';

			$terms_manager=new WC_Terms_Manager();

			include_once __DIR__ . '/includes/admin/class-wc-admin-importers.php';
			include_once __DIR__ . '/includes/admin/class-wc-admin-exporters.php';
		}
	}
	add_action( 'woocommerce_integrations_init', 'wooimportexport_register_admin_page' );

	add_action( 'current_screen', 'wooimportexport_register_admin_styles');

}

if ( ! function_exists( 'wp_body_open' ) ) {

	/**
	 * Add wp_body_open() template tag if it doesn't exist (WP versions less than 5.2).
	 */
	function wp_body_open() {
		/**
		 * Triggered after the opening body tag.
		 *
		 * @since 5.2.0
		 */
		do_action( 'wp_body_open' );

	}
}

