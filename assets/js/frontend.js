(function ($) {
    logo_yet = [];
    $(document).ready(function () {

        const originalFetch = window.fetch;
        window.fetch = function (...args) {
            const [url, options] = args;

            return originalFetch.apply(this, args).then(response => {
                // Reinit logo_yet to allow re-adding logos if shipping methods are re-rendered
                logo_yet = [];
                
                const clonedResponse = response.clone();
                if (typeof url === 'string') {
                    const isBatchWithUpdateCustomer = url.includes('/wc/store/v1/batch');
                    const isCartRequest = url.includes('/wc/store/v1/cart') && url.includes('_locale=user');

                    if (isBatchWithUpdateCustomer) {
                        if (options?.body) {
                            try {
                                const parsedBody = JSON.parse(options.body);
                                const found = parsedBody?.requests?.some(req => req.path === '/wc/store/v1/cart/update-customer');
                                if (found) {
                                    clonedResponse.json().then(() => {
                                        setTimeout(() => setLogo(), 1000);
                                    });
                                }
                            } catch (e) {
                                console.warn('Erreur de parsing batch body', e);
                            }
                        }
                    }

                    if (isCartRequest) {
                        // Quand le panier est mis à jour directement
                        clonedResponse.json().then(() => {
                            setTimeout(() => setLogo(), 1000);
                        });
                    }
                }

                return response;
            });
        };

        // if ($("#trackthis").length) {
        //     $("#trackthis").click(function (e) {
        //         e.preventDefault();
        //         var wc_order_id = $(this).data('order_id');
        //         var _this = $(this);
        //         var apiuid = $(this).data('api_uuid');
        //         var data = {
        //             action: 'mfb_check_status',
        //             apiuid: apiuid,
        //             wc_order_id: wc_order_id,
        //             lang: mfb_var.lang,
        //             front: true
        //         };

        //         $.ajax({
        //             url: mfb_var.ajax_url,
        //             data: data,
        //             type: 'POST',
        //             success: function (response) {
        //                 if ("true" == response.success) {
        //                     var status = response.status;
        //                     _this.closest(".woocommerce-order-details").find('#last-status').html(status);
        //                 } else {
        //                     _this.closest(".woocommerce-order-details").find('#last-status').text("");
        //                 }
        //             }
        //         });
        //         return false;
        //     });
        // }
    });

    function setLogo() {
        // Only proceed if we are on the checkout page and there are shipping methods available
        if ($('.wp-block-woocommerce-checkout-shipping-methods-block .wc-block-components-radio-control__option').length) {
            $('.wp-block-woocommerce-checkout-shipping-methods-block .wc-block-components-radio-control__option').each(function () {
                const $option = $(this);
                const $input = $option.find('input[type="radio"]');
                const $label = $option.find('.wc-block-components-radio-control__label');
                const wrap_label = $label.parent("div.wc-block-components-radio-control__label-group");

                if ($input.length === 0) {
                    return;
                }

                const methodId = $input.val();

                if ($label.length === 0 || wrap_label.find('img').length > 0 || ($.inArray(methodId, logo_yet) !== -1)) {
                    return;
                }

                logo_yet.push(methodId);

                $.ajax({
                    url: mfb_var.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'get_shipping_method_logo',
                        method_id: methodId
                    },
                    success: function (response) {
                        if (response.success && response.data.logo) {
                            const $img = $('<img>', {
                                src: response.data.logo,
                                alt: '',
                                css: {
                                    height: '45px',
                                    marginRight: '8px',
                                    verticalAlign: 'middle'
                                }
                            });
                            wrap_label.prepend($img);
                        }
                    }
                });
            });
        }
    }

    $(window).on("load", function () {
        if ($('body').hasClass('woocommerce-checkout')) {
            setTimeout(function () {
                setLogo();
            }, 1000);
        }
    });

})(jQuery);