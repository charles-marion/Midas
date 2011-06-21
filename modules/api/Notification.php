<?php
/** notification manager*/
class Api_Notification extends MIDAS_Notification
  {
  public $_models=array('User');
  
  /** init notification process*/
  public function init()
    {
    $this->addCallBack('CALLBACK_CORE_GET_CONFI_TABS', 'getConfigTabs');
    }//end init
    
  /** get Config Tabs */
  public function getConfigTabs()
    {
    $fc = Zend_Controller_Front::getInstance();
    $moduleWebroot = $fc->getBaseUrl().'/api';
    return array('Api' => $moduleWebroot.'/config/usertab');
    }
  } //end class
?>