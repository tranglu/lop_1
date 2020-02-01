(function ($) {
    'use strict';

    if (!$) {
        throw new Error('jQuery is not found!');
    }

    function tabs () {
        $("#tabs").tabs({
            activate: function(event, ui) {
                $('.nav-tab').removeClass('nav-tab-active');
                $(ui.newTab).children().addClass('nav-tab-active');
            }
        });
    }

    function scrollableButton () {
        var headingTop = $('#set-main-settings').position().top - $(window).height() + 120;

        if(!$('#set-main-settings').hasClass('fixed')) {
            $('#set-main-settings').addClass('fixed');
        }

        $(window).scroll(function() {
            if(headingTop <= $(window).scrollTop()) {
                if ($('#set-main-settings').hasClass('fixed')) {
                    $('#set-main-settings').removeClass('fixed');
                }
            } else { 
                if (!$('#set-main-settings').hasClass('fixed')) {
                    $('#set-main-settings').addClass('fixed');
                }
            }
        });
    }


    $(document).ready(function () {
        tabs();
        scrollableButton();
    });
})(window.jQuery);