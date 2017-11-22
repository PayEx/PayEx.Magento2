/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'PayEx_Payments/js/view/payment/method-renderer/abstract'
    ],
    function (ko, $, Component) {
        'use strict';

        return Component.extend({
            defaults: {
                self: this,
                template: 'PayEx_Payments/payment/gc'
            }
        });
    }
);
