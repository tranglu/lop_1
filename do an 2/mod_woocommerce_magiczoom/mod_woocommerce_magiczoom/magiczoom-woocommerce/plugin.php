<?php
/*

Copyright 2017 MagicToolbox (email : support@magictoolbox.com)

*/

$error_message = false;
$update_plugin = true;

function WooCommerce_MagicZoom_activate () {

    set_transient( 'WooCommerce_MagicZoom_welcome_license_activation_redirect', true, 30 );

    if(!function_exists('file_put_contents')) {
        function file_put_contents($filename, $data) {
            $fp = fopen($filename, 'w+');
            if ($fp) {
                fwrite($fp, $data);
                fclose($fp);
            }
        }
    }

    /* === onlyForMod start: woocommerce */

    //fix url's in css files
    $fileContents = file_get_contents(dirname(__FILE__) . '/core/magiczoom.css');
    $cssPath = preg_replace('/https?:\/\/[^\/]*/is', '', get_option("siteurl"));

    $cssPath .= '/wp-content/'.preg_replace('/^.*?\/(plugins\/.*?)$/is', '$1', str_replace("\\","/",dirname(__FILE__))).'/core';

    $pattern = '#url\(\s*(\'|")?(?!data:|mhtml:|http(?:s)?:|/)([^\)\s\'"]+?)(?(1)\1)\s*\)#is';
    $replace = 'url($1'.$cssPath.'/$2$1)';

    $fixedFileContents = preg_replace($pattern, $replace, $fileContents);
    if($fixedFileContents != $fileContents) {
        file_put_contents(dirname(__FILE__) . '/core/magiczoom.css', $fixedFileContents);
    }

    magictoolbox_WooCommerce_MagicZoom_create_db();

    magictoolbox_WooCommerce_MagicZoom_init();

    WooCommerce_MagicZoom_send_stat('install');
}

function WooCommerce_MagicZoom_deactivate () {}

function WooCommerce_MagicZoom_uninstall() {

    magictoolbox_WooCommerce_MagicZoom_delete_row_from_db('WooCommerce_MagicZoom_magicscroll');
    magictoolbox_WooCommerce_MagicZoom_delete_row_from_db();

    if (magictoolbox_WooCommerce_MagicZoom_is_empty_db() && !count(WooCommerce_MagicZoom_get_active_modules())) {
        magictoolbox_WooCommerce_MagicZoom_remove_db();
    }

    delete_option("WooCommerceMagicZoomCoreSettings");
    WooCommerce_MagicZoom_send_stat('uninstall');
}

function WooCommerce_MagicZoom_get_active_modules() {
    $name = explode('/', plugin_basename( __FILE__ ));
    $name = $name[0];
    $mtb_ap = array();

    foreach (get_option('active_plugins') as $value) {
        $name2 = explode('/', $value);
        $name2 = $name2[0];

        if ($name2 != $name && preg_match('/magiczoom|magiczoomplus|magic360|magicslideshow|magicscroll|magicthumb/', $value)) {
            $mtb_ap[] = $value;
        }
    }

    return $mtb_ap;
}

function WooCommerce_MagicZoom_send_stat($action = '') {

    //NOTE: don't send from working copy
    if('working' == 'v6.8.10' || 'working' == 'v5.3.2') {
        return;
    }

    $hostname = 'www.magictoolbox.com';

    $url = preg_replace('/^https?:\/\//is', '', get_option("siteurl"));
    $url = urlencode(urldecode($url));

    $platformVersion = defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : '';

    $path = "api/stat/?action={$action}&tool_name=magiczoom&license=trial&tool_version=v5.3.2&module_version=v6.8.10&platform_name=woocommerce&platform_version={$platformVersion}&url={$url}";
    $handle = @fsockopen('ssl://' . $hostname, 443, $errno, $errstr, 30);
    if($handle) {
        $headers  = "GET /{$path} HTTP/1.1\r\n";
        $headers .= "Host: {$hostname}\r\n";
        $headers .= "Connection: Close\r\n\r\n";
        fwrite($handle, $headers);
        fclose($handle);
    }

}

//add tab with videos in woocommerce product data
add_filter( 'woocommerce_product_data_tabs', 'WooCommerce_MagicZoom_add_videos_product_data_tab' , 99 , 1 );
function WooCommerce_MagicZoom_add_videos_product_data_tab( $product_data_tabs ) {
    $product_data_tabs['product-videos'] = array(
        'label'  => __( 'Product videos', 'my_text_domain' ),
        'target' => 'product_videos_product_data',
        'class'  => array('linked_product_options'),
    );
    return $product_data_tabs;
}

//content for tab videos
add_action( 'woocommerce_product_data_panels', 'WooCommerce_MagicZoom_add_videos_product_data_fields' );
function WooCommerce_MagicZoom_add_videos_product_data_fields() {
    global $woocommerce, $post;

    if ( metadata_exists( 'post', $post->ID, '_provide_videolinks_field' ) ) {
        $data = get_post_meta( $post->ID, '_provide_videolinks_field', true );
        if (!empty($data) && preg_match('/a\:\d+/is',$data)) {
            $data = unserialize($data);
            $data = array_keys($data);
            $data = implode("\n", $data)."\n";
        } else {
            $data = '';
        }
    }else{
        $data = '';
    }
    ?>
    <div id="product_videos_product_data" class="panel woocommerce_options_panel">
        <p class="_provide_videolinks_field_field" style="display: block;">
            <span>Provide links to YouTube or Vimeo videos separated by a space or new line, https://www.youtube.com/watch?v=XXXXXXXXXXX or https://vimeo.com/XXXXXXXXX</span>
            <textarea class="magictoolbox-video-field" style="height: 260px; width: 100%;" name="_provide_videolinks_field" id="_provide_videolinks_field" placeholder="" rows="2" cols="20"><?php echo $data; ?></textarea>
        </p>
    </div>
    <?php
}

//save videolinks in DB
add_action( 'woocommerce_process_product_meta', 'WooCommerce_MagicZoom_woocommerce_process_product_meta_fields_save' );
function WooCommerce_MagicZoom_woocommerce_process_product_meta_fields_save( $post_id ){
    //if (isset( $_POST['_provide_videolinks_field']) && !empty($_POST['_provide_videolinks_field']) ){
    if (isset( $_POST['_provide_videolinks_field'])){
        $urls = preg_split('/\n++|\s++/', $_POST['_provide_videolinks_field'], -1, PREG_SPLIT_NO_EMPTY);
        $attrNewValue = array();
        foreach($urls as $key => $_url) {

            $url = parse_url($_url);
            if (!$url) {
                $attrNewValue[$_url] = array();
                continue;
            }

            $isVimeo = false;
            $videoCode = null;
            if (preg_match('#youtube\.com|youtu\.be#', $url['host'])) {
                if (isset($url['query']) && preg_match('#\bv=([^&]+)(?:&|$)#', $url['query'], $matches)) {
                    $videoCode = $matches[1];
                } elseif (isset($url['path']) && preg_match('#^/(?:embed/|v/)?([^/\?]+)(?:/|\?|$)#', $url['path'], $matches)) {
                    $videoCode = $matches[1];
                }
            } elseif (preg_match('#(?:www\.|player\.)?vimeo\.com#', $url['host'])) {
                $isVimeo = true;
                if (isset($url['path']) && preg_match('#/(?:channels/[^/]+/|groups/[^/]+/videos/|album/[^/]+/video/|video/|)(\d+)(?:/|\?|$)#', $url['path'], $matches)) {
                    $videoCode = $matches[1];
                }
            }

            if (!$videoCode) {
                $attrNewValue[$_url] = array();
                continue;
            }

            if($isVimeo) {
                $hash = unserialize(file_get_contents('https://vimeo.com/api/v2/video/'.$videoCode.'.php'));
                $thumb = $hash[0]['thumbnail_small'];
            } else {
                $thumb = 'https://i1.ytimg.com/vi/'.$videoCode.'/1.jpg';
            }

            $attrNewValue[$_url] = array(
                'code' => $videoCode,
                'thumb' => $thumb,
                'vimeo' => $isVimeo,
                'youtube' => !$isVimeo,
            );
        }
        update_post_meta( $post_id, '_provide_videolinks_field', serialize($attrNewValue));
    }
}




function showMessage_WooCommerce_MagicZoom($message, $errormsg = false) {
    if ($errormsg) {
        echo '<div id="message" class="error">';
    } else {
        echo '<div id="message" class="updated fade">';
    }
    echo "<p><strong>$message</strong></p></div>";
}


function showAdminMessages_WooCommerce_MagicZoom(){
    global $error_message;
    if (current_user_can('edit_posts')) {
       showMessage_WooCommerce_MagicZoom($error_message,true);
    }
}

function WooCommerce_MagicZoom_LoadScroll($tool) {

    $tool->params->setProfile('product');

    if($tool->params->checkValue('magicscroll', 'yes') && $tool->type == 'standard') {
        require_once(dirname(__FILE__) . '/core/magicscroll.module.core.class.php');
        $scroll = new MagicScrollModuleCoreClass(false);
        $GLOBALS["magictoolbox"]["scroll"] = & $tool;
        //NOTE: load params in a separate profile, in order not to overwrite the options of MagicScroll module
        $scroll->params->appendParams($tool->params->getParams('product'), 'product-magicscroll-options');
        if($tool->params->checkValue('template', 'classic')) {
            $scroll->params->setValue('orientation', 'horizontal', 'product-magicscroll-options');
        }
        if($tool->params->checkValue('template', 'selectors-left')) {
            $scroll->params->setValue('orientation', 'vertical', 'product-magicscroll-options');
        }
        if($tool->params->checkValue('template', array('left', 'right'), 'product')) {
            $scroll->params->setValue('orientation', 'vertical', 'product-magicscroll-options');
        }
        if($tool->params->checkValue('template', array('top', 'bottom'), 'product')) {
            $scroll->params->setValue('orientation', 'horizontal', 'product-magicscroll-options');
        }
        return $scroll;
    }

    return false;
}

function plugin_get_version_WooCommerce_MagicZoom() {
    $plugin_data = get_plugin_data(dirname(plugin_dir_path(__FILE__)).'/mod_woocommerce_magiczoom.php');
    $plugin_version = $plugin_data['Version'];
    return $plugin_version;
}

function update_plugin_message_WooCommerce_MagicZoom() {
    $ver = json_decode(@file_get_contents('http://www.magictoolbox.com/api/platform/wordpress/version/'));
    if (empty($ver)) return false;
    $ver = str_replace('v','',$ver->version);
    $oldVer = plugin_get_version_WooCommerce_MagicZoom();
    if (version_compare($oldVer, $ver, '<')) {
        echo '<div id="message" class="updated fade">
                  <p>New version available! We recommend that you download the <a href="'.WooCommerceMagicZoom_url('http://magictoolbox.com/magiczoom/modules/woocommerce/',' plugins page update link ').'">latest version</a> of Magic Zoom for WooCommerce . </p>
              </div>';
    }
}

function get_tool_version_WooCommerce_MagicZoom($tool=null) {
    global $wp_filesystem;

    if (!$tool) {
        $tool = 'magiczoom';
    }

    WP_Filesystem();

    if (empty($wp_filesystem)) {
        require_once (ABSPATH . '/wp-admin/includes/file.php');
        WP_Filesystem();
    }

    $r = $wp_filesystem->get_contents(plugin_dir_path( __FILE__ ).'core/'.$tool.'.js');

    if (!preg_match('/demo/is',$r)) {
        $version = 'commercial';
    } else {
        $version = 'trial';
    }
    return $version;
}


