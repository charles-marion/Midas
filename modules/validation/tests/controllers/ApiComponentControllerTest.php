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

/** Tests the functionality of the web API methods */
class Validation_ApiComponentControllerTest extends ControllerTestCase
{
    /** set up tests */
    public function setUp()
    {
        $db = Zend_Registry::get('dbAdapter');
        $configDatabase = Zend_Registry::get('configDatabase');
        if ($configDatabase->database->adapter == 'PDO_PGSQL') {
            $db->query(
                "SELECT setval('validation_dashboard_dashboard_id_seq', (SELECT MAX(dashboard_id) FROM validation_dashboard)+1);"
            );
            $db->query(
                "SELECT setval('validation_dashboard2folder_dashboard2folder_id_seq', (SELECT MAX(dashboard2folder_id) FROM validation_dashboard2folder)+1);"
            );
            $db->query(
                "SELECT setval('validation_dashboard2scalarresult_dashboard2scalarresult_id_seq', (SELECT MAX(dashboard2scalarresult_id) FROM  validation_dashboard2scalarresult)+1);"
            );
            $db->query(
                "SELECT setval('validation_scalarresult_scalarresult_id_seq', (SELECT MAX(scalarresult_id) FROM validation_scalarresult)+1);"
            );
        }
        $this->setupDatabase(array('default')); // core dataset
        $this->setupDatabase(array('default'), 'validation'); // module dataset
        $this->enabledModules = array('api', 'validation');
        $this->_models = array('User', 'Folder');
        $this->_daos = array('User', 'Folder');

        parent::setUp();
    }

    /** Invoke the JSON web API */
    private function _callJsonApi($sessionUser = null)
    {
        $this->dispatchUrl($this->webroot.'api/json', $sessionUser);

        return json_decode($this->getBody());
    }

    /** Make sure we got a good response from a web API call */
    private function _assertStatusOk($resp)
    {
        $this->assertNotEquals($resp, false);
        $this->assertEquals($resp->message, '');
        $this->assertEquals($resp->stat, 'ok');
        $this->assertEquals($resp->code, 0);
        $this->assertTrue(isset($resp->data));
    }

    /** Test to see that the response is bad (for testing exceptional cases) */
    private function _assertStatusFailed($resp)
    {
        $this->assertEquals($resp->stat, "fail");
        $this->assertEquals($resp->code, -1);
    }

    /** Authenticate using the default api key */
    private function _loginUsingApiKey()
    {
        $usersFile = $this->loadData('User', 'default');
        $userDao = $this->User->load($usersFile[0]->getKey());

        /** @var UserapiModel $userApiModel */
        $userApiModel = MidasLoader::loadModel('Userapi');
        $userApiModel->createDefaultApiKey($userDao);
        $apiKey = $userApiModel->getByAppAndUser('Default', $userDao)->getApikey();

        $this->params['method'] = 'midas.login';
        $this->params['email'] = $usersFile[0]->getEmail();
        $this->params['appname'] = 'Default';
        $this->params['apikey'] = $apiKey;
        $this->request->setMethod('POST');

        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(strlen($resp->data->token), 32);

        // **IMPORTANT** This will clear any params that were set before this
        // function was called
        $this->resetAll();

        return $resp->data->token;
    }

    /** Authenticate using the default api key */
    private function _loginUsingApiKeyAsAdmin()
    {
        $usersFile = $this->loadData('User', 'default');
        $userDao = $this->User->load($usersFile[0]->getKey());
        $userDao->setAdmin(1);
        $this->User->save($userDao);

        /** @var UserapiModel $userApiModel */
        $userApiModel = MidasLoader::loadModel('Userapi');
        $userApiModel->createDefaultApiKey($userDao);
        $apiKey = $userApiModel->getByAppAndUser('Default', $userDao)->getApikey();

        $this->params['method'] = 'midas.login';
        $this->params['email'] = $usersFile[0]->getEmail();
        $this->params['appname'] = 'Default';
        $this->params['apikey'] = $apiKey;
        $this->request->setMethod('POST');

        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(strlen($resp->data->token), 32);

        // **IMPORTANT** This will clear any params that were set before this
        // function was called
        $this->resetAll();

        return $resp->data->token;
    }

    /** test getAllDashboards */
    public function testGetAllDashboards()
    {
        $this->params['method'] = 'midas.validation.getalldashboards';
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $dashboards = $resp->data;
        $this->assertEquals(1, count($dashboards));
        $this->assertEquals($dashboards[0]->dashboard_id, "1");
        $this->assertEquals($dashboards[0]->owner_id, "1");
        $this->assertEquals($dashboards[0]->name, "foo");
        $this->assertEquals($dashboards[0]->description, "bar");
        $this->assertEquals($dashboards[0]->truthfolder_id, "1");
        $this->assertEquals($dashboards[0]->testingfolder_id, "2");
        $this->assertEquals($dashboards[0]->trainingfolder_id, "3");
    }

