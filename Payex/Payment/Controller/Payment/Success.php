<?php

namespace Payex\Payment\Controller\Payment;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\Order;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Class Success
 * @package Payex\Payment\Controller\Payment
 */
class Success extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;
    /**
     * @var Order
     */
    protected $_order;
    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $_transaction;
    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * Success constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Service\InvoiceService $_invoiceService
     * @param Order $_order
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Framework\DB\Transaction $_transaction
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerSession $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Service\InvoiceService $_invoiceService,
        Order $_order,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Framework\DB\Transaction $_transaction,
        CustomerRepositoryInterface $customerRepository,
        CustomerSession $customerSession
    ) {
        $this->_invoiceService = $_invoiceService;
        $this->_transaction    = $_transaction;
        $this->_order          = $_order;
        $this->checkoutSession  = $checkoutSession;
        $this->transactionRepository = $transactionRepository;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->context         = $context;
        parent::__construct($context);
    }

    public function execute()
    {
        try {  
            $_request = $this->getRequest();
            $incrementId = $_request->getParam('reference_number', false);
            $txn_id = $_request->getParam('txn_id', false);
            $auth_code = $_request->getParam('auth_code', false);
            $external_txn_id = $_request->getParam('external_txn_id', false);
            $payment_intent = $_request->getParam('payment_intent', false);
            $signature = $_request->getParam('signature', false);
            $responseMsg = $_request->getParam('response', '');

            if ($incrementId) {
                $order = $this->_order->loadByIncrementId($incrementId);
                $payment = $order->getPayment();
                $comment = __('Payment successful.  %1', $responseMsg);
                $comment .= "Txn Id -> $txn_id";
                $comment .= "External Txn Id -> $external_txn_id";
                $comment .= "Payment Intent -> $payment_intent";
                $comment .= "Signature -> $signature";
                // add information
                /*$order->addCommentToStatusHistory(
                    $comment,
                    false,
                    true
                );*/
                $status = Order::STATE_CANCELED;
                if (in_array($auth_code, ['09', '99']) ){
                    $status = Order::STATE_PAYMENT_REVIEW;
                    $status = Order::STATE_NEW;
                }elseif ($auth_code == '00'){
                    $status = Order::STATE_PROCESSING;
                }

                $order->setState($status)
                    ->setStatus($order->getConfig()->getStateDefaultStatus($status));

                $transaction = $this->transactionRepository->getByTransactionId(
                    "-1",
                    $payment->getId(),
                    $order->getId()
                );
                if ($transaction) {
                    $transaction->setTxnId($txn_id);
                    $transaction->setAdditionalInformation(
                        "Payex Transaction Id $payment_intent"
                    );
                    if($status == Order::STATE_PROCESSING){
                        $transaction->setAdditionalInformation(
                            "status", "successful"
                        );
                        $transaction->setIsClosed(1);
                    }
                    $transaction->save();

                }

                $payment->addTransactionCommentsToOrder(
                    $transaction,
                    "Transaction is completed successfully. $comment"
                );
                $payment->setParentTransactionId(null);

                # send new email
                $order->setCanSendNewEmailFlag(true);

                $this->_objectManager->create('Magento\Sales\Model\OrderNotifier')->notify($order);

                $payment->save();
                $order->save();


                $this->checkoutSession
                    ->setLastQuoteId($order->getQuoteId())
                    ->setLastSuccessQuoteId($order->getQuoteId())/*->clearHelperData()*/
                ;

                $this->checkoutSession->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastOrderStatus($order->getStatus());
                 
                if (!$this->customerSession->isLoggedIn() &&
                    $_request->getParam('logged_in', false) &&
                    $_request->getParam('id', false)
                ) {
                    try {
                        // @var CustomerRepositoryInterface $customer
                        $customer = $this->customerRepository->getById($order->getCustomerId());
                    } catch (\Exception $exception) {  }

                    $this->customerSession->setCustomerDataAsLoggedIn($customer);
                }
            }
        } catch (\Exception $e) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/payex_paymentt.log');
            $this->logger = new \Zend\Log\Logger();
            $this->logger->addWriter($writer);
            $this->logger->info(json_encode($this->getRequest()->getParams()));
            $this->logger->info($e->getMessage());
            $this->logger->info($e->getTraceAsString());
        }
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}