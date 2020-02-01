<?php

if(!defined('MagicZoomModuleCoreClassLoaded')) {

    define('MagicZoomModuleCoreClassLoaded', true);

    require_once(dirname(__FILE__) . '/magictoolbox.params.class.php');

    /**
     * MagicZoomModuleCoreClass
     *
     */
    class MagicZoomModuleCoreClass {

        /**
         * MagicToolboxParamsClass class
         *
         * @var   MagicToolboxParamsClass
         *
         */
        var $params;

        /**
         * Tool type
         *
         * @var   string
         *
         */
        var $type = 'standard';

        /**
         * Constructor
         *
         * @return void
         */
        function __construct() {
            $this->params = new MagicToolboxParamsClass();
            $this->params->setScope('magiczoom');
            $this->params->setMapping(array(
                'zoomWidth' => array('0' => 'auto'),
                'zoomHeight' => array('0' => 'auto'),
                'upscale' => array('Yes' => 'true', 'No' => 'false'),
                'lazyZoom' => array('Yes' => 'true', 'No' => 'false'),
                'rightClick' => array('Yes' => 'true', 'No' => 'false'),
                'transitionEffect' => array('Yes' => 'true', 'No' => 'false'),
                'variableZoom' => array('Yes' => 'true', 'No' => 'false'),
                'autostart' => array('Yes' => 'true', 'No' => 'false'),
                'smoothing' => array('Yes' => 'true', 'No' => 'false'),
            ));
            $this->loadDefaults();
        }

        /**
         * Method to get headers string
         *
         * @param string $jsPath  Path to JS file
         * @param string $cssPath Path to CSS file
         *
         * @return string
         */
        function getHeadersTemplate($jsPath = '', $cssPath = null) {
            //to prevent multiple displaying of headers
            if(!defined('MAGICZOOM_MODULE_HEADERS')) {
                define('MAGICZOOM_MODULE_HEADERS', true);
            } else {
                return '';
            }
            if($cssPath == null) {
                $cssPath = $jsPath;
            }
            $headers = array();
            $headers[] = '<!-- Magic Zoom WooCommerce module version v6.8.10 [v1.6.86:v5.3.2] -->';
            $headers[] = '<script type="text/javascript">window["mgctlbx$Pltm"] = "WooCommerce";</script>';
            $headers[] = '<link type="text/css" href="' . $cssPath . '/magiczoom.css" rel="stylesheet" media="screen" />';
            $headers[] = '<link type="text/css" href="' . $cssPath . '/magiczoom.module.css" rel="stylesheet" media="screen" />';
            $headers[] = '<script type="text/javascript" src="' . $jsPath . '/magiczoom.js"></script>';
            $headers[] = '<script type="text/javascript" src="' . $jsPath . '/magictoolbox.utils.js"></script>';
            $headers[] = $this->getOptionsTemplate();
            return "\r\n" . implode("\r\n", $headers) . "\r\n";
        }

        /**
         * Method to get options string
         *
         * @return string
         */
        function getOptionsTemplate() {
            $autostart = $this->params->getValue('autostart');//NOTE: true | false
            if($autostart !== null) {
                $autostart = "\n\t\t'autostart':" . $autostart . ',';
            } else {
                $autostart = '';
            }
            return "<script type=\"text/javascript\">\n\tvar mzOptions = {{$autostart}\n\t\t" . $this->params->serialize(true, ",\n\t\t") . "\n\t}\n</script>\n" .
                   "<script type=\"text/javascript\">\n\tvar mzMobileOptions = {" .
                   "\n\t\t'zoomMode':'" . str_replace('\'', '\\\'', $this->params->getValue('zoomModeForMobile')) . "'," .
                   "\n\t\t'textHoverZoomHint':'" . str_replace('\'', '\\\'', $this->params->getValue('textHoverZoomHintForMobile')) . "'," .
                   "\n\t\t'textClickZoomHint':'" . str_replace('\'', '\\\'', $this->params->getValue('textClickZoomHintForMobile')) . "'" .
                   "\n\t}\n</script>";
        }

        /**
         * Method to get main image HTML
         *
         * @param array $params Params
         *
         * @return string
         */
        function getMainTemplate($params) {
            $img = '';
            $thumb = '';
            $thumb2x = '';
            $id = '';
            $alt = '';
            $title = '';
            $width = '';
            $height = '';
            $link = '';

            extract($params);

            if(empty($img)) {
                return false;
            }
            if(empty($thumb)) {
                $thumb = $img;
            }
            if(empty($id)) {
                $id = hash('md5', $img, false);
            }

            if(!empty($title)) {
                $title = htmlspecialchars(htmlspecialchars_decode($title, ENT_QUOTES));
                if(empty($alt)) {
                    $alt = $title;
                } else {
                    $alt = htmlspecialchars(htmlspecialchars_decode($alt, ENT_QUOTES));
                }
                $title = " title=\"{$title}\"";
            } else {
                $title = '';
                if(empty($alt)) {
                    $alt = '';
                } else {
                    $alt = htmlspecialchars(htmlspecialchars_decode($alt, ENT_QUOTES));
                }
            }

            if(empty($width)) {
                $width = '';
            } else {
                $width = " width=\"{$width}\"";
            }
            if(empty($height)) {
                $height = '';
            } else {
                $height = " height=\"{$height}\"";
            }

            if($this->params->checkValue('show-message', 'Yes')) {
                $message = '<div class="MagicToolboxMessage">' . $this->params->getValue('message') . '</div>';
            } else {
                $message = '';
            }

            if(empty($link)) {
                $link = '';
            } else {
                $link = " data-link=\"{$link}\"";
            }

            $options = $this->params->serialize();

            if(!empty($options)) {
                $options = " data-options=\"{$options}\"";
            }

            $mobileOptions = array(
                'zoomModeForMobile'          => 'zoomMode',
                'textHoverZoomHintForMobile' => 'textHoverZoomHint',
                'textClickZoomHintForMobile' => 'textClickZoomHint',
            );
            $profile = $this->params->getProfile();
            foreach($mobileOptions as $mId => $option) {
                if(!$this->params->paramExists($mId, $profile) || $this->params->checkValue($mId, $this->params->getValue($mId, $this->params->generalProfile), $profile)) {
                    $mobileOptions[$mId] = '';
                    continue;
                }
                $mobileOptions[$mId] = "{$option}:" . str_replace('"', '&quot;', $this->params->getValue($mId, $profile)) . ';';
            }
            $mobileOptions = implode('', $mobileOptions);
            if(!empty($mobileOptions)) {
                $options .= " data-mobile-options=\"{$mobileOptions}\"";
            }

            if (!empty($thumb2x)) {
                //NOTICE: temporary disabled because of issue with zoom images (when the picture size is not big enough)
                //$dataImage2x = ' data-zoom-image-2x="' . $img . '" data-image-2x="' . $thumb2x . '" ';
                $dataImage2x = ' data-image-2x="' . $thumb2x . '" ';
                //$thumb2x = ' srcset="' . $thumb2x . ' 2x"';
                //$thumb2x = ' srcset="' . $thumb . ' 1x, ' . $thumb2x . ' 2x"';
                $thumb2x = ' srcset="' . str_replace(' ', '%20', $thumb) . ' 1x, ' . str_replace(' ', '%20', $thumb2x) . ' 2x"';
            } else {
                $dataImage2x = '';
            }


            return "<a id=\"MagicZoomImage{$id}\" {$dataImage2x} class=\"MagicZoom\" href=\"{$img}\"{$link}{$title}{$options}><img itemprop=\"image\" src=\"{$thumb}\" {$thumb2x} alt=\"{$alt}\"{$width}{$height} /></a>{$message}";
        }

        /**
         * Method to get selectors HTML
         *
         * @param array $params Params
         *
         * @return string
         */
        function getSelectorTemplate($params) {
            $img = '';
            $medium = '';
            $medium2x = '';
            $thumb = '';
            $thumb2x = '';
            $id = '';
            $alt = '';
            $title = '';
            $width = '';
            $height = '';

            extract($params);

            if(empty($img)) {
                return false;
            }
            if(empty($medium)) {
                $medium = $img;
            }
            if(empty($thumb)) {
                $thumb = $img;
            }
            if(empty($id)) {
                $id = hash('md5', $img, false);
            }

            if(!empty($title)) {
                $title = htmlspecialchars(htmlspecialchars_decode($title, ENT_QUOTES));
                if(empty($alt)) {
                    $alt = $title;
                } else {
                    $alt = htmlspecialchars(htmlspecialchars_decode($alt, ENT_QUOTES));
                }
                $title = " title=\"{$title}\"";
            } else {
                $title = '';
                if(empty($alt)) {
                    $alt = '';
                } else {
                    $alt = htmlspecialchars(htmlspecialchars_decode($alt, ENT_QUOTES));
                }
            }

            if(empty($width)) {
                $width = '';
            } else {
                $width = " width=\"{$width}\"";
            }
            if(empty($height)) {
                $height = '';
            } else {
                $height = " height=\"{$height}\"";
            }

            if (!empty($thumb2x)) {
                //$thumb2x = ' srcset="' . $thumb2x . ' 2x"';
                //$thumb2x = ' srcset="' . $thumb . ' 1x, ' . $thumb2x . ' 2x"';
                $thumb2x = ' srcset="' . str_replace(' ', '%20', $thumb) . ' 1x, ' . str_replace(' ', '%20', $thumb2x) . ' 2x"';
            }

            if (!empty($medium2x)) {
                //NOTICE: temporary disabled because of issue with zoom images (when the picture size is not big enough)
                //$medium2x = ' data-zoom-image-2x="' . $img . '" data-image-2x="' . $medium2x . '" ';
                $medium2x = ' data-image-2x="' . $medium2x . '" ';
            }

            return "<a data-zoom-id=\"MagicZoomImage{$id}\" href=\"{$img}\" {$medium2x} data-image=\"{$medium}\"{$title}><img src=\"{$thumb}\" {$thumb2x} alt=\"{$alt}\"{$width}{$height} /></a>";
        }

        /**
         * Method to load defaults options
         *
         * @return void
         */
        function loadDefaults() {
            $params = array(
				"include-headers"=>array("id"=>"include-headers","group"=>"General","order"=>"1","default"=>"No","label"=>"Include headers on all pages","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"module"),
				"page-status"=>array("id"=>"page-status","group"=>"General","order"=>"5","default"=>"No","label"=>"Enable Magic Zoom","type"=>"array","subType"=>"select","values"=>array("Yes","No"),"scope"=>"module"),
				"magicscroll"=>array("id"=>"magicscroll","group"=>"General","order"=>"22","default"=>"No","label"=>"Scroll thumbnails","description"=>"Powered by the versatile <a href=\"http://www.magictoolbox.com/magicscroll/examples/\">Magic Scroll</a>™. Normally £29, yours is discounted to £19. <a href=\"https://www.magictoolbox.com/buy/magicscroll/\">Buy a license</a> and upload magicscroll.js to your server. <a href=\"http://www.magictoolbox.com/contact/\">Contact us</a> for help.","type"=>"array","subType"=>"select","values"=>array("Yes","No"),"scope"=>"module"),
				"zoomWidth"=>array("id"=>"zoomWidth","group"=>"Positioning and Geometry","order"=>"20","default"=>"auto","label"=>"Width of zoom window","description"=>"pixels or percentage, e.g. 400 or 100%.","type"=>"text","scope"=>"magiczoom"),
				"zoomHeight"=>array("id"=>"zoomHeight","group"=>"Positioning and Geometry","order"=>"30","default"=>"auto","label"=>"Height of zoom window","description"=>"pixels or percentage, e.g. 400 or 100%.","type"=>"text","scope"=>"magiczoom"),
				"zoomPosition"=>array("id"=>"zoomPosition","group"=>"Positioning and Geometry","order"=>"40","default"=>"right","label"=>"Position of zoom window","type"=>"array","subType"=>"radio","values"=>array("top","right","bottom","left","inner"),"scope"=>"magiczoom"),
				"zoomDistance"=>array("id"=>"zoomDistance","group"=>"Positioning and Geometry","order"=>"50","default"=>"15","label"=>"Zoom distance","description"=>"Distance between small image and zoom window (in pixels).","type"=>"num","scope"=>"magiczoom"),
				"thumb-max-width"=>array("id"=>"thumb-max-width","group"=>"Positioning and Geometry","order"=>"100","default"=>"450","label"=>"Maximum width of thumbnail (in pixels)","type"=>"num","scope"=>"module"),
				"thumb-max-height"=>array("id"=>"thumb-max-height","group"=>"Positioning and Geometry","order"=>"110","default"=>"450","label"=>"Maximum height of thumbnail (in pixels)","type"=>"num","scope"=>"module"),
				"square-images"=>array("id"=>"square-images","group"=>"Positioning and Geometry","order"=>"310","default"=>"disable","label"=>"Create square images","description"=>"The white/transparent padding will be added around the image or the image will be cropped.","type"=>"array","subType"=>"radio","values"=>array("extend","crop","disable"),"scope"=>"module"),
				"selectorTrigger"=>array("id"=>"selectorTrigger","advanced"=>"1","group"=>"Multiple images","order"=>"10","default"=>"click","label"=>"Swap trigger","description"=>"Mouse event used to switch between multiple images.","type"=>"array","subType"=>"radio","values"=>array("click","hover"),"scope"=>"magiczoom","desktop-only"=>""),
				"transitionEffect"=>array("id"=>"transitionEffect","advanced"=>"1","group"=>"Multiple images","order"=>"20","default"=>"Yes","label"=>"Transition effect on swap","description"=>"Whether to enable dissolve effect when switching between images.","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"magiczoom"),
				"template"=>array("id"=>"template","group"=>"Multiple images","order"=>"20","default"=>"bottom","label"=>"Location of thumbnails","type"=>"array","subType"=>"select","values"=>array("bottom","left","right","top"),"scope"=>"module"),
				"selectors-margin"=>array("id"=>"selectors-margin","group"=>"Multiple images","order"=>"40","default"=>"5","label"=>"Margin between selectors and main image (in pixels)","type"=>"num","scope"=>"module"),
				"variations-selectors"=>array("id"=>"variations-selectors","group"=>"Multiple images","order"=>"41","default"=>"No","label"=>"Show variation images within product images gallery","type"=>"array","subType"=>"select","values"=>array("Yes","No"),"scope"=>"module"),
				"selector-max-width"=>array("id"=>"selector-max-width","group"=>"Multiple images","order"=>"135","default"=>"200","label"=>"Maximum width of selector (in pixels)","type"=>"num","scope"=>"module"),
				"selector-max-height"=>array("id"=>"selector-max-height","group"=>"Multiple images","order"=>"137","default"=>"75","label"=>"Maximum height of selector (in pixels)","type"=>"num","scope"=>"module"),
				"lazyZoom"=>array("id"=>"lazyZoom","group"=>"Miscellaneous","order"=>"10","default"=>"No","label"=>"Lazy load of zoom image","description"=>"Whether to load large image on demand (on first activation).","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"magiczoom"),
				"rightClick"=>array("id"=>"rightClick","group"=>"Miscellaneous","order"=>"20","default"=>"No","label"=>"Right-click menu on image","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"magiczoom","desktop-only"=>""),
				"cssClass"=>array("id"=>"cssClass","advanced"=>"1","group"=>"Miscellaneous","order"=>"30","default"=>"","label"=>"Extra CSS","description"=>"Extra CSS class(es) to apply to zoom instance.","type"=>"text","scope"=>"magiczoom"),
				"create-main-image-selector"=>array("id"=>"create-main-image-selector","group"=>"Miscellaneous","order"=>"40","default"=>"Yes","label"=>"Create additional selector of main image","type"=>"array","subType"=>"select","values"=>array("Yes","No"),"scope"=>"module"),
				"link-to-product-page"=>array("id"=>"link-to-product-page","group"=>"Miscellaneous","order"=>"40","default"=>"Yes","label"=>"Link enlarged image to the product page","type"=>"array","subType"=>"select","values"=>array("Yes","No"),"scope"=>"module"),
				"show-message"=>array("id"=>"show-message","group"=>"Miscellaneous","order"=>"370","default"=>"No","label"=>"Show message under images","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"module"),
				"message"=>array("id"=>"message","group"=>"Miscellaneous","order"=>"380","default"=>"Move your mouse over image","label"=>"Enter message to appear under images","type"=>"text","scope"=>"module"),
				"imagemagick"=>array("id"=>"imagemagick","advanced"=>"1","group"=>"Miscellaneous","order"=>"550","default"=>"off","label"=>"Path to ImageMagick binaries (convert tool)","description"=>"You can set 'auto' to automatically detect ImageMagick location or 'off' to disable ImageMagick and use php GD lib instead","type"=>"text","scope"=>"module"),
				"image-quality"=>array("id"=>"image-quality","group"=>"Miscellaneous","order"=>"560","default"=>"75","label"=>"Quality of thumbnails and watermarked images (1-100)","description"=>"1 = worst quality / 100 = best quality","type"=>"num","scope"=>"module"),
				"zoomMode"=>array("id"=>"zoomMode","group"=>"Zoom mode","order"=>"10","default"=>"zoom","label"=>"Zoom mode","description"=>"How to zoom image. off - disable zoom.","type"=>"array","subType"=>"radio","values"=>array("zoom","magnifier","preview","off"),"scope"=>"magiczoom","desktop-only"=>"preview"),
				"zoomOn"=>array("id"=>"zoomOn","group"=>"Zoom mode","order"=>"20","default"=>"hover","label"=>"Zoom on","description"=>"When to activate zoom.","type"=>"array","subType"=>"radio","values"=>array("hover","click"),"scope"=>"magiczoom","desktop-only"=>""),
				"upscale"=>array("id"=>"upscale","advanced"=>"1","group"=>"Zoom mode","order"=>"30","default"=>"Yes","label"=>"Upscale image","description"=>"Whether to scale up the large image if its original size is not enough for a zoom effect.","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"magiczoom"),
				"smoothing"=>array("id"=>"smoothing","advanced"=>"1","group"=>"Zoom mode","order"=>"35","default"=>"Yes","label"=>"Smooth zoom movement","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"magiczoom"),
				"variableZoom"=>array("id"=>"variableZoom","advanced"=>"1","group"=>"Zoom mode","order"=>"40","default"=>"No","label"=>"Variable zoom","description"=>"Whether to allow changing zoom ratio with mouse wheel.","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"magiczoom","desktop-only"=>""),
				"zoomCaption"=>array("id"=>"zoomCaption","group"=>"Zoom mode","order"=>"50","default"=>"off","label"=>"Caption in zoom window","description"=>"Position of caption on zoomed image. off - disable caption on zoom window.","type"=>"array","subType"=>"radio","values"=>array("top","bottom","off"),"scope"=>"magiczoom"),
				"watermark"=>array("id"=>"watermark","group"=>"Watermark","order"=>"10","default"=>"","label"=>"Watermark image path","description"=>"Enter location of watermark image on your server. Leave field empty to disable watermark","type"=>"text","scope"=>"module"),
				"watermark-max-width"=>array("id"=>"watermark-max-width","group"=>"Watermark","order"=>"20","default"=>"30%","label"=>"Maximum width of watermark image","description"=>"pixels = fixed size (e.g. 50) / percent = relative for image size (e.g. 50%)","type"=>"text","scope"=>"module"),
				"watermark-max-height"=>array("id"=>"watermark-max-height","group"=>"Watermark","order"=>"21","default"=>"30%","label"=>"Maximum height of watermark image","description"=>"pixels = fixed size (e.g. 50) / percent = relative for image size (e.g. 50%)","type"=>"text","scope"=>"module"),
				"watermark-opacity"=>array("id"=>"watermark-opacity","group"=>"Watermark","order"=>"40","default"=>"50","label"=>"Watermark image opacity (1-100)","description"=>"0 = transparent, 100 = solid color","type"=>"num","scope"=>"module"),
				"watermark-position"=>array("id"=>"watermark-position","group"=>"Watermark","order"=>"50","default"=>"center","label"=>"Watermark position","description"=>"Watermark size settings will be ignored when watermark position is set to 'stretch'","type"=>"array","subType"=>"select","values"=>array("top","right","bottom","left","top-left","bottom-left","top-right","bottom-right","center","stretch"),"scope"=>"module"),
				"watermark-offset-x"=>array("id"=>"watermark-offset-x","advanced"=>"1","group"=>"Watermark","order"=>"60","default"=>"0","label"=>"Watermark horizontal offset","description"=>"Offset from left and/or right image borders. Pixels = fixed size (e.g. 20) / percent = relative for image size (e.g. 20%). Offset will disable if 'watermark position' set to 'center'","type"=>"text","scope"=>"module"),
				"watermark-offset-y"=>array("id"=>"watermark-offset-y","advanced"=>"1","group"=>"Watermark","order"=>"70","default"=>"0","label"=>"Watermark vertical offset","description"=>"Offset from top and/or bottom image borders. Pixels = fixed size (e.g. 20) / percent = relative for image size (e.g. 20%). Offset will disable if 'watermark position' set to 'center'","type"=>"text","scope"=>"module"),
				"hint"=>array("id"=>"hint","group"=>"Hint","order"=>"10","default"=>"once","label"=>"Display hint to suggest image is zoomable","description"=>"How to show hint. off - disable hint.","type"=>"array","subType"=>"radio","values"=>array("once","always","off"),"scope"=>"magiczoom"),
				"textHoverZoomHint"=>array("id"=>"textHoverZoomHint","advanced"=>"1","group"=>"Hint","order"=>"20","default"=>"Hover to zoom","label"=>"Hint to suggest image is zoomable (on hover)","description"=>"Hint that shows when zoom mode is enabled, but inactive, and zoom activates on hover (Zoom on: hover).","type"=>"text","scope"=>"magiczoom"),
				"textClickZoomHint"=>array("id"=>"textClickZoomHint","advanced"=>"1","group"=>"Hint","order"=>"21","default"=>"Click to zoom","label"=>"Hint to suggest image is zoomable (on click)","description"=>"Hint that shows when zoom mode is enabled, but inactive, and zoom activates on click (Zoom on: click).","type"=>"text","scope"=>"magiczoom"),
				"zoomModeForMobile"=>array("id"=>"zoomModeForMobile","group"=>"Mobile","order"=>"10","default"=>"off","label"=>"Zoom mode","description"=>"How to zoom image. off - disable zoom.","type"=>"array","subType"=>"radio","values"=>array("zoom","magnifier","off"),"scope"=>"magiczoom-mobile"),
				"textHoverZoomHintForMobile"=>array("id"=>"textHoverZoomHintForMobile","advanced"=>"1","group"=>"Mobile","order"=>"20","default"=>"Touch to zoom","label"=>"Hint to suggest image is zoomable (on hover)","description"=>"Hint that shows when zoom mode is enabled, but inactive, and zoom activates on hover (Zoom on: hover).","type"=>"text","scope"=>"magiczoom-mobile"),
				"textClickZoomHintForMobile"=>array("id"=>"textClickZoomHintForMobile","advanced"=>"1","group"=>"Mobile","order"=>"21","default"=>"Double tap or pinch to zoom","label"=>"Hint to suggest image is zoomable (on click)","description"=>"Hint that shows when zoom mode is enabled, but inactive, and zoom activates on click (Zoom on: click).","type"=>"text","scope"=>"magiczoom-mobile"),
				"width"=>array("id"=>"width","group"=>"Scroll","order"=>"10","default"=>"auto","label"=>"Scroll width","description"=>"auto | pixels | percetage","type"=>"text","scope"=>"magicscroll"),
				"height"=>array("id"=>"height","group"=>"Scroll","order"=>"20","default"=>"auto","label"=>"Scroll height","description"=>"auto | pixels | percetage","type"=>"text","scope"=>"magicscroll"),
				"orientation"=>array("id"=>"orientation","group"=>"Scroll","order"=>"30","default"=>"horizontal","label"=>"Orientation of scroll","type"=>"array","subType"=>"radio","values"=>array("horizontal","vertical"),"scope"=>"magicscroll"),
				"mode"=>array("id"=>"mode","group"=>"Scroll","order"=>"40","default"=>"scroll","label"=>"Scroll mode","type"=>"array","subType"=>"radio","values"=>array("scroll","animation","carousel","cover-flow"),"scope"=>"magicscroll"),
				"items"=>array("id"=>"items","group"=>"Scroll","order"=>"50","default"=>"3","label"=>"Items to show","description"=>"auto | fit | integer | array","type"=>"text","scope"=>"magicscroll"),
				"speed"=>array("id"=>"speed","group"=>"Scroll","order"=>"60","default"=>"600","label"=>"Scroll speed (in milliseconds)","description"=>"e.g. 5000 = 5 seconds","type"=>"num","scope"=>"magicscroll"),
				"autoplay"=>array("id"=>"autoplay","group"=>"Scroll","order"=>"70","default"=>"0","label"=>"Autoplay speed (in milliseconds)","description"=>"e.g. 0 = disable autoplay; 600 = 0.6 seconds","type"=>"num","scope"=>"magicscroll"),
				"loop"=>array("id"=>"loop","group"=>"Scroll","order"=>"80","advanced"=>"1","default"=>"infinite","label"=>"Continue scroll after the last(first) image","description"=>"infinite - scroll in loop; rewind - rewind to the first image; off - stop on the last image","type"=>"array","subType"=>"radio","values"=>array("infinite","rewind","off"),"scope"=>"magicscroll"),
				"step"=>array("id"=>"step","group"=>"Scroll","order"=>"90","default"=>"auto","label"=>"Number of items to scroll","description"=>"auto | integer","type"=>"text","scope"=>"magicscroll"),
				"arrows"=>array("id"=>"arrows","group"=>"Scroll","order"=>"100","default"=>"inside","label"=>"Prev/Next arrows","type"=>"array","subType"=>"radio","values"=>array("inside","outside","off"),"scope"=>"magicscroll"),
				"pagination"=>array("id"=>"pagination","group"=>"Scroll","order"=>"110","advanced"=>"1","default"=>"No","label"=>"Show pagination (bullets)","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"magicscroll"),
				"easing"=>array("id"=>"easing","group"=>"Scroll","order"=>"120","advanced"=>"1","default"=>"cubic-bezier(.8, 0, .5, 1)","label"=>"CSS3 Animation Easing","description"=>"see cubic-bezier.com","type"=>"text","scope"=>"magicscroll"),
				"scrollOnWheel"=>array("id"=>"scrollOnWheel","group"=>"Scroll","order"=>"130","advanced"=>"1","default"=>"auto","label"=>"Scroll On Wheel mode","description"=>"auto - automatically turn off scrolling on mouse wheel in the 'scroll' and 'animation' modes, and enable it in 'carousel' and 'cover-flow' modes","type"=>"array","subType"=>"radio","values"=>array("auto","turn on","turn off"),"scope"=>"magicscroll"),
				"lazy-load"=>array("id"=>"lazy-load","group"=>"Scroll","order"=>"140","advanced"=>"1","default"=>"No","label"=>"Lazy load","description"=>"Delay image loading. Images outside of view will be loaded on demand.","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"magicscroll"),
				"scroll-extra-styles"=>array("id"=>"scroll-extra-styles","group"=>"Scroll","order"=>"150","advanced"=>"1","default"=>"","label"=>"Scroll extra styles","description"=>"mcs-rounded | mcs-shadows | bg-arrows | mcs-border","type"=>"text","scope"=>"module"),
				"show-image-title"=>array("id"=>"show-image-title","group"=>"Scroll","order"=>"160","default"=>"No","label"=>"Show image title","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"module"),
				"use-wordpress-images"=>array("id"=>"use-wordpress-images","group"=>"Use Wordpress images","order"=>"6","default"=>"No","label"=>"Use Wordpress images","type"=>"array","subType"=>"radio","values"=>array("Yes","No"),"scope"=>"module"),
				"single-wordpress-image"=>array("id"=>"single-wordpress-image","group"=>"Use Wordpress images","order"=>"6","default"=>"full","label"=>"Size for single product image","type"=>"array","subType"=>"select","values"=>array("full"),"scope"=>"module"),
				"thumbnails-wordpress-image"=>array("id"=>"thumbnails-wordpress-image","group"=>"Use Wordpress images","order"=>"6","default"=>"shop_thumbnail","label"=>"Size for product thumbnails","type"=>"array","subType"=>"select","values"=>array("shop_thumbnail"),"scope"=>"module"),
				"category-wordpress-image"=>array("id"=>"category-wordpress-image","group"=>"Use Wordpress images","order"=>"6","default"=>"catalog_thumbnail","label"=>"Size for catalog thumbnails","type"=>"array","subType"=>"select","values"=>array("catalog_thumbnail"),"scope"=>"module")
			);
            $this->params->appendParams($params);
        }
    }

}

?>
