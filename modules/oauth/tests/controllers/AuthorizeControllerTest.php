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

/** test oauth authorize controller */
class Oauth_AuthorizeControllerTest extends ControllerTestCase
{
    /** set up tests */
    public function setUp()
    {
        $this->setupDatabase(array('default')); // core dataset
        $this->setupDatabase(array('default'), 'oauth');
        $this->enabledModules = array('api', 'oauth');
        $this->_models = array('User');

        parent::setUp();
    }

    /**
     * Helper function to get test that each parameter in the array is required
     */
    private function _testParamsRequired($uri, $params, $userDao = null)
    {
        foreach ($params as $key => $value) {
            $localParams = $params; // copy array
            unset($localParams[$key]);
            $this->resetAll();
            $this->params = $localParams;
            $this->getRequest()->setMethod('GET');
            $this->dispatchUri($uri, $userDao, true);
        }
    }

    /**
     * Tests the login screen used by the user to authorize the client
     */
    public function testLoginScreen()
    {
        $_SERVER['HTTPS'] = true; // must set this to trick the action into thinking we're using SSL
        $params = array('client_id' => '1000', 'response_type' => 'code', 'redirect_uri' => 'http://google.com');
        $this->_testParamsRequired('/oauth/authorize', $params);

        $scopes = array(
            MIDAS_API_PERMISSION_SCOPE_READ_USER_INFO,
            MIDAS_API_PERMISSION_SCOPE_WRITE_USER_INFO,
            MIDAS_API_PERMISSION_SCOPE_READ_DATA,
        );
        $this->resetAll();
        $this->params = $params;
        $this->params['state'] = 'my_state_value';
        $this->params['scope'] = JsonComponent::encode($scopes);
        $this->dispatchUrl('/oauth/authorize', null);
        $this->assertQueryCount('ul.scopeList li', count($scopes));
        $scopeMap = Zend_Registry::get('permissionScopeMap');

        foreach ($scopes as $scope) {
            $this->assertQueryContentContains('ul.scopeList li', $scopeMap[$scope]);
        }
    }

    /**
     * Test the submission of the login form, authorizing the client
     */
    public function testSubmitAction()
    {
        $user = $this->User->load(1);
        $this->User->changePassword($user, 'myPassword'); // easiest way to set the password
        $params = array(
            'client_id' => '1000',
            'login' => $user->getEmail(),
            'password' => 'wrongPass',
            'redirect_uri' => 'http://google.com',
        );
        $this->_testParamsRequired('/oauth/authorize/submit', $params);

        $scopes = array(
            MIDAS_API_PERMISSION_SCOPE_READ_USER_INFO,
            MIDAS_API_PERMISSION_SCOPE_WRITE_USER_INFO,
            MIDAS_API_PERMISSION_SCOPE_READ_DATA,
        );

        // Test with incorrect password
        $this->resetAll();
        $this->params = $params;
        $this->params['state'] = 'my_state_value';
        $this->params['scope'] = JsonComponent::encode($scopes);
        $this->params['allowOrDeny'] = 'Allow';
        $this->dispatchUrl('/oauth/authorize/submit', null);
        $json = JsonComponent::decode($this->getBody());
        $this->assertEquals($json['status'], 'error');
        $this->assertEquals($json['message'], 'Invalid username or password');

        // Test user denying the request
        $this->resetAll();
        $this->params = $params;
        $this->params['state'] = 'my_state_value';
        $this->params['scope'] = JsonComponent::encode($scopes);
        $this->params['allowOrDeny'] = 'Deny';
        $this->dispatchUrl('/oauth/authorize/submit', null);
        $json = JsonComponent::decode($this->getBody());
        $this->assertEquals($json['status'], 'ok');
        $this->assertEquals(
            $json['redirect'],
            $params['redirect_uri'].'?error=access_denied&state='.$this->params['state']
        );

        // Test user allowing the request
        $this->resetAll();
        $this->params = $params;
        $this->params['state'] = 'my_state_value';
        $this->params['scope'] = JsonComponent::encode($scopes);
        $this->params['allowOrDeny'] = 'Allow';
        $this->params['password'] = 'myPassword';
        $this->dispatchUrl('/oauth/authorize/submit', null);

        /** @var Oauth_CodeModel $codeModel */
        $codeModel = MidasLoader::loadModel('Code', 'oauth');
        $codeDaos = $codeModel->getByUser($user);
        $codeDao = end($codeDaos);

        $json = JsonComponent::decode($this->getBody());
        $this->assertEquals($json['status'], 'ok');
        $this->assertEquals(
            $json['redirect'],
            $params['redirect_uri'].'?code='.$codeDao->getCode().'&state='.$this->params['state']
        );
    }
}
