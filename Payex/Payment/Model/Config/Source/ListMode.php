<?php 
namespace Payex\Payment\Model\Config\Source;

use \Payex\Payment\Helper\Data as Helper;

class ListMode implements \Magento\Framework\Data\OptionSourceInterface
{

	public function toOptionArray(){
		return [
			['value' => Helper::PRODUCTION_CODE, 'label' => __('Production')],
			['value' => Helper::STAGE_CODE, 'label' => __('Sandbox')]
		];
	}
}