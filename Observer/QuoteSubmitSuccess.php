<?php

namespace PayEx\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use PayEx\Payments\Model\Method\Cc;

class QuoteSubmitSuccess implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        // Mark quote as active
        try {
            if ($order->getPayment()->getMethod() === Cc::METHOD_CODE) {
                $quote->setIsActive(true);
            }
        } catch (\Exception $e) {
            //
        }

        return $this;
    }
}
