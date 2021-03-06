<?php	 	 	 	 	 	 	 	 	 	 	 	 	 	 	 	 	 	 	 	 	 	 	 	 
class CosmoCommerce_Sns_Block_Adminhtml_Sns extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_sns';
    $this->_blockGroup = 'sns';
    $this->_headerText = Mage::helper('sns')->__('Item Manager');
    $this->_addButtonLabel = Mage::helper('sns')->__('Add Item');
    parent::__construct();
  }
}