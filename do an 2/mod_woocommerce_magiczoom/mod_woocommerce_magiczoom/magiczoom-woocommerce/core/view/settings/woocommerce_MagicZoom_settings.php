<?php
    $id = 'WooCommerceMagicZoom';
    $settings = get_option("WooCommerceMagicZoomCoreSettings");
    $map = WooCommerceMagicZoom_getParamsMap();

    if(isset($_POST["submit"])) {
        $allSettings = array();
        /* save settings */
        foreach (WooCommerceMagicZoom_getParamsProfiles() as $profile => $name) {
            $GLOBALS['magictoolbox'][$id]->params->setProfile($profile);
            foreach($_POST as $name => $value) {
            
                //cut the url from watermark path
                if (strpos($name,'watermark') !== false) { 
                    $value = str_replace(site_url('','http').DIRECTORY_SEPARATOR,'',$value);
                    $value = str_replace(site_url('','https').DIRECTORY_SEPARATOR,'',$value);
                    $value = str_replace(str_replace('http://','',site_url('','http')).DIRECTORY_SEPARATOR,'',$value);
                }
            
            
                if(preg_match('/magiczoomsettings_'.ucwords($profile).'_(.*)/is',$name,$matches)) {
                    $GLOBALS['magictoolbox'][$id]->params->setValue($matches[1], $value);
                }
            }
            $allSettings[$profile] = $GLOBALS['magictoolbox'][$id]->params->getParams($profile);
        }

        update_option($id . "CoreSettings", $allSettings);
        $settings = $allSettings;
    }

    $corePath = preg_replace('/https?:\/\/[^\/]*/is', '', get_option("siteurl"));
    $corePath .= '/wp-content/'.preg_replace('/^.*?\/(plugins\/.*?)$/is', '$1', str_replace("\\", "/", dirname(dirname(dirname(__FILE__))) ));

    
    if (!function_exists('magictoolbox_WooCommerce_MagicZoom_get_wordpress_image_sizes')) {
        function magictoolbox_WooCommerce_MagicZoom_get_wordpress_image_sizes( $unset_disabled = true ) {
        $wais = & $GLOBALS['_wp_additional_image_sizes'];

        $sizes = array();

        foreach ( get_intermediate_image_sizes() as $_size ) {
            if ( in_array( $_size, array('thumbnail', 'medium', 'medium_large', 'large') ) ) {
                $sizes[ $_size ] = array(
                    'width'  => get_option( "{$_size}_size_w" ),
                    'height' => get_option( "{$_size}_size_h" ),
                    'crop'   => (bool) get_option( "{$_size}_crop" ),
                );
            }
            elseif ( isset( $wais[$_size] ) ) {
                $sizes[ $_size ] = array(
                    'width'  => $wais[ $_size ]['width'],
                    'height' => $wais[ $_size ]['height'],
                    'crop'   => $wais[ $_size ]['crop'],
                );
            }

            // size registered, but has 0 width and height
            if( $unset_disabled && ($sizes[ $_size ]['width'] == 0) && ($sizes[ $_size ]['height'] == 0) )
                unset( $sizes[ $_size ] );
        }

        return $sizes;
        }
    }

    
    function WooCommerceMagicZoom_get_description(&$description) {
        $result = '';
        if (gettype($description) == "array" && count($description)) {
            $result .= '<span>'.array_shift($description).'</span>';
        }
        return $result;
    }

    function WooCommerceMagicZoom_widthout_img($id) {
        $result = false;
        //$arr = array('include-headers');
        $arr = array();


        if (in_array($id, $arr)) {
            $result = true;
        }
        return $result;
    }

    function WooCommerceMagicZoom_get_options_groups ($settings, $profile = 'default', $map, $id, $corePath) {
        $html = '';
        $toolAbr = '';
        $abr = explode(" ", strtolower("Magic Zoom"));

        foreach ($abr as $word) $toolAbr .= $word{0};

        if (!isset($settings[$profile])) return false;
        $settings = $settings[$profile];
        $imgSizes = magictoolbox_WooCommerce_MagicZoom_get_wordpress_image_sizes();


        $groups = array();
        $imgArray = array('zoom & expand','zoom&expand','yes','zoom','expand','swap images only','original','expanded','no','left','top left','top','top right', 'right', 'bottom right', 'bottom', 'bottom left'); //array for the images ordering

        $result = '';

        foreach($settings as $name => $s) {
            if (!isset($map[$profile][$s['group']]) || !in_array($s['id'], $map[$profile][$s['group']])) continue;
            if ($profile == 'product' || $profile == 'default') {
                if ($s['id'] == 'page-status' && !isset($s['value'])) {
                    $s['default'] = 'Yes';
                }
            }

            if (!isset($s['value'])) $s['value'] = $s['default'];

            if ($profile == 'product') {
                if ($s['id'] == 'page-status' && !isset($s['value'])) {
                    $s['default'] = 'Yes';
                }
            }


            if (strtolower($s['id']) == 'direction') continue;
            if (strtolower($s['id']) == 'include-headers' && $profile != 'default') continue;

            if ( in_array($s['id'], array('single-wordpress-image','thumbnails-wordpress-image','category-wordpress-image'))) {
                $s['values'] = array();
                foreach ($imgSizes as $size_title => $size_info) {
                    $s['values'][] = $size_title;
                }
                sort($s['values']);
                $s["type"] = 'dropdown';
            }



            if (!isset($groups[$s['group']])) {
                $groups[$s['group']] = array();
            }

            //$s['value'] = $GLOBALS['magictoolbox'][$id]->params->getValue($name);
            if (strpos($s["label"],'(')) {
                $before = substr($s["label"],0,strpos($s["label"],'('));
                $after = ' '.str_replace(')','',substr($s["label"],strpos($s["label"],'(')+1));
            } else {
                $before = $s["label"];
                $after = '';
            }
            if (strpos($after,'%')) $after = ' %';
            if (strpos($after,'in pixels')) $after = ' pixels';
            if (strpos($after,'milliseconds')) $after = ' milliseconds';

            $description2 = array();
            if (isset($s["description"]) && trim($s["description"]) != '') {
                $description = $s["description"];
                if (strtolower($s['id']) == 'include-headers') {
                    $description2 = explode('|', $description);
                    $description = '';
                }
            } else {
                $description = '';
            }

            $html  .= '<tr>';
            $html  .= '<th width="30%">';
            $html  .= '<label for="magiczoomsettings'.'_'.ucwords($profile).'_'. $name.'">'.$before.'</label>';

           
            if(($s['type'] != 'array') && isset($s['values']) && $s['type'] != 'dropdown') $html .= '<br/> <span class="afterText">' . implode(', ',$s['values']).'</span>';

            $html .= '</th>';
            $html .= '<td width="70%">';

            switch($s["type"]) {
                case "array":
                    $rButtons = array();
                    foreach($s["values"] as $p) {
                        $rButtons[strtolower($p)] = '<label><input type="radio" value="'.$p.'"'. ($s["value"]==$p?"checked=\"checked\"":"").' name="magiczoomsettings'.'_'.ucwords($profile).'_'.$name.'" id="magiczoomsettings'.'_'.ucwords($profile).'_'. $name.$p.'">';
                        $pName = ucwords($p);
                        if(strtolower($p) == "yes") {
                            if (WooCommerceMagicZoom_widthout_img(strtolower($s['id']))) {
                                $rButtons[strtolower($p)] .= WooCommerceMagicZoom_get_description($description2);
                            } else {
                                $rButtons[strtolower($p)] .= '<img src="'.$corePath.'/admin_graphics/yes.gif" alt="'.$pName.'" title="'.$pName.'" />';
                            }
                            $rButtons[strtolower($p)] .= '</label>';
                        } elseif(strtolower($p) == "no") {
                            if (WooCommerceMagicZoom_widthout_img(strtolower($s['id']))) {
                                $rButtons[strtolower($p)] .= WooCommerceMagicZoom_get_description($description2);
                            } else {
                                $rButtons[strtolower($p)] .= '<img src="'.$corePath.'/admin_graphics/no.gif" alt="'.$pName.'" title="'.$pName.'" />';
                            }
                            $rButtons[strtolower($p)] .= '</label>';
                        }
                        elseif(strtolower($p) == "left")
                            $rButtons[strtolower($p)] .= '<img src="'.$corePath.'/admin_graphics/left.gif" alt="'.$pName.'" title="'.$pName.'" /></label>';
                        elseif(strtolower($p) == "right")
                            $rButtons[strtolower($p)] .= '<img src="'.$corePath.'/admin_graphics/right.gif" alt="'.$pName.'" title="'.$pName.'" /></label>';
                        elseif(strtolower($p) == "top")
                            $rButtons[strtolower($p)] .= '<img src="'.$corePath.'/admin_graphics/top.gif" alt="'.$pName.'" title="'.$pName.'" /></label>';
                        elseif(strtolower($p) == "bottom")
                            $rButtons[strtolower($p)] .= '<img src="'.$corePath.'/admin_graphics/bottom.gif" alt="'.$pName.'" title="'.$pName.'" /></label>';
                        elseif(strtolower($p) == "bottom left" || strtolower($p) == "bl")
                            $rButtons[strtolower($p)] .= '<img src="'.$corePath.'/admin_graphics/bottom-left.gif" alt="'.$pName.'" title="'.$pName.'" /></label>';
                        elseif(strtolower($p) == "bottom right" || strtolower($p) == "br")
                            $rButtons[strtolower($p)] .= '<img src="'.$corePath.'/admin_graphics/bottom-right.gif" alt="'.$pName.'" title="'.$pName.'" /></label>';
                        elseif(strtolower($p) == "top left" || strtolower($p) == "tl")
                            $rButtons[strtolower($p)] .= '<img src="'.$corePath.'/admin_graphics/top-left.gif" alt="'.$pName.'" title="'.$pName.'" /></label>';
                        elseif(strtolower($p) == "top right" || strtolower($p) == "tr")
                            $rButtons[strtolower($p)] .= '<img src="'.$corePath.'/admin_graphics/top-right.gif" alt="'.$pName.'" title="'.$pName.'" /></label>';
                        else {
                            // if (strtolower($p) == 'load,hover' || strtolower($p) == 'load,click') {
                            //     if (strtolower($p) == 'load,hover') $pl = 'Load & hover';
                            //     if (strtolower($p) == 'load,click') $pl = 'Load & click';
                            //         $rButtons[strtolower($p)] .= '<span>'.ucwords($pl).'</span></label>';
                            // } else {
                            //     $rButtons[strtolower($p)] .= '<span>'.ucwords($p).'</span></label>';
                            // } //TODO

                            // if (strtolower($p) == 'load,hover') $p = 'Load & hover';
                            // if (strtolower($p) == 'load,click') $p = 'Load & click';
                            // $rButtons[strtolower($p)] .= '<span>'.ucwords($p).'</span></label>';


                            $rButtons[strtolower($p)] .= '<span>'.ucwords(('load,hover' == $p || 'load,click' == $p) ? str_replace(',', ' & ', $p) : $p).'</span></label>';
                        }
                    }
                    foreach ($imgArray as $img){
                        if (isset($rButtons[$img])) {
                            $html .= $rButtons[$img];
                            unset($rButtons[$img]);
                        }
                    }
                    $html .= implode('',$rButtons);
                    break;
                case "num":
                    $html .= '<input  style="width:60px;" type="text" name="magiczoomsettings'.'_'.ucwords($profile).'_'.$name.'" id="magiczoomsettings'.'_'.ucwords($profile).'_'. $name.'" value="'.$s["value"].'" />';
                    break;
                case "text":
                    if (strtolower($s["value"]) == 'auto' ||
                        strtolower($s["value"]) == 'fit' ||
                        strpos($s["value"],'%') !== false ||
                        ctype_digit($s["value"])) {
                            $width = 'style="width:60px;"';
                    } else {
                        $width = '';
                    }
                    if (strtolower($name) == 'message' || strtolower($name) == 'selector-path' || strtolower($name) == 'watermark') {
                        $width = 'style="width:95%;"';
                    }
                    
                    $html .= '<input '.$width.' type="text" name="magiczoomsettings'.'_'.ucwords($profile).'_'.$name.'" id="magiczoomsettings'.'_'.ucwords($profile).'_'. $name.'" value="'.$s["value"].'" />';

                    break;
                case "dropdown":
                    $html .= '<select name="magiczoomsettings'.'_'.ucwords($profile).'_'.$name.'" id=magiczoomsettings'.'_'.ucwords($profile).'_'. $name.'">';
                    $html .= '<option '.($s["value"]=='full'?"selected":"").' value="full">Original image</option>';
                    foreach ($s['values'] as $subvalue) {
                    
                        $subvalue_title = $subvalue;
                        if (isset($imgSizes[$subvalue])) {
                            $subvalue_title = $subvalue.' ('.$imgSizes[$subvalue]['width'].'x'.$imgSizes[$subvalue]['height'].')';
                        } 
                        
                        $html .= '<option '.(strtolower($s["value"])==strtolower($subvalue)?"selected":"").' value="'.$subvalue.'">'.$subvalue_title.'</option>';
                    }
                    $html .= '</select>';
                   
                    break;

                default:
                    if (strtolower($name) == 'message' || strtolower($name) == 'selector-path') {
                        $width = 'style="width:95%;"';
                    } else {
                        $width = '';
                    }
                    $html .= '<input '.$width.' type="text" name="magiczoomsettings'.'_'.ucwords($profile).'_'.$name.'" id="magiczoomsettings'.'_'.ucwords($profile).'_'. $name.'" value="'.$s["value"].'" />';
                    break;
            }
            $html .= '<span class="afterText">'.$after.'</span>';
            if (!empty($description)) $html .= '<span class="help-block">'.$description.'</span>';
            $html .= '</td>';
            $html .= '</tr>';
            $groups[$s['group']][] = $html;
            $html = '';
        }

        if (isset($groups['top'])) { //move 'top' group to the top
            $top = $groups['top'];
            unset($groups['top']);
            array_unshift($groups, $top);
        }

        if (isset($groups['Miscellaneous'])) {
            $misc = $groups['Miscellaneous'];
            unset($groups['Miscellaneous']);
            $groups['Miscellaneous'] = $misc; //move Miscellaneous to bottom
        }
        
        if (isset($groups['Use Wordpress images'])) {
            $uwpi = $groups['Use Wordpress images'];
            if (isset($groups['General'])) {
                $general = $groups['General'];
                unset($groups['General']);
            }
            unset($groups['Use Wordpress images']);
            $oldgroups = $groups;
            $groups = array();
            if (isset($general)) {
                $groups['General'] = $general;
            }
            $groups['Use Wordpress images'] = $uwpi; //move wp images to General
            $groups = array_merge($groups,$oldgroups);
        }

        foreach ($groups as $name => $group) {
            $i = 0;
                $group[count($group)-1] = str_replace('<tr','<tr class="last"',$group[count($group)-1]); //set "last" class
            $result .= '<h3 class="settingsTitle">'.$name.'</h3>
                        <div class="'.$toolAbr.'params">
                        <table class="params" cellspacing="0">';
            if (is_array($group)) {
                foreach ($group as $g) {
                    if (++$i%2==0) { //set stripes
                        if (strpos($g,'class="last"')) {
                            $g = str_replace('class="last"','class="back last"',$g);
                        } else {
                            $g = str_replace('<tr','<tr class="back"',$g);
                        }
                    }
                    $result .= $g;
                }
            }
            $result .= '</table> </div>';
        }

        return $result;
    }
