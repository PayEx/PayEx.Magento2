<?php

namespace PayEx\Payments\Controller\Cc;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Exception\CouldNotSaveException;

class Redirect extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * @var \PayEx\Payments\Helper\Data
     */
    private $payexHelper;

    /**
     * @var \PayEx\Payments\Logger\Logger
     */
    private $payexLogger;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    private $orderSender;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * @var CartManagementInterface
     */
    private $quoteManagement;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var ServiceInputProcessor
     */
    private $inputProcessor;

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * Success constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \PayEx\Payments\Helper\Data $payexHelper
     * @param \PayEx\Payments\Logger\Logger $payexLogger
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\OrderFactory $orderFactory,
     * @param CartManagementInterface $quoteManagement
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $cartRepository
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param ServiceInputProcessor $inputProcessor
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Logger\Logger $payexLogger,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        CartManagementInterface $quoteManagement,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        ServiceInputProcessor $inputProcessor,
        \Magento\Framework\Registry $registry
    ) {
    
        parent::__construct($context);

        $this->logger = $logger;
        $this->urlBuilder = $context->getUrl();
        $this->checkoutHelper = $checkoutHelper;
        $this->payexHelper = $payexHelper;
        $this->payexLogger = $payexLogger;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender = $orderSender;
        $this->orderFactory = $orderFactory;
        $this->quoteManagement = $quoteManagement;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->inputProcessor = $inputProcessor;
        $this->registry = $registry;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $payload = $this->getRequest()->getParam('payload');
        $payload = json_decode($payload, true);
        $cartId = $payload['cartId'];
        $email = isset($payload['email']) ? $payload['email'] : '';

        $quoteId = $cartId;
        if (!$this->customerSession->isLoggedIn()) {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            $quoteId = $quoteIdMask->getQuoteId();
        }

        // Remove order if exists
        $order = $this->orderFactory->create()->load($quoteId, 'quote_id');
        if ($order->getId()) {
            if ($this->customerSession->isLoggedIn() &&
                $order->getCustomerId() !== $this->customerSession->getCustomerId()
            ) {
                throw new LocalizedException(__('Invalid Customer Id.'));
            }

            $this->registry->register('isSecureArea', true);
            $order->delete();
        }

        // Place Order
        /** @var \Magento\Quote\Api\Data\PaymentInterface $pm */
        $pm = $this->inputProcessor->convertValue($payload['paymentMethod'], 'Magento\Quote\Api\Data\PaymentInterface');

        /** @var \Magento\Quote\Api\Data\AddressInterface $ba */
        $ba = $this->inputProcessor->convertValue($payload['billingAddress'], 'Magento\Quote\Api\Data\AddressInterface');

        try {
            if (!$this->customerSession->isLoggedIn()) {
                /** @var \Magento\Checkout\Model\GuestPaymentInformationManagement $info */
                $info = $this->_objectManager->create('Magento\Checkout\Model\GuestPaymentInformationManagement');
                $orderId = $info->savePaymentInformationAndPlaceOrder($cartId, $email, $pm, $ba);
            } else {
                /** @var \Magento\Checkout\Model\PaymentInformationManagement $info */
                $info = $this->_objectManager->create('Magento\Checkout\Model\PaymentInformationManagement');
                $orderId = $info->savePaymentInformationAndPlaceOrder($cartId, $pm, $ba);
            }
        } catch (CouldNotSaveException $e) {
            throw $e;
        }

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderFactory->create()->load($orderId);
        if (!$order->getId()) {
            //$this->checkoutHelper->getCheckout()->restoreQuote();
            $this->messageManager->addError(__('No order for processing found'));
            $this->_redirect('checkout/cart');
            return;
        }

        // Add order information to the session
        $this->checkoutSession
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());

        // Remove Redirect Url from Session
        //$this->checkoutHelper->getCheckout()->unsPayexRedirectUrl();

        //$order_id = $order->getIncrementId();

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $order->getPayment()->getMethodInstance();

        // Init PayEx Environment
        $accountnumber = $method->getConfigData('accountnumber');
        $encryptionkey = $method->getConfigData('encryptionkey');
        $debug = (bool)$method->getConfigData('debug');
        $this->payexHelper->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);

        $order_id = $order->getIncrementId();

        // Get Currency code
        $currency_code = $order->getOrderCurrency()->getCurrencyCode();

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = $method->getConfigData('transactiontype');

        // Get Payment Type (PX, CREDITCARD etc)
        $payment_type = $method->getConfigData('payment_type');

        // Get Additional Values
        $additional = ($payment_type === 'PX' ? 'PAYMENTMENU=TRUE' : '');

        // Direct Debit uses 'SALE' only
        if ($payment_type === 'DIRECTDEBIT') {
            $operation = 'SALE';
        }

        // Responsive Skinning
        if ($method->getConfigData('responsive') === '1') {
            $separator = (!empty($additional) && mb_substr($additional, -1) !== '&') ? '&' : '';

            // PayEx Payment Page 2.0  works only for View 'Credit Card' and 'Direct Debit' at the moment
            if (in_array($payment_type, ['CREDITCARD', 'DIRECTDEBIT'])) {
                $additional .= $separator . 'RESPONSIVE=1';
            } else {
                $additional .= $separator . 'USECSS=RESPONSIVEDESIGN';
            }
        }

        // Language
        $language = $method->getConfigData('language');
        if (empty($language)) {
            $language = $this->payexHelper->getLanguage();
        }

        // Get Amount
        //$amount = $order->getGrandTotal();
        $items = $this->payexHelper->getOrderItems($order);
        $amount = array_sum(array_column($items, 'price_with_tax'));

        // Call PxOrder.Initialize8
        $params = [
            'accountNumber' => '',
            'purchaseOperation' => $operation,
            'price' => round($amount * 100),
            'priceArgList' => '',
            'currency' => $currency_code,
            'vat' => 0,
            'orderID' => $order_id,
            'productNumber' => $order_id,
            'description' => $this->payexHelper->getStore()->getName(),
            'clientIPAddress' => $this->payexHelper->getRemoteAddr(),
            'clientIdentifier' => 'USERAGENT=' . $this->_request->getServer('HTTP_USER_AGENT'),
            'additionalValues' => $additional,
            'externalID' => '',
            'returnUrl' => $this->urlBuilder->getUrl('payex/cc/success', [
                '_secure' => $this->_request->isSecure()
            ]),
            'view' => $payment_type,
            'agreementRef' => '',
            'cancelUrl' => $this->urlBuilder->getUrl('payex/cc/cancel', [
                '_secure' => $this->_request->isSecure()
            ]),
            'clientLanguage' => $language
        ];
        $result = $this->payexHelper->getPx()->Initialize8($params);
        $this->payexLogger->info('PxOrder.Initialize8', $result);

        // Check Errors
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = $this->payexHelper->getVerboseErrorMessage($result);
            throw new LocalizedException(__($message));
        }

        $order_ref = $result['orderRef'];
        $redirectUrl = $result['redirectUrl'];

        // Add Order Info
        if ($method->getConfigData('checkoutinfo')) {
            // Add Order Items
            foreach ($items as $index => $item) {
                // Call PxOrder.AddSingleOrderLine2
                $params = [
                    'accountNumber' => '',
                    'orderRef' => $order_ref,
                    'itemNumber' => ($index + 1),
                    'itemDescription1' => $item['name'],
                    'itemDescription2' => '',
                    'itemDescription3' => '',
                    'itemDescription4' => '',
                    'itemDescription5' => '',
                    'quantity' => $item['qty'],
                    'amount' => (int)(100 * $item['price_with_tax']), //must include tax
                    'vatPrice' => (int)(100 * $item['tax_price']),
                    'vatPercent' => (int)(100 * $item['tax_percent'])
                ];

                $result = $this->payexHelper->getPx()->AddSingleOrderLine2($params);
                $this->payexLogger->info('PxOrder.AddSingleOrderLine2', $result);
            }

            // Add Order Address Info
            $params = array_merge([
                'accountNumber' => '',
                'orderRef' => $order_ref
            ], $this->payexHelper->getAddressInfo($order));

            $result = $this->payexHelper->getPx()->AddOrderAddress2($params);
            $this->payexLogger->info('PxOrder.AddOrderAddress2', $result);
        }

        // Set Pending Payment status
        $order->setCanSendNewEmailFlag(false);
        $order->addStatusHistoryComment(__('The customer was redirected to PayEx.'), Order::STATE_PENDING_PAYMENT);
        $order->save();

        // Save Redirect URL in Session
        $this->checkoutHelper->getCheckout()->setPayexRedirectUrl($redirectUrl);

        // Redirect to PayEx
        $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($redirectUrl);
        return $resultRedirect;
    }

    /**
     * Get order object
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        $incrementId = $this->checkoutHelper->getCheckout()->getLastRealOrderId();
        return $this->orderFactory->create()->loadByIncrementId($incrementId);
    }
}
