<?php
/**
 * Admin View: user Export
 *
 * @package WooCommerce\Admin\Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if($ext==null){
	exit;
}

wp_enqueue_script( 'wc-'.$ext['slug'].'-export' );

	$e_class='WC_'.ucfirst($ext['slug']).'_CSV_Exporter';
	$exporter = new $e_class();

?>
<div class="wrap woocommerce">
	<h1><?php esc_html_e( ucfirst($ext['slug']).' Export', 'woocommerce' ); ?></h1>
	<div class="woocommerce-exporter-wrapper">
		<form class="woocommerce-exporter">
			<header>
				<span class="spinner is-active"></span>
				<h2><?php esc_html_e( ucfirst($ext['slug']).' Export (CSV)', 'woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'This tool allows you to generate and download a CSV file containing a '.$ext['slug'].' list.', 'woocommerce' ); ?></p>
			</header>
			<section>
				<table class="form-table woocommerce-exporter-options">
					<tbody>
						<tr>
							<th scope="row">
								<label for="woocommerce-exporter-columns"><?php esc_html_e( 'Which columns should be exported?', 'woocommerce' ); ?></label>
							</th>
							<td>
								<select id="woocommerce-exporter-columns" class="woocommerce-exporter-columns wc-enhanced-select" style="width:100%;" multiple data-placeholder="<?php esc_attr_e( 'Export all columns', 'woocommerce' ); ?>">
									<?php
									foreach ( $exporter->get_default_column_names() as $column_id => $column_name ) {
										echo '<option value="' . esc_attr( $column_id ) . '">' . esc_html( $column_name ) . '</option>';
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="woocommerce-exporter-types"><?php esc_html_e( 'Which user roles should be exported?', 'woocommerce' ); ?></label>
							</th>
							<td>
								<select id="woocommerce-exporter-types" class="woocommerce-exporter-types wc-enhanced-select" style="width:100%;" multiple data-placeholder="<?php esc_attr_e( 'Export all users', 'woocommerce' ); ?>">
									<?php
										foreach ( $exporter->get_default_types() as $value => $label ) {
											echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
										}
									?>
								</select>
							</td>
						</tr>
						<?php if($ext['use_meta']){ ?>
							<tr>
								<th scope="row">
									<label for="woocommerce-exporter-meta"><?php esc_html_e( 'Export custom meta?', 'woocommerce' ); ?></label>
								</th>
								<td>
									<input type="checkbox" id="woocommerce-exporter-meta" value="1" />
									<label for="woocommerce-exporter-meta"><?php esc_html_e( 'Yes, export all custom meta', 'woocommerce' ); ?></label>
								</td>
							</tr>
						<?php } ?>

						<?php do_action( 'woocommerce_image_data_export_row' ); ?>

					</tbody>
				</table>
				<progress class="woocommerce-exporter-progress" max="100" value="0"></progress>
			</section>
			<div class="wc-actions">
				<button type="submit" class="woocommerce-exporter-button button button-primary" value="<?php esc_attr_e( 'Generate CSV', 'woocommerce' ); ?>"><?php esc_html_e( 'Generate CSV', 'woocommerce' ); ?></button>
			</div>
		</form>
	</div>
</div>
