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

require_once BASE_PATH.'/modules/remoteprocessing/constant/module.php';

/** API component controller tests for the remote processing module. */
class Remoteprocessing_ApiComponentControllerTest extends ControllerTestCase
{
    /** set up tests */
    public function setUp()
    {
        $this->setupDatabase(array('default', 'adminUser'));
        $this->_models = array('User', 'Item');
        $this->enabledModules = array('scheduler', 'remoteprocessing', 'api');
        parent::setUp();
    }

    /** Test API submission process. */
    public function testAllApiSubmissionProcess()
    {
        /** @var $settingModel SettingModel */
        $settingModel = MidasLoader::loadModel('Setting');

        // register (create user)
        $this->resetAll();
        $this->params = array();
        $this->params['securitykey'] = $settingModel->getValueByName(MIDAS_REMOTEPROCESSING_SECURITY_KEY_KEY, 'remoteprocessing');
        $this->params['os'] = MIDAS_REMOTEPROCESSING_OS_WINDOWS;
        $this->request->setMethod('POST');

        $this->dispatchUrl('/api/json?method=midas.remoteprocessing.registerserver');

        $jsonResults = $this->getBody();
        $this->resetAll();
        $results = JsonComponent::decode($jsonResults);

        if ($results['stat'] !== 'ok') {
            $this->fail($results['message']);
        }

        $email = $results['data']['email'];
        $apikey = $results['data']['apikey'];

        // authenticate
        $this->params = array();
        $this->params['securitykey'] = $settingModel->getValueByName(MIDAS_REMOTEPROCESSING_SECURITY_KEY_KEY, 'remoteprocessing');
        $this->params['email'] = $email;
        $this->params['apikey'] = $apikey;
        $this->request->setMethod('POST');

        $this->dispatchUrl('/api/json?method=midas.remoteprocessing.registerserver');

        $jsonResults = $this->getBody();
        $this->resetAll();
        $results = JsonComponent::decode($jsonResults);

        if ($results['stat'] !== 'ok') {
            $this->fail($results['message']);
        }

        $token = $results['data']['token'];

        // ask action
        $this->params = array();
        $this->params['token'] = $token;
        $this->params['os'] = '123';
        $this->request->setMethod('POST');

        $this->dispatchUrl('/api/json?method=midas.remoteprocessing.keepaliveserver');

        $jsonResults = $this->getBody();
        $this->resetAll();
        $results = JsonComponent::decode($jsonResults);

        if ($results['stat'] !== 'ok') {
            $this->fail($results['message']);
        }

        if ($results['data']['action'] != 'wait') {
            $this->fail('Should be wait, was '.$results['data']['action']);
        }

        // add a job
        $scriptParams['script'] = 'script';
        $scriptParams['os'] = MIDAS_REMOTEPROCESSING_OS_WINDOWS;
        $scriptParams['condition'] = '';
        $scriptParams['params'] = array();
        Zend_Registry::get('notifier')->callback("CALLBACK_REMOTEPROCESSING_ADD_JOB", $scriptParams);

        $this->params = array();
        $this->params['token'] = $token;
        $this->params['os'] = MIDAS_REMOTEPROCESSING_OS_WINDOWS;
        $this->request->setMethod('POST');

        $this->dispatchUrl('/api/json?method=midas.remoteprocessing.keepaliveserver');

        $jsonResults = $this->getBody();
        $this->resetAll();
        $results = JsonComponent::decode($jsonResults);

        if ($results['stat'] !== 'ok') {
            $this->fail($results['message']);
        }

        if ($results['data']['action'] != 'process') {
            $this->fail('Should be process, was '.$results['data']['action']);
        }

        // send results
        $this->params = array();
        $this->params['token'] = $token;
        $this->params['os'] = MIDAS_REMOTEPROCESSING_OS_WINDOWS;
        $this->request->setMethod('POST');

        $this->dispatchUrl('/api/json?method=midas.remoteprocessing.resultsserver&testingmode=1');
        $jsonResults = $this->getBody();
        $this->resetAll();
        $results = JsonComponent::decode($jsonResults);

        if ($results['stat'] !== 'ok') {
            $this->fail($results['message']);
        }
    }
}
