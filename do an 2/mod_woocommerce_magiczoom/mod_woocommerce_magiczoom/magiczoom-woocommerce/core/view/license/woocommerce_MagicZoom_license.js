(function ($) {
    'use strict';
    var timer;

    function CheckLicense(node) {
        var license,
            container = $(node),
            extraParam = container.attr('data-papam'),
            toolLower = 'magiczoom',
            toolName = 'Magic Zoom',
            key = container.find('input.license-key'),
            kinc = container.find('div.key-is-not-correct'),
            lf = container.find('div.license-failed'),
            wpe = container.find('div.wordpress-error'),
            // hm = container.find('div.happy-message'),
            pb = container.find('div.problem'),
            btn = container.find('button.register-btn'),
            loader = container.find('div.authentication');

        if (extraParam) {
            toolLower = extraParam;
            toolName = extraParam.replace(/(magic)(.*)/, function(str, _1, _2) {
                return _1.charAt(0).toUpperCase() + _1.slice(1) + ' ' + _2.charAt(0).toUpperCase() + _2.slice(1);
            });
        }

        function click(e) {
            var self = $(this);
            e.preventDefault();
            e.stopPropagation();

            btn.prop('disabled', true);

            function showBlock(block) {
                var i, bs = [loader, kinc, lf, wpe, pb];

                clearTimeout(timer);

                for(i = 0; i < bs.length; i++) {
                    if (bs[i] !== block) {
                        bs[i].css('display', 'none');
                    } else {
                        if (loader === block) {
                            timer = setTimeout(function() {
                                clearTimeout(timer);
                                loader.css('display', 'block');
                            }, 100);
                        } else {
                            bs[i].css('display', 'block');
                        }
                    }
                }
            }

            function checkKey(str) {
                var s = $.trim(str);
                if ('' === s) {
                    return false;
                } else {
                    return str;
                }
            }

            showBlock(loader);

            license = checkKey(key.attr('value'));

            if (!license) {
                // The key is not correct.
                showBlock(kinc);
                return false;
            }

            $.post(magictoolbox_WooCommerce_MagicZoom_admin_modal_object.ajax, {
                action: "magictoolbox_WooCommerce_MagicZoom_set_license",
                nonce: magictoolbox_WooCommerce_MagicZoom_admin_modal_object.nonce,
                key: license,
                param: extraParam ? extraParam : 'null'
            }).success(function(_data) {
                var p, str = '', html = '';
                if ('string' === jQuery.type(_data)) {
                    _data = JSON.parse(_data);
                }
                if (_data.error) {
                    if ('limit' === _data.error) {
                        // limit
                        showBlock(pb);
                    } else if ('license failed' === _data.error) {
                        // license failed
                        showBlock(lf);
                    } else {
                        // Worpress error.
                        showBlock(wpe);
                    }
                } else {
                    clearTimeout(timer);
                    p = container.parent();
                    container.remove();

                    if (extraParam) {
                        str = ' Magic Scroll';
                        html = '<br/><hr style="max-width: 50%; margin-left: 0;">';
                    }
                    html += '<p><span>License' + str + ' key: ' + license + '</span></p>';
                    p.html(html);

                    if ($(self).hasClass('main-b')) {
                        $('.magictoolbox-trial-box').hide();
                        $('.magictoolbox-trial-text').hide();
                    }
                }
                btn.prop('disabled', false);
            }).error(function() {
                // Worpress error.
                showBlock(wpe);
                btn.prop('disabled', false);
            });

            return false;
        }
        $(key).on('keydown', function(e) { btn.prop('disabled', false); });
        $(key).on('keypress', function(e) { if (13 === e.keyCode) { return click(e); } });
        $(btn).on('click', click);
    };

    $(document).ready(function() {
        $.each($('.CheckLicense'), function(index, value) {
            new CheckLicense(value);
        });
    });
})(jQuery);
