define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'payex',
                component: 'Payex_Payment/js/view/payment/method-renderer/payex'
            }
        );
        return Component.extend({});
    }
);
