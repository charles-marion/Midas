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

require_once BASE_PATH.'/modules/api/tests/controllers/CallMethodsTestCase.php';

/** Tests the functionality of the web API methods */
class Api_CallFolderMethodsTest extends Api_CallMethodsTestCase
{
    /** Test creating a folder */
    public function testFolderCreate()
    {
        /** @var UserModel $userModel */
        $userModel = MidasLoader::loadModel('User');

        /** @var FolderModel $folderModel */
        $folderModel = MidasLoader::loadModel('Folder');

        $this->resetAll();
        $this->params['token'] = $this->_loginAsAdministrator();
        $this->params['method'] = 'midas.folder.create';
        $this->params['name'] = 'testFolderCreate';
        $this->params['parentid'] = '1001';
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);

        // Make sure folder was created correctly
        $this->assertNotEmpty($resp->data->uuid);
        $this->assertEquals(1001, $resp->data->parent_id);
        $this->assertEquals('testFolderCreate', $resp->data->name);
        $this->assertEquals('', $resp->data->description);
        // ensure privacy status is correct on returned array
        $this->assertEquals(
            $resp->data->privacy_status,
            MIDAS_PRIVACY_PUBLIC,
            'created folder has wrong privacy status'
        );
        // ensure default privacy is Public
        $this->assertPrivacyStatus(array($folderModel->load($resp->data->folder_id)), array(), MIDAS_PRIVACY_PUBLIC);

        // try to create a duplicate folder with same parent and same name, should fail
        $this->resetAll();
        $this->params['token'] = $this->_loginAsAdministrator();
        $this->params['method'] = 'midas.folder.create';
        $this->params['name'] = 'testFolderCreate';
        $this->params['parentid'] = '1001';
        $resp = $this->_callJsonApi();
        $this->_assertStatusFail($resp, MIDAS_INVALID_PARAMETER);

        // tests for creating a new folder passing in a privacy value
        $this->resetAll();
        $this->params['token'] = $this->_loginAsAdministrator();
        $this->params['method'] = 'midas.folder.create';
        $this->params['name'] = 'testFolderCreatePublic';
        $this->params['parentid'] = '1001';
        $this->params['privacy'] = 'Public';
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        // ensure privacy status is correct on returned array
        $this->assertEquals(
            $resp->data->privacy_status,
            MIDAS_PRIVACY_PUBLIC,
            'created folder has wrong privacy status'
        );
        $this->assertPrivacyStatus(array($folderModel->load($resp->data->folder_id)), array(), MIDAS_PRIVACY_PUBLIC);

        $this->resetAll();
        $this->params['token'] = $this->_loginAsAdministrator();
        $this->params['method'] = 'midas.folder.create';
        $this->params['name'] = 'testFolderCreatePrivate';
        $this->params['parentid'] = '1001';
        $this->params['privacy'] = 'Private';
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        // ensure privacy status is correct on returned array
        $this->assertEquals(
            $resp->data->privacy_status,
            MIDAS_PRIVACY_PRIVATE,
            'created folder has wrong privacy status'
        );
        // ensure privacy status is correct on loaded resource
        $this->assertPrivacyStatus(array($folderModel->load($resp->data->folder_id)), array(), MIDAS_PRIVACY_PRIVATE);