function  magictoolbox_WooCommerce_MagicZoom_init() {

    add_action( 'admin_init', 'WooCommerceMagicZoom_welcome_license_do_redirect' );

    global $error_message;


    add_action("admin_menu", "magictoolbox_WooCommerce_MagicZoom_config_page_menu");
    add_action('admin_enqueue_scripts', 'WooCommerce_MagicZoom_load_admin_scripts');
    add_action('wp_enqueue_scripts', 'WooCommerce_MagicZoom_load_frontend_scripts');

    //add_filter('filesystem_method', create_function('$a', 'return "direct";' ));
    add_filter('filesystem_method', function($a) { return "direct"; });


    require_once(dirname(__FILE__)."/core/autoupdate.php");
    require_once(dirname(__FILE__)."/core/view/import_export/export.php");
    add_action('wp_ajax_WooCommerce_MagicZoom_import', 'WooCommerce_MagicZoom_import');
    add_action('wp_ajax_WooCommerce_MagicZoom_export', 'WooCommerce_MagicZoom_export');


    add_action('wp_ajax_magictoolbox_WooCommerce_MagicZoom_set_license', 'magictoolbox_WooCommerce_MagicZoom_set_license');


    add_filter( 'woocommerce_ajax_variation_threshold', 'magictoolbox_WooCommerce_MagicZoom_magictoolbox_wc_ajax_variation_threshold', 10, 2 );

    
    
    
    add_filter( 'plugin_action_links', 'magictoolbox_WooCommerce_MagicZoom_links', 10, 2 );
    add_filter( 'plugin_row_meta', 'magictoolbox_WooCommerce_MagicZoom_plugin_row_meta' , 10, 2 );

    if ($error_message) add_action('admin_notices', 'showAdminMessages_WooCommerce_MagicZoom');

    if(!isset($GLOBALS['magictoolbox']['WooCommerceMagicZoom'])) {
        require_once(dirname(__FILE__) . '/core/magiczoom.module.core.class.php');
        $coreClassName = "MagicZoomModuleCoreClass";
        $GLOBALS['magictoolbox']['WooCommerceMagicZoom'] = new $coreClassName;
        $coreClass = &$GLOBALS['magictoolbox']['WooCommerceMagicZoom'];
    }
    $coreClass = &$GLOBALS['magictoolbox']['WooCommerceMagicZoom'];
    /* get current settings from db */
    $settings = get_option("WooCommerceMagicZoomCoreSettings");
    if($settings !== false && is_array($settings) && isset($settings['default']) && !isset($_GET['reset_settings'])) {
        foreach (WooCommerceMagicZoom_getParamsProfiles() as $profile => $name) {
        if (isset($settings[$profile])) {
        $coreClass->params->appendParams($settings[$profile],$profile);
        }
    }
    } else { //set defaults
        $allParams = array();
        $defaults = $coreClass->params->getParams('default');
        $map = WooCommerceMagicZoom_getParamsMap();

    foreach (WooCommerceMagicZoom_getParamsProfiles() as $profile => $name) {
        $params = array();
        foreach ($defaults as $id => $param) {;
                if (isset($map[$profile][$param['group']]) && is_array($map[$profile][$param['group']]) && in_array($id,$map[$profile][$param['group']])) { //set defaults only according to mapping
                    $params[$id] = $param;
                }
            }
            $coreClass->params->setParams($params,$profile);

        $allParams[$profile] = $coreClass->params->getParams($profile);
    }

    $allParams['product']['page-status']['default'] = 'yes'; //TODO?

    delete_option("WooCommerceMagicZoomCoreSettings");
        add_option("WooCommerceMagicZoomCoreSettings", $allParams);
    }

    add_action( 'upgrader_process_complete', 'WooCommerce_MagicZoom_get_packed_js', 10, 2 );
    
    if (!$coreClass->params->checkValue('page-status','No','product')) {
        add_filter( 'woocommerce_locate_template', 'WooCommerce_MagicZoom_locate_template', 100, 3 );
    }
    if (!$coreClass->params->checkValue('page-status','No','category')) {
        add_action( 'woocommerce_before_shop_loop_item', 'magictoolbox_WooCommerce_MagicZoom_start_parsing', 10, 3 );
        add_action( 'woocommerce_after_shop_loop_item', 'magictoolbox_WooCommerce_MagicZoom_end_parsing', 10, 3 );
    }
}

function WooCommerce_MagicZoom_init_wp_filesystem($form_url) {
    global $wp_filesystem;
    $creds = request_filesystem_credentials($form_url, '', false, plugin_dir_path( __FILE__ ), false);

    if (!WP_Filesystem($creds)) {
        request_filesystem_credentials($form_url, '', true, plugin_dir_path( __FILE__ ), false);
        return false;
    }
    return true;
}

function WooCommerce_MagicZoom_write_file ($url, $content) {
    global $wp_filesystem;
    // if (empty($wp_filesystem)) {
    //     require_once (ABSPATH . '/wp-admin/includes/file.php');
    // }
    WooCommerce_MagicZoom_init_wp_filesystem($url);

    $result = $wp_filesystem->put_contents($url, $content, FS_CHMOD_FILE );

    return $result ? null : "Failed to write to file";
}

function WooCommerce_MagicZoom_rewrite ($option, $tool) {
    $response = get_option($option);
    $result = WooCommerce_MagicZoom_write_file(plugin_dir_path(__FILE__).'core/'.$tool.'.js', $response);
    return $result;
}

function WooCommerce_MagicZoom_get_packed_js ($upgrader_object, $options) {
    if ('update' == $options['action'] && 'plugin' == $options['type']) {
        foreach ($options['plugins'] as $pl) {
            $_plugin = explode("/", $pl);
            $_plugin = $_plugin[count($_plugin) - 1];
            if ('mod_woocommerce_magiczoom.php' === $_plugin) {
                $key = magictoolbox_WooCommerce_MagicZoom_get_data_from_db();
                if (!$key) {
                    $result = WooCommerce_MagicZoom_rewrite("WooCommerce_MagicZoom_backup", 'magiczoom');
                }
                $result2 = WooCommerce_MagicZoom_rewrite("WooCommerce_MagicZoom_magicscroll_backup", 'magicscroll');
                break;
            }
        }
    }
}

function WooCommerce_MagicZoom_load_frontend_scripts () {
    $plugin = $GLOBALS['magictoolbox']['WooCommerceMagicZoom'];

    $tool_lower = 'magiczoom';
    switch ($tool_lower) {
        case 'magicthumb':      $priority = '10'; break;
        case 'magic360':        $priority = '11'; break;
        case 'magiczoom':       $priority = '12'; break;
        case 'magiczoomplus':   $priority = '13'; break;
        case 'magicscroll':     $priority = '14'; break;
        case 'magicslideshow':  $priority = '15'; break;
        default :               $priority = '11'; break;
    }

    wp_register_style( 'magictoolbox_magiczoom_style', plugin_dir_url( __FILE__ ).'core/magiczoom.css', array(), false, 'all');
    wp_register_style( 'magictoolbox_magiczoom_module_style', plugin_dir_url( __FILE__ ).'core/magiczoom.module.css', array(), false, 'all');
    wp_register_script( 'magictoolbox_magiczoom_script', plugin_dir_url( __FILE__ ).'core/magiczoom.js', array(), false, true);
    add_action("wp_footer", "magictoolbox_WooCommerce_MagicZoom_add_src_to_footer", $priority);
    add_action("wp_footer", "magictoolbox_WooCommerce_MagicZoom_add_options_script", 10001);
}

function WooCommerce_MagicZoom_load_admin_scripts () {
    wp_enqueue_script( 'jquery' ,includes_url('/js/jquery/jquery.js'));
    wp_enqueue_script( 'jquery-ui-core', includes_url('/js/jquery/ui/core.js') );
    wp_enqueue_script( 'jquery-ui-tabs', includes_url('/js/jquery/ui/tabs.js') );

    $ownPage = false;
    if (array_key_exists('page', $_GET)) {
        $ownPage =  "WooCommerceMagicZoom-config-page" ==  $_GET["page"]        ||
                    "WooCommerceMagicZoom-shortcodes-page" ==  $_GET["page"]    ||
                    "WooCommerceMagicZoom-import-export-page" ==  $_GET["page"] ||
                    "WooCommerceMagicZoom-license-page" ==  $_GET["page"];
    }

    if (is_admin()) {
        wp_register_script( 'woocommerce_MagicZoom_admin_adminpage_script', plugin_dir_url( __FILE__ ).'core/woocommerce_MagicZoom_adminpage.js', array('jquery', 'jquery-ui-core', 'jquery-ui-tabs'), null );
        wp_enqueue_style( 'magictoolbox_woocommerce_MagicZoom_admin_menu_style', plugin_dir_url( __FILE__ ).'core/admin_menu.css', array(), null );
        if ($ownPage) {
            wp_enqueue_style( 'magictoolbox_woocommerce_MagicZoom_admin_page_style', plugin_dir_url( __FILE__ ).'core/admin.css', array(), null );
        }

        if ($ownPage) {
            wp_enqueue_style( 'WooCommerce_MagicZoom_admin_import_export_style', plugin_dir_url( __FILE__ ).'core/view/import_export/woocommerce_MagicZoom_import_export.css', array(), null );
            wp_enqueue_style( 'WooCommerce_MagicZoom_admin_license_style', plugin_dir_url( __FILE__ ).'core/view/license/woocommerce_MagicZoom_license.css', array(), null );
        }
        wp_register_script( 'WooCommerce_MagicZoom_admin_import_export_script', plugin_dir_url( __FILE__ ).'core/view/import_export/woocommerce_MagicZoom_import_export.js', array('jquery'), null );
        wp_register_script( 'WooCommerce_MagicZoom_admin_license_script', plugin_dir_url( __FILE__ ).'core/view/license/woocommerce_MagicZoom_license.js', array('jquery'), null );
    }
}
function magictoolbox_WooCommerce_MagicZoom_magictoolbox_wc_ajax_variation_threshold( $qty, $product ) {
    return 150;
}

/*
function magictoolbox_WooCommerce_MagicZoom_denided_prettyPhoto_inline(){
  if( wp_script_is( 'jquery', $list = 'enqueued' ) && (wp_script_is( 'avia-prettyPhoto', $list = 'enqueued' ) || wp_script_is( 'avia-default', $list = 'enqueued' )) ) {
    ?>
    <script type="text/javascript">
    if (typeof(jQuery)=="function" && typeof(jQuery.fn)=="object" && (typeof(jQuery.fn.prettyPhoto)=="function") || (typeof(jQuery.fn.avia_activate_lightbox)=="function")) {
      jQuery.fn.prettyPhoto = function(){};
      jQuery.fn.avia_activate_lightbox = function(){};
      jQuery.fn.avia_activate_hover_effect = function(){};

    function cart_improvement_functions_new()
      {
          //single products are added via ajax //doesnt work currently
          //jQuery('.summary .cart .button[type=submit]').addClass('add_to_cart_button product_type_simple');

          //downloadable products are now added via ajax as well
          jQuery('.product_type_downloadable, .product_type_virtual').addClass('product_type_simple');

          //clicking tabs dont activate smoothscrooling
          jQuery('.woocommerce-tabs .tabs a').addClass('no-scroll');
      }

      window.cart_improvement_functions = cart_improvement_functions_new;

    }
    </script>
    <?php
  }
}
*/

/**
  * Show row meta on the plugin screen.
  *
  * @param  mixed $links Plugin Row Meta
  * @param  mixed $file  Plugin Base file
  * @return array
  */

function magictoolbox_WooCommerce_MagicZoom_plugin_row_meta( $links, $file ) {

    if (strpos(plugin_dir_path(__FILE__),plugin_dir_path($file))) {
        $row_meta = array($links[0],$links[1]);
        $row_meta['Settings'] = '<a href="admin.php?page=WooCommerceMagicZoom-config-page">'.__('Settings').'</a>';
        $row_meta['Support'] =  '<a target="_blank" href="'.WooCommerceMagicZoom_url('https://www.magictoolbox.com/contact/','plugins page support link').'">Support</a>';
        $row_meta['Buy'] = '<a target="_blank" href="'.WooCommerceMagicZoom_url('https://www.magictoolbox.com/buy/magiczoom/','plugins page buy link').'">Buy</a>';
        $row_meta['More cool plugins'] = '<a target="_blank" href="'.WooCommerceMagicZoom_url('https://www.magictoolbox.com/woocommerce/','plugins page more cool plugins link').'">More cool plugins</a>';

        return $row_meta;
    }

    return (array) $links;
}

function WooCommerceMagicZoom_config_page() {
    include 'core/view/settings/woocommerce_MagicZoom_settings.php';
}

function WooCommerce_MagicZoom_add_admin_src_to_menu_page() {
    wp_enqueue_script( 'woocommerce_MagicZoom_admin_adminpage_script' );

    $arr = array(
        'ajax'   => get_site_url().'/wp-admin/admin-ajax.php',
        'nonce'  => wp_create_nonce('magic-everywhere'),
        'mtburl' => 'https://www.magictoolbox.com/site/order/'
    );

    wp_localize_script( 'woocommerce_MagicZoom_admin_adminpage_script', 'magictoolbox_WooCommerce_MagicZoom_admin_modal_object', $arr);
}