    /** test getDashboard */
    public function testGetDashboard()
    {
        $this->params['method'] = 'midas.validation.getdashboard';
        $this->params['dashboard_id'] = 1;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $dashboard = $resp->data;
        $this->assertEquals($dashboard->dashboard_id, "1");
        $this->assertEquals($dashboard->owner_id, "1");
        $this->assertEquals($dashboard->name, "foo");
        $this->assertEquals($dashboard->description, "bar");
        $this->assertEquals($dashboard->truthfolder_id, "1");
        $this->assertEquals($dashboard->testingfolder_id, "2");
        $this->assertEquals($dashboard->trainingfolder_id, "3");
    }

    /** test getDashboard (failure case) */
    public function testGetDashboardFailure()
    {
        $this->params['method'] = 'midas.validation.getdashboard';
        $this->params['dashboard_id'] = 2;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->assertEquals($resp->message, "No dashboard found with that id.");
        $this->assertEquals($resp->stat, "fail");
        $this->assertEquals($resp->code, -1);
    }

    /** test createDashboard */
    public function testCreateDashboard()
    {
        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.createdashboard';
        $this->params['name'] = "testing123";
        $this->params['description'] = "testing456";
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $dashboardId = $resp->data->dashboard_id;
        $this->resetAll();

        $this->params['method'] = 'midas.validation.getdashboard';
        $this->params['dashboard_id'] = $dashboardId;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $dashboard = $resp->data;
        $this->assertEquals($dashboard->dashboard_id, $dashboardId);
        $this->assertEquals($dashboard->name, "testing123");
        $this->assertEquals($dashboard->description, "testing456");
    }

    /** test createDashboard (without admin credentials)*/
    public function testCreateDashboardFailure()
    {
        // Test as anon
        $this->params['method'] = 'midas.validation.createdashboard';
        $this->params['name'] = "testing123";
        $this->params['description'] = "testing456";
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->assertEquals($resp->message, "Only an admin can create a dashboard.");
        $this->assertEquals($resp->stat, "fail");
        $this->assertEquals($resp->code, -1);
        $this->resetAll();

        // Test as normal user
        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.createdashboard';
        $this->params['name'] = "testing123";
        $this->params['description'] = "testing456";
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->assertEquals($resp->message, "Only an admin can create a dashboard.");
        $this->assertEquals($resp->stat, "fail");
        $this->assertEquals($resp->code, -1);
    }

    /** test setTestingFolder */
    public function testSetTestingFolder()
    {
        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.settestingfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals($resp->data->dashboard_id, 1);
        $this->resetAll();

        $this->params['method'] = 'midas.validation.getdashboard';
        $this->params['dashboard_id'] = 1;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $dashboard = $resp->data;
        $this->assertEquals($dashboard->testingfolder_id, 1000);
    }

    /** test setTrainingFolder */
    public function testSetTrainingFolder()
    {
        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.settrainingfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals($resp->data->dashboard_id, 1);
        $this->resetAll();

        $this->params['method'] = 'midas.validation.getdashboard';
        $this->params['dashboard_id'] = 1;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $dashboard = $resp->data;
        $this->assertEquals($dashboard->trainingfolder_id, 1000);
    }

    /** test setTruthFolder */
    public function testSetTruthFolder()
    {
        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.settruthfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals($resp->data->dashboard_id, 1);
        $this->resetAll();

        $this->params['method'] = 'midas.validation.getdashboard';
        $this->params['dashboard_id'] = 1;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $dashboard = $resp->data;
        $this->assertEquals($dashboard->truthfolder_id, 1000);
    }

    /**
     * Test the setting of testing, training, and truth as anonymous user.
     */
    public function testSetFoldersAnonymously()
    {
        $this->params['method'] = 'midas.validation.settestingfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
        $this->resetAll();

        $this->params['method'] = 'midas.validation.settrainingfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
        $this->resetAll();

        $this->params['method'] = 'midas.validation.settruthfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
        $this->resetAll();
    }

    /**
     * Test the setting of testing, training, and truth as non-admin user.
     */
    public function testSetFoldersAsNonAdmin()
    {
        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.settestingfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
        $this->resetAll();

        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.settrainingfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
        $this->resetAll();

        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.settruthfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
        $this->resetAll();
    }

    /**
     * Test the setting of testing, training, and truth as as admin, but with
     * an invalid folder.
     */
    public function testSetFoldersWithInvalidFolders()
    {
        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.settestingfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1337;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
        $this->resetAll();

        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.settrainingfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1337;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
        $this->resetAll();

        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.settruthfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1337;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
        $this->resetAll();
    }

