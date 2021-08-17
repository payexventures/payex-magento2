<?php

namespace Payex\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

class CustomConfigProvider implements ConfigProviderInterface
{

    protected $_helper;

    public function __construct(
        \Payex\Payment\Helper\Data $helper
    ) {
        $this->_helper = $helper;
    }

    // get configuration
    public function getConfig()
    {
        $config = [
            'payment' => [
                'payex' => [
                    'title' => $this->_helper->getConfigData('title'),
                    'description' => $this->_helper->getConfigData('description'),
                    'status' => $this->_helper->getConfigData('active')
                ]
            ]
        ];
        return $config;
    }
}