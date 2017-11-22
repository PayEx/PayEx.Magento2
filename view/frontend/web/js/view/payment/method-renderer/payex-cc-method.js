/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'PayEx_Payments/js/action/select-payment-method',
        'PayEx_Payments/js/action/get-payment-url',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/model/messageList',
        'mage/translate'
    ],
    function (
        $,
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        getPaymentUrlAction,
        additionalValidators,
        customer,
        quote,
        customerData,
        fullScreenLoader,
        globalMessageList,
        $t
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                self: this,
                template: 'PayEx_Payments/payment/cc'
            },
            redirectAfterPlaceOrder: false,

            /** Redirect to PayEx */
            placeOrder: function () {
                if (additionalValidators.validate()) {
                    var self = this;
                    var paymentData = this.getData();
                    selectPaymentMethodAction(this.getData()).done(function () {
                        // Prepare payload
                        var payload = {
                            cartId: quote.getQuoteId(),
                            billingAddress: quote.billingAddress(),
                            paymentMethod: paymentData
                        };

                        if (!customer.isLoggedIn()) {
                            payload.email = quote.guestEmail;
                        }

                        customerData.invalidate(['cart']);

                        // Make request
                        var form = $('<form>', {
                            'action': window.checkoutConfig.payment.payex_cc.redirect_url,
                            'method': 'post'
                        }).append($('<input>', {
                            'type': 'hidden',
                            'name': 'payload',
                            'value': JSON.stringify(payload)
                        }));
                        $(document.body).append(form);
                        form.submit();
                    });

                    return false;
                }
            }
        });
    }
);