function WooCommerce_MagicZoom_import() {
    if(!(is_array($_POST) && defined('DOING_AJAX') && DOING_AJAX)){
        return;
    }

    $nonce = $_POST['nonce'];
    $tool = 'woocommerce_magiczoom';

    if ( !wp_verify_nonce( $nonce, 'magic-everywhere' ) ) {
        return;
    }

    $file = $_FILES['file'];

    $arr = (array) simplexml_load_string(file_get_contents($file["tmp_name"]),'SimpleXMLElement', LIBXML_NOCDATA);

    if (array_key_exists('tool', $arr) && $tool == $arr['tool']) {
        if (array_key_exists('license', $arr) && $arr['license'] != 'trial' && strlen($arr['license']) == 7) {
            magictoolbox_WooCommerce_MagicZoom_update_db($arr['license']);

            $url = 'https://www.magictoolbox.com/site/order/'.$arr['license'].'/magiczoom.js';
            $response = magictoolbox_WooCommerce_MagicZoom_get_file($url);
            if($response['status'] == 200) {
                WooCommerce_MagicZoom_write_file(plugin_dir_path( __FILE__ ).'core/magiczoom.js', $response['content']);
            }
        }
        if (array_key_exists('scrolllicense', $arr) && $arr['scrolllicense'] != 'trial' && strlen($arr['scrolllicense']) == 7) {
            magictoolbox_WooCommerce_MagicZoom_update_db($arr['scrolllicense'], 'WooCommerce_MagicZoom_magicscroll');
            $url = 'https://www.magictoolbox.com/site/order/'.$arr['scrolllicense'].'/magicscroll.js';
            $response = magictoolbox_WooCommerce_MagicZoom_get_file($url);
            if($response['status'] == 200) {
                WooCommerce_MagicZoom_write_file(plugin_dir_path( __FILE__ ).'core/magicscroll.js', $response['content']);
            }
        }

        if (array_key_exists('core', $arr)) {
            $core = (array) $arr['core'];

            $settings = get_option("WooCommerceMagicZoomCoreSettings");

            foreach ($core as $profile => $name) {
                $name = (array) $name;
                foreach ($name as $key => $value) {
                    $value = (array) $value;
                    if ('' != $value[0]) {
                        $settings[$profile][$key]['value'] = $value[0];
                    }
                }
            }

            delete_option("WooCommerceMagicZoomCoreSettings");
            add_option("WooCommerceMagicZoomCoreSettings", $settings);
        }

    }
    // exit;
}

function WooCommerce_MagicZoom_export() {
    if(!(is_array($_POST) && defined('DOING_AJAX') && DOING_AJAX)){
        return;
    }

    $nonce = $_POST['nonce'];
    $value = $_POST['value'];
    $secret_data = null;

    if ( !wp_verify_nonce( $nonce, 'magic-everywhere' ) ) {
        return;
    }

    WooCommerce_MagicZoom_wp_export($value, get_option("WooCommerceMagicZoomCoreSettings"), $secret_data);
    exit;
}

function magictoolbox_WooCommerce_MagicZoom_add_src_to_footer() {
    global $magictoolbox_MagicZoom_page_has_shortcode,
           $magictoolbox_MagicZoom_page_has_tool,
           $magictoolbox_page_has_gallery,
           $magictoolbox_MagicZoom_page_added_script,
           $magictoolbox_page_added_gallery_script;

    if (!$magictoolbox_MagicZoom_page_has_tool) {
        $plugin = $GLOBALS['magictoolbox']['WooCommerceMagicZoom'];

        if ($plugin->params->checkValue('include-headers','yes') || isset($GLOBALS['custom_template_headers'])) {
            $magictoolbox_MagicZoom_page_has_tool = true; // add footers for all pages
            //if (isset($GLOBALS['custom_template_headers'])) unset($GLOBALS['custom_template_headers']); //prevent render on non-product pages
            $magictoolbox_page_has_gallery = true;
        }
    }

    if (!$magictoolbox_MagicZoom_page_added_script) {
        $magictoolbox_MagicZoom_page_added_script = true;

        if ($magictoolbox_MagicZoom_page_has_shortcode || $magictoolbox_MagicZoom_page_has_tool) {
            wp_enqueue_style('magictoolbox_magiczoom_style');
            wp_enqueue_style('magictoolbox_magiczoom_module_style');
            wp_enqueue_script('magictoolbox_magiczoom_script');
        }


        if ($magictoolbox_MagicZoom_page_has_tool) {
            // wp_enqueue_style('magictoolbox_magiczoom_style');
            // wp_enqueue_style('magictoolbox_magiczoom_module_style');
            // wp_enqueue_script('magictoolbox_magiczoom_script');
            if (!WooCommerce_MagicZoom_page_check('WooCommerce')) {
                wp_enqueue_script( 'magictoolbox-product', plugins_url('/core/woocommerce_MagicZoom_product.js', __FILE__));
            }
        }
    }

    if (!$magictoolbox_page_added_gallery_script && $magictoolbox_page_has_gallery) {
        $magictoolbox_page_added_gallery_script = true;
        wp_enqueue_style('magictoolbox_magiczoom_gallery_style');
        wp_enqueue_script('magictoolbox_magiczoom_gallery_script');
    }
}

function magictoolbox_WooCommerce_MagicZoom_add_options_script () {
    global $magictoolbox_MagicZoom_page_added_options,
            $magictoolbox_MagicZoom_page_has_shortcode,
            $magictoolbox_MagicZoom_page_has_tool;
    $footers = '';
    $cat = WooCommerce_MagicZoom_page_check('WooCommerce');

    if (!$magictoolbox_MagicZoom_page_added_options) {
        $magictoolbox_MagicZoom_page_added_options = true;


        if ($magictoolbox_MagicZoom_page_has_shortcode || $magictoolbox_MagicZoom_page_has_tool) {
            $plugin = $GLOBALS['magictoolbox']['WooCommerceMagicZoom'];
            $footers = $plugin->getOptionsTemplate();


            if (function_exists('plugins_url')) {
                $core_url = plugins_url();
            } else {
                $core_url = get_option("siteurl").'/wp-content/plugins';
            }
            $path = preg_replace('/^.*?\/plugins\/(.*?)$/is', '$1', str_replace("\\","/",dirname(__FILE__)));
            $scroll = WooCommerce_MagicZoom_LoadScroll($plugin);
            if($scroll) {
                $scroll->params->resetProfile();
                $footers .= $scroll->getHeadersTemplate($core_url."/{$path}/core", null, false);
            }

            global $sTemplateJs;
            if (isset($sTemplateJs) && !empty($sTemplateJs)) {
                $footers .= $sTemplateJs;
            }
            if (!$cat) {
                $jsonVariations = WooCommerce_MagicZoom_get_product_variations();
                $footers .= $jsonVariations;
            }
        }
        echo $footers;
    }
}

function magictoolbox_WooCommerce_MagicZoom_get_file($url) {
    $result = array( 'content' => '', 'status' => 0);

    if ($url && is_string($url)) {
        $url = trim($url);
        if ('' != $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $result['content'] = $response;
            $result['status'] = $code;
        }
    }

    return $result;
}

function magictoolbox_WooCommerce_MagicZoom_set_license() {
    global $wp_filesystem;

    if (empty($wp_filesystem)) {
        require_once (ABSPATH . '/wp-admin/includes/file.php');
    }

    WP_Filesystem();
    // ob_end_clean();

    if(!(is_array($_POST) && defined('DOING_AJAX') && DOING_AJAX)){
        return;
    }

    $nonce = $_POST['nonce'];
    $key = $_POST['key'];
    $extra_param = $_POST['param'];
    $result = '{"error": "error"}';

    if (!$extra_param || 'null' == $extra_param) {
        $extra_param = null;
        $tool_name = 'magiczoom';
    } else {
        $tool_name = $extra_param;
        $extra_param = 'WooCommerce_MagicZoom_'.$extra_param;
    }

    if ( !wp_verify_nonce( $nonce, 'magic-everywhere' ) ) {
        $result = '{"error": "verification failed"}';
    } else {
        if ($key && '' != $key) {
            $url = 'https://www.magictoolbox.com/site/order/'.$key.'/'.$tool_name.'.js';
            $response = magictoolbox_WooCommerce_MagicZoom_get_file($url);

            $code = $response['status'];
            $response = $response['content'];

            if($code == 200) {
                $result = WooCommerce_MagicZoom_write_file(plugin_dir_path( __FILE__ ).'core/'.$tool_name.'.js', $response);
                if (!$result) {
                    magictoolbox_WooCommerce_MagicZoom_update_db($key, $extra_param);
                    $result = 'null';
                }
                $result = '{"error": '.$result.'}';
            } else if($code == 403) {
                $result = '{"error": "limit"}';
                //Download limit reached
                //Your license has been downloaded 10 times already.
                //If you wish to download your license again, please contact us.
            } else if ($code == 404) {
                $result = '{"error": "license failed"}';
            } else {
                $result = '{"error": "Other errors"}';
            }
        }
    }
    ob_end_clean();
    echo $result;
    wp_die();
}

function magictoolbox_WooCommerce_MagicZoom_create_db() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'magictoolbox_store';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
          id int unsigned NOT NULL auto_increment,
          name varchar(50) DEFAULT NULL,
          license varchar(50) DEFAULT NULL,
          UNIQUE KEY id (id));";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}

function magictoolbox_WooCommerce_MagicZoom_remove_db() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'magictoolbox_store';

    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        $wpdb->query("DROP TABLE IF EXISTS ".$table_name);
    }
}

function magictoolbox_WooCommerce_MagicZoom_update_db($key, $name=null) {
    global $wpdb;
    $result = false;

    if (!$name || !is_string($name)) {
        $name = 'WooCommerce_MagicZoom';
    }

    if ($key && is_string($key)) {
        $table_name = $wpdb->prefix . 'magictoolbox_store';

        $data = $wpdb->get_results("SELECT * FROM ".$table_name." WHERE name = '" . $name . "'");

        if ($data && count($data) > 0) {
            $result = $wpdb->update($table_name, array('license' => $key), array('name' => $name), array( '%s' ), array( '%s' ));
            $result = !!$result;
        } else {
            $result = $wpdb->insert($table_name, array('name' => $name, 'license' => $key));
        }
    }

    return $result;
}

function magictoolbox_WooCommerce_MagicZoom_delete_row_from_db($name=null) {
    global $wpdb;

    if (!$name || !is_string($name)) {
        $name = 'WooCommerce_MagicZoom';
    }

    $table_name = $wpdb->prefix . 'magictoolbox_store';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        return $wpdb->delete( $table_name, array( 'name' => $name ) );
    } else {
        return false;
    }
}

function magictoolbox_WooCommerce_MagicZoom_is_empty_db() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'magictoolbox_store';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        $result = $wpdb->get_results("SELECT * FROM ".$table_name);
        return !(count($result) > 0);
    } else {
        return false;
    }
}

function magictoolbox_WooCommerce_MagicZoom_get_data_from_db($name=null) {
    global $wpdb;
    
    if (!$name || !is_string($name)) {
        $name = 'WooCommerce_MagicZoom';
    }
    
    if (isset($GLOBALS['WooCommerce_MagicZoom_get_data_from_db'][$name])) {
        return $GLOBALS['WooCommerce_MagicZoom_get_data_from_db'][$name];
    }

    $table_name = $wpdb->prefix . 'magictoolbox_store';
    $result = $wpdb->get_results("SELECT * FROM ".$table_name." WHERE name = '".$name."'");
    
    if ($result && count($result) > 0) {
        $GLOBALS['WooCommerce_MagicZoom_get_data_from_db'][$name] = $result[0];
        return $result[0];
    } else {
        $GLOBALS['WooCommerce_MagicZoom_get_data_from_db'][$name] = false;
        return false;
    }
}

function magictoolbox_WooCommerce_MagicZoom_links( $links, $file ) {
    $fileName = 'mod_woocommerce_magiczoom_trial/mod_woocommerce_magiczoom.php';
    
    $fileName = preg_replace('/\_trial\//', '/', $fileName);
    $fileName = preg_replace('/\_commercial\//', '/', $fileName);
    
    if ($file == $fileName) {
        $settings_link = '<a href="admin.php?page=WooCommerceMagicZoom-config-page">'.__('Settings').'</a>';
        array_unshift( $links, $settings_link );
    }
    return $links;
}

