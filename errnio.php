<?php
/*
Plugin Name: Searchy Site by errnio
Plugin URI: http://errnio.com
Description: errnio Mobile Site Search plugin is a mobile-first search box for your site, with far better engagement for internal site search.
Version: 2.0
Author: Errnio
Author URI: http://errnio.com
*/

/***** Constants ******/

define('SEARCHY_SITE_BY_ERRNIO_INSTALLER_NAME', 'wordpress_searchy_site_by_errnio');

define('SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGID', 'errnio_id');
define('SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGTYPE', 'errnio_id_type');

define('SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGID_LEGACY', 'errnio_api');
define('SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGTYPE_LEGACY', 'errnio_api_type');

define('SEARCHY_SITE_BY_ERRNIO_EVENT_NAME_ACTIVATE', 'wordpress_activated');
define('SEARCHY_SITE_BY_ERRNIO_EVENT_NAME_DEACTIVATE', 'wordpress_deactivated');
define('SEARCHY_SITE_BY_ERRNIO_EVENT_NAME_UNINSTALL', 'wordpress_uninstalled');

define('SEARCHY_SITE_BY_ERRNIO_TAGTYPE_TEMP', 'temporary');
define('SEARCHY_SITE_BY_ERRNIO_TAGTYPE_PERM', 'permanent');

/***** Utils ******/

function searchy_site_by_errnio_do_wp_post_request($url, $data) {
	$data = json_encode( $data );
	$header = array('Content-type' => 'application/json');
	$response = wp_remote_post($url, array(
	    'headers' => $header,
	    'body' => $data
	));

	return json_decode(wp_remote_retrieve_body($response));
}

function searchy_site_by_errnio_send_event($eventType) {
	$tagId = get_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGID);
	if ($tagId) {
		$urlpre = 'http://customer.errnio.com';
	 	$createTagUrl = $urlpre.'/sendEvent';

	 	$params = array('tagId' => $tagId, 'eventName' => $eventType);
	 	$response = searchy_site_by_errnio_do_wp_post_request($createTagUrl, $params);
	}
	// No tagId - no point sending an event
}

function searchy_site_by_errnio_create_tagid() {
	$urlpre = 'http://customer.errnio.com';
 	$createTagUrl = $urlpre.'/createTag';
 	$params = array('installerName' => SEARCHY_SITE_BY_ERRNIO_INSTALLER_NAME);
 	$response = searchy_site_by_errnio_do_wp_post_request($createTagUrl, $params);

	if ($response && $response->success) {
		$tagId = $response->tagId;
		add_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGID, $tagId);
	 	add_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGTYPE, SEARCHY_SITE_BY_ERRNIO_TAGTYPE_TEMP);
		return $tagId;
	}
	
	return NULL;
}

function searchy_site_by_errnio_convert_legacy_options() {
	$tagtype = get_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGTYPE_LEGACY);
	$tagId = get_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGID_LEGACY);

	// This is to make sure these are not empty
	if ($tagtype == SEARCHY_SITE_BY_ERRNIO_TAGTYPE_PERM || $tagtype == SEARCHY_SITE_BY_ERRNIO_TAGTYPE_TEMP) {
		add_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGID, $tagId);
		add_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGTYPE, $tagtype);
	}

	if ($tagtype == SEARCHY_SITE_BY_ERRNIO_TAGTYPE_PERM) {
		return false;
	} else if ($tagtype == SEARCHY_SITE_BY_ERRNIO_TAGTYPE_TEMP) {
		return true;
	}
}

function searchy_site_by_errnio_check_need_register() {
	$tagtype = get_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGTYPE);
	$needregister = true;
	
	if ($tagtype == SEARCHY_SITE_BY_ERRNIO_TAGTYPE_PERM) {
		$needregister = false;
	} else if ($tagtype == SEARCHY_SITE_BY_ERRNIO_TAGTYPE_TEMP) {
		$needregister = true;
	} else {
		// No such options - need to check legacy
		$needregister = searchy_site_by_errnio_convert_legacy_options();
	}
	
	return $needregister;
}

/***** Activation / Deactivation / Uninstall hooks ******/

function searchy_site_by_errnio_activate() {
	if ( ! current_user_can( 'activate_plugins' ) )
	        return;
	
	$tagId = get_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGID);

	if ( $tagId === FALSE || empty($tagId) ) {
		// First time activation
		$tagId = searchy_site_by_errnio_create_tagid();
	} else {
		// Previously activated - meaning tagType + tagId should exists
	}
	
	// Send event - activated
	searchy_site_by_errnio_send_event(SEARCHY_SITE_BY_ERRNIO_EVENT_NAME_ACTIVATE);
}

function searchy_site_by_errnio_deactivate() {
	if ( ! current_user_can( 'activate_plugins' ) )
	        return;
	
	// Send event - deactivated
	searchy_site_by_errnio_send_event(SEARCHY_SITE_BY_ERRNIO_EVENT_NAME_DEACTIVATE);
}

function searchy_site_by_errnio_uninstall() {
	if ( ! current_user_can( 'activate_plugins' ) )
	        return;

	// Send event - uninstall
	searchy_site_by_errnio_send_event(SEARCHY_SITE_BY_ERRNIO_EVENT_NAME_UNINSTALL);	
	
	delete_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGID);
	delete_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGTYPE);
}

