<?php
/*=========================================================================
 MIDAS Server
 Copyright (c) Kitware SAS. 26 rue Louis Guérin. 69100 Villeurbanne, FRANCE
 All rights reserved.
 More information http://www.kitware.com

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

         http://www.apache.org/licenses/LICENSE-2.0.txt

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
=========================================================================*/

require_once BASE_PATH.'/modules/api/library/KwWebApiCore.php';

/** Main controller for the web api module */
class Api_IndexController extends Api_AppController
{
    public $_models = array('Setting');
    public $_moduleComponents = array('Api');

    public $kwWebApiCore = null;
    public $apicallbacks = array();
    public $helpContent = array();

    // Config parameters
    public $apiEnable = '';
    public $apiSetup = array();

    /** Before filter */
    public function preDispatch()
    {
        parent::preDispatch();
        $this->apiEnable = true;

        // define api parameters
        $this->apiSetup['testing'] = Zend_Registry::get('configGlobal')->environment == 'testing';
        $this->apiSetup['tmpDirectory'] = $this->getTempDirectory();
        $this->apiSetup['apiMethodPrefix'] = $this->Setting->getValueByName(API_METHOD_PREFIX_KEY, $this->moduleName);

        $this->action = $actionName = Zend_Controller_Front::getInstance()->getRequest()->getActionName();

        if ($this->action === 'json') {
            $this->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            $this->ModuleComponent->Api->controller = &$this;
            $this->ModuleComponent->Api->apiSetup = &$this->apiSetup;
            $this->ModuleComponent->Api->userSession = &$this->userSession;
        }
    }

    /** Index function */
    public function indexAction()
    {
        $this->_computeApiHelp($this->apiSetup['apiMethodPrefix']);

        // Prepare the data used by the view
        $data = array(
            'api.enable' => $this->apiEnable,
            'api.methodprefix' => $this->apiSetup['apiMethodPrefix'],
            'api.listmethods' => array_keys($this->apicallbacks),
        );

        $this->view->data = $data; // transfer data to the view
        $this->view->help = $this->helpContent;
        $this->view->serverURL = $this->getServerURL();
    }

    /** This is called when calling a web api method */
    private function _computeApiCallback($method_name, $apiMethodPrefix)
    {
        $tokens = explode('.', $method_name);
        if (array_shift($tokens) != $apiMethodPrefix
        ) { // pop off the method prefix token
            return; // let the web API core write out its method doesn't exist message
        }

        $method = implode($tokens);
        if (method_exists($this->ModuleComponent->Api, $method)) {
            $this->apicallbacks[$method_name] = array(&$this->ModuleComponent->Api, $method);
        } else { // it doesn't exist here, check in the module specified by the 2nd token
            $moduleName = array_shift($tokens);
            $moduleMethod = implode('', $tokens);
            $retVal = Zend_Registry::get('notifier')->callback(
                'CALLBACK_API_METHOD_'.strtoupper($moduleName),
                array('methodName' => $moduleMethod)
            );
            foreach ($retVal as $method) {
                $this->apicallbacks[$method_name] = array($method['object'], $method['method']);
                break;
            }
        }
    }

    /** This index function uses this to display the list of web api methods */
    private function _computeApiHelp($apiMethodPrefix)
    {
        $apiMethodPrefix = KwWebApiCore::checkApiMethodPrefix($apiMethodPrefix); // append the . if needed

        // Get the list of methods in each module (including this one)
        $apiMethods = Zend_Registry::get('notifier')->callback('CALLBACK_API_HELP', array());
        foreach ($apiMethods as $module => $methods) {
            foreach ($methods as $method) {
                $apiMethodName = $apiMethodPrefix;
                if ($module != $this->moduleName) { // for functions in this module, don't append module name
                    $apiMethodName .= $module.'.';
                }
                $apiMethodName .= $method['name'];
                $this->helpContent[$apiMethodName] = $method['help'];
                $this->apicallbacks[$apiMethodName] = array($method['callbackObject'], $method['callbackFunction']);
            }
        }
    }

    /** Controller action handling JSON request */
    public function jsonAction()
    {
        $this->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $methodName = $this->getParam('method');

        if (!isset($methodName)) {
            echo 'Inconsistent request: please set a method parameter';
            exit;
        }

        $requestData = $this->getAllParams();
        $apiMethodPrefix = $this->apiSetup['apiMethodPrefix'];
        $this->_computeApiCallback($methodName, $apiMethodPrefix);
        $this->kwWebApiCore = new KwWebApiCore($apiMethodPrefix, $this->apicallbacks, $requestData);
    }
}