function magictoolbox_WooCommerce_MagicZoom_config_page_menu() {
    if(function_exists("add_menu_page")) {
        $page = add_menu_page(__("Magic Zoom for WooCommerce"), __("Magic Zoom for WooCommerce"), "edit_posts", "WooCommerceMagicZoom-config-page", "WooCommerceMagicZoom_config_page", plugin_dir_url( __FILE__ )."core/admin_graphics/icon.svg");
        add_submenu_page( "WooCommerceMagicZoom-config-page", 'Settings', 'Settings', 'edit_posts', "WooCommerceMagicZoom-config-page" );
        add_action('admin_print_scripts-' . $page, 'WooCommerce_MagicZoom_add_admin_src_to_menu_page');
    }

    if(function_exists("add_submenu_page")) {

        $license_page = add_submenu_page("WooCommerceMagicZoom-config-page", "License", "License", "edit_posts", "WooCommerceMagicZoom-license-page", "WooCommerce_MagicZoom_license_page");
        add_action('admin_print_scripts-' . $license_page, 'WooCommerce_MagicZoom_add_admin_src_to_license_page');
        $import_export_page = add_submenu_page("WooCommerceMagicZoom-config-page", "Backup / Restore", "Backup / Restore", "edit_posts", "WooCommerceMagicZoom-import-export-page", "WooCommerce_MagicZoom_import_export_page");
        add_action('admin_print_scripts-' . $import_export_page, 'WooCommerce_MagicZoom_add_admin_src_to_import_export_page');

    }
}


function WooCommerce_MagicZoom_import_export_page() {
    include 'core/view/import_export/woocommerce_MagicZoom_import_export.php';
}

function WooCommerce_MagicZoom_add_admin_src_to_import_export_page() {
    wp_enqueue_script( 'WooCommerce_MagicZoom_admin_import_export_script' );
    wp_localize_script( 'WooCommerce_MagicZoom_admin_import_export_script', 'magictoolbox_WooCommerce_MagicZoom_admin_modal_object', array('ajax' =>  get_site_url().'/wp-admin/admin-ajax.php', 'nonce' => wp_create_nonce('magic-everywhere')) );
}

function WooCommerce_MagicZoom_license_page() {
    include 'core/view/license/woocommerce_MagicZoom_license.php';
}

function WooCommerce_MagicZoom_add_admin_src_to_license_page() {
    wp_enqueue_script( 'WooCommerce_MagicZoom_admin_license_script' );
    wp_localize_script( 'WooCommerce_MagicZoom_admin_license_script', 'magictoolbox_WooCommerce_MagicZoom_admin_modal_object', array('ajax' =>  get_site_url().'/wp-admin/admin-ajax.php', 'nonce' => wp_create_nonce('magic-everywhere')) );
}

function magictoolbox_WooCommerce_MagicZoom_styles() {
    if(!defined('MAGICTOOLBOX_MAGICZOOM_HEADERS_LOADED')) {
        $plugin = $GLOBALS['magictoolbox']['WooCommerceMagicZoom'];

        
        if (function_exists('plugins_url')) {
            $core_url = plugins_url();
        } else {
            $core_url = get_option("siteurl").'/wp-content/plugins';
        }


        $path = preg_replace('/^.*?\/plugins\/(.*?)$/is', '$1', str_replace("\\","/",dirname(__FILE__)));

        $headers = $plugin->getHeadersTemplate($core_url."/{$path}/core");

            $scroll = WooCommerce_MagicZoom_LoadScroll($plugin);
            if($scroll) {
                $scroll->params->resetProfile();
                $headers .= $scroll->getHeadersTemplate($core_url."/{$path}/core", null, false);
            }
            //$headers .= '<script type="text/javascript" src="'. $core_url .'/'. $path .'/core/woocommerce_MagicZoom_product.js"></script>';
        echo $headers;
        define('MAGICTOOLBOX_MAGICZOOM_HEADERS_LOADED', true);
    }
}

function magictoolbox_WooCommerce_MagicZoom_start_parsing () {
    $GLOBALS['magictoolbox']['WooCommerce_MagicZoom']['parse_status'] = 'started';
    ob_start();
}

function magictoolbox_WooCommerce_MagicZoom_end_parsing () {
    if (!isset($GLOBALS['magictoolbox']['WooCommerce_MagicZoom']['parse_status'])) return;
    $GLOBALS['magictoolbox']['WooCommerce_MagicZoom']['parse_status'] = 'ended';
    $content = ob_get_contents();
    ob_end_clean();
    $content = magictoolbox_WooCommerce_MagicZoom_create($content);
    echo $content;
}

function magictoolbox_WooCommerce_MagicZoom_start_alternative_parsing () {
    if (!isset($GLOBALS['magictoolbox']['WooCommerce_MagicZoom']['parse_status'])) ob_start();
}

function magictoolbox_WooCommerce_MagicZoom_end_alternative_parsing () {
    if (!isset($GLOBALS['magictoolbox']['WooCommerce_MagicZoom']['parse_status'])) {
        $content = ob_get_contents();
        ob_end_clean();
        $content = magictoolbox_WooCommerce_MagicZoom_create($content);
        echo $content;
    }
}
function magictoolbox_WooCommerce_MagicZoom_contentClean ($content) {
    global $wp_query;
    $plugin = $GLOBALS['magictoolbox']['WooCommerceMagicZoom'];
    if (!$plugin->params->checkValue('use-effect-on-product-page','No') && isset($wp_query->query_vars['post_type']) && $plugin->params->checkValue('use_only_product_gallery','No') && $wp_query->query_vars['post_type'] == 'product') { //TODO
        $content = preg_replace('/(?:<a([^>]*)>)[^<]*<img([^>]*)(?:>)(?:[^<]*<\/img>)?(.*?)[^<]*?<\/a>/is','',$content); //TODO delete only what we really need.
        $content = preg_replace ('/<div id=\"attachment\_[0-9]+\"[^>]*?>.*?<\/div>/is','',$content);
        $content = preg_replace ('/<div[^>]id=[\"\']gallery-1[\"\'][^>]*>.*?<\/div>/is','',$content);
    }
    return $content;
}



