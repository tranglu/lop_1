(function ($) {
    'use strict';
    var importButton,
        importFile,
        importOk,
        importNo,
        exportButton,
        msgTimer, file;

    function showMessage(node) {
        clearTimeout(msgTimer);
        importOk.css('display', 'none');
        importNo.css('display', 'none');
        node.css('display', 'inline');
        msgTimer = setTimeout(function() {
            node.css('display', 'none');
        }, 2000);
    }

    function importClick(e) {
        var xhr, formData;
        if (!file) { showMessage(importNo); return false; }

        importButton.addClass('loading');
        importButton.prop('disabled', true);

        formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'WooCommerce_MagicZoom_import');
        formData.append('nonce', magictoolbox_WooCommerce_MagicZoom_admin_modal_object.nonce);

        xhr = new XMLHttpRequest();

        // xhr.upload.onprogress = function(event) {
        //     console.log(event.loaded + ' / ' + event.total);
        // };

        xhr.onload = xhr.onerror = function(e) {
            importButton.removeClass('loading');
            importButton.prop('disabled', false);
            if (this.status == 200) {
                showMessage(importOk);
                // setTimeout(function() {
                //     location.href = location.href.replace('&reset_settings=true', '');
                  //location.reload();
                // }, 700);
            } else {
                showMessage(importNo);
            }
        };

        xhr.open("POST", magictoolbox_WooCommerce_MagicZoom_admin_modal_object.ajax, true);
        xhr.send(formData);

        return false;
    }

    function importChange(e) {
        file = this.files[0];
    }

    function exportClick(e) {
        var f, i, v = 'core_param';
        $('.export-radio-group').find('input').each(function(index) {
            if ($(this).attr('checked')) {
                v = $(this).attr('value');
            }
        });

        f = $('<form>');
        f.attr('id', 'download_form');
        f.attr('method', 'post');
        f.attr('action', magictoolbox_WooCommerce_MagicZoom_admin_modal_object.ajax);

        i = $('<input>');
        i.attr('type', 'button');
        i.attr('name', 'export');
        i.attr('value', 'export');
        f.append(i[0]);

        i = $('<input>');
        i.attr('type', 'hidden');
        i.attr('name', 'action');
        i.attr('value', 'WooCommerce_MagicZoom_export');
        f.append(i[0]);

        i = $('<input>');
        i.attr('type', 'hidden');
        i.attr('name', 'value');
        i.attr('value', v);
        f.append(i[0]);

        i = $('<input>');
        i.attr('type', 'hidden');
        i.attr('name', 'nonce');
        i.attr('value', magictoolbox_WooCommerce_MagicZoom_admin_modal_object.nonce);
        f.append(i[0]);

        $(document.body).append(f);
        f.submit();
        f.remove();

        return false;
    }

    $(document).ready(function() {
        importFile = $('#import-file');
        importButton = $('#import-btn');
        importOk = $('#import-msg-ok');
        importNo = $('#import-msg-no');
        exportButton = $('#export-btn');

        importButton.on('click', importClick);
        importFile.on('change', importChange);

        exportButton.on('click', exportClick);
    });
})(jQuery);
