/*browser:true*/
/*global define*/

require.config({
    paths: { payex: window.checkoutConfig.payment.payex_psp_checkout.frontend_script }
});

define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'PayEx_Payments/js/action/select-payment-method',
        'PayEx_Payments/js/action/get-payment-url',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/model/messageList',
        'mage/translate',
        'Magento_Checkout/js/action/redirect-on-success',
        'payex'
    ],
    function (
        $,
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        getPaymentUrlAction,
        additionalValidators,
        quote,
        customerData,
        fullScreenLoader,
        globalMessageList,
        $t,
        redirectOnSuccessAction
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                self: this,
                template: 'PayEx_Payments/payment/psp_checkout'
            },
            redirectAfterPlaceOrder: false,

            /** Pay by PayEx */
            placeOrder: function () {
                if (additionalValidators.validate()) {
                    var self = this;
                    fullScreenLoader.startLoader();

                    // Init PayEx Checkout
                    var payment_id = window.checkoutConfig.payment.payex_psp_checkout.payment_id;
                    if (!payment_id) {
                        fullScreenLoader.stopLoader();
                        globalMessageList.addErrorMessage({
                            message: $t('Payment session is lost. Please try to place the order again.')
                        });
                        return false;
                    }

                    payex.checkout(payment_id, {
                        onClose: function() {
                            fullScreenLoader.stopLoader();
                        },
                        onComplete: function() {
                            placeOrderAction(self.getData(), self.messageContainer).done(function () {
                                fullScreenLoader.startLoader();
                                customerData.invalidate(['cart']);

                                redirectOnSuccessAction.execute();
                            });
                        },
                        onError: function () {
                            fullScreenLoader.stopLoader();
                            globalMessageList.addErrorMessage({
                                message: $t('An error occurred on the server. Please try to place the order again.')
                            });
                        },
                        onOpen: function () {
                            fullScreenLoader.stopLoader();
                        }
                    }, 'open');

                    return false;
                }
            }
        });
    }
);
