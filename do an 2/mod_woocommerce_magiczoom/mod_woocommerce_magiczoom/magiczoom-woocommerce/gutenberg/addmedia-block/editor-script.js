(function(wpElement, wpBlocks, wpComponents, wpEditor, wpi18n){
    let __ = wpi18n.__;
    let el = wpElement.createElement;
    let registerBlockType = wpBlocks.registerBlockType;
    let richText = wpEditor.RichText;
    let InspectorControls = wpEditor.InspectorControls;
    let button = wp.components.Button;
    let PanelBody = wpComponents.PanelBody;
    let SelectControl = wpComponents.SelectControl;
    let ToggleControl = wpComponents.ToggleControl;
    let TextControl = wpComponents.TextControl;
    
  
    const { apiFetch } = wp;
    const {
        registerStore,
        withSelect,
    } = wp.data;

    
    const actions = {
        
        setShortCodes( shortcodes ) {
                return {
                        type: 'SET_SHORTCODES',
                        shortcodes,
                };
        },
        
        receiveShortCodes( path ) {
                return {
                        type: 'RECEIVE_SHORTCODES',
                        path,
                };
        },
};

    const store = registerStore( 'woocommerce-magiczoom/addmedia-block', {
        reducer( state = { shortcodes: {} }, action ) {

                switch ( action.type ) {
                        case 'SET_SHORTCODES':
                                return {
                                        ...state,
                                        shortcodes: action.shortcodes,
                                };
                }

                return state;
        },

        actions,

        selectors: {
                receiveShortCodes( state ) {
                        const { shortcodes } = state;
                        return shortcodes;
                },
        },

        controls: {
                RECEIVE_SHORTCODES( action ) {
                        return apiFetch( { path: action.path } );
                },
        },

        resolvers: {
                * receiveShortCodes( state ) {
                        const shortcodes = yield actions.receiveShortCodes( '/magiczoom/get-shortcodes' );
                        return actions.setShortCodes( shortcodes );
                },
        },
} );

    function renderInspectorControls( props ){
        let isResponsive = props.attributes.isResponsive == 'true';
        let lazyLoading = props.attributes.lazyLoading == 'true';
        let isLink = props.attributes.isLink == 'true';

        return [
                el(InspectorControls,{key: 'inspector'},
                    el(PanelBody, {title: __('Responsive/Static images settings'), },
                        el(
                            SelectControl,
                            {
                                label: __('Align'),
                                value: props.attributes.align,
                                options: [
                                    { label: 'Default', value: '' },
                                    { label: 'Left', value: 'alignleft' },
                                    { label: 'Right', value: 'alignright' },
                                    { label: 'Center', value: 'aligncenter' },
                                ],
                                onChange: function ( value ) {
                                    props.setAttributes({align: value});
                                },
                            }
                        ),
                        !isResponsive && el(
                            TextControl,
                            {
                                label: __('Width'),
                                value: props.attributes.width,
                                onChange: function( newWidth ){
                                    let width = isNaN(Number(newWidth)) ? '' : newWidth;
                                    props.setAttributes({width: width});
                                },
                            }
                        ),
                        el(
                            ToggleControl,
                            {
                                label: __('Responsive'),
                                checked: isResponsive,
                                instanceId: '',
                                onChange: function ( event ) {
                                    props.setAttributes({isResponsive: '' + event});
                                }
                            }
                        ),
                        isResponsive && el(
                            ToggleControl,
                            {
                                label: __('Lazy loading'),
                                checked: lazyLoading,
                                instanceId: '',
                                onChange: function (event) {
                                    props.setAttributes({lazyLoading: '' + event});
                                }
                            }
                        ),
                        el(
                            ToggleControl,
                            {
                                label: __('Link to big image'),
                                checked: isLink,
                                instanceId: '',
                                onChange: function (event) {
                                    //props.setAttributes({enableAjax: !props.attributes.enableAjax});
                                    props.setAttributes({isLink: '' + event});

                                }
                            }
                        )
                    ),
                ),
        ];
    }


    registerBlockType( 'woocommerce-magiczoom/addmedia-block', {
        title: __('MagicZoom'),
        description: __(''),
        icon: 'search',
        category: 'common',
        attributes: {
            shId: {
                type: 'string',
                source: 'attribute',
                selector: '.magicSc',
                attribute: 'data-id',
            },
            shText: {
                type: 'string',
                source: 'attribute',
                selector: '.magicSc',
                attribute: 'data-text',
            },
        },

        edit:  withSelect( ( select ) => {
               return {
                    shortcodes: select('woocommerce-magiczoom/addmedia-block').receiveShortCodes(),
                };
                } )
                
                ( props => {
                        const { attributes: { shorcode }, shortcodes, className, setAttributes } = props;
                        const handlShortcodeChange = ( shortcode ) => setAttributes( { shortcode: JSON.stringify( shortcode ) } );
                        
                        let selectedShortcodes = [];

                if ( ! shortcodes.length ) {
                        return 'Loading shortcodes...';
                }
            
                let options = [ { value: 0, label: __( 'Select Shortcode' ) } ];
                if ( shortcodes !== null ) {
                    jQuery.each(shortcodes, function(i, item) {
                        //options[i+1] = { value: i+1, label: shortcodes[i]['name'] };
                        options[i+1] = { value: shortcodes[i]['id'], label: shortcodes[i]['name'] };
                    });
                }
                return el(
                    SelectControl,
                    {
                        label: __('Select shortcode'),
                        value: props.attributes.shId,
                        options: options ,
                        onChange: function ( value, label ) {
                            props.setAttributes({shText: label});
                            props.setAttributes({shId: value});
                        },
                    }
                );
        }),

        save: function( props ){
            
            return el('div', {
                        class: 'magicSc',
                        'data-id': props.attributes.shId,
                        'data-text': props.attributes.shText,
                        },[
                                __('[magiczoom id="'+props.attributes.shId+'"]'),
                        ]
                );
            
            
            //__('[magicslideshow id="'+props.attributes.shId+'"]');
        },
    });

})(window.wp.element, window.wp.blocks, window.wp.components, window.wp.editor, window.wp.i18n);