function  magictoolbox_WooCommerce_MagicZoom_create($content) {
    global $magictoolbox_MagicZoom_page_has_tool;
    /*add_action( 'wp_print_footer_scripts', 'magictoolbox_WooCommerce_MagicZoom_denided_prettyPhoto_inline' );*/

    $plugin = $GLOBALS['magictoolbox']['WooCommerceMagicZoom'];


    /*set watermark options for all profiles START */
    $defaultParams = $plugin->params->getParams('default');
    $wm = array();
    $profiles = $plugin->params->getProfiles();
    foreach ($defaultParams as $id => $values) {
    if (($values['group']) == 'Watermark') {
        $wm[$id] = $values;
    }
    }
    foreach ($profiles as $profile) {
    $plugin->params->appendParams($wm,$profile);
    }
    /*set watermark options for all profiles END */

    $toolPatern = "<a\s+[^>]*class\s*=[^>]*\"MagicZoom[^>]*\"[^>]*>\s*<img[^>]*>\s*<\s*\/\s*a>";


    $wooVersion = WC()->version;
    $cat = WooCommerce_MagicZoom_page_check('WooCommerce');
    if ($cat === false) {

        $pattern = "(?:<a([^>]*(?:rel|class|data-rel)=[\'\"][^\"]*(?:thumbnails|woocommerce\-main\-image|prettyPhoto|zoom)[^\"]*[\'\"][^>]*)>)(?:<[^>]*>)?[^<]*<img([^>]*)(?:>)(?:[^<]*<\/img>)?(.*?)[^<]*?<\/a>";

        if (!preg_match("/{$pattern}/is",$content)) {
            $pattern = "<div[^>]*class=\"woocommerce-product-gallery__image\"><a([^>]*?)><img([^>]*(?:rel|class|data-rel)=[\'\"][^\"]*(?:thumbnails|woocommerce\-main\-image|prettyPhoto|zoom|attachment-shop_single)[^>]*)>(.*?)<\/a><\/div>";
        }

    } else if ($cat === true) {
        $pattern = "(?:<a([^>]*)>)[^<]*(?:<span[^>]*>[^<]*<\/span>)?[^<]*<img([^>]*)(?:>)(?:[^<]*<\/img>)?(.*?)<\/a>";
    } else {
        return $content;
    }

    $oldContent = $content;
    global $wp_query;
    $post_id = $wp_query->post->ID;



        $content = preg_replace_callback("/{$pattern}/is","magictoolbox_WooCommerce_MagicZoom_callback",$content);
        if ($content == $oldContent) return $content;
        $magictoolbox_MagicZoom_page_has_tool = true;


    if (@count($GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_SELECTORS']) > 0) { //if there any additional images present
        $selectors = '<div class="MagicToolboxSelectorsContainer">'.implode($GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_SELECTORS']).'</div>';
        $content = str_replace('{MAGICTOOLBOX_'.strtoupper('magiczoom').'_SELECTORS}',$selectors,$content); // insert selectors under main image

        $contentWithGallery = $content;


    }

    /*$content = str_replace('{MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR}',$GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR'],$content);  //add main image selector to other
    $content = str_replace('{MAGICTOOLBOX_'.strtoupper('magiczoom').'_SELECTORS}','',$content); //if no selectors - remove constant
     onlyForModend  */

    
    if (!$plugin->params->checkValue('template','original') && $plugin->type == 'standard' && isset($GLOBALS['magictoolbox']['MagicZoom']['main'])) {
        // template helper class
        require_once(dirname(__FILE__) . '/core/magictoolbox.templatehelper.class.php');
        MagicToolboxTemplateHelperClass::setPath(dirname(__FILE__).'/core/templates');
        MagicToolboxTemplateHelperClass::setOptions($plugin->params);
        if (!WooCommerce_MagicZoom_page_check('WooCommerce')) { //do not render thumbs on category pages
            $thumbs = WooCommerce_MagicZoom_get_prepared_selectors($post_id);
        } else {
            $thumbs = array();
        }

        if (isset($GLOBALS['MAGICTOOLBOX_'.strtoupper('MagicZoom').'_SELECTORS']) && is_array($GLOBALS['MAGICTOOLBOX_'.strtoupper('MagicZoom').'_SELECTORS'])) {
        $thumbs = array_merge($thumbs,$GLOBALS['MAGICTOOLBOX_'.strtoupper('MagicZoom').'_SELECTORS']);
        }



    if (!isset($GLOBALS['magictoolbox']['prods_info']['product_id']) && isset($post_id)) {
        $GLOBALS['magictoolbox']['prods_info']['product_id'] = $post_id;
    } else if (!isset($GLOBALS['magictoolbox']['prods_info']['product_id']) && !isset($post_id)) {
        $GLOBALS['magictoolbox']['prods_info']['product_id'] = '';
    }
        $scroll = WooCommerce_MagicZoom_LoadScroll($plugin);

        $magicscrollOptions = '';
        if($plugin->params->checkValue('magicscroll', 'Yes')) {
            $magicscrollOptions = $scroll->params->serialize(false, '', 'product-magicscroll-options');
            $magicscrollOptions .= 'autostart:false;';
        }


        $html = MagicToolboxTemplateHelperClass::render(array(
            'main' => $mainHTML,
            'thumbs' => (count($thumbs) >= 1) ? $thumbs : array(),
            'magicscrollOptions' => $magicscrollOptions,
            'pid' => $GLOBALS['magictoolbox']['prods_info']['product_id'],
        ));


        /*if (!$cat) {
            $html .= magictoolbox_WooCommerce_MagicZoom_getMagicToolBoxEvent($plugin, $post_id);
        }*/

        $content = str_replace('MAGICTOOLBOX_PLACEHOLDER', $html, $content);
    } else if ($plugin->params->checkValue('template','original') || $plugin->type != 'standard') {
        if (isset($GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR'])) {
            $content = str_replace('{MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR}', $GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR'], $content);  //add main image selector to other
        }
        if (isset($GLOBALS['magictoolbox']['MagicZoom'])) {
            $html = $GLOBALS['magictoolbox']['MagicZoom']['main'];
            $content = str_replace('MAGICTOOLBOX_PLACEHOLDER', $html, $content);
        }
    }
    $content = str_replace('{MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR}','',$content);
    $content = str_replace('{MAGICTOOLBOX_'.strtoupper('magiczoom').'_SELECTORS}','',$content); //if no selectors - remove constant
    $content = preg_replace ('/<div[^>]*?class="thumbnails[^"]*?"[^>]*?>.*?div>/is','',$content); // remove selectors div
    $content = preg_replace ('/<div[^>]*?class="woocommerce-product-gallery__image[^"]*?"[^>]*?>.*?div>/is','',$content); // remove selectors div
    //$content = preg_replace('/<div id="slider".*?(.*?)<\/li>.*?<\/ul>.*?<\/div>/ims','$1',$content);
    //$content = preg_replace('/<div id="carousel" class="flexslider">.*?<\/div>/ims','',$content);
    $content = preg_replace('/<ul class="slides">.*?(.*?)<\/li>.*?<\/ul>/ims','$1',$content,1);
    $content = preg_replace('/<div id="carousel" class="flexslider">.*?<\/div>/ims','',$content);
    $content = preg_replace ('/<span[^>]*?class="onsale"[^>]*?>.*?span>/is','',$content); // remove promo span



    if (!$magictoolbox_MagicZoom_page_has_tool) {
        if (preg_match("/{$toolPatern}/is", $content)) {
            $magictoolbox_MagicZoom_page_has_tool = true;
        }
    }

    return $content;
}

function magictoolbox_WooCommerce_MagicZoom_key_sort($a, $b){
    return strnatcasecmp(basename($a['img']),basename($b['img']));
}

function magictoolbox_WooCommerce_MagicZoom_check_plugin_active($plugin_id){

    switch ($plugin_id) {
       case 'magic360':
           $pattern = '/woocommerce_magic360/is';
           break;
       case 'others':
           $pattern = '/(woocommerce_magiczoom|woocommerce_magicthumb)/is';
           break;
   }
   if ( ! function_exists( 'get_plugins' ) ) {
       require_once ABSPATH . 'wp-admin/includes/plugin.php';
   }

   $all_plugins = get_plugins();

   $magic_plugin = '';
   foreach ($all_plugins as $plugin_name => $plugin_info) {
        if (is_plugin_active($plugin_name) && preg_match($pattern, $plugin_name)){
           return true;
       }
   }

   return false;
}

function magictoolbox_WooCommerce_MagicZoom_getMagicToolBoxEvent($plugin, $post_id){
    $mouseEvent = $plugin->params->getValue('selectorTrigger', 'product');
    if($mouseEvent == 'hover') $mouseEvent = 'mouseover';
    $magictoolboxEvent = "
        <style>
            .MagicScroll > a[data-magic-slide-id]{visibility: hidden;}
            .MagicScroll > *:nth-child(n+2) {display: none;}
            .mcs-item a[data-magic-slide-id]{display: inline-block;}
       </style>
        <script type=\"text/javascript\">
            var magicToolboxTool = 'magiczoom';
            var productId = '". $post_id . "';
            var magicToolboxToolMainId = 'MagicZoomImage_Main';
            var magicToolboxSwitchMetod = '".$mouseEvent."';
            //NOTE: in order to have time to switch the picture
            var magicToolboxMouseoverDelay = 350;
            if(magicToolboxSwitchMetod == 'mouseover') magicToolboxMouseoverDelay = magicToolboxMouseoverDelay + 60;
        </script>
        ";

    return $magictoolboxEvent;
}


function  magictoolbox_WooCommerce_MagicZoom_callback($matches) {
    $plugin = $GLOBALS['magictoolbox']['WooCommerceMagicZoom'];
    $title = "";
    $float = "";
    $cat = WooCommerce_MagicZoom_page_check('WooCommerce');
    if ($cat === 'error') return $matches[0];
    if ($cat) $plugin->params->setProfile('category');
    if (!$cat) $plugin->params->setProfile('product');
    $plugin_enabled = true;
    $is_selector = true;
    $is_main = true;
    


    if(!preg_match("/class\s*=\s*[\'\"][^\"]*?zoom[^\"]*?[\'\"]/iUs",$matches[0]) && !preg_match("/class=[\'\"]attachment-shop_(?:catalog|single)[^\"]*?wp-post-image[\'\"]/iUs",$matches[0])) {
        $is_main = false;
    }
    if(!preg_match("/class\s*=\s*[\'\"][^\"]*?(?:zoom|attachment-thumbnail|size-medium)[^\"]*?[\'\"]/iUs",$matches[0])) {
        $is_selector = false;
    }
    if (!$is_selector && !$is_main) {
        $plugin_enabled = false;
    }
    if ($plugin->params->checkValue('page-status','No')) $plugin_enabled = false;
    if (!$plugin_enabled) return $matches[0];


    $alignclass = preg_replace('/^.*?align(left|right|center|none).*$/is', '$1', $matches[2]);
    if($alignclass != $matches[2]) {
        $alignclass = ' align'.$alignclass;
    } else {
        $alignclass='';
        $float = preg_replace('/^.*?float:\s*(left|right|none).*$/is', '$1', $matches[2]);
        if($float == $matches[2]) {
            $float = '';
        } else {
            $float = ' float: ' . $float . ';';
        }
    }
    // get needed attributes
    global $wp_query;
    $alt = preg_replace("/^.*?alt\s*=\s*[\"\'](.*?)[\"\'].*$/is","$1",$matches[2]);
    if (isset($matches[1]) && !empty($matches[1])) { // thecartpress fix
    $img = preg_replace("/^.*?href\s*=\s*[\"\'](.*?)[\"\'].*$/is","$1",$matches[1]);
    $thumb = preg_replace("/^.*?src\s*=\s*[\"\'](.*?)[\"\'].*$/is","$1",$matches[2]);
    } else {
    $thumb = $img = preg_replace("/^.*?href\s*=\s*[\"\'](.*?)[\"\'].*$/is","$1",$matches[2]); // only thecartpress
    }
    
    
    //$prod_name = $wp_query->post->post_title;
    $title = get_post(get_post_thumbnail_id($wp_query->post->ID))->post_title;
    if (empty($title)) $title = $wp_query->post->post_title;
    
    
    /*if (!$cat || !$is_main) {
    $title = $prod_name;
    } else {
    $title = preg_replace("/^(?:.*?alt\s*=\s*[\"\'])(.*?)(?:[\"\'].*)/is","$1",$matches[2]);
    }*/
    

    

    if (!$cat) {
        $additionalDescription = preg_replace ('/<a[^>]*><img[^>]*><\/a>/is','',$wp_query->post->post_excerpt);
        $description = preg_replace ('/<a[^>]*><img[^>]*><\/a>/is','',$wp_query->post->post_content);
        $description = preg_replace ('/\[caption id=\"attachment_[0-9]+\"[^\]]*?\][^\[]*?\[\/caption\]/is','',$description);
        $id = '_Main_Product'.$wp_query->post->ID;
    } else {
        $id = '_Main';
        $description = $additionalDescription = '';
        $link = $img;
        $info = substr($matches[3],0,-1);
        $info = preg_replace('/(.*?)(<strong>.*?<\/strong>)(.*)/is','$1 <a href="'.$link.'">$2</a>$3',$info);

        $info = '<a href="'.$link.'">'.$info.'</a>';

        $plugin->params->setValue('show-message', 'no');
        if (!$plugin->params->checkValue('link-to-product-page', 'Yes')) {
            $link = false;
        }
    }

    $aStyles = $matches[1];
    $imgStyles = $matches[2];
    
    $matches[1] = preg_replace("/^(.*?)rel\s*=\s*[\"\'].*?[\"\']/is","$1",$matches[1]);
    
    $matches[1] = preg_replace("/^(.*?)id\s*=\s*[\"\'].*?[\"\']/is","$1",$matches[1]);
    $matches[1] = preg_replace("/^(.*?)class\s*=\s*[\"\'].*?[\"\']/is","$1",$matches[1]);
    $matches[1] = preg_replace("/^(.*?)title\s*=\s*[\"\'].*?[\"\']/is","$1",$matches[1]);
    $matches[1] = preg_replace("/^(.*?)rev\s*=\s*[\"\'].*?[\"\']/is","$1",$matches[1]);
    $matches[1] = preg_replace("/^(.*?)href\s*=\s*[\"\'].*?[\"\']/is","$1",$matches[1]);
    // remove src attribute from img
    $matches[2] = preg_replace("/^(.*?)src\s*=\s*[\"\'].*?[\"\']/is","$1",$matches[2]);
    $matches[2] = preg_replace("/\/\s*$/is"," ",$matches[2]);
    $matches[2] = preg_replace("/^(.*?)srcset\s*=\s*[\"\'].*?[\"\']/is","$1",$matches[2]);
    

    if ($is_main) { //if it is MAIN IMAGE

        if ($cat) $id = $id.md5(rand());
        if (isset($GLOBALS['magictoolbox_main_image_set']) && !$cat) {
            return $matches[0];
        }
        $GLOBALS['magictoolbox_main_image_set'] = true;



        $img_name = preg_replace('/(^.*?)(\/wp-content.*?(?:\.(?:jpg|jpeg|gif|png)))(.*)/is','$2',$thumb);
        $img_name = preg_replace('/(.*)-[0-9]+x[0-9]+(\.(jpg|png|jpeg|gif))/is','$1$2',$img_name);


        if (!preg_match('/wp-content/is',$img_name)) { //try to fix subdomain path
           $img_name = '/wp-content/uploads'.$img_name;
        }

        if ($is_main && !$cat) { //90639
            $img_name = str_replace(get_site_url(),'',wp_get_attachment_url( get_post_thumbnail_id($GLOBALS['post']->ID) ));
        }

        $thumb = WooCommerce_MagicZoom_get_product_image($img_name,'thumb');
        $thumb2x = WooCommerce_MagicZoom_get_product_image($img_name,'thumb2x');
    WooCommerce_MagicZoom_get_product_variations(); //call only for onload variation check

        $img = WooCommerce_MagicZoom_get_product_image($img_name,'original');
        //$invisImg = '<a class="zoom invisImg" href="'.$img.'" style="display:none;"><img style="display:none;" src="'.$thumb.'"/></a>';
        $invisImg = '<figure class="woocommerce-product-gallery__image--placeholder"><a class="zoom invisImg wp-post-image" href="'.$img.'" style="display:none;"><img style="display:none;" src="'.$thumb.'"/></a></figure>';

        if (!$cat) {
            $result = 'MAGICTOOLBOX_PLACEHOLDER';
            //$result = $result.$jsonVariations;
            if (isset($GLOBALS['variation_on_load'])) {
                $img = $GLOBALS['variation_on_load']['original'];
                $thumb = $GLOBALS['variation_on_load']['thumb'];
                $thumb2x = $GLOBALS['variation_on_load']['thumb2x'];
            }

            $GLOBALS['magictoolbox']['MagicZoom']['main'] = $plugin->getMainTemplate(compact('img','thumb','thumb2x','id','title','description','additionalDescription','link'));

        } else {
            $result = $plugin->getMainTemplate(compact('img','thumb','thumb2x','id','title','alt','description','additionalDescription','link'));
        }


        if (!$plugin->params->checkValue('create-main-image-selector','No') && !isset($GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SET'])) {
            $medium = WooCommerce_MagicZoom_get_product_image($img_name,'thumb');
            $medium2x = WooCommerce_MagicZoom_get_product_image($img_name,'thumb2x');
            $thumb = WooCommerce_MagicZoom_get_product_image($img_name,'selector');
            $thumb2x = WooCommerce_MagicZoom_get_product_image($img_name,'selector2x');
            //if (isset($title)) { $alt = $title; } else { $alt = ''; }
            //$GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR'] = $plugin->getSelectorTemplate(compact('alt','img','medium','thumb','id')); //save main image selector to globals
            $GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR'] = $GLOBALS['magictoolbox']['MagicZoom']['selectors'][] = $plugin->getSelectorTemplate(compact('alt','img','medium','medium2x','thumb','thumb2x','id')); //save main image selector to globals
            //$GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR'] = str_replace('<img','<img class="attachment-90x90" ',$GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR']);
            $GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR'] = str_replace('<img','<img class="attachment-90x90" ',$GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR']);
        }

    }
     if ($is_selector && !$is_main && !$cat) { //if image is SELECTOR
        if (isset($title)) { $alt = $title; } else { $alt = ''; }
        $medium_name = str_replace(site_url('','http'),'',$thumb);
        if ($medium_name == $thumb) { //maybe https
        $medium_name = str_replace(site_url('','https'),'',$thumb);
        }
        if ($medium_name == $thumb) {
        $medium_name = preg_replace('/https?:\/\/.*?(\/.*)/ims','$1',$thumb);
    }
        $medium_name = preg_replace('/(.*)-[0-9]+x[0-9]+(\.(jpg|png|jpeg|gif))/is','$1$2',$medium_name);
        $medium = WooCommerce_MagicZoom_get_product_image($medium_name,'thumb');
        $medium2x = WooCommerce_MagicZoom_get_product_image($medium_name,'thumb2x');
        $img = WooCommerce_MagicZoom_get_product_image($medium_name,'original');
        $thumb = WooCommerce_MagicZoom_get_product_image($medium_name,'selector');
        $thumb2x = WooCommerce_MagicZoom_get_product_image($medium_name,'selector2x');
        if ($plugin->params->checkValue('template','original')) {
            $result = $plugin->getSelectorTemplate(compact('alt','img','medium','medium2x','thumb','thumb2x','id','title'));
        } else {
            $result = '';
            $GLOBALS['magictoolbox']['MagicZoom']['selectors'][] = $plugin->getSelectorTemplate(compact('alt','img','medium','medium2x','thumb','thumb2x','id','title'));
        }

        if (!isset($GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR_SET'])) {
            $prefix = '{MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR}';
            $GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SELECTOR_SET'] = true;
        }
    }
    $result = preg_replace("/^(.*?)<a(.*?)$/is","$1<a {$matches[1]}$2",$result);
    $result = preg_replace("/^(.*?)<img(.*?)$/is","$1<img {$matches[2]}$2",$result);


    if (empty($prefix)) $prefix = '';
    if (empty($info)) $info = '';
     if ($is_main) {
        $prefix = $prefix . $invisImg;
        $result = $prefix."<div style=\"{$float}\" class=\"MagicToolboxContainer\">{$result}</div>";
        $result = $result . $info;
        if ($plugin->params->checkValue('keep-selectors-position','No')) {//load selectors under main image
            $result = $result.'{MAGICTOOLBOX_'.strtoupper('magiczoom').'_SELECTORS}';
        }
        /*if (!$cat) {
            if (!isset($GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SET'])) $result = $matches[0];
            $GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_MAIN_IMAGE_SET'] = 'true';
        }*/ //TODO WHAT IS THIS ?
    } else if ($is_selector) {
        $result = $prefix.$result;
         if ($plugin->params->checkValue('keep-selectors-position','No')) {//load selectors under main image
            $GLOBALS['MAGICTOOLBOX_'.strtoupper('magiczoom').'_SELECTORS'][] = $result;
            $result = $matches[0];
        }
    }

    return $result;

}

function WooCommerce_MagicZoom_get_product_image($title,$size = 'thumb', $image_id = false) {
    $cat = WooCommerce_MagicZoom_page_check('WooCommerce');
    $result = $title;

    global $wp_query;
    $post_id = $wp_query->post->ID;


    
    $oldLocale = setlocale(LC_ALL, NULL);
    setlocale(LC_ALL, 'en_US.UTF8');
    
    $plugin = $GLOBALS["magictoolbox"]["WooCommerceMagicZoom"];
    $useWpImages = $plugin->params->checkValue('use-wordpress-images','yes');
    
    if ($useWpImages ) { 
        
         if (!$image_id) {
            $image_id = get_post_thumbnail_id( $post_id );
        } 
        
        if ($size != 'original') {
            if (!$cat) {
                $thumb = wp_get_attachment_image_src( $image_id, $plugin->params->getValue('single-wordpress-image') );
            } else {
                $thumb = wp_get_attachment_image_src( $image_id, $plugin->params->getValue('category-wordpress-image') );
            }
        } else {
            $thumb = wp_get_attachment_image_src( $image_id, 'full' ); 
        }

        $result = $thumb[0];
        
    } else {

        

        if (!isset($GLOBALS['imagehelper'])) {
            require_once(dirname(__FILE__) . '/core/magictoolbox.imagehelper.class.php');
            $image_dir = 'wp-content/uploads/';
            $url = site_url();
            $shop_dir = ABSPATH;
            $GLOBALS['imagehelper'] = new MagicToolboxImageHelperClass($shop_dir, $image_dir.'magictoolbox_cache', $plugin->params, null, $url);
        }

        $result = $GLOBALS['imagehelper']->create( $title, $size, $post_id);
    }
    setlocale(LC_ALL, $oldLocale);

    return $result;

}

function WooCommerce_MagicZoom_get_post_attachments($addMain = false)  {

    global $wp_query;
    $plugin = $GLOBALS["magictoolbox"]["WooCommerceMagicZoom"];
    $post_id = $wp_query->post->ID;
    $attachments = array();
    //global $product;
    $product = wc_get_product( $post_id );

    if(method_exists($product, 'get_gallery_image_ids')) {
        $metaGallery = $product->get_gallery_image_ids();
    } else if(method_exists($product, 'get_gallery_attachment_ids')) {
        $metaGallery = $product->get_gallery_attachment_ids();
    } else {
        return $attachments;
    }

    foreach ($metaGallery as $attr_id) {
        $attachments[$attr_id] = get_post($attr_id);
    }
    if (count($metaGallery) > 0){
        foreach ($metaGallery as $attr_id) {
            $attachments[$attr_id] = get_post($attr_id);
        }
    }


    $mainImage = get_post(get_post_thumbnail_id( $post_id ));
    $mainImageAdded = false;
    if (count($attachments) == 1) {
        if (isset($attachments[0]->guid) && $mainImage->guid != $attachments[0]->guid) {
            $attachments_to_add[get_post_thumbnail_id( $post_id )] = $mainImage;
            array_splice($attachments, 0, 0, $attachments_to_add);
            $mainImageAdded = true;
        }
    }
    if (!$plugin->params->checkValue('create-main-image-selector','No') && !$mainImageAdded &&  $addMain) {

    $attachments_to_add[get_post_thumbnail_id( $post_id )] = $mainImage;
        array_splice($attachments, 0, 0, $attachments_to_add);
    }


    return $attachments;
}


function WooCommerce_MagicZoom_get_product_variations ($product_id = false) {

    global $wp_query;
    $post_id = $wp_query->post->ID;
    $product = wc_get_product( $post_id );
    
    if (is_null($product) || empty($product)) return '';
    
    $GLOBALS['MAGICTOOLBOX_'.strtoupper('MagicZoom').'_VARIATIONS_SELECTORS'] = array();
    
    $plugin = $GLOBALS['magictoolbox']['WooCommerceMagicZoom'];

    $varImages = false;
    if ( $product->is_type('variable')) {
        $variations = $product->get_available_variations();
        if (is_array($variations) && count($variations) > 0) {
            $varImages = array();
            foreach ($variations as $variation) {

                if (isset($variation['image']) && isset($variation['image']['src'])) {
                    $variation['image_src'] = $variation['image']['src'];
                }

                if (isset($variation['image']) && isset($variation['image']['url'])) {
                    $variation['image_link'] = $variation['image']['url'];
                }

                if (isset($variation['image_src']) && !empty($variation['image_src']) && isset($variation['image_link']) && !empty($variation['image_link'])) {
                    $img_name = str_replace(site_url('','http'),'',$variation['image_link']);
                    if ($img_name == $variation['image_link']) { //maybe https
            $img_name = str_replace(site_url('','https'),'',$variation['image_link']);
                    }
                    if ($img_name == $variation['image_link']) {
            $img_name = preg_replace('/https?:\/\/.*?(\/.*)/ims','$1',$variation['image_link']);
                    }
                    $img = WooCommerce_MagicZoom_get_product_image($img_name,'original');
                    $thumb = WooCommerce_MagicZoom_get_product_image($img_name);
                    $thumb2x = WooCommerce_MagicZoom_get_product_image($img_name,'thumb2x');
                    $selector = WooCommerce_MagicZoom_get_product_image($img_name,'selector');
                    $selector2x = WooCommerce_MagicZoom_get_product_image($img_name,'selector2x');
                    $varImages[$variation['variation_id']] = array(
                     'attributes' => $variation['attributes'],
                     'link' => $variation['image_link'],
                                         'original' => $img,
                                         'thumb' => $thumb,
                                         'thumb2x' => $thumb2x,
                                         'selector' => $selector,
                                         'selector2x' => $selector2x,
                                         'srcset'     => str_replace(' ','%20',$thumb).' 1x, '.str_replace(' ','%20',$thumb2x).' 2x'); //array vith variations images

                    //onload variation check: start
                    if (!isset($GLOBALS['variation_on_load'])) {
                        $attribute_match = array();
                        foreach ($variation['attributes'] as $attribute_name => $attribute_value) {
                            if (isset($_GET[$attribute_name]) && !empty($_GET[$attribute_name])) {
                            
                                //$attribute_name = str_replace('attribute_pa_','',$attribute_name); //fix in some cases
                                
                                if (strtolower($_GET[$attribute_name]) == strtolower($attribute_value) || empty($attribute_value)) {
                                    $attribute_match[] = 'true';
                                } else {
                                    $attribute_match[] = 'false';
                                }
                            }
                        }
                        if (count($attribute_match) && !in_array('false',$attribute_match)) $GLOBALS['variation_on_load'] = $varImages[$variation['variation_id']]; //found variation for onload set
                    }
                    //onload variation check: end

            $medium = $thumb;
            $medium2x = $thumb2x;

            if (!$plugin->params->checkValue('variations-selectors','No')) {

            $thumb = $selector;
            $thumb2x = $selector2x;

            //$id = '_Main';
            $id = '_Main_Product'.$product->get_id();

            $alt = $title = ''; //TODO

            $a = $plugin->getSelectorTemplate(compact('alt','img','medium','medium2x','thumb','thumb2x','id','title'));
            //$a = str_replace('<a','<a onclick="onMagicZoomSelectorClick(this);" data-image="'.$variation['image_link'].'" ',$a);
            $a = str_replace('<a','<a onclick="onMagicZoomSelectorClick(this);" ',$a);

            $GLOBALS['MAGICTOOLBOX_'.strtoupper('MagicZoom').'_VARIATIONS_SELECTORS'][] = $a;
            }

                }
            }
        }
    }
    //return $varImages;
    $jsonVariations = json_encode($varImages);
    if (empty($jsonVariations) || $jsonVariations === '' || $jsonVariations === false || $jsonVariations === 'false') {
        $jsonVariations = '';
    } else {
        $jsonVariations = '<script type="text/javascript">
            var thumbWidth = "'.$plugin->params->getValue('selector-max-width','product').'px";
            var thumbHeight = "'.$plugin->params->getValue('selector-max-height','product').'px";
            var useWpImages = "'.$plugin->params->getValue('useWpImages','product').'";
            (function($, isWoo301, prodId) {
                var jsonVariations = '.$jsonVariations.';
                '.file_get_contents(dirname(__FILE__) . '/core/woocommerce_MagicZoom_variations.js').'
            })(jQuery, '.(version_compare(WC()->version,'3','<') ? 'false' : 'true').', '.$product->get_id().');
        </script>';

    }
    return $jsonVariations;
}
function WooCommerce_MagicZoom_get_prepared_selectors ($post_id, $useWpImages = false) {

    $attachments = WooCommerce_MagicZoom_get_post_attachments($useWpImages);

    /*global $wp_query, $product;
    $post_id = $wp_query->post->ID;*/
    $product = wc_get_product( $post_id );
    $id = '_Main_Product'.$product->get_id();



    
    $selectors = array();
    $oldLocale = setlocale(LC_ALL, NULL);
    setlocale(LC_ALL, 'en_US.UTF8');

    $plugin = $GLOBALS['magictoolbox']['WooCommerceMagicZoom'];

    if ($useWpImages) { 
    
        foreach ($attachments as $attachment) {

            $img = wp_get_attachment_image_src( $attachment->ID, 'full' ); 
            $img = $img[0];
            
            $medium = wp_get_attachment_image_src( $attachment->ID, $plugin->params->getValue('single-wordpress-image') );
            $medium = $medium[0];
            
            $thumb = wp_get_attachment_image_src( $attachment->ID, $plugin->params->getValue('thumbnails-wordpress-image') ); 
            $thumb = $thumb[0];
            
            $alt = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
            $title = $attachment->post_title;
            
            $selectors[] = $plugin->getSelectorTemplate(compact('alt','img','medium','medium2x','thumb','thumb2x','id','title'));
            
        }
        
    } else {

        require_once(dirname(__FILE__) . '/core/magictoolbox.imagehelper.class.php');
        $url = site_url();
        $shop_dir = ABSPATH;
        $image_dir = 'wp-content/uploads/';
        $imagehelper = new MagicToolboxImageHelperClass($shop_dir, $image_dir.'magictoolbox_cache', $plugin->params, null, $url);

        if (!$plugin->params->checkValue('create-main-image-selector','No') && !isset($GLOBALS['MAGICTOOLBOX_'.strtoupper('MagicZoom').'_MAIN_IMAGE_SET'])) {
            $thumb = $old_thumb = $product->get_image();
            $thumb = preg_replace("/^.*?src\s*=\s*[\"\'](.*?)[\"\'].*$/is","$1",$thumb);
            
            if (strpos($thumb,'data:image') !== false) { //try data-src
                $thumb = preg_replace("/^.*?data-src\s*=\s*[\"\'](.*?)[\"\'].*$/is","$1",$old_thumb);
            }
            
            $img_name = preg_replace('/'.str_replace('/','\/',site_url('','http')).'(.*)(?:-[0-9]+x[0-9]+)?(\.(jpg|png|jpeg|gif))/is','$1$2',$thumb);

            if ($img_name == $thumb) { //maybe https
                $img_name = preg_replace('/'.str_replace('/','\/',site_url('','https')).'(.*)(?:-[0-9]+x[0-9]+)?(\.(jpg|png|jpeg|gif))/is','$1$2',$thumb);
            }

            if ($img_name == $thumb) {
                $img_name = preg_replace('/https?:\/\/.*?(\/.*)/ims','$1',$thumb);
            }

            if (preg_match('/(.*)\-[0-9]+x[0-9]+\.(jpg|png|jpeg|gif)/is',$img_name)) { //small fix
                $img_name = preg_replace('/(.*)\-[0-9]+x[0-9]+\.(jpg|png|jpeg|gif)/is','$1.$2',$img_name);
            }
            $img_name = preg_replace('/(^.*?wp-content)(.*)/is','/wp-content$2',$img_name);
            
            $GLOBALS['MAGICTOOLBOX_'.strtoupper('MagicZoom').'_MAIN_IMAGE_SET'] = true;
            
            $img = WooCommerce_MagicZoom_get_product_image($img_name,'original');
            $medium = WooCommerce_MagicZoom_get_product_image($img_name,'thumb');
            $medium2x = WooCommerce_MagicZoom_get_product_image($img_name,'thumb2x');
            $thumb = WooCommerce_MagicZoom_get_product_image($img_name,'selector');
            $thumb2x = WooCommerce_MagicZoom_get_product_image($img_name,'selector2x');

            $thumbnail_id  = get_post_thumbnail_id($post_id);
            $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
            $title = get_post(get_post_thumbnail_id($thumbnail_id))->post_title;

            
            $main_selector = $plugin->getSelectorTemplate(compact('alt','img','medium','medium2x','thumb','thumb2x','id')); //save main image selector to globals
            $main_selector = str_replace('<img','<img class="attachment-90x90" ',$main_selector);
            
            $selectors[] = $main_selector;
            
        }
        
        if (isset($main_selector)) {
            $test_link = preg_replace('/.*?href=[\'\"](.*?)[\'\"].*/is','$1',$main_selector);
            if (count($attachments) == 1) {
                $att_keys = array_keys($attachments);
                if (basename($attachments[$att_keys[0]]->guid) == basename($test_link)) return array();
            }
        } else {
            $test_link = false;
        }
        
        

        foreach ($attachments as $attachment) {
        if (is_object($attachment)) {
            if (!preg_match('/image/is',$attachment->post_mime_type)) continue;
            $meta = wp_get_attachment_metadata($attachment->ID);
            if ($test_link) {
                if (basename($attachment->guid) == basename($test_link)) continue;
            }
            //$title = $alt = '';
            $title = $attachment->post_title;
            $alt = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);

            
            $imgpath = str_replace($shop_dir,'/',get_attached_file($attachment->ID));

            $img = $imagehelper->create( $imgpath, 'original', $post_id);//$url.'/'.$image_dir.$meta['file'];
            $medium = $imagehelper->create( $imgpath, array($plugin->params->getValue('thumb-max-width'),$plugin->params->getValue('thumb-max-height')), $post_id);
            $medium2x = $imagehelper->create( $imgpath, array($plugin->params->getValue('thumb-max-width')*2,$plugin->params->getValue('thumb-max-height')*2), $post_id);
            $thumb = $imagehelper->create( $imgpath, array($plugin->params->getValue('selector-max-width'),$plugin->params->getValue('selector-max-height')), $post_id);
            $thumb2x = $imagehelper->create( $imgpath, array($plugin->params->getValue('selector-max-width')*2,$plugin->params->getValue('selector-max-height')*2), $post_id);
            $selectors[] = $plugin->getSelectorTemplate(compact('alt','img','medium','medium2x','thumb','thumb2x','id','title'));
        } else { // thecartpress
            $title = $alt = 'NO TITLE YET';
            $file = preg_replace('/^.*?wp-content\/uploads\//is','',$attachment);
            $img = $imagehelper->create( '/'.$image_dir.$file, 'original', $post_id);
            $medium = $imagehelper->create( '/'.$image_dir.$file, array($plugin->params->getValue('thumb-max-width'),$plugin->params->getValue('thumb-max-height')), $post_id);
            $medium2x = $imagehelper->create( '/'.$image_dir.$file, array($plugin->params->getValue('thumb-max-width')*2,$plugin->params->getValue('thumb-max-height')*2), $post_id);
            $thumb = $imagehelper->create( '/'.$image_dir.$file, array($plugin->params->getValue('selector-max-width'),$plugin->params->getValue('selector-max-height')), $post_id);
            $thumb2x = $imagehelper->create( '/'.$image_dir.$file, array($plugin->params->getValue('selector-max-width')*2,$plugin->params->getValue('selector-max-height')*2), $post_id);
            $selectors[] = $plugin->getSelectorTemplate(compact('alt','img','medium','medium2x','thumb','thumb2x','id','title'));
        }

        }
    }

    foreach($selectors as $key => $selector){
        $selectors[$key] = preg_replace('/(<a)/', "$1" . ' class="lightbox-added"', $selector);
    }

    setlocale(LC_ALL, $oldLocale);

    return $selectors;
}


function WooCommerce_MagicZoom_page_check ($moduleName = false) {
    switch (strtolower($moduleName)) {
        case 'wpecommerce' : {
            if (!WPSC_VERSION) return 'error';
            if (WPSC_PRESENTABLE_VERSION == '3.7.6.3' || WPSC_PRESENTABLE_VERSION == '3.7.6.4' || WPSC_PRESENTABLE_VERSION == '3.7.8') {
                if ($GLOBALS["wpsc_title_data"]["product"]) {
                    $cat = false;
                }else {
                    $cat = true;
                }
            } else if (WPSC_VERSION == '3.8') {
                if (isset($GLOBALS['wp_the_query']->query_vars['wpsc-product']) && $GLOBALS['wp_the_query']->query_vars['wpsc-product'] != '') {
                    $cat = false;
                } else {
                    $cat = true;
                }
            } else
            if (version_compare(WPSC_VERSION, '3.8.1', '>=')) {
                    if ( $GLOBALS['wp_the_query']->is_single == '1') { /*isset($GLOBALS['wp_the_query']->is_product) && $GLOBALS['wp_the_query']->is_product == '1'*/
                        $cat = false;
                    } else {
                        $cat = true;
                    }
            } else {
                if (!empty($GLOBALS['wp_query']->query_vars['product_url_name']) && $GLOBALS['wp_query']->query_vars['product_url_name'] != '') {
                    $cat = false;
                } else {
                    $cat = true;
                }
            }
        break;}
        case 'jigoshop' : {
            if (!JIGOSHOP_VERSION) return 'error';
            if (function_exists('is_product') && function_exists('is_product_list')) {

              //if (is_product()) $cat = false; else $cat = true;
              if ($GLOBALS['post']->post_type=='product' || is_product()) $cat = false; else $cat = true;

              if (is_product_list()) $cat = true; else $cat = false;
            } else {
              return 'error';
            }
        break;}
    case 'woocommerce' : {
            if (!WOOCOMMERCE_VERSION) return 'error';
            if (function_exists('is_product') && function_exists('is_product_category')) {
              if (is_product()) { 
                    $cat = false; 
                } else { 
                    if (isset($GLOBALS['post']->post_content) && preg_match('/\[product_page\s+id=\"\d+\"\]/is',$GLOBALS['post']->post_content) !== false) {
                        $cat = false; 
                    } else {
                        $cat = true;     
                    }
                }
              //if ($GLOBALS['post']->post_type=='product' || is_product()) $cat = false; else $cat = true;
            } else {
              return 'error';
            }
        break;}
        case 'thecartpress' : {
        global $thecartpress;
            if (!isset($thecartpress->settings)) return 'error';
            if (function_exists('is_single')) {
              if (is_single()) $cat = false; else $cat = true;
            } else {
              return 'error';
            }
        break;}

        default : return 'error';
    }
    return $cat;
}



function WooCommerceMagicZoom_url ($url,$position) {

    if ('commercial' == get_tool_version_WooCommerce_MagicZoom()) {
    $utm_source = 'CommercialVerison';
    } else {
    if (magictoolbox_WooCommerce_MagicZoom_get_data_from_db()) {
        $utm_source = 'CommercialVersion';
    } else {
        $utm_source = 'TrialVersion';
    }
    }

    $utm_medium = 'WooCommerce';
    $utm_content = preg_replace('/\s+/is','-',trim($position));
    $utm_campaign = 'MagicZoom';

    $link = $url.'?utm_source='.$utm_source.'&utm_medium='.$utm_medium.'&utm_content='.$position.'&utm_campaign='.$utm_campaign;

    return $link;
}

function WooCommerceMagicZoom_params_map_check ($profile = 'default', $group, $parameter) {
    $map = WooCommerceMagicZoom_getParamsMap();
    if (isset($map[$profile][$group][$parameter])) return true;
    return false;
}
function WooCommerceMagicZoom_getParamsMap () {
    $map = array(
		'product' => array(
			'General' => array(
				'include-headers',
				'page-status',
				'magicscroll',
			),
			'Positioning and Geometry' => array(
				'zoomWidth',
				'zoomHeight',
				'zoomPosition',
				'zoomDistance',
				'thumb-max-width',
				'thumb-max-height',
				'square-images',
			),
			'Multiple images' => array(
				'selectorTrigger',
				'transitionEffect',
				'template',
				'selectors-margin',
				'variations-selectors',
				'selector-max-width',
				'selector-max-height',
			),
			'Miscellaneous' => array(
				'lazyZoom',
				'rightClick',
				'cssClass',
				'create-main-image-selector',
				'show-message',
				'message',
			),
			'Zoom mode' => array(
				'zoomMode',
				'zoomOn',
				'upscale',
				'smoothing',
				'variableZoom',
				'zoomCaption',
			),
			'Hint' => array(
				'hint',
				'textHoverZoomHint',
				'textClickZoomHint',
			),
			'Mobile' => array(
				'zoomModeForMobile',
				'textHoverZoomHintForMobile',
				'textClickZoomHintForMobile',
			),
			'Scroll' => array(
				'width',
				'height',
				'mode',
				'items',
				'speed',
				'autoplay',
				'loop',
				'step',
				'arrows',
				'pagination',
				'easing',
				'scrollOnWheel',
				'lazy-load',
				'scroll-extra-styles',
				'show-image-title',
			),
			'Use Wordpress images' => array(
				'use-wordpress-images',
				'single-wordpress-image',
				'thumbnails-wordpress-image',
			),
		),
		'category' => array(
			'General' => array(
				'include-headers',
				'page-status',
			),
			'Positioning and Geometry' => array(
				'zoomWidth',
				'zoomHeight',
				'zoomPosition',
				'zoomDistance',
				'thumb-max-width',
				'thumb-max-height',
				'square-images',
			),
			'Miscellaneous' => array(
				'lazyZoom',
				'rightClick',
				'cssClass',
				'create-main-image-selector',
				'link-to-product-page',
				'show-message',
				'message',
			),
			'Zoom mode' => array(
				'zoomMode',
				'zoomOn',
				'upscale',
				'smoothing',
				'variableZoom',
				'zoomCaption',
			),
			'Hint' => array(
				'hint',
				'textHoverZoomHint',
				'textClickZoomHint',
			),
			'Mobile' => array(
				'zoomModeForMobile',
				'textHoverZoomHintForMobile',
				'textClickZoomHintForMobile',
			),
			'Use Wordpress images' => array(
				'use-wordpress-images',
				'category-wordpress-image',
			),
		),
		'default' => array(
			'General' => array(
				'include-headers',
			),
			'Positioning and Geometry' => array(
				'zoomWidth',
				'zoomHeight',
				'zoomPosition',
				'zoomDistance',
				'thumb-max-width',
				'thumb-max-height',
			),
			'Miscellaneous' => array(
				'lazyZoom',
				'rightClick',
				'cssClass',
				'create-main-image-selector',
				'link-to-product-page',
				'show-message',
				'message',
				'imagemagick',
				'image-quality',
			),
			'Zoom mode' => array(
				'zoomMode',
				'zoomOn',
				'upscale',
				'smoothing',
				'variableZoom',
				'zoomCaption',
			),
			'Watermark' => array(
				'watermark',
				'watermark-max-width',
				'watermark-max-height',
				'watermark-opacity',
				'watermark-position',
				'watermark-offset-x',
				'watermark-offset-y',
			),
			'Hint' => array(
				'hint',
				'textHoverZoomHint',
				'textClickZoomHint',
			),
			'Mobile' => array(
				'zoomModeForMobile',
				'textHoverZoomHintForMobile',
				'textClickZoomHintForMobile',
			),
			'Use Wordpress images' => array(
				'use-wordpress-images',
				'category-wordpress-image',
			),
		),
	);
    return $map;
}

function WooCommerceMagicZoom_getParamsProfiles () {

    $blocks = array(
		'product' => 'Product pages',
		'category' => 'Category pages',
		'default' => 'General',
	);

    return $blocks;
}

function WooCommerceMagicZoom_welcome_license_do_redirect() {
  // Bail if no activation redirect
    if ( ! get_transient( 'WooCommerce_MagicZoom_welcome_license_activation_redirect' ) ) {
    return;
  }

  // Delete the redirect transient
  delete_transient( 'WooCommerce_MagicZoom_welcome_license_activation_redirect' );

  // Bail if activating from network, or bulk
  if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
    return;
  }

  // Redirect to bbPress about page
  wp_safe_redirect( add_query_arg( array( 'page' => 'WooCommerceMagicZoom-license-page' ), admin_url( 'admin.php' ) ) );

}

function WooCommerceMagicZoom_plugin_path() {

  return untrailingslashit( plugin_dir_path( __FILE__ ) );
 
}
 
function WooCommerce_MagicZoom_locate_template( $template, $template_name, $template_path ) {
 
  global $woocommerce;
 
  $_template = $template;
 
  if ( ! $template_path ) $template_path = $woocommerce->template_url;
 
  $plugin_path  = WooCommerceMagicZoom_plugin_path() . '/core/templates/';
  
  $template = locate_template(
 
    array(
 
      $template_path . $template_name,
 
      $template_name
 
    )
 
  );
    $post_id = get_the_id();

    if(function_exists('wpml_get_default_language')){
        $default_language = wpml_get_default_language();
        global $main_id;
        $main_id = icl_object_id($post_id, 'post', true, $default_language);

        if($main_id !== $post_id) $post_id = $main_id;   
    }
  
    if ( file_exists( $plugin_path . $template_name ) ) {
        $template = $plugin_path . $template_name;
    } else {
        $template = $_template;
    }
  
    if ( ! $template ) {
        $template = $_template;
    }

  return $template;
 
}

function WooCommerce_MagicZoom_get_containers_data($thumbs = array(), $post_id = false, $useWpImages = false) {

    $mainHTML = '';
    $GLOBALS['defaultContainerId'] = 'zoom';
    $containersData = array(
        'zoom' => '',
        '360' => '',
    );
    $productImagesHTML = array();

    $plugin = $GLOBALS['magictoolbox']['WooCommerceMagicZoom'];
    
    $main_image = $GLOBALS['magictoolbox']['MagicZoom']['main'];
    $main_image = preg_replace('/(<a.*?class=\".*?)\"/is', "$1" . ' lightbox-added"', $main_image);
    $containersData['zoom'] = $main_image;

    if(isset($thumbs) && !empty($thumbs)){ 
        foreach ($thumbs as $index => $thumb) {
            $thumbs[$index] = str_replace('<a ', '<a data-product-id="'.$post_id.'" data-magic-slide-id="zoom" ', $thumb);
        }
     }

    if ($useWpImages) {
        global $_wp_additional_image_sizes;
        $imageSize = $plugin->params->getValue('thumbnails-wordpress-image', 'product');
        if (in_array( $imageSize, array('thumbnail', 'medium', 'medium_large', 'large'))) {
            $sMaxWidth = (int)get_option($imageSize.'_size_w');
            $sMaxHeight = (int)get_option($imageSize.'_size_h');
        } else if (isset( $_wp_additional_image_sizes[$imageSize] ) ) {
            $sMaxWidth = (int)$_wp_additional_image_sizes[$imageSize]['width'];
            $sMaxHeight = (int)$_wp_additional_image_sizes[$imageSize]['height'];
        }
    } else {
        $sMaxWidth = (int)$plugin->params->getValue('selector-max-width', 'product');
        $sMaxHeight = (int)$plugin->params->getValue('selector-max-height', 'product');
    }

    //video data
    if (metadata_exists( 'post', $post_id, '_provide_videolinks_field' )){
        $scrollEnabled = $plugin->params->checkValue('magicscroll', 'Yes');
        $productVideos = get_post_meta( $post_id, '_provide_videolinks_field', true );
        
        if (!empty($productVideos) && preg_match('/a\:\d+/is',$productVideos)) {
        
            $productVideos = unserialize($productVideos);
            $videoIndex = 1;
         
            foreach ($productVideos as $videoUrl => $videoData) {
                if($videoData['youtube']) {
                    $dataVideoType = 'youtube';
                    $url = 'https://www.youtube.com/embed/'.$videoData['code'];
                    $containersData['video-'.$videoIndex] = '<iframe src="https://www.youtube.com/embed/'.$videoData['code'].'?enablejsapi=1"';
                } else {
                    $dataVideoType = 'vimeo';
                    $url = 'https://player.vimeo.com/video/'.$videoData['code'];
                    $containersData['video-'.$videoIndex] = '<iframe src="https://player.vimeo.com/video/'.$videoData['code'].'?byline=0&portrait=0"';
                }
                
                $containersData['video-'.$videoIndex] .=' frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen data-product-id="'.$post_id.'" data-video-type="'.$dataVideoType.'"></iframe>';
                
                $videoData['thumb'] = str_replace('http://', 'https://', $videoData['thumb']);
                
                $productImagesHTML[] =
                    '<a data-magic-slide-id="video-'.$videoIndex.'" data-product-id="'.$post_id.'" data-video-type="'.$dataVideoType.'" class="video-selector" href="#" onclick="return false">'.
                    '<span><b></b></span>'.
                    '<img src="'.$videoData['thumb'].'" alt="video"'.($scrollEnabled ? '' : ' style="max-width: '.$sMaxWidth.'px; max-height: '.$sMaxHeight.'px;"').'/>'.
                    '</a>';
                $videoIndex++;
            }
            
        }
    }

    if (metadata_exists( 'post', $post_id, '_magic360_data' ) && magictoolbox_WooCommerce_MagicZoom_check_plugin_active('magic360') ){

        $magic360_plugin = $GLOBALS['magictoolbox']['WooCommerceMagic360'];
        $magic360_plugin->params->setProfile('product');

        if(!empty($magic360_plugin) && $magic360_plugin->params->checkValue('page-status','Yes') ){

            $magic360_data = json_decode((get_post_meta( $post_id, '_magic360_data', true )), true);
            $magic360_image_gallery = Array();
            if(!empty($magic360_data) && array_key_exists('images_ids', $magic360_data)) $magic360_image_gallery = $magic360_data['images_ids'];

            if(!empty($magic360_image_gallery)){

                $watermark = $plugin->params->getValue('watermark');
                $plugin->params->setValue('watermark', '');

                $magic360_selector_path = $magic360_plugin->params->getValue('selector-path');
                
                if (!$useWpImages) {
                    $magic360_selector = '<a data-product-id="'.$post_id.'" data-magic-slide-id="360" style="display:inline-block;" class="m360-selector" title="360" href="#" onclick="return false;"><img src="'.WooCommerce_MagicZoom_get_product_image($magic360_selector_path,'selector').'" alt="360" /></a>';
                } else {
                    $magic360_selector = '<a data-product-id="'.$post_id.'" data-magic-slide-id="360" style="display:inline-block;" class="m360-selector" title="360" href="#" onclick="return false;"><img style="max-width: '.$sMaxWidth.'px; max-height: '.$sMaxHeight.'px;" src="'.get_site_url().$magic360_selector_path.'" alt="360" /></a>';
                }     
                
                $plugin->params->setValue('watermark', $watermark);
                array_unshift($thumbs, $magic360_selector);

                foreach($magic360_image_gallery as $i => $image_id) {
                    $image_src = wp_get_attachment_image_src($image_id, 'original', $image_id);
                    $image_src = preg_replace('/.*(\/wp-content.*)/','$1', $image_src[0]);
                    $GLOBALS['magic360images'][$i] = array(
                        'medium' => WooCommerce_Magic360_get_product_image($image_src,'thumb', $image_id),
                        'img' => WooCommerce_Magic360_get_product_image($image_src,'original', $image_id)
                    );
                }

                $magic360_plugin->params->setValue('columns', $magic360_data['options']['columns']);

                usort($GLOBALS['magic360images'], 'magictoolbox_WooCommerce_MagicZoom_key_sort');

                $containersData['360'] = $magic360_plugin->getMainTemplate($GLOBALS['magic360images']);
                
                $defaultView = $magic360_plugin->params->getValue('default-spin-view');
                if ($defaultView == 'Spin') {
                    $GLOBALS['defaultContainerId'] = '360';
                } else {
                    $GLOBALS['defaultContainerId'] = 'zoom';
                }

                global $magictoolbox_Magic360_page_has_tool;
                $magictoolbox_Magic360_page_has_tool = true;
                unset($GLOBALS['magic360images']);
            }
        }

    }
    
    return array('containersData'       => $containersData,
                 'productImagesHTML'    => $productImagesHTML,
                 'thumbs'               => $thumbs);
    
}


if( function_exists('register_block_type' ) ){
  if( !function_exists('WooCommerce_MagicZoom_addmedia_block')){
    function WooCommerce_MagicZoom_addmedia_block(){

      wp_register_script(
        'woocommerce-magiczoom-addmedia-block-editor-js',
        plugins_url('/gutenberg/addmedia-block/editor-script.js', __FILE__),
        array( 'wp-blocks', 'wp-element', 'wp-editor', 'jquery'), NULL
      );

      register_block_type( 'woocommerce-magiczoom/addmedia-block', array(
          'editor_script' => 'woocommerce-magiczoom-addmedia-block-editor-js',
      ) );
    }

    add_action( 'init', 'WooCommerce_MagicZoom_addmedia_block' );
  }

}

function WooCommerce_MagicZoom_slideshow_gallery($atts){

    global $wpdb;

    $table_name = strtolower($wpdb->prefix . 'MagicZoom_store');
    $result = $wpdb->get_results("SELECT id,name FROM $table_name ");

    return rest_ensure_response( $result );
}

add_action( 'rest_api_init', 'WooCommerce_MagicZoom_gallery_route');

function WooCommerce_MagicZoom_gallery_route() {
            
    register_rest_route( 'MagicZoom', 'get-shortcodes', array(
            'methods' => 'GET',
            'callback' => 'WooCommerce_MagicZoom_slideshow_gallery',
            /*'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
                }, */
    ));
}


?>
