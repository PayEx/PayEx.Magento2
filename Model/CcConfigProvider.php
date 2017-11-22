<?php

namespace PayEx\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\ObjectManager;
use PayEx\Payments\Model\Method\Cc;
use PayEx\Payments\Model\Method\Bankdebit;
use PayEx\Payments\Model\Method\MasterPass;

class CcConfigProvider implements ConfigProviderInterface
{

    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param PaymentHelper $paymentHelper
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        PaymentHelper $paymentHelper,
        UrlInterface $urlBuilder
    ) {
        $this->appState = $context->getAppState();
        $this->checkoutHelper = $checkoutHelper;
        $this->paymentHelper = $paymentHelper;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                Cc::METHOD_CODE => [],
                Bankdebit::METHOD_CODE => [],
                MasterPass::METHOD_CODE => []
            ],
        ];

        /** @var Cc $method */
        $method = $this->paymentHelper->getMethodInstance(Cc::METHOD_CODE);
        if ($method->isAvailable()) {
            $config['payment'][Cc::METHOD_CODE]['redirect_url'] = $method->getOrderPlaceRedirectUrl();
        }

        /** @var Bankdebit $method */
        $method = $this->paymentHelper->getMethodInstance(Bankdebit::METHOD_CODE);
        if ($method->isAvailable()) {
            $banks = ObjectManager::getInstance()->get('PayEx\Payments\Block\Bankdebit\Banks')->getAvailableBanks();
            $config['payment'][Bankdebit::METHOD_CODE]['banks'] = $banks;
        }

        /** @var \PayEx\Payments\Model\Method\MasterPass $method */
        $method = $this->paymentHelper->getMethodInstance(MasterPass::METHOD_CODE);
        if ($method->isAvailable()) {
            $config['payment'][MasterPass::METHOD_CODE]['redirectUrl'] = $method->getCheckoutRedirectUrl();
        }

        return $config;
    }
}
