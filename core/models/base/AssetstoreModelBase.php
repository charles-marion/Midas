<?php
/** Assetstore Model Base*/
abstract class AssetstoreModelBase extends AppModel
{
  /** Constructor*/
  public function __construct()
    {
    parent::__construct();
    $this->_name = 'assetstore';
    $this->_key = 'assetstore_id';
  
    $this->_mainData = array(
        'assetstore_id' =>  array('type' => MIDAS_DATA),
        'name' =>  array('type' => MIDAS_DATA),
        'path' =>  array('type' => MIDAS_DATA),
        'type' =>  array('type' => MIDAS_DATA),
        );
    $this->initialize(); // required
    } // end __construct()
   
  /** Abstract functions */
  abstract function getAll();
 
} // end class AssetstoreModelBase