        // try to create a folder where have read access on the parent folder
        $this->resetAll();
        $this->params['token'] = $this->_loginAsNormalUser();
        $this->params['method'] = 'midas.folder.create';
        $this->params['name'] = 'testFolderCreate';
        $this->params['parentid'] = "1012";
        $resp = $this->_callJsonApi();
        $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);

        // now with write access
        $this->resetAll();
        $this->params['token'] = $this->_loginAsNormalUser();
        $this->params['method'] = 'midas.folder.create';
        $this->params['name'] = 'testFolderCreate';
        $this->params['parentid'] = "1013";
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);

        $userDao = $userModel->load('1');
        $readFolder = $folderModel->load('1012');
        $writeFolder = $folderModel->load('1013');
        $adminFolder = $folderModel->load('1014');
        $nonWrites = array($readFolder);
        $nonAdmins = array($readFolder, $writeFolder);
        $writes = array($writeFolder, $adminFolder);

        // try to set name with read, should fail
        foreach ($nonWrites as $folder) {
            $this->resetAll();
            $this->params['token'] = $this->_loginAsUser($userDao);
            $this->params['method'] = 'midas.folder.create';
            $this->params['name'] = 'readFolder';
            $this->params['uuid'] = $folder->getUuid();
            $resp = $this->_callJsonApi();
            $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);
        }
        // try to set name with writes, should pass
        foreach ($writes as $folder) {
            // get the current folder name
            $freshfolder = $folderModel->load($folder->getFolderId());
            $folderName = $freshfolder->getName();
            $newfolderName = $folderName."suffix";
            $this->resetAll();
            $this->params['token'] = $this->_loginAsUser($userDao);
            $this->params['method'] = 'midas.folder.create';
            $this->params['name'] = $newfolderName;
            $this->params['uuid'] = $folder->getUuid();
            $resp = $this->_callJsonApi();
            $this->_assertStatusOk($resp);
            $refreshFolder = $folderModel->load($folder->getFolderId());
            // ensure that the name was properly updated
            $this->assertEquals($newfolderName, $refreshFolder->getName(), 'Folder name should have been changed');
        }
        // try to set privacy without admin, should fail
        foreach ($nonAdmins as $folder) {
            $this->resetAll();
            $this->params['token'] = $this->_loginAsUser($userDao);
            $this->params['method'] = 'midas.folder.create';
            $this->params['name'] = 'writeFolder';
            $this->params['uuid'] = $folder->getUuid();
            $this->params['privacy'] = 'Private';
            $resp = $this->_callJsonApi();
            $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);
        }

        // try to set privacy to an invalid string
        $this->resetAll();
        $this->params['token'] = $this->_loginAsUser($userDao);
        $this->params['method'] = 'midas.folder.create';
        $this->params['name'] = 'writeFolder';
        $this->params['uuid'] = $adminFolder->getUuid();
        $this->params['privacy'] = 'El Duderino';
        $resp = $this->_callJsonApi();
        $this->_assertStatusFail($resp, MIDAS_INVALID_PARAMETER);

        // want to test changing privacy using this method
        // test cases   Public -> Public
        //              Public -> Private
        //              Private -> Private
        //              Private -> Public
        $privacyStatuses = array(MIDAS_PRIVACY_PUBLIC, MIDAS_PRIVACY_PRIVATE);
        $privacyStrings = array(MIDAS_PRIVACY_PUBLIC => "Public", MIDAS_PRIVACY_PRIVATE => "Private");
        foreach ($privacyStatuses as $initialStatus) {
            foreach ($privacyStatuses as $finalStatus) {
                $this->initializePrivacyStatus(array($adminFolder), array(), $initialStatus);

                // try to set privacy with admin, should pass
                $this->resetAll();
                $this->params['token'] = $this->_loginAsUser($userDao);
                $this->params['method'] = 'midas.folder.create';
                $this->params['name'] = 'writeFolder';
                $this->params['uuid'] = $adminFolder->getUuid();
                $this->params['privacy'] = $privacyStrings[$finalStatus];
                $resp = $this->_callJsonApi();
                $this->_assertStatusOk($resp);

                $adminFolder = $folderModel->load($adminFolder->getFolderId());
                $this->assertPrivacyStatus(array($adminFolder), array(), $finalStatus);
            }
        }
    }

    /** Test listing of child folders */
    public function testFolderChildren()
    {
        $this->resetAll();
        $token = $this->_loginAsNormalUser();
        $this->params['token'] = $token;
        $this->params['method'] = 'midas.folder.children';
        $this->params['id'] = 1000;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);

        // Should contain 2 folders and 0 items
        $this->assertEquals(count($resp->data->folders), 2);
        $this->assertEquals(count($resp->data->items), 0);

        $this->assertEquals($resp->data->folders[0]->folder_id, 1001);
        $this->assertEquals($resp->data->folders[1]->folder_id, 1002);
        $this->assertEquals($resp->data->folders[0]->name, 'User 1 name Folder 2');
        $this->assertEquals($resp->data->folders[1]->name, 'User 1 name Folder 3');
        $this->assertEquals($resp->data->folders[0]->description, 'Description Folder 2');
        $this->assertEquals($resp->data->folders[1]->description, 'Description Folder 3');

        $this->resetAll();
        $this->params['token'] = $token;
        $this->params['method'] = 'midas.folder.children';
        $this->params['id'] = 1001;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);

        // Should contain 0 folders and 2 items
        $this->assertEquals(count($resp->data->folders), 0);
        $this->assertEquals(count($resp->data->items), 3);

        $this->assertEquals($resp->data->items[0]->item_id, 1000);
        $this->assertEquals($resp->data->items[1]->item_id, 1001);
        $this->assertEquals($resp->data->items[0]->name, 'name 1');
        $this->assertEquals($resp->data->items[1]->name, 'name 2');
        $this->assertEquals($resp->data->items[0]->description, 'Description 1');
        $this->assertEquals($resp->data->items[1]->description, 'Description 2');
    }

    /** Test the folder.move method */
    public function testFolderMove()
    {
        $foldersFile = $this->loadData('Folder', 'default');

        $this->resetAll();
        $folderDao = $this->Folder->load($foldersFile[4]->getKey());
        $this->assertEquals($folderDao->getParentId(), '1000');

        $token = $this->_loginAsNormalUser();
        $this->params['token'] = $token;
        $this->params['method'] = 'midas.folder.move';
        $this->params['id'] = $foldersFile[4]->getKey();
        $this->params['dstfolderid'] = 1002;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);

        $this->assertEquals($resp->data->parent_id, '1002');

        // check that user can't move a folder they only have read on
        $this->resetAll();
        $this->params['token'] = $this->_loginAsNormalUser();
        $this->params['method'] = 'midas.folder.move';
        $this->params['id'] = '1012';
        $this->params['dstfolderid'] = '1002';
        $resp = $this->_callJsonApi();
        $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);

        // check that user can't move a folder they only have read on
        $this->resetAll();
        $this->params['token'] = $this->_loginAsNormalUser();
        $this->params['method'] = 'midas.folder.move';
        $this->params['id'] = '1012';
        $this->params['dstfolderid'] = '1002';
        $resp = $this->_callJsonApi();
        $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);

        // check that user can't move a folder they only have write on
        $this->resetAll();
        $this->params['token'] = $this->_loginAsNormalUser();
        $this->params['method'] = 'midas.folder.move';
        $this->params['id'] = '1013';
        $this->params['dstfolderid'] = '1002';
        $resp = $this->_callJsonApi();
        $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);

        // check that user can't move to a folder they only have read on
        $this->resetAll();
        $this->params['token'] = $this->_loginAsNormalUser();
        $this->params['method'] = 'midas.folder.move';
        $this->params['id'] = '1014';
        $this->params['dstfolderid'] = '1012';
        $resp = $this->_callJsonApi();
        $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);

        // check that user can move from admin to write
        $this->resetAll();
        $this->params['token'] = $this->_loginAsNormalUser();
        $this->params['method'] = 'midas.folder.move';
        $this->params['id'] = '1014';
        $this->params['dstfolderid'] = '1013';
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
    }

    /** Test the folder.list.permissions method */
    public function testFolderListPermissions()
    {
        /** @var UserModel $userModel */
        $userModel = MidasLoader::loadModel('User');
        $userDao = $userModel->load('1');

        /** @var FolderModel $folderModel */
        $folderModel = MidasLoader::loadModel('Folder');
        $readFolder = $folderModel->load('1012');
        $writeFolder = $folderModel->load('1013');
        $adminFolder = $folderModel->load('1014');
        $nonAdmins = array($readFolder, $writeFolder);

        $params = array('method' => 'midas.folder.list.permissions', 'token' => $this->_loginAsUser($userDao));

        // try to list permissions without admin, should fail
        foreach ($nonAdmins as $folder) {
            $this->resetAll();
            $params['folder_id'] = $folder->getFolderId();
            $this->params = $params;
            $resp = $this->_callJsonApi();
            $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);
        }

        // now with admin perms

        // first check both privacy statuses
        $privacyStatuses = array(MIDAS_PRIVACY_PUBLIC, MIDAS_PRIVACY_PRIVATE);

        foreach ($privacyStatuses as $privacyStatus) {
            $this->initializePrivacyStatus(array($adminFolder), array(), $privacyStatus);

            $this->resetAll();
            $params['folder_id'] = $adminFolder->getFolderId();
            $this->params = $params;
            $resp = $this->_callJsonApi();
            $this->_assertStatusOk($resp);

            $this->assertPrivacyStatus(array($adminFolder), array(), $privacyStatus);
        }

        // ensure user perms are correct from the most recent call
        $privilegeCodes = array(
            "Admin" => MIDAS_POLICY_ADMIN,
            "Write" => MIDAS_POLICY_WRITE,
            "Read" => MIDAS_POLICY_READ,
        );
        $userPolicies = $adminFolder->getFolderpolicyuser();
        $apiUserPolicies = $resp->data->user;
        foreach ($userPolicies as $userPolicy) {
            $user = $userPolicy->getUser();
            $userId = (string) $user->getUserId();
            $userFound = false;
            foreach ($apiUserPolicies as $apiUserPolicy) {
                if ($apiUserPolicy->user_id == $userId) {
                    $userFound = true;
                    $apiPolicyCode = $privilegeCodes[$apiUserPolicy->policy];
                    $this->assertEquals($apiPolicyCode, $userPolicy->getPolicy());
                }
            }
            $this->assertTrue($userFound, 'API call missing user '.$userId);
        }
        // ensure group perms are correct
        $groupPolicies = $adminFolder->getFolderpolicygroup();
        $apiGroupPolicies = $resp->data->group;
        foreach ($groupPolicies as $groupPolicy) {
            $group = $groupPolicy->getGroup();
            $groupId = (string) $group->getGroupId();
            $groupFound = false;
            foreach ($apiGroupPolicies as $apiGroupPolicy) {
                if ($apiGroupPolicy->group_id == $groupId) {
                    $groupFound = true;
                    $apiPolicyCode = $privilegeCodes[$apiGroupPolicy->policy];
                    $this->assertEquals($apiPolicyCode, $groupPolicy->getPolicy());
                }
            }
            $this->assertTrue($groupFound, 'API call missing group '.$groupId);
        }
    }

    /** Test the folder.set.privacy.recursive method */
    public function testFolderSetPrivacyRecursive()
    {
        /** @var UserModel $userModel */
        $userModel = MidasLoader::loadModel('User');

        /** @var ItemModel $itemModel */
        $itemModel = MidasLoader::loadModel('Item');

        /** @var FolderpolicyuserModel $folderpolicyuserModel */
        $folderpolicyuserModel = MidasLoader::loadModel("Folderpolicyuser");

        /** @var ItempolicyuserModel $itempolicyuserModel */
        $itempolicyuserModel = MidasLoader::loadModel("Itempolicyuser");

        $userDao = $userModel->load('1');

        /** @var FolderModel $folderModel */
        $folderModel = MidasLoader::loadModel('Folder');
        $readFolder = $folderModel->load('1012');
        $writeFolder = $folderModel->load('1013');
        $adminFolder = $folderModel->load('1014');
        $nonAdmins = array($readFolder, $writeFolder);

        $params = array('method' => 'midas.folder.set.privacy.recursive', 'token' => $this->_loginAsUser($userDao));

        // try to list permissions without admin, should fail
        foreach ($nonAdmins as $folder) {
            $this->resetAll();
            $params['folder_id'] = $folder->getFolderId();
            $params['privacy'] = 'Public';
            $this->params = $params;
            $resp = $this->_callJsonApi();
            $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);
        }

        // now with admin perms

        // first create a folder structure
        // -testrootfolder
        // --childfolder1
        // ---childfolder11
        // ----item111
        // --childfolder2
        // ---item21
        $testrootfolder = $folderModel->createFolder('testrootfolder', 'testrootfolder', $adminFolder);
        $childfolder1 = $folderModel->createFolder('childfolder1', 'childfolder1', $testrootfolder);
        $childfolder11 = $folderModel->createFolder('childfolder11', 'childfolder11', $childfolder1);
        $childfolder2 = $folderModel->createFolder('childfolder2', 'childfolder2', $testrootfolder);

        $item111 = $itemModel->createItem('item111', 'item111', $childfolder11);
        $item21 = $itemModel->createItem('item21', 'item21', $childfolder2);

        $testFolders = array($testrootfolder, $childfolder1, $childfolder11, $childfolder2);
        $testItems = array($item111, $item21);

        // set the user as an Admin on these test resources
        foreach ($testFolders as $folder) {
            $folderpolicyuserModel->createPolicy($userDao, $folder, MIDAS_POLICY_ADMIN);
            $folderModel->save($folder);
        }
        foreach ($testItems as $item) {
            $itempolicyuserModel->createPolicy($userDao, $item, MIDAS_POLICY_ADMIN);
            $itemModel->save($item);
        }

        // want to test Public -> Public
        //              Public -> Private
        //              Private -> Private
        //              Private -> Public
        $privacyStatuses = array(MIDAS_PRIVACY_PUBLIC, MIDAS_PRIVACY_PRIVATE);
        $privacyStrings = array(MIDAS_PRIVACY_PUBLIC => "Public", MIDAS_PRIVACY_PRIVATE => "Private");
        foreach ($privacyStatuses as $initialStatus) {
            foreach ($privacyStatuses as $finalStatus) {
                $this->initializePrivacyStatus($testFolders, $testItems, $initialStatus);
                // change privacy through the API
                $this->resetAll();
                $params['folder_id'] = $testrootfolder->getFolderId();
                $params['privacy'] = $privacyStrings[$finalStatus];
                $this->params = $params;
                $resp = $this->_callJsonApi();
                $this->_assertStatusOk($resp);
                $this->assertEquals(
                    $resp->data->success,
                    count($testItems) + count($testFolders),
                    'Have set the privacy on an incorrect number of resources'
                );
                $this->assertPrivacyStatus($testFolders, $testItems, $finalStatus);
            }
        }
    }

    /**
     * Test the folder.add.policygroup and folder.remove.policygroup api calls.
     */
    public function testFolderAddRemovePolicygroup()
    {
        /** @var UserModel $userModel */
        $userModel = MidasLoader::loadModel('User');

        /** @var ItemModel $itemModel */
        $itemModel = MidasLoader::loadModel('Item');

        /** @var GroupModel $groupModel */
        $groupModel = MidasLoader::loadModel('Group');

        /** @var FolderpolicyuserModel $folderpolicyuserModel */
        $folderpolicyuserModel = MidasLoader::loadModel("Folderpolicyuser");

        /** @var ItempolicyuserModel $itempolicyuserModel */
        $itempolicyuserModel = MidasLoader::loadModel("Itempolicyuser");

        $userDao = $userModel->load('1');

        /** @var FolderModel $folderModel */
        $folderModel = MidasLoader::loadModel('Folder');
        $readFolder = $folderModel->load('1012');
        $writeFolder = $folderModel->load('1013');
        $adminFolder = $folderModel->load('1014');
        $nonAdmins = array($readFolder, $writeFolder);

        $params = array('method' => 'midas.folder.add.policygroup', 'token' => $this->_loginAsUser($userDao));

        $deletioncommModeratorGroup = $groupModel->load('3004');
        $deletioncommMemberGroup = $groupModel->load('3005');

        // try to add without admin, should fail
        foreach ($nonAdmins as $folder) {
            $this->resetAll();
            $params['folder_id'] = $folder->getFolderId();
            $params['group_id'] = $deletioncommMemberGroup->getGroupId();
            $params['policy'] = 'Admin';
            $this->params = $params;
            $resp = $this->_callJsonApi();
            $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);
        }

        // try to set an invalid policy, should fail
        $this->resetAll();
        $params['folder_id'] = $adminFolder->getFolderId();
        $params['group_id'] = $deletioncommMemberGroup->getGroupId();
        $params['policy'] = 'Arithmatic';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusFail($resp, MIDAS_INVALID_PARAMETER);

        // create a structure with a root folder, with two children, one grandchild,
        // and 1 item in each of these folders
        $testrootfolder = $folderModel->createFolder('testrootfolder', 'testrootfolder', $adminFolder);
        $childfolder1 = $folderModel->createFolder('childfolder1', 'childfolder1', $testrootfolder);
        $childfolder11 = $folderModel->createFolder('childfolder11', 'childfolder11', $childfolder1);
        $childfolder2 = $folderModel->createFolder('childfolder2', 'childfolder2', $testrootfolder);

        $itemroot = $itemModel->createItem('itemroot', 'itemroot', $childfolder11);
        $item1 = $itemModel->createItem('item1', 'item1', $childfolder1);
        $item11 = $itemModel->createItem('item11', 'item11', $childfolder11);
        $item2 = $itemModel->createItem('item2', 'item2', $childfolder2);

        $testFolders = array($testrootfolder, $childfolder1, $childfolder11, $childfolder2);
        $testFoldersWithoutRoot = array($childfolder1, $childfolder11, $childfolder2);
        $testItems = array($itemroot, $item1, $item11, $item2);

        // set the user as an Admin on these test resources
        foreach ($testFolders as $folder) {
            $folderpolicyuserModel->createPolicy($userDao, $folder, MIDAS_POLICY_ADMIN);
            $folderModel->save($folder);
        }
        foreach ($testItems as $item) {
            $itempolicyuserModel->createPolicy($userDao, $item, MIDAS_POLICY_ADMIN);
            $itemModel->save($item);
        }

        // add a policy to root, check that root has the policy and no children do
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['group_id'] = $deletioncommMemberGroup->getGroupId();
        $params['policy'] = 'Write';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            1,
            'Have added a foldergrouppolicy on an incorrect number of resources'
        );

        $this->assertPolicygroupExistence(
            array($testrootfolder),
            array(),
            $deletioncommMemberGroup,
            MIDAS_POLICY_WRITE
        );
        $this->assertPolicygroupNonexistence($testFoldersWithoutRoot, $testItems, $deletioncommMemberGroup);

        // add a second policy to root, check that root has and no children do
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['group_id'] = $deletioncommModeratorGroup->getGroupId();
        $params['policy'] = 'Read';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            1,
            'Have added a foldergrouppolicy on an incorrect number of resources'
        );

        $this->assertPolicygroupExistence(
            array($testrootfolder),
            array(),
            $deletioncommMemberGroup,
            MIDAS_POLICY_WRITE
        );
        $this->assertPolicygroupNonexistence($testFoldersWithoutRoot, $testItems, $deletioncommMemberGroup);
        $this->assertPolicygroupExistence(
            array($testrootfolder),
            array(),
            $deletioncommModeratorGroup,
            MIDAS_POLICY_READ
        );
        $this->assertPolicygroupNonexistence($testFoldersWithoutRoot, $testItems, $deletioncommModeratorGroup);

        // change the second policy
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['group_id'] = $deletioncommModeratorGroup->getGroupId();
        $params['policy'] = 'Admin';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            1,
            'Have added a foldergrouppolicy on an incorrect number of resources'
        );

        $this->assertPolicygroupExistence(
            array($testrootfolder),
            array(),
            $deletioncommMemberGroup,
            MIDAS_POLICY_WRITE
        );
        $this->assertPolicygroupNonexistence($testFoldersWithoutRoot, $testItems, $deletioncommMemberGroup);
        $this->assertPolicygroupExistence(
            array($testrootfolder),
            array(),
            $deletioncommModeratorGroup,
            MIDAS_POLICY_ADMIN
        );
        $this->assertPolicygroupNonexistence($testFoldersWithoutRoot, $testItems, $deletioncommModeratorGroup);

        // change the first policy recursively
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['group_id'] = $deletioncommMemberGroup->getGroupId();
        $params['policy'] = 'Read';
        $params['recursive'] = 'recursive';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            count($testFolders) + count($testItems),
            'Have added a foldergrouppolicy on an incorrect number of resources'
        );

        $this->assertPolicygroupExistence($testFolders, $testItems, $deletioncommMemberGroup, MIDAS_POLICY_READ);
        $this->assertPolicygroupExistence($testFolders, $testItems, $deletioncommModeratorGroup, MIDAS_POLICY_ADMIN);

        // test remove
        $params = array('method' => 'midas.folder.remove.policygroup', 'token' => $params['token']);

        // try to remove without admin, should fail
        foreach ($nonAdmins as $folder) {
            $this->resetAll();
            $params['folder_id'] = $folder->getFolderId();
            $params['group_id'] = $deletioncommMemberGroup->getGroupId();
            $this->params = $params;
            $resp = $this->_callJsonApi();
            $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);
        }

        // remove the member policy only from the testrootfolder
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['group_id'] = $deletioncommMemberGroup->getGroupId();
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            1,
            'Have removed a foldergrouppolicy from an incorrect number of resources'
        );

        $this->assertPolicygroupExistence(
            $testFoldersWithoutRoot,
            $testItems,
            $deletioncommMemberGroup,
            MIDAS_POLICY_READ
        );
        $this->assertPolicygroupExistence($testFolders, $testItems, $deletioncommModeratorGroup, MIDAS_POLICY_ADMIN);
        $this->assertPolicygroupNonexistence(array($testrootfolder), array(), $deletioncommMemberGroup);

        // remove the moderator policy testrootfolder recursively
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['group_id'] = $deletioncommModeratorGroup->getGroupId();
        $params['recursive'] = 'recursive';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            count($testFolders) + count($testItems),
            'Have removed a foldergrouppolicy from an incorrect number of resources'
        );

        $this->assertPolicygroupNonexistence($testFolders, $testItems, $deletioncommMemberGroup);
        $this->assertPolicygroupNonexistence($testFolders, $testItems, $deletioncommModeratorGroup);
    }

    /**
     * Test the folder.add.policygroup and folder.remove.policyuser api calls.
     */
    public function testFolderAddRemovePolicyuser()
    {
        /** @var UserModel $userModel */
        $userModel = MidasLoader::loadModel('User');

        /** @var ItemModel $itemModel */
        $itemModel = MidasLoader::loadModel('Item');

        /** @var FolderpolicyuserModel $folderpolicyuserModel */
        $folderpolicyuserModel = MidasLoader::loadModel("Folderpolicyuser");

        /** @var ItempolicyuserModel $itempolicyuserModel */
        $itempolicyuserModel = MidasLoader::loadModel("Itempolicyuser");

        $userDao = $userModel->load('1');
        $folderModel = MidasLoader::loadModel('Folder');
        $readFolder = $folderModel->load('1012');
        $writeFolder = $folderModel->load('1013');
        $adminFolder = $folderModel->load('1014');
        $nonAdmins = array($readFolder, $writeFolder);

        $params = array('method' => 'midas.folder.add.policyuser', 'token' => $this->_loginAsUser($userDao));

        $targetUser = $userModel->load('2');
        $targetUser3 = $userModel->load('3');

        // try to add without admin, should fail
        foreach ($nonAdmins as $folder) {
            $this->resetAll();
            $params['folder_id'] = $folder->getFolderId();
            $params['user_id'] = $targetUser->getUserId();
            $params['policy'] = 'Admin';
            $this->params = $params;
            $resp = $this->_callJsonApi();
            $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);
        }

        // try to set an invalid policy, should fail
        $this->resetAll();
        $params['folder_id'] = $adminFolder->getFolderId();
        $params['user_id'] = $targetUser->getUserId();
        $params['policy'] = 'Arithmatic';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusFail($resp, MIDAS_INVALID_PARAMETER);

        // create a structure with a root folder, with two children, one grandchild,
        // and 1 item in each of these folders
        $testrootfolder = $folderModel->createFolder('testrootfolder', 'testrootfolder', $adminFolder);
        $childfolder1 = $folderModel->createFolder('childfolder1', 'childfolder1', $testrootfolder);
        $childfolder11 = $folderModel->createFolder('childfolder11', 'childfolder11', $childfolder1);
        $childfolder2 = $folderModel->createFolder('childfolder2', 'childfolder2', $testrootfolder);

        $itemroot = $itemModel->createItem('itemroot', 'itemroot', $childfolder11);
        $item1 = $itemModel->createItem('item1', 'item1', $childfolder1);
        $item11 = $itemModel->createItem('item11', 'item11', $childfolder11);
        $item2 = $itemModel->createItem('item2', 'item2', $childfolder2);

        $testFolders = array($testrootfolder, $childfolder1, $childfolder11, $childfolder2);
        $testFoldersWithoutRoot = array($childfolder1, $childfolder11, $childfolder2);
        $testItems = array($itemroot, $item1, $item11, $item2);

        // set the user as an Admin on these test resources
        foreach ($testFolders as $folder) {
            $folderpolicyuserModel->createPolicy($userDao, $folder, MIDAS_POLICY_ADMIN);
            $folderModel->save($folder);
        }
        foreach ($testItems as $item) {
            $itempolicyuserModel->createPolicy($userDao, $item, MIDAS_POLICY_ADMIN);
            $itemModel->save($item);
        }

        // add a policy to root, check that root has the policy and no children do
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['user_id'] = $targetUser->getUserId();
        $params['policy'] = 'Write';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            1,
            'Have added a folderuserpolicy on an incorrect number of resources'
        );

        $this->assertPolicyuserExistence(array($testrootfolder), array(), $targetUser, MIDAS_POLICY_WRITE);
        $this->assertPolicyuserNonexistence($testFoldersWithoutRoot, $testItems, $targetUser);

        // add a second policy to root, check that root has and no children do
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['user_id'] = $targetUser3->getUserId();
        $params['policy'] = 'Read';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            1,
            'Have added a folderuserpolicy on an incorrect number of resources'
        );

        $this->assertPolicyuserExistence(array($testrootfolder), array(), $targetUser, MIDAS_POLICY_WRITE);
        $this->assertPolicyuserNonexistence($testFoldersWithoutRoot, $testItems, $targetUser);
        $this->assertPolicyuserExistence(array($testrootfolder), array(), $targetUser3, MIDAS_POLICY_READ);
        $this->assertPolicyuserNonexistence($testFoldersWithoutRoot, $testItems, $targetUser3);

        // change the second policy
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['user_id'] = $targetUser3->getUserId();
        $params['policy'] = 'Admin';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            1,
            'Have added a folderuserpolicy on an incorrect number of resources'
        );

        $this->assertPolicyuserExistence(array($testrootfolder), array(), $targetUser, MIDAS_POLICY_WRITE);
        $this->assertPolicyuserNonexistence($testFoldersWithoutRoot, $testItems, $targetUser);
        $this->assertPolicyuserExistence(array($testrootfolder), array(), $targetUser3, MIDAS_POLICY_ADMIN);
        $this->assertPolicyuserNonexistence($testFoldersWithoutRoot, $testItems, $targetUser3);

        // change the first policy recursively
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['user_id'] = $targetUser->getUserId();
        $params['policy'] = 'Read';
        $params['recursive'] = 'recursive';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            count($testFolders) + count($testItems),
            'Have added a folderuserpolicy on an incorrect number of resources'
        );

        $this->assertPolicyuserExistence($testFolders, $testItems, $targetUser, MIDAS_POLICY_READ);
        $this->assertPolicyuserExistence($testFolders, $testItems, $targetUser3, MIDAS_POLICY_ADMIN);

        // test remove
        $params = array('method' => 'midas.folder.remove.policyuser', 'token' => $params['token']);

        // try to remove without admin, should fail
        foreach ($nonAdmins as $folder) {
            $this->resetAll();
            $params['folder_id'] = $folder->getFolderId();
            $params['user_id'] = $targetUser->getUserId();
            $this->params = $params;
            $resp = $this->_callJsonApi();
            $this->_assertStatusFail($resp, MIDAS_INVALID_POLICY);
        }

        // remove the targetUser policy only from the testrootfolder
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['user_id'] = $targetUser->getUserId();
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            1,
            'Have removed a folderuserpolicy from an incorrect number of resources'
        );

        $this->assertPolicyuserExistence($testFoldersWithoutRoot, $testItems, $targetUser, MIDAS_POLICY_READ);
        $this->assertPolicyuserExistence($testFolders, $testItems, $targetUser3, MIDAS_POLICY_ADMIN);
        $this->assertPolicyuserNonexistence(array($testrootfolder), array(), $targetUser);

        // remove the targetUser3 policy testrootfolder recursively
        $this->resetAll();
        $params['folder_id'] = $testrootfolder->getFolderId();
        $params['user_id'] = $targetUser3->getUserId();
        $params['recursive'] = 'recursive';
        $this->params = $params;
        $resp = $this->_callJsonApi();
        $this->_assertStatusOk($resp);
        $this->assertEquals(
            $resp->data->success,
            count($testFolders) + count($testItems),
            'Have removed a foldergrouppolicy from an incorrect number of resources'
        );

        $this->assertPolicyuserNonexistence($testFolders, $testItems, $targetUser);
        $this->assertPolicyuserNonexistence($testFolders, $testItems, $targetUser3);
    }
}
