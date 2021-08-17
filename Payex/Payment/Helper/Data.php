<?php

namespace Payex\Payment\Helper;

use Magento\Framework\App\Helper\Context;
use Psr\Log\LoggerInterface as PsrLogger;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\Storage\WriterInterface;
use \Magento\Store\Model\ScopeInterface;
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const STAGE_CODE = 'sandbox';
    const PRODUCTION_CODE = 'production';

    const CONFIG_PREFIX = 'payment/payex/';
    const AUTH_CONFIG_PATH = 'authtoken';

    const STAGE_URL = 'https://sandbox-payexapi.azurewebsites.net';
    const PRODUCTION_URL = 'https://api.payex.io';
    const AUTH_API = '/api/Auth/Token';
    const PAYEMENTINTENTS_API = '/api/v1/PaymentIntents';

    public $scopeConfig;
    protected $_paymentIntentParams = [];
    public $order;
    public $modelOrder;
    public $cart;
    /**
     * @var Curl
     */
    protected $_curl;
    protected $configWriter;

    /**
     * Data constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\Sales\Model\Order $modelOrder
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\Framework\ObjectManagerInterface $_objectManager
     * @param PsrLogger $logger
     * @param Curl $curl
     */
    public function __construct(
        Context $context,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Sales\Model\Order $modelOrder,
        \Magento\Checkout\Model\Cart $cart,
        WriterInterface $configWriter,
        \Magento\Checkout\Model\Type\Onepage $onepage,
        Curl $curl
    ) {
        parent::__construct($context);
        $this->onepage = $onepage;
        $this->order = $order;
        $this->modelOrder = $modelOrder;
        $this->cart = $cart;
        $this->_curl = $curl;

        $this->_moduleManager = $context->getModuleManager();
        $this->_logger = $context->getLogger();
        $this->_request = $context->getRequest();
        $this->_urlBuilder = $context->getUrlBuilder();
        $this->_httpHeader = $context->getHttpHeader();
        $this->_eventManager = $context->getEventManager();
        $this->_remoteAddress = $context->getRemoteAddress();
        $this->_cacheConfig = $context->getCacheConfig();
        $this->urlEncoder = $context->getUrlEncoder();
        $this->urlDecoder = $context->getUrlDecoder();
        $this->scopeConfig = $context->getScopeConfig();
        $this->configWriter = $configWriter;
    }

    /**
     * @return string|null
     */
    public function getPaymentIntentResponce()
    {
        $hashVal = $this->getAuthToken(true);
        $responce = $this->callCurl(
            $this->getApiUrl(self::PAYEMENTINTENTS_API),
            [ 'Content-Type' => 'application/json', 'accept' => 'application/json',"Authorization" => "Bearer $hashVal"],
            json_encode([$this->getPaymentIntentParams()]),
            'post'
        );
        return $responce;
    }

    /**
     * @return array
     */
    private function getPaymentIntentParams(){
        if(empty($this->_paymentIntentParams)) {
            $checkout = $this->onepage->getCheckout();
            $_customerSession = $this->onepage->getCustomerSession();
            $this->order->loadByIncrementId($checkout->getLastRealOrder()->getEntityId());
            $salesOrder = $this->modelOrder->load($checkout->getLastRealOrder()->getEntityId());
            $items = [];
            foreach ($salesOrder->getAllVisibleItems() as $visibleItem) {
                $tmp = [];
                $tmp['sku'] = $visibleItem->getSku();
                $tmp['quantity'] = $visibleItem->getQtyOrdered();
                $tmp['item_id'] = $visibleItem->getItemId();
                $tmp['name'] = $visibleItem->getProduct()->getName();
                $tmp['product_id'] = $visibleItem->getProductId();
                $tmp['total'] = $visibleItem->getBaseRowTotal();
                $tmp['total_tax'] = $visibleItem->getBaseRowTotalInclTax();
                $tmp['tax'] = $visibleItem->getBaseTaxAmount();
                $items[] = $tmp;
            }
            $billingAddress = $salesOrder->getBillingAddress();
            $shippingAddress = $salesOrder->getShippingAddress();
            $this->_paymentIntentParams = [
                "source" => "magento",
                "amount" => $salesOrder->getBaseGrandTotal() * 100,
                "currency" => $salesOrder->getOrderCurrencyCode(),
                "collection_id" => "",
                "customer_id" => $salesOrder->getCustomerId(),
                "customer_name" => $salesOrder->getCustomerFirstname()." ".$salesOrder->getCustomerLastname(),
                "email" => $salesOrder->getCustomerEmail(),
                "contact_number" => $billingAddress->getTelephone(),
                "address" => (is_array($billingAddress->getStreet()) ? implode(', ', $billingAddress->getStreet()) : $billingAddress->getStreet()),
                "postcode" => $billingAddress->getPostcode(),
                "city" => $billingAddress->getCity(),
                "state" => $billingAddress->getRegion(),
                "country" => $billingAddress->getCountryId(),
                "description" => __('Payment for Order Reference: %1', $salesOrder->getIncrementId()),
                "reference_number" => $salesOrder->getIncrementId(),
                "return_url" => $this->_urlBuilder->getUrl(
                    'payex/payment/success',
                    ['logged_in' => $_customerSession->isLoggedIn(), "id" => $_customerSession->getCustomerId()]),
                "callback_url" => $this->_urlBuilder->getUrl('payex/payment/callback'),
                "accept_url" => $this->_urlBuilder->getUrl(
                    'payex/payment/success',
                    ['logged_in' => $_customerSession->isLoggedIn(), "id" => $_customerSession->getCustomerId()]),
                "reject_url" => $this->_urlBuilder->getUrl(
                    'payex/payment/cancel',
                    ['logged_in' => $_customerSession->isLoggedIn(), "id" => $_customerSession->getCustomerId()]),
                "nonce" => $salesOrder->getIncrementId(),
                "item" => $items,
            ];
            if($shippingAddress->getId()){
                $this->_paymentIntentParams['shipping_name'] = $shippingAddress->getFirstname()." ".$shippingAddress->getLastname();
                $this->_paymentIntentParams['shipping_contact_number'] = $shippingAddress->getTelephone();
                $this->_paymentIntentParams['shipping_email'] = $shippingAddress->getEmail();
                $this->_paymentIntentParams['shipping_address'] = (is_array($shippingAddress->getStreet()) ? implode(', ', $shippingAddress->getStreet()) : $shippingAddress->getStreet());
                $this->_paymentIntentParams['shipping_postcode'] = $shippingAddress->getPostcode();
                $this->_paymentIntentParams['shipping_city'] = $shippingAddress->getCity();
                $this->_paymentIntentParams['shipping_state'] = $shippingAddress->getRegion();
                $this->_paymentIntentParams['shipping_country'] = $shippingAddress->getCountryId();
            }
        }
        return $this->_paymentIntentParams;
    }
    public function getConfigData($suffix, $scope = ScopeInterface::SCOPE_STORE, $scopeVal = 0)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PREFIX.$suffix,
            $scope,
            $scopeVal
        );
    }
 
    // TODO: add support for installments
    public function getAuthToken($forceGenerate = false)
    {
        $responce = $token = null;
        try{
            $authToken = $this->getConfigData(self::AUTH_CONFIG_PATH);
            if(!$authToken || $forceGenerate){
                $hashVal = base64_encode("{$this->getConfigData('username')}:{$this->getConfigData('password')}");

                $responce = $this->callCurl(
                    $this->getApiUrl(self::AUTH_API),
                    [ 'accept' => 'application/json',"Authorization"=> "Basic {$hashVal}"],
                    [],
                    'post'
                );
                $responce = json_decode($responce, true);
                if($responce && isset($responce['token'])){
                    $token = $responce['token'];
                    $responce['mode'] = $this->getConfigData('mode');
                    $responce = json_encode($responce);
                }elseif (($responce && isset($responce['message']))){
                    throw new \Exception(__($responce['message']));
                }else{
                    throw new \Exception(__('Unable to authenticate with the payment gateway.'));
                }
                $this->configWriter->save(
                    self::CONFIG_PREFIX.self::AUTH_CONFIG_PATH,
                    $responce
                );
            }else{
                $responce = $this->getConfigData(self::AUTH_CONFIG_PATH);
                $responce = json_decode($responce, true);

                $isModeSatisfied = (isset($responce['mode']) && $responce['mode'] == $this->getConfigData('mode'));
                $isDateSatisfied = (isset($responce['expiration']) && strtotime($responce['expiration']) > strtotime(date("Y-m-d")));
                $token = ($isModeSatisfied && $isDateSatisfied && isset($responce['token']))?$responce['token']:$this->getAuthToken(true);
            }
        }catch (\Exception $e){
            echo $e;die(__FILE__);
            $token = null;
            $this->_logger->critical('Error Curl', ['exception' => $e]);
        }
        return $token;
    }

    /**
     * @param $suffix
     * @return string
     */
    public function getApiUrl($suffix){

        $mode = $this->getConfigData('mode');
        $url = null;
        if($mode == self::STAGE_CODE){
            $url = self::STAGE_URL;
        }elseif ($mode == self::PRODUCTION_CODE){
            $url = self::PRODUCTION_URL;
        }
        return $url.$suffix;
    }

    public function getPostUrl()
    {
        return $this->scopeConfig->getValue(
            'payment/payex/post_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    private function callCurl($url, $header = [], $params=[], $method= 'get'){
        try{
            $curl = $this->_curl;
            $curl->setHeaders($header);

            if($method == 'get'){
                //if the method is get
                $curl->get($url);
            }elseif($method == 'post'){
                //if the method is post
                $curl->post($url, $params);
            }else{
                return null;
            }
            $response = $curl->getBody();
            return $response;
        } catch (\Exception $e) {
            echo $e;die(__FILE__);
            $this->_logger->critical('Error Curl', ['exception' => $e]);
        }
    }
}
