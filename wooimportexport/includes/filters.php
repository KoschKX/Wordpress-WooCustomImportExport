<?php 

	function woocommerce_user_allow_meta_keys($arr, $user=-1) {
		$arr=array(
			'description',
			'wc_last_active',
			'wp_wc_stripe_customer_live',
			'wp_wc_stripe_customer_test',
			'_woocommerce_persistent_cart_1',
			'_last_order',
			'shipping_method',
			'paying_customer'			
		);
		return $arr;
	}
	add_filter( 'woocommerce_user_export_allow_meta_keys', 'woocommerce_user_allow_meta_keys' );
    function woocommerce_user_skip_meta_keys($arr, $user=-1) {
        $arr=array(
			'user_login',
			'user_pass',
			'user_nicename',
			'user_email',
			'user_url',
			'user_registered',
			'user_activation_key',
			'user_status',
			'display_name',
			'wp_user_level',
			'wp_capabilities',
			'first_name',
			'last_name',
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_postcode',
			'billing_state',
			'billing_phone',
			'billing_email',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_postcode',
			'shipping_state',
			'shipping_phone',
			'shipping_email',
			'_order_count',
			'last_update',
			'nickname',
			'rich_editing',
			'syntax_highlighting',
			'comment_shortcuts',
			'admin_color',
			'use_ssl',
			'show_admin_bar_front',
			'locale',
			'wp_capabilities',
			'wp_user_level',
			'dismissed_wp_pointers',
			'show_welcome_panel',
			'wp_dashboard_quick_press_last_post_id',
			'community-events-location',
			'dismissed_update_notice',
			'last_update',
			'woocommerce_admin_activity_panel_inbox_last_read',
			'jetpack_tracks_anon_id',
			'wp_user-settings',
			'_wc_plugin_framework_facebook_for_woocommerce_dismissed_messages',
			'woocommerce_admin_task_list_tracked_started_tasks',
			'wpseo_title',
			'wpseo_metadesc',
			'wpseo_noindex_author',
			'wpseo_content_analysis_disable',
			'wpseo_keyword_analysis_disable',
			'wp_yoast_notifications',
			'closedpostboxes_product',
			'metaboxhidden_product',
			'jetpack_tracks_wpcom_id',
			'dismissed_no_secure_connection_notice',
			'closedpostboxes_',
			'metaboxhidden_',
			'wp_product_import_error_log',
			'_yoast_wpseo_profile_updated',
			'default_password_nag',
			'grant_user_role',
			'grant_user_role_status',
			'ur_form_id',
			'wp_woocommerce_product_import_mapping',
			'managenav-menuscolumnshidden',
			'metaboxhidden_nav-menus',
			'closedpostboxes_nav-menus',
			'nav_menu_recently_edited',
			'closedpostboxes_dashboard',
			'metaboxhidden_dashboard',
			'dcf_dismissed_notices',
			'meta-box-order_dashboard',
			'closedpostboxes_toplevel_page_gfw_tests',
			'metaboxhidden_toplevel_page_gfw_tests',
			'w3tc_features_seen',
			'wp_statistics',
			'wcpay_currency',
			'wp_user-settings-time',
		);
		return $arr;
    }
	add_filter( 'woocommerce_user_export_skip_meta_keys', 'woocommerce_user_skip_meta_keys' );

	add_filter('woocommerce_product_export_meta_value',  'woo_handle_export', 10, 4); 
	function woo_handle_export($value, $meta, $product, $row)
	{
		if ($meta->key == '_knawatfibu_url') {
			if(is_array($value)){
				$value=$value['img_url'];
				//$value=wp_json_encode($value);
			}
			if($value=="0"){
				$value='';
			}
		    return $value;
		}
	}