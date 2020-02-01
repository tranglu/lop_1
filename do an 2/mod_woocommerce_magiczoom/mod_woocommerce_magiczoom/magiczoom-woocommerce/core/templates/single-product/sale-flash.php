<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $post, $woocommerce, $product, $main_id;

if(!function_exists('WooCommerce_MagicZoom_remove_placeholder')){
    function WooCommerce_MagicZoom_remove_placeholder($images){
        $arr_copy = $images;
        $pattern = 'placeholder.png';
        foreach($images as $key => $image){
            if(stripos($image, $pattern) !== false){
                unset($arr_copy[$key]);
            }
        }
        return $arr_copy;
    }
}

if (!isset($GLOBALS['magictoolbox']['WooCommerce_MagicZoom_product_loaded'])) {

?>
<div class="images">

	<?php
		$flag = (isset($main_id) or has_post_thumbnail()) ? true : false; 

        if ( $flag ) {                 
                        $pid = isset($main_id) ? $main_id : $product->get_id();
                        
                        $apath = str_replace(join(DIRECTORY_SEPARATOR, array('templates', 'single-product')),'',dirname(__FILE__));
                        require_once($apath . 'magictoolbox.templatehelper.class.php');
                        $plugin = $GLOBALS['magictoolbox']['WooCommerceMagicZoom'];
                        
                        $GLOBALS['custom_template_headers'] = true;
                        
                        $plugin->params->setProfile('product');
                        MagicToolboxTemplateHelperClass::setPath($apath.'templates');
                        
                        MagicToolboxTemplateHelperClass::setOptions($plugin->params);
                        $useWpImages = $plugin->params->checkValue('use-wordpress-images','yes');
                        $plugin->params->setProfile('product');
                        
                        if (!$useWpImages) { //no need in watermark with wp images

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
                        }
                        
                            
                        $thumbs = WooCommerce_MagicZoom_get_prepared_selectors($pid, $useWpImages);
                        $thumbs = WooCommerce_MagicZoom_remove_placeholder($thumbs);
                        
                        //$id = '_Main';
                        $id = '_Main_Product'.$pid;
                        $thumbnail_id  = get_post_thumbnail_id($pid);
                        
                        $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                        $title = get_post($thumbnail_id)->post_title;
                        if (empty($title)) $title = $post->post_title;
                        
                        $additionalDescription = preg_replace ('/<a[^>]*><img[^>]*><\/a>/is','',$post->post_excerpt);
                        $description = preg_replace ('/<a[^>]*><img[^>]*><\/a>/is','',$post->post_content);
                        $description = preg_replace ('/\[caption id=\"attachment_[0-9]+\"[^\]]*?\][^\[]*?\[\/caption\]/is','',$description);
                        
                        $link = '';
                        
                        if ($useWpImages) {
                            
                            $img = wp_get_attachment_image_src( $thumbnail_id, 'full' ); 
                            $img = $img[0];
                            
                            $thumb = wp_get_attachment_image_src( $thumbnail_id, $plugin->params->getValue('single-wordpress-image') );
                            $thumb = $thumb[0];
                            
                            $img_result = $plugin->getMainTemplate(compact('img','thumb','thumb2x','id','title','alt','description','additionalDescription','link'));
                            
                        } else {

                            $img_name = str_replace(get_site_url(),'',wp_get_attachment_url( $thumbnail_id ));
                            
                            $thumb = WooCommerce_MagicZoom_get_product_image($img_name,'thumb');
                            $thumb2x = WooCommerce_MagicZoom_get_product_image($img_name,'thumb2x');
                            
                            WooCommerce_MagicZoom_get_product_variations(); //call only for onload variation check

                            $img = WooCommerce_MagicZoom_get_product_image($img_name,'original');
                            $img_result = $plugin->getMainTemplate(compact('img','thumb','thumb2x','id','title','alt','description','additionalDescription','link'));
                        }
                        $img_result = preg_replace('/(<a.*?class=\".*?)\"/is', "$1" . ' lightbox-added"', $img_result);
                        $GLOBALS['magictoolbox']['MagicZoom']['main'] = $img_result;
                        $mainHTML = $GLOBALS['magictoolbox']['MagicZoom']['main'];

                        $containersData = WooCommerce_MagicZoom_get_containers_data($thumbs,$pid,$useWpImages);
                        $thumbs = $containersData['thumbs'];
                        $mainHTML = '';
                        foreach($containersData['containersData'] as $containerId => $containerHTML) {
                            $activeClass = $GLOBALS['defaultContainerId'] == $containerId ? ' mt-active' : '';
                            $mainHTML .= "<div class=\"magic-slide product{$pid}{$activeClass}\" data-magic-slide=\"{$containerId}\">{$containerHTML}</div>";
                        }

                        if (isset($GLOBALS['MAGICTOOLBOX_'.strtoupper('MagicZoom').'_VARIATIONS_SELECTORS']) && count($GLOBALS['MAGICTOOLBOX_'.strtoupper('MagicZoom').'_VARIATIONS_SELECTORS'])) { //if variation selectors are present
                            $thumbs = array_merge($thumbs,$GLOBALS['MAGICTOOLBOX_'.strtoupper('MagicZoom').'_VARIATIONS_SELECTORS']);
                        }
                        
                        if(!empty($containersData['productImagesHTML'])){
                            $thumbs = array_merge($thumbs, $containersData['productImagesHTML']);
                        }
                        
                        
                        
                        
                        
                        $invisImg = '<figure class="woocommerce-product-gallery__image--placeholder"><a class="zoom invisImg wp-post-image" href="'.$img.'" style="display:none;"><img style="display:none;" src="'.$thumb.'"/></a></figure>';
                        
                        $scroll =  WooCommerce_MagicZoom_LoadScroll($plugin);
                        
                        $html = MagicToolboxTemplateHelperClass::render(array(
                            'main' => $mainHTML,
                            'thumbs' => (count($thumbs) >= 1) ? $thumbs : array(),
                            'magicscrollOptions' => $scroll ? $scroll->params->serialize(false, '', 'product-magicscroll-options') : '',
                            'pid' => $pid,
                        ));
                        $html .= magictoolbox_WooCommerce_MagicZoom_getMagicToolBoxEvent($plugin, $pid);
                        echo $invisImg.$html;
                        $GLOBALS['magictoolbox']['WooCommerce_MagicZoom_product_loaded'] = true;
                            
                        
		}

?>

</div>

<?php } ?>