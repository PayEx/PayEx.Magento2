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
                template: 'PayEx_Payments/payment/bankdebit'
            },
            /**
             * @override
             */
            getData: function () {
                return {
                    'method': this.getCode(),
                    'additional_data': {
                        'bank_id': $('select[name="bank_id"]').val()
                    }
                };
            },
            availableBanks: function () {
                return window.checkoutConfig.payment.payex_bankdebit.banks;
            }
        });
    }
);
