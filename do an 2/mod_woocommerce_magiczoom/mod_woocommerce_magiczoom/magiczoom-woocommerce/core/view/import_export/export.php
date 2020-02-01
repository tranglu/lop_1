<?php
    function WooCommerce_MagicZoom_wp_export($set="all", $core_sett, $constructor_sett) {
        $tool = 'woocommerce_magiczoom';
        $core_settings = ('all' == $set || 'core_param' == $set);
        $constructor_settings = ('all' == $set || 'constructor_param' == $set);

        if ($core_settings) {
            $tmp = false;
            foreach ($core_sett as $profile => $name) {
                if (!$tmp) {
                    foreach ($name as $key => $value) {
                        if (isset($value['value'])) {
                            $tmp = true;
                            break;
                        }
                    }
                }
            }
            $core_settings = $tmp;
        }

        if ($constructor_settings && 0 == count($constructor_sett)) {
            $constructor_settings = false;
        }

        $license = magictoolbox_WooCommerce_MagicZoom_get_data_from_db();
        if ($license) {
            $license = $license->license;
        } else {
            $license = 'trial';
        }

        $scroll_license = false;
        $scroll_license = magictoolbox_WooCommerce_MagicZoom_get_data_from_db('WooCommerce_MagicZoom_magicscroll');
        if ($scroll_license) {
            $scroll_license = $scroll_license->license;
        } else {
            $scroll_license = 'trial';
        }

        $sitename = sanitize_key( get_bloginfo( 'name' ) );
        if ( ! empty($sitename) ) $sitename .= '.';
        $filename = $sitename . 'woocommerce.magiczoom.' . date( 'Y-m-d' ) . '.xml';
        // header("Cache-Control: private, post-check=1, pre-check=1");
        // header("Cache-Control: private");
        header( 'Content-Description: File Transfer' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );
        // header('Content-Type: text/xml; charset=UTF-8');
        // header("Expires: 0");
        // header("Pragma: no-cache");
        // header( 'Content-Type: text/xml', true );
        ob_end_clean();
echo '<?xml version="1.0" encoding="UTF-8" ?>'."\n";
?>
<params>
    <tool><?php echo $tool; ?></tool>
    <license><?php echo $license; ?></license>
<?php if ($scroll_license) { ?>
    <scrolllicense><?php echo $scroll_license; ?></scrolllicense>
<?php }
    if ($core_settings) {
?>
    <core>
    <?php foreach($core_sett as $profile => $name) { ?>
        <<?php echo $profile; ?>>
        <?php foreach($name as $key => $value) {
            if (isset($value['value'])) {
            // if (isset($value['default'])) {
        ?>
        <<?php echo $key; ?>><?php echo $value['value']; ?></<?php echo $key; ?>>
        <?php }} ?>
        </<?php echo $profile; ?>>
    <?php } ?>
</core>
<?php }

if ($constructor_settings) { ?>
    <constructor>
    <?php foreach ($constructor_sett as $key => $value) {?>
    <<?php echo $tool; ?>>
    <?php foreach ($value as $key2 => $value2) {
    ?>
        <<?php echo $key2; ?>><?php echo ('options' != $key2 && 'additional_options' != $key2 && 'html' != $key2 && 'saved_data' != $key2) ? $value2 : '<![CDATA['.$value2.']]>'; ?></<?php echo $key2; ?>>
    <?php } ?>
    </<?php echo $tool; ?>>
    <?php } ?>
</constructor>
<?php } ?>
</params>
<?php
    };
?>