    /**
     * Test adding a result folder as a user and as anonymous to get either a
     * valid addition or a failure respectively.
     */
    public function testAddResultFolder()
    {
        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1, $resp->data->dashboard_id);
        $this->resetAll();

        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1337;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
        $this->resetAll();

        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
    }

    /**
     * Test removing a result folder as an administrative user
     */
    public function testRemoveResultFolderAsAdmin()
    {
        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1, $resp->data->dashboard_id);
        $this->resetAll();

        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.removeresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1, $resp->data->dashboard_id);
    }

    /**
     * Test removing a result folder
     */
    public function testRemoveResultFolderAsUser()
    {
        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1, $resp->data->dashboard_id);
        $this->resetAll();

        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.removeresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
    }

    /**
     * Test removing a result folder as anonymous
     */
    public function testRemoveResultFolderAsAnonymous()
    {
        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1, $resp->data->dashboard_id);
        $this->resetAll();

        $this->params['method'] = 'midas.validation.removeresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusFailed($resp);
    }

    /**
     * Test getting the result folders
     */
    public function testGetResults()
    {
        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1, $resp->data->dashboard_id);
        $this->resetAll();

        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1, $resp->data->dashboard_id);
        $this->resetAll();

        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1002;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1, $resp->data->dashboard_id);
        $this->resetAll();

        $this->params['method'] = 'midas.validation.getresultfolders';
        $this->params['dashboard_id'] = 1;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $expected = array(1000, 1001, 1002);
        sort($resp->data->results);
        $this->assertEquals($expected, $resp->data->results);
    }

    /**
     * Test setting and getting a scalar result
     */
    public function testGetSetScalarResult()
    {
        // Add a result folder
        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1, $resp->data->dashboard_id);
        $this->resetAll();

        // Add a scalar result
        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.setscalarresult';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->params['item_id'] = 1000;
        $this->params['value'] = 3.14;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->resetAll();

        $this->params['method'] = 'midas.validation.getscalarresult';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->params['item_id'] = 1000;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1000, $resp->data->item_id);
        $this->assertEquals(3.14, $resp->data->value);
        $this->resetAll();

        // Add a scalar result to the same slot as earlier
        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.setscalarresult';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->params['item_id'] = 1000;
        $this->params['value'] = 3.14;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
    }

    /**
     * Test getting scalar results in batch
     * @SuppressWarnings controlCloseCurly
     */
    public function testGetScores()
    {
        // Add a result folder
        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1, $resp->data->dashboard_id);
        $this->resetAll();

        // Add a scalar result
        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.setscalarresult';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->params['item_id'] = 1000;
        $this->params['value'] = 3;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->resetAll();

        // Add a scalar result
        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.setscalarresult';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->params['item_id'] = 1001;
        $this->params['value'] = 7;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->resetAll();

        $this->params['method'] = 'midas.validation.getscores';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $expected = array();
        $expected[1] = 3;
        $expected[2] = 7;
        $this->assertEquals(1, $resp->data->dashboard_id);
        $this->assertEquals($expected[1], $resp->data->scores->{1000});
        $this->assertEquals($expected[2], $resp->data->scores->{1001});
    }

    /**
     * Test getting all scalar results for a given dashboard
     * @SuppressWarnings controlCloseCurly
     */
    public function testGetAllScores()
    {
        // Acquire the dashboard from the database
        /** @var Validation_DashboardModel $dashboardModel */
        $dashboardModel = MidasLoader::loadModel('Dashboard', 'validation');

        /** @var ItemModel $itemModel */
        $itemModel = MidasLoader::loadModel('Item');

        /** @var FolderModel $folderModel */
        $folderModel = MidasLoader::loadModel('Folder');

        $dashboardDao = $dashboardModel->load(1);
        $folderDao = $folderModel->load(1000);

        // Create additional result item
        $resultItem = null;
        $createdItems = array();
        $expected = array();
        for ($i = 0; $i < 2; ++$i) {
            $resultItem = new ItemDao();
            $resultItem->setName('img0'.$i.'.mha');
            $resultItem->setDescription('result img '.$i);
            $resultItem->setType(0);
            $itemModel->save($resultItem);
            $createdItems[$resultItem->getKey()] = $i * 5 + 10;
            $folderModel->addItem($folderDao, $resultItem);
        }
        $expected[$folderDao->getKey()] = $createdItems;
        $dashboardModel->addResult($dashboardDao, $folderDao);
        $dashboardModel->setScores($dashboardDao, $folderDao, $createdItems);

        // Add a result folder
        $this->params['token'] = $this->_loginUsingApiKey();
        $this->params['method'] = 'midas.validation.addresultfolder';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(1, $resp->data->dashboard_id);
        $this->resetAll();

        // Add a scalar result
        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.setscalarresult';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->params['item_id'] = 1000;
        $this->params['value'] = 3;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->resetAll();

        // Add a scalar result
        $this->params['token'] = $this->_loginUsingApiKeyAsAdmin();
        $this->params['method'] = 'midas.validation.setscalarresult';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->params['item_id'] = 1001;
        $this->params['value'] = 7;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->resetAll();

        $this->params['method'] = 'midas.validation.getallscores';
        $this->params['dashboard_id'] = 1;
        $this->params['folder_id'] = 1001;
        $this->request->setMethod('POST');
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $expected[1001] = array();
        $expected[1001][1000] = 3;
        $expected[1001][1001] = 7;
        $this->assertEquals(1, $resp->data->dashboard_id);

        // Crazy looping because the json is parsed in as an object rather than
        // an array (not really a bad thing, just something to work around)
        $scores = $resp->data->scores;
        foreach ($expected as $folderId => $items) {
            foreach ($items as $itemId => $val) {
                $this->assertEquals($val, $scores->{$folderId}->{$itemId});
            }
        }
    }
}
