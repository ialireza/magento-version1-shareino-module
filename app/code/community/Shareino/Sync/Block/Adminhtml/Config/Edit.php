<?php

class Shareino_Sync_Block_Adminhtml_Config_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->_blockGroup = 'sync';
        $this->_controller = 'adminhtml_config';
        $this->_headerText = Mage::helper('sync')->__('Edit Form');
        

    }

    public function getOperationAction($op)
    {

        return $this->getUrl("*/*/$op",array('id' => $this->getRequest()->getParam('id')));
    }

    public function getSkinUrl($file = null, array $params = array())
    {
        return parent::getSkinUrl($file, $params); // TODO: Change the autogenerated stub
    }


}