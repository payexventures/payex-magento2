define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/action/redirect-on-success'
    ],
    function($, Component, urlBuilder, redirectOnSuccessAction) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Payex_Payment/payment/payex',
                redirectAfterPlaceOrder: false
            },
            afterPlaceOrder: function(url) {

                $.ajax({
                    url: urlBuilder.build('payex/payment/redirect/'),
                    type: 'post',
                    async: true,
                    dataType: 'json',
                    showLoader: true,
                    context: this,
                    data: {
                        'isAjax': true,
                        'form_key': $.cookie('form_key')
                    },

                    /**
                     * @param {Object} response
                     */
                    success: function (response) {
                        console.log(response.returnUrl);
                        window.response = response;
                        window.location.replace(response.returnUrl);
                        // redirectOnSuccessAction.execute();
                    },

                    /** Complete callback. */
                    complete: function () {
                        if(!window.response){
                            redirectOnSuccessAction.execute();
                        }
                    }
                });
                // window.location.replace(urlBuilder.build('payex/payment/redirect/'));
            },
            getDescription: function() {
                return window.checkoutConfig.payment.payex.description;
            },
            getTitle: function() {
                return window.checkoutConfig.payment.payex.title;
            }
        });
    }
);