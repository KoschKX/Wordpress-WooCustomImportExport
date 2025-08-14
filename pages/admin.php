<?php
	//global $wp;
	//require_once("../../../../wp-load.php");

	

	wp_enqueue_script('wooimportexport-script', plugin_dir_url( __FILE__ ) . '../js/admin.js');
	wp_enqueue_style('wooimportexport-style', plugin_dir_url( __FILE__ ) . '../css/admin.css');
	wp_localize_script('wooimportexport-script', 'wooimportexportScript', array(
	    'pluginsUrl' => plugin_dir_url( __FILE__ ).'../',
	));

	$wooimportexport_options = get_option('plugin_options');




?>
<div class="wrap wooimportexport">
	<h1><?php esc_html_e( 'Import Product Dates', 'wooimportexport' ); ?></h1>
	<div id="content_wrap" style="text-align:center;">
		<div id="main" style="visibility:hidden;">
			<div id="controls">
				
				
				     <input id="csv_file" type='file' name='file'>
				  
				      <input id="update_dates" type='submit' name='but_submit' value='Update' valueA="Update" valueB="Cancel" state="A">
				
				
			</div>
			<div id="loader" style="display:none;"></div>
			<div id="kosch_message"></div>
		</div>
	</div>
</div>