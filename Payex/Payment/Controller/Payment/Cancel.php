<?php

namespace Payex\Payment\Controller\Payment;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Sales\Model\Order;

/**
 * Class Cancel
 * @package Payex\Payment\Controller\Payment
 */
class Cancel extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Order
     */
    protected $_order;

    /**
     * Cancel constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param Order $_order
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerSession $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session,
        Order $_order,
        CustomerRepositoryInterface $customerRepository,
        CustomerSession $customerSession
    ) {
        $this->session = $session;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->_order          = $_order;
        parent::__construct($context);
    }

    /**
     * @return mixed
     */
    public function execute()
    {

        try {
            $_request = $this->getRequest();
            $postData = $_request->getPostValue();
            $incrementId = $_request->getParam('reference_number', false);
            $errorMsg = $_request->getParam('response', _("Please try again."));
            $errorMsg = trim(strip_tags($errorMsg));
            //Redirect to payment step
            $this->messageManager->addErrorMessage(__("Payment declined. $errorMsg"));

            if (!$this->customerSession->isLoggedIn() &&
                $_request->getParam('logged_in', false) &&
                $_request->getParam('id', false) && $incrementId
            ) {

                $order = $this->_order->loadByIncrementId($incrementId);
                $this->session->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId());

                try {
                    // @var CustomerRepositoryInterface $customer
                    $customer = $this->customerRepository->getById($_request->getParam('id'));
                    $this->customerSession->setCustomerDataAsLoggedIn($customer);
                } catch (\Exception $exception) {  }
            }

            $this->cancelCurrentOrder($errorMsg);
            $this->restoreQuote();
            return $this->_redirect('checkout/cart');
        } catch (\Exception $e) {

            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/payex_payment.log');
            $this->logger = new \Zend\Log\Logger();
            $this->logger->addWriter($writer);
            $this->logger->info($e->getMessage());
            $this->logger->info(json_encode($postData));

            $this->messageManager->addErrorMessage(__('Payment was unsuccessful.  %1', $this->getRequest()->getParam('response', _("Please try again."))));
        }
        return $this->_redirect('checkout/onepage/failure');;
    }
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Cancel last placed order with specified comment message
     *
     * @param string $comment Comment appended to order history
     * @return bool True if order cancelled, false otherwise
     */
    public function cancelCurrentOrder($comment)
    {
        $order = $this->session->getLastRealOrder();
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();
            return true;
        }
        return false;
    }

    /**
     * Restores quote
     *
     * @return bool
     */
    public function restoreQuote()
    {
        return $this->session->restoreQuote();
    }
}