(function($) {
    if (!$) throw 'MagicToolboxGallery: jQuery is not detected!';
    if ($.fn.MagicToolboxGallery) { return; }

    var GALLERY_CLASS = 'MagicToolboxGallery';
    var MAIN_CONTAINER = 'MagicToolboxContainer';
    var SLIDES_CONTAINER = 'MagicToolboxMainContainer';
    var SELECTORS_CONTAINER = 'MagicToolboxSelectorsContainer';
    var ACTIVE_CLASS = 'mt-gallery-active';
    var PRIVATE_DATA_OPTIONS = 'data-mt-gallery-options';

    var OPTIONS = { startSlide: 0 };

    function detectSlide(index, node) {
        var firstChild = $($(node)[0].firstChild),
            result = {
                index: index,
                id: firstChild.attr('id')
            };

        if (firstChild.hasClass('MagicZoom')) { // TODO
            result.type = 'MagicZoom';
        } else if (firstChild.hasClass('MagicZoomPlus')) {
            result.type = 'MagicZoomPlus';
        }

        return result;
    }

    function getSelectorIndex(node, arr) {
        var result = 0;

        for (var i = 0; i < arr.length; i++) {
            if (arr[i][0] === node) {
                result = i;
                break;
            }
        }

        return result;
    }

    var SlideInstance = function (node, options) {
        var self = this;
        this.node = $(node);
        this.node.addClass('mtb-gallery-slide');

        this.tool = $($(this.node)[0].firstChild);

        this.options = $.extend({
            index: 0,
            id: null,
            type: null // null || MagicThumb || MagicZoom || MagicZoomPlus
        }, options);

        this.thumbUrl = { thumbnail: { url: null } };
        this.selectors = $([]);
        this.isActive = false;
        this.activeSelector = null;
        this.selectorState = 0; // 0 - not loded, 1 - loading, 2 - loaded

        if (!this.options.type) {
            if (this.tool[0].tagName.toLowerCase() === 'iframe') {
                this.node.addClass('mt-gallery-video');
            }
        }

        this.mouseDownHandler = function (e) {
            var element = $(this);
            if (!self.options.type) {
                e.stopPropagation();
                e.preventDefault();
            }

            setTimeout(function() {
                $(self).trigger('selectorAction', {
                    slideIndex: self.options.index,
                    selectorIndex: element.data('galleryIndex')
                });
            }, 90);
        };
    };

    SlideInstance.prototype = $.extend(SlideInstance.prototype, {
        stopVideo: function() {
            if (!this.options.type) {
                var src = this.tool.attr('src');
                this.tool.attr('src', '');
                this.tool.attr('src', src);
            }
        },

        loadSelector: function (node) {
            var self = this, img = null;
            if (!this.options.type && !this.selectorState) {
                try {
                    img = $(node).find('img')[0];
                } catch (e) {}

                if (img) {
                    this.selectorState = 2;
                    this.thumbUrl.thumbnail.url = $(img).attr('src');
                } else {
                    this.selectorState = 1;
                    this.tool.MagicToolboxGalleryGetVideoImage(function(data) {
                        if (data) { self.thumbUrl = data }
                        self.selectorState = 2;
                        if (self.selectors.length) {
                            self.selectors.each(function(index, selector) {
                                self.setSelectorImage(selector);
                            });
                        }
                    }, true);
                }
            }
        },

        setSelectorImage: function (node) {
            var img, attr, thumbName = 'thumbnail';
            node = $(node);
            if (this.thumbUrl.thumbnail.url && !$(node).find('img').length) {
                img = $('<img>');

                attr = node.attr('data-max-width');
                if (typeof attr !== typeof undefined && attr !== false) {
                    if (this.thumbUrl.medium) {
                        if (parseInt(attr) > this.thumbUrl.thumbnail.width) { thumbName = 'medium'; }
                    }
                    img.css('max-width', attr + 'px');
                }

                attr = node.attr('data-max-height');
                if (typeof attr !== typeof undefined && attr !== false) {
                    img.css('max-height', attr + 'px');
                }

                img.attr('src', this.thumbUrl[thumbName].url);
            }

            if (img) { node.append(img); }
        },

        activateSlide: function (selectorIndex) {
            if (!this.isActive) {
                this.isActive = true;
                this.node.addClass(ACTIVE_CLASS);
            }

            if (!this.activeSelector || !this.isSelectorActive(selectorIndex)) {
                this.deactivateSlector();
                if (selectorIndex !== null) {
                    this.activateSlector(this.selectors[selectorIndex]);
                }
            }
        },

        deactivateSlide: function () {
            if (this.isActive) {
                this.isActive = false;
                this.node.removeClass(ACTIVE_CLASS);
                this.stopVideo();
                this.deactivateSlector();
            }
        },

        activateSlector: function (selector) {
            if (!this.activeSelector) {
                $(selector).addClass(ACTIVE_CLASS);
                this.activeSelector = selector;
            }
        },

        deactivateSlector: function () {
            if (this.activeSelector) {
                this.activeSelector.removeClass(ACTIVE_CLASS);
                this.activeSelector = null;
            }
        },

        isSelectorActive: function (selectorIndex) {
            var result = false;

            if (selectorIndex !== null && this.activeSelector) {
                result = (this.activeSelector.data('galleryIndex') === selectorIndex);
            }

            return result;
        },

        isSelectorFromHere: function (node) {
            var result = false, id;

            if (this.options.type) {
                id = $(node).attr('data-zoom-id');
            } else {
                id = $(node).attr('data-video-id') || $(node).attr('id');
            }

            if (id === this.options.id) {
                result = true;
            }

            return result;
        },

        addSelector: function (node) {
            var self = this;
            node = $(node);

            node.data('galleryIndex', this.selectors.length);

            this.loadSelector(node);
            this.setSelectorImage(node);

            node.on('click', function (e) {
                e.stopPropagation();
                e.preventDefault();
            });

            node[0].addEventListener('mousedown', this.mouseDownHandler, true);

            this.selectors.push(node);
        },

        detectStartSelector: function () {
            var i, result = null, l = this.selectors.length;

            if (l < 2 || !this.options.type) {
                result = 0;
            } else {
                for (i = 0; i < l; i++) {
                    if (this.selectors[i].attr('href') === this.tool.attr('href')) {
                        result = i;
                        break;
                    }
                }
            }

            return result;
        },

        destroy: function () {
            this.deactivateSlide();

            $(this.selectors).each(function(index, selector) {
                $(selector).data('galleryIndex', null);
                $(selector).off('click');
                $(selector)[0].removeEventListener('mousedown', this.mouseDownHandler, true);
            });
            this.selectors = [];
        }
    });

    var Instance = function(node, options) {
        var self = this, tmp;

        this.options = $.extend({}, OPTIONS, options, $(node).attr(PRIVATE_DATA_OPTIONS) || {});
        this.slideNodes = $($(node).find('.' + SLIDES_CONTAINER)[0]).children();
        this.selectorsNodes = $(node).find('.' + SELECTORS_CONTAINER + ' div:first-child').children();

        // TODO
        // tmp = $(node).find('.' + SELECTORS_CONTAINER + ' div:first-child');
        // if (tmp.hasClass('MagicScroll')) {
        //     this.selectorsNodes = tmp.find('.mcs-item');
        // } else {
        //     this.selectorsNodes = tmp.children();
        // }

        if (!this.slideNodes.length) {
            this.slideNodes = $(node).children().not('.' + SELECTORS_CONTAINER);
        }

        this.activeSlide = null;

        this.slideInstances = this.slideNodes.map(function(index, node) {
            var slide = new SlideInstance(node, detectSlide(index, node));

            $(slide).on('selectorAction', function (e, data) {
                self.activateSlide(data.slideIndex, data.selectorIndex);
            });

            return slide;
        });

        this.selectorsNodes.each(function(index, selector) {
            for (var i = 0; i < self.slideInstances.length; i++) {
                if (self.slideInstances[i].isSelectorFromHere(selector)) {
                    self.slideInstances[i].addSelector(selector);
                    break;
                }
            }
        });

        this.activateSlide(this.options.startSlide, this.slideInstances[this.options.startSlide].detectStartSelector());
    };

    Instance.prototype = $.extend(Instance.prototype, {
        activateSlide: function (slideIndex, selectorIndex) {
            var slide;

            if ($.isNumeric(slideIndex)) {
                slideIndex = parseInt(slideIndex);
                if (slideIndex > -1 && slideIndex < this.slideInstances.length) {
                    slide = this.slideInstances[slideIndex];
                    if ($.isNumeric(selectorIndex)) {
                        selectorIndex = parseInt(selectorIndex);
                    } else {
                        selectorIndex = null;
                    }

                    if (this.activeSlide !== slide || !this.activeSlide.isSelectorActive(selectorIndex)) {
                        if (this.activeSlide) {
                            this.activeSlide.deactivateSlide();
                        }
                        slide.activateSlide(selectorIndex);
                        this.activeSlide = slide;
                    }
                }
            }
        },

        destroy: function () {
            this.slideInstances.each(function(index, slide) {
                $(slide).off('selectorAction');
                slide.destroy();
            });
        }
    });

    $.fn.MagicToolboxGallery = function (options) {
        if (!options) { options = {}; }

        return this.each(function() {
            if (!$(this).data(GALLERY_CLASS)) {
                $(this).data(GALLERY_CLASS, new Instance(this, options));
            }

            return this;
        });
    };

    $.fn.MagicToolboxGalleryDestroy = function () {
        return this.each(function() {
            if ($(this).data(GALLERY_CLASS)) {
                $(this).data(GALLERY_CLASS).destroy();
                $(this).data(GALLERY_CLASS, null);
            }
            return this;
        });
    };

    $.fn.MagicToolboxGalleryGetVideoImage = function (callback, getAll) {
        var sources = {};

        var youtubeImgs = {
            'thumb1': '1.jpg',                // 120x90
            'thumb2': '2.jpg',                // 120x90
            'thumb3': '3.jpg',                // 120x90
            'def0': '0.jpg',                  // 480x360
            'def1': 'default.jpg',            // 120x90
            'middleQuality': 'mqdefault.jpg', // 320x180
            'highQuality': 'hqdefault.jpg',   // 480x360
            'maxSize': 'maxresdefault.jpg'    // 1920x1080
        };

        function getType(str) {
            var result = null, format;

            if (/youtube/.test(str) || /youtu\.be/.test(str)) {
                result = 'youtube';
            } else if (/vimeo/.test(str)){
                result = 'vimeo';
            }

            return result;
        }

        function getYouTobeId(url) {
            var result = null;

            url = url.replace(/(>|<)/gi,'').split(/(vi\/|v=|\/v\/|youtu\.be\/|\/embed\/)/);
            if(url[2] !== undefined) {
                url = url[2].split(/[^0-9a-z_\-]/i);
                if (url.length && url[0]) {
                    result = url[0];
                }
            }

            return result;
        }

        function getVimeoId(url) {
            var result = null;

            url = url.match(/(?:https?:\/\/)?(?:www.)?(?:player.)?vimeo.com\/(?:[a-z]*\/)*([0-9]{6,11})[?]?.*/)[1];
            if (url) { result = url; }

            return result;
        }

        function getSrc (url, cb) {
            var type, thumbUrl = null, id, xhttp;

            if (url) {
                if (sources[url]) {
                    if (getAll) {
                        if (sources[url].all) {
                            cb(sources[url].all);
                            return;
                        }
                    } else {
                        if (sources[url].url) {
                            cb(sources[url].url);
                            return;
                        } else if (sources[url].all) {
                            cb(sources[url].all.thumbnail.url);
                            return;
                        }
                    }
                }
                type = getType(url);
                if (type === 'youtube') {
                    id = getYouTobeId(url);
                    if (id) {
                        if (getAll) {
                            thumbUrl = {
                                thumbnail: {
                                    url: 'https://img.youtube.com/vi/' + id + '/' + youtubeImgs.def1,
                                    width: 120,
                                    height: 90
                                },
                                medium: {
                                    url: 'https://img.youtube.com/vi/' + id + '/' + youtubeImgs.def0,
                                    width: 480,
                                    height: 360
                                }
                            };
                        } else {
                            // thumbUrl = 'https://img.youtube.com/vi/' + id + '/' + youtubeImgs.thumb1;
                            thumbUrl = 'https://img.youtube.com/vi/' + id + '/' + youtubeImgs.def1;
                        }
                    }
                    if (!sources[url]) { sources[url] = {}; }

                    if (getAll) {
                        sources[url].all = thumbUrl;
                    } else {
                        sources[url].url = thumbUrl;
                    }
                    
                    cb(thumbUrl);
                } else {
                    id = getVimeoId(url);
                    if (id) {
                        thumbUrl = 'https://vimeo.com/api/v2/video/' + id + '.json';

                        xhttp = new XMLHttpRequest();
                        xhttp.open("GET", thumbUrl, true);

                        xhttp.onreadystatechange = function() {
                            var imgUrl = null, obj;
                            if (xhttp.readyState === 4) {
                                if (xhttp.status === 200) {
                                    try  {
                                        if (getAll) {
                                            obj = JSON.parse(xhttp.responseText)[0];
                                            imgUrl = {
                                                thumbnail: {
                                                    url: obj.thumbnail_small,
                                                    width: 100,
                                                    height: 75
                                                },
                                                medium: {
                                                    url: obj.thumbnail_medium,
                                                    width: 200,
                                                    height: 150
                                                }
                                            };
                                        } else {
                                            imgUrl = (JSON.parse(xhttp.responseText)[0]).thumbnail_small;
                                        }
                                    } catch (e) {}

                                    if (imgUrl) {
                                        if (!sources[url]) { sources[url] = {}; }

                                        if (getAll) {
                                            sources[url].all = imgUrl;
                                        } else {
                                            sources[url].url = imgUrl;
                                        }    
                                    }

                                    cb(imgUrl);
                                } else {
                                    cb(imgUrl);
                                }
                            }
                        };

                        xhttp.send(true);
                    } else {
                        cb(thumbUrl);
                    }
                }
            } else {
                cb(thumbUrl);
            }
        }

        return this.each(function() {
            if (this.tagName.toLowerCase() === 'iframe') {
                getSrc($(this).attr('src'), function(thumbUrl) { callback(thumbUrl); });
            } else {
                callback(null);
            }
            return this;
        });
    };

    $(document).ready(function() { $('.MagicToolboxContainer.' + GALLERY_CLASS).MagicToolboxGallery(); });
})(jQuery);