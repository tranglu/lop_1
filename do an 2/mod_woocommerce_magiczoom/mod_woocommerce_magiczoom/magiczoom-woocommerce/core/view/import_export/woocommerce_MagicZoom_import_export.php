<?php
    $corePath = preg_replace('/https?:\/\/[^\/]*/is', '', get_option("siteurl"));
    $tmp = str_replace('/view/import_export', '', dirname(__FILE__));
    $corePath .= '/wp-content/'.preg_replace('/^.*?\/(plugins\/.*?)$/is', '$1', str_replace("\\","/", $tmp));
?>

<div class="import-export-container">
    <h1>Magic Zoom settings backup / restore</h1>
    <p>Save your plugin settings so you can import them into another WordPress website e.g. a development or staging site. Or if you'll be removing/reinstalling the Magic Zoom plugin.</p>
    <br/>
    <span class="title">Backup settings</span>
    <hr>
    <div>
        <button id="export-btn" class="button button-primary" title="Download file">Backup settings</button>
    </div>
    <br/><br/>

    <span class="title">Restore settings</span>
    <hr>
    <div class="import-container">
        <p>
            <label>
                <input id="import-file" type="file" style="width: 60%;" accept="text/xml">
            </label>
        </p>
        <div>
            <button id="import-btn" class="button button-primary" title="Get settings">
                <span>Restore settings</span>
            </button>
            <img id="import-msg-ok" src="<?php echo $corePath; ?>/admin_graphics/yes.gif" alt="ok">
            <img id="import-msg-no" src="<?php echo $corePath; ?>/admin_graphics/no.gif" alt="no">
        </div>
    </div>
</div>
