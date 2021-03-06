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

/** GroupModelBase */
abstract class GroupModelBase extends AppModel
{
    /** Constructor */
    public function __construct()
    {
        parent::__construct();
        $this->_name = 'group';
        $this->_key = 'group_id';
        $this->_mainData = array(
            'group_id' => array('type' => MIDAS_DATA),
            'community_id' => array('type' => MIDAS_DATA),
            'name' => array('type' => MIDAS_DATA),
            'community' => array(
                'type' => MIDAS_MANY_TO_ONE,
                'model' => 'Community',
                'parent_column' => 'community_id',
                'child_column' => 'community_id',
            ),
            'users' => array(
                'type' => MIDAS_MANY_TO_MANY,
                'model' => 'User',
                'table' => 'user2group',
                'parent_column' => 'group_id',
                'child_column' => 'user_id',
            ),
        );
        $this->initialize(); // required
    }

    /** Add a user to a group */
    abstract public function addUser($group, $user);

    /** Remove user */
    abstract public function removeUser($group, $user);

    /** Find by community */
    abstract public function findByCommunity($communityDao);

    /** Get group from search */
    abstract public function getGroupFromSearch($search, $limit = 14);

    /** load */
    public function load($key = null)
    {
        if ($key == MIDAS_GROUP_ANONYMOUS_KEY) {
            /** @var GroupDao $dao */
            $dao = MidasLoader::newDao('GroupDao');
            $dao->setGroupId(MIDAS_GROUP_ANONYMOUS_KEY);
            $dao->setCommunityId(0);
            $dao->setName('Anonymous');
            $dao->saved = true;

            return $dao;
        } elseif ($key == MIDAS_GROUP_SERVER_KEY) {
            /** @var GroupDao $dao */
            $dao = MidasLoader::newDao('GroupDao');
            $dao->setGroupId(MIDAS_GROUP_SERVER_KEY);
            $dao->setCommunityId(0);
            $dao->setName('Servers');
            $dao->saved = true;

            return $dao;
        } else {
            return parent::load($key);
        }
    }

    /** Delete a group */
    public function delete($group)
    {
        if (!$group instanceof GroupDao) {
            throw new Zend_Exception("Should be a group.");
        }
        $users = $group->getUsers();
        foreach ($users as $user) {
            $this->removeUser($group, $user);
        }

        /** @var FeedpolicygroupModel $feedpolicygroupModel */
        $feedpolicygroupModel = MidasLoader::loadModel('Feedpolicygroup');
        $feedpolicygroupModel->deleteGroupPolicies($group);

        /** @var ItempolicygroupModel $itempolicygroupModel */
        $itempolicygroupModel = MidasLoader::loadModel('Itempolicygroup');
        $itempolicygroupModel->deleteGroupPolicies($group);

        /** @var FolderpolicygroupModel $folderpolicygroupModel */
        $folderpolicygroupModel = MidasLoader::loadModel('Folderpolicygroup');
        $folderpolicygroupModel->deleteGroupPolicies($group);

        /** @var NewUserInvitationModel $newUserInvitationModel */
        $newUserInvitationModel = MidasLoader::loadModel('NewUserInvitation');
        $newUserInvitationModel->deleteByGroup($group);

        parent::delete($group);
        unset($group->group_id);
        $group->saved = false;
    }

    /** create a group
     *
     * @return GroupDao
     */
    public function createGroup($communityDao, $name)
    {
        if (!$communityDao instanceof CommunityDao) {
            throw new Zend_Exception("Should be a acommunity.");
        }
        if (!is_string($name)) {
            throw new Zend_Exception("Should be a string.");
        }

        /** @var GroupDao $group */
        $group = MidasLoader::newDao('GroupDao');
        $group->setName($name);
        $group->setCommunityId($communityDao->getCommunityId());
        $this->save($group);

        return $group;
    }

    /**
     * Return (bool) whether or not the user is in the group
     */
    public function userInGroup($user, $group)
    {
        if (!$user instanceof UserDao) {
            throw new Zend_Exception('Should be a user');
        }
        if (!$group instanceof GroupDao) {
            throw new Zend_Exception('Should be a group');
        }

        foreach ($user->getGroups() as $usergroup) {
            if ($usergroup->getKey() == $group->getKey()) {
                return true;
            }
        }

        return false;
    }
}
