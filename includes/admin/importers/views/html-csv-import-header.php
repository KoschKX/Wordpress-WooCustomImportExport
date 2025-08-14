<?php
/**
 * Admin View: Header
 *
 * @package WooCommerce\Admin\Importers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap woocommerce">
	<h1><?php esc_html_e( ucfirst($this->import_type).' Import', 'woocommerce' ); ?></h1>

	<div class="woocommerce-progress-form-wrapper">