register_activation_hook( __FILE__, 'searchy_site_by_errnio_activate' );
register_deactivation_hook( __FILE__, 'searchy_site_by_errnio_deactivate' );
register_uninstall_hook( __FILE__, 'searchy_site_by_errnio_uninstall' );

/***** Client side script load ******/

function searchy_site_by_errnio_load_client_script() {
	$list = 'enqueued';
	$handle = 'errnio_script';

	// Script already running on this page
	if (wp_script_is($handle, $list)) {
		return;
	}

	$tagId = get_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGID);

	if (!$tagId || empty($tagId)) {
		$tagId = searchy_site_by_errnio_create_tagid();
	}

	if ($tagId) {
		$script_url = "//service.errnio.com/loader?tagid=".$tagId;
		wp_register_script($handle, $script_url, false, '1.0', true);
		wp_enqueue_script($handle );
	}
}

function searchy_site_by_errnio_load_client_script_add_async_attr( $url ) {
	if(FALSE === strpos( $url, 'service2.errnio.com')){
		return $url;
	}

	return "$url' async='async";
}

add_filter('clean_url', 'searchy_site_by_errnio_load_client_script_add_async_attr', 11, 1);
add_action('wp_enqueue_scripts', 'searchy_site_by_errnio_load_client_script', 99999 );

/***** Admin ******/

function searchy_site_by_errnio_add_settings_menu_option() {
    add_menu_page (
        'Errnio Options',   //page title
        'Errnio Settings',  //menu title
        'manage_options',   //capability
        'errnio-options',   //menu_slug
        'searchy_site_by_errnio_admin_page',  //function
        plugin_dir_url( __FILE__ ) . '/assets/img/errnio-icon.png'  //icon_url
        //There is another parameter - position
    );
}

function searchy_site_by_errnio_add_settings_link_on_plugin($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
		$adminpage_url = admin_url( 'admin.php?page=errnio-options' );
        $settings_link = '<a href="'.$adminpage_url.'">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}

function searchy_site_by_errnio_admin_notice() {
	$needregister = searchy_site_by_errnio_check_need_register();
	$settingsurl = admin_url( 'admin.php?page=errnio-options' );
	
	if($needregister){
		echo('<div style="font-weight: bold; background-color: #7ad03a; padding: 1px 12px; box-shadow: 0 1px 1px 0 rgba(0,0,0,.1); margin: 5px 0 15px;"><p style="font-size: 14px;">Please register your site in the errnio settings section <a href="'.$settingsurl.'">here</a></p></div>');
	}
}

function searchy_site_by_errnio_admin_page() {
	$stylehandle = 'errnio-style';
	$jshandle = 'errnio-js';
	wp_register_style('googleFonts', 'http://fonts.googleapis.com/css?family=Exo+2:700,400,200,700italic,300italic,300');
	wp_enqueue_style('googleFonts');
	wp_register_style($stylehandle, plugins_url('assets/css/errnio.css', __FILE__));
	wp_enqueue_style($stylehandle);
	wp_register_script($jshandle, plugins_url('assets/js/errnio.js', __FILE__), array('jquery'));
	wp_enqueue_script($jshandle);
	wp_localize_script($jshandle, 'errniowp', array('ajax_url' => admin_url( 'admin-ajax.php' )));
    ?>
    <div class="wrap">
		<?php
		$needregister = searchy_site_by_errnio_check_need_register();
		$tagId = get_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGID);

		echo '<h2>Errnio Options</h2>';

		if (!$needregister) {
			echo '<div class="bold"><p>Your new errnio plugin is up and running.<br/>For configuration and reports please visit your dashboard at <a href="http://brutus.errnio.com/">brutus.errnio.com</a></p><br/><img src="'.plugins_url('assets/img/logo-366x64.png', __FILE__).'"/></div>';
		} else {
			if ($tagId) {
				echo '<div class="errnio" height="100%" width="100%" data-tagId="'.$tagId.'" data-installName="'.SEARCHY_SITE_BY_ERRNIO_INSTALLER_NAME.'">';
				include 'assets/includes/errnio-admin.php';
				echo '</div>';
			} else {
				echo '<p>There was an error :( Contact <a href="mailto:support@errnio.com">support@errnio.com</a> for help.</p>';
			}
		};

		?>
    </div>
    <?php
}

function searchy_site_by_errnio_register_callback() {
	$type = $_POST['type'];
	$tagId = $_POST['tag_id'];

	if ($type == 'switchTag') {
		update_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGID, $tagId);
	}

	update_option(SEARCHY_SITE_BY_ERRNIO_OPTION_NAME_TAGTYPE, SEARCHY_SITE_BY_ERRNIO_TAGTYPE_PERM);

	wp_die(); // this is required to terminate immediately and return a proper response
}

add_action('admin_menu', 'searchy_site_by_errnio_add_settings_menu_option');
add_filter('plugin_action_links', 'searchy_site_by_errnio_add_settings_link_on_plugin', 10, 2);
add_action('admin_notices', 'searchy_site_by_errnio_admin_notice');
add_action('wp_ajax_searchy_site_by_errnio_register', 'searchy_site_by_errnio_register_callback');