?>

<div class="icon32" id="icon-options-general"><br></div>

<h1>Magic Zoom settings</h1>
<br/>
<p style="margin-right:20px; float:right; font-size:15px; white-space: nowrap;">
        &nbsp;<a href="<?php echo WooCommerceMagicZoom_url('http://www.magictoolbox.com/magiczoom/modules/woocommerce/',' configuration page resources settings link'); ?>" target="_blank">Documentation<span class="dashicons dashicons-share-alt2" style="text-decoration: none;line-height:1.3;margin-left:5px;"></span></a>&nbsp;|
        &nbsp;<a href="<?php echo WooCommerceMagicZoom_url('http://www.magictoolbox.com/magiczoom/examples/',' configuration page resources examples link'); ?>" target="_blank">Examples<span class="dashicons dashicons-share-alt2" style="text-decoration: none;line-height:1.3;margin-left:5px;"></span></a>&nbsp;|
        &nbsp;<a href="<?php echo WooCommerceMagicZoom_url('http://www.magictoolbox.com/contact/','configuration page resources support link'); ?>" target="_blank">Support<span class="dashicons dashicons-share-alt2" style="text-decoration: none;line-height:1.3;margin-left:5px;"></span></a>&nbsp;
        |&nbsp;<a href="<?php echo WooCommerceMagicZoom_url('http://www.magictoolbox.com/buy/magiczoom/','configuration page resources buy link'); ?>" target="_blank">Buy<span class="dashicons dashicons-share-alt2" style="text-decoration: none;line-height:1.3;margin-left:5px;"></span></a>
