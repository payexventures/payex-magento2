<?php

namespace Payex\Payment\Controller\Payment;

class Redirect extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $jsonResultFactory;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Payex\Payment\Helper\Data
     */
    protected $_helper;

    /**
     * Redirect constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Payex\Payment\Helper\Data $_helper
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Payex\Payment\Helper\Data $_helper,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->_helper = $_helper;
        $this->jsonResultFactory = $jsonResultFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $returnUrl = '';
        if($this->getRequest()->getParam('isAjax', false)){
            $responce = $this->_helper->getPaymentIntentResponce();
            $responce = json_decode($responce, true);

            if(isset($responce['message']) &&isset($responce['result']) && $responce['message'] == 'Success'){
                $responce = $responce['result'];
                $responce = current($responce);
                if(isset($responce['status']) && $responce['status'] == '00'){
                    $returnUrl = $responce['url']  ;
                }
            }
        }
        $data = ['returnUrl' => $returnUrl];
        $result = $this->jsonResultFactory->create();
        $result->setData($data);
        return $result;
        return $returnUrl;
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Loading...'));
        
        $block = $resultPage->getLayout()
                ->createBlock('Payex\Payment\Block\Payment\Redirect')
                ->setTemplate('Payex_Payment::payment/redirect.phtml')
                ->toHtml();
        $this->getResponse()->setBody($block);
        return $this->resultPageFactory->create();
    }
}
