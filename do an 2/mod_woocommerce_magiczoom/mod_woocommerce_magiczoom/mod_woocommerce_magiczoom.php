<?php
/*

Copyright 2008 MagicToolbox (email : support@magictoolbox.com)
Plugin Name: Magic Zoom for WooCommerce
Plugin URI: http://www.magictoolbox.com/magiczoom/?utm_source=TrialVersion&utm_medium=WooCommerce&utm_content=plugins-page-plugin-url-link&utm_campaign=MagicZoom
Description: Sell more by showing beautiful zoomed images on hover. Choose internal zoom, magnifying glass and a further <a href="admin.php?page=WooCommerceMagicZoom-config-page">24 easy customisation options</a>.
Version: 6.8.10
Author: Magic Toolbox
Author URI: http://www.magictoolbox.com/?utm_source=TrialVersion&utm_medium=WooCommerce&utm_content=plugins-page-author-url-link&utm_campaign=MagicZoom


*/

/*
    WARNING: DO NOT MODIFY THIS FILE!

    NOTE: If you want change Magic Zoom settings
            please go to plugin page
            and click 'Magic Zoom Configuration' link in top navigation sub-menu.
*/

if(!function_exists('magictoolbox_WooCommerce_MagicZoom_init')) {
    /* Include MagicToolbox plugins core funtions */
    require_once(dirname(__FILE__)."/magiczoom-woocommerce/plugin.php");
}

//MagicToolboxPluginInit_WooCommerce_MagicZoom ();
register_activation_hook( __FILE__, 'WooCommerce_MagicZoom_activate');

register_deactivation_hook( __FILE__, 'WooCommerce_MagicZoom_deactivate');

register_uninstall_hook(__FILE__, 'WooCommerce_MagicZoom_uninstall');

magictoolbox_WooCommerce_MagicZoom_init();
?>