</p>
<form action="" method="post" id="magiczoom-config-form">
    <div id="tabs">
        <h2 class="nav-tab-wrapper">
            <ul>
                <?php /*<li><a data-toggle="tab" class="nav-tab nav-tab-active" href="#tab-general">General</a></li>*/ ?>
                <?php foreach (WooCommerceMagicZoom_getParamsProfiles() as $block_id => $block_name) {
                    if (!isset($tactive)) {
                        $tactive = 'nav-tab-active';
                    } else {
                        $tactive = '';
                    }
                ?>
                <li><a data-toggle="tab" class="nav-tab <?php echo $tactive; ?>" href="#tab-<?php echo $block_id; ?>"><?php echo $block_name; ?></a></li>
                <?php } ?>
            </ul>
        </h2>

        <div id="tab-default">
        <?php
            //echo WooCommerceMagicZoom_get_options_groups($settings, 'default', array('default' => array('Watermark' => $map['default']['Watermark'])),$id);
            echo WooCommerceMagicZoom_get_options_groups($settings, 'default', array('default' => array('Watermark' => $map['default']['Watermark'],
                                                                                                         'General' => array('include-headers'),
                                                                                                         'Miscellaneous' => array('image-quality','imagemagick')
                                                                                                         )), $id, $corePath);

        ?>
        </div>

        <?php 
            foreach (WooCommerceMagicZoom_getParamsProfiles() as $block_id => $block_name) {
            if ($block_id == 'default') continue;
            ?>
            <div id="tab-<?php echo $block_id; ?>">
                <?php echo WooCommerceMagicZoom_get_options_groups($settings, $block_id, $map, $id, $corePath); ?>
            </div>
        <?php }  ?>
    </div>

    <p id="set-main-settings"><input type="submit" name="submit" class="button-primary" value="Save settings" />&nbsp;<a id="resetLink" style="color:red; margin-left:25px;" href="admin.php?page=WooCommerceMagicZoom-config-page&reset_settings=true">Reset to defaults</a></p>
</form>

<!-- === onlyForMod start: wordpress -->
<div style="font-size:12px;margin:5px auto;text-align:center;">Learn more about the <a href="http://www.magictoolbox.com/magiczoom_integration/" target="_blank">customisation options<span class="dashicons dashicons-share-alt2" style="text-decoration: none;margin-left:2px;"></span></a></div>
<!-- === onlyForMod end -->