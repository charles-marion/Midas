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

/** UserModelBase */
abstract class UserModelBase extends AppModel
{
    /** Constructor */
    public function __construct()
    {
        parent::__construct();
        $this->_name = 'user';
        $this->_key = 'user_id';
        $this->_mainData = array(
            'user_id' => array('type' => MIDAS_DATA),
            'firstname' => array('type' => MIDAS_DATA),
            'lastname' => array('type' => MIDAS_DATA),
            'email' => array('type' => MIDAS_DATA),
            'thumbnail' => array('type' => MIDAS_DATA),
            'company' => array('type' => MIDAS_DATA),
            'hash_alg' => array('type' => MIDAS_DATA),
            'salt' => array('type' => MIDAS_DATA),
            'creation' => array('type' => MIDAS_DATA),
            'folder_id' => array('type' => MIDAS_DATA),
            'admin' => array('type' => MIDAS_DATA),
            'privacy' => array('type' => MIDAS_DATA),
            'view' => array('type' => MIDAS_DATA),
            'uuid' => array('type' => MIDAS_DATA),
            'city' => array('type' => MIDAS_DATA),
            'country' => array('type' => MIDAS_DATA),
            'website' => array('type' => MIDAS_DATA),
            'biography' => array('type' => MIDAS_DATA),
            'dynamichelp' => array('type' => MIDAS_DATA),
            'folder' => array(
                'type' => MIDAS_MANY_TO_ONE,
                'model' => 'Folder',
                'parent_column' => 'folder_id',
                'child_column' => 'folder_id',
            ),
            'groups' => array(
                'type' => MIDAS_MANY_TO_MANY,
                'model' => 'Group',
                'table' => 'user2group',
                'parent_column' => 'user_id',
                'child_column' => 'group_id',
            ),
            'invitations' => array(
                'type' => MIDAS_ONE_TO_MANY,
                'model' => 'CommunityInvitation',
                'parent_column' => 'user_id',
                'child_column' => 'user_id',
            ),
            'folderpolicyuser' => array(
                'type' => MIDAS_ONE_TO_MANY,
                'model' => 'Folderpolicyuser',
                'parent_column' => 'user_id',
                'child_column' => 'user_id',
            ),
            'itempolicyuser' => array(
                'type' => MIDAS_ONE_TO_MANY,
                'model' => 'Itempolicyuser',
                'parent_column' => 'user_id',
                'child_column' => 'user_id',
            ),
            'feeds' => array(
                'type' => MIDAS_ONE_TO_MANY,
                'model' => 'Feed',
                'parent_column' => 'user_id',
                'child_column' => 'user_id',
            ),
            'feedpolicyuser' => array(
                'type' => MIDAS_ONE_TO_MANY,
                'model' => 'Feedpolicyuser',
                'parent_column' => 'user_id',
                'child_column' => 'user_id',
            ),
            'itemrevisions' => array(
                'type' => MIDAS_ONE_TO_MANY,
                'model' => 'ItemRevision',
                'parent_column' => 'user_id',
                'child_column' => 'user_id',
            ),
        );
        $this->initialize(); // required
    }

    /** Get by email */
    abstract public function getByEmail($email);

    /** Get by user id */
    abstract public function getByUser_id($userid);

    /** Get by name */
    abstract public function getByName($firstName, $lastName);

    /** Get user communities */
    abstract public function getUserCommunities($userDao);

    /** Get by UUID */
    abstract public function getByUuid($uuid);

    /** Returns a user given its root folder */
    abstract public function getByFolder($folder);

    /** Returns all the users */
    abstract public function getAll(
        $onlyPublic = false,
        $limit = 20,
        $order = 'lastname',
        $offset = null,
        $currentUser = null
    );

    /** Store password hash */
    abstract public function storePasswordHash($hash);

    /** Legacy authenticate */
    abstract public function legacyAuthenticate($userDao, $instanceSalt, $password);

    /** save */
    public function save($dao)
    {
        if (!isset($dao->uuid) || empty($dao->uuid)) {
            /** @var UuidComponent $uuidComponent */
            $uuidComponent = MidasLoader::loadComponent('Uuid');
            $dao->setUuid($uuidComponent->generate());
        }
        parent::save($dao);
    }

    /**
     * Deletes a user and all record of their existence, including:
     * -Community invitations
     * -Policies (folder, item, and feed)
     * -User's folder tree
     * -Group memberships
     * -Itemrevision upload records (replace with superadmin)
     * -Feeds
     * Issues the CALLBACK_CORE_USER_DELETED signal that modules can use to handle user deletion events,
     * passing the argument 'userDao'. Signal is emitted before any core data is deleted.
     */
    public function delete($user)
    {
        Zend_Registry::get('notifier')->callback('CALLBACK_CORE_USER_DELETED', array('userDao' => $user));

        // Delete any community invitations for this user
        /** @var CommunityInvitationModel $ciModel */
        $ciModel = MidasLoader::loadModel('CommunityInvitation');
        $invitations = $user->getInvitations();
        foreach ($invitations as $invitation) {
            // Must call removeInvitation instead of delete so the corresponding feed is also deleted
            $ciModel->removeInvitation($invitation->getCommunity(), $user);
        }

        // Delete this user's folder tree recursively
        /** @var FolderModel $folderModel */
        $folderModel = MidasLoader::loadModel('Folder');
        $folderModel->delete($user->getFolder());

        // Delete remaining folder policies for the user
        /** @var FolderpolicyuserModel $folderpolicyuserModel */
        $folderpolicyuserModel = MidasLoader::loadModel('Folderpolicyuser');
        $folderpolicies = $user->getFolderpolicyuser();
        foreach ($folderpolicies as $folderpolicy) {
            $folderpolicyuserModel->delete($folderpolicy);
        }

        // Delete remaining item policies for the user
        /** @var ItempolicyuserModel $itempolicyuserModel */
        $itempolicyuserModel = MidasLoader::loadModel('Itempolicyuser');
        $itempolicies = $user->getItempolicyuser();
        foreach ($itempolicies as $itempolicy) {
            $itempolicyuserModel->delete($itempolicy);
        }

        // Delete all user's feeds
        /** @var FeedModel $feedModel */
        $feedModel = MidasLoader::loadModel('Feed');
        $feeds = $user->getFeeds();
        foreach ($feeds as $feed) {
            $feedModel->delete($feed);
        }

        // Delete remaining feed policies for the user
        /** @var FeedpolicyuserModel $feedpolicyuserModel */
        $feedpolicyuserModel = MidasLoader::loadModel('Feedpolicyuser');
        $feedpolicies = $user->getFeedpolicyuser();
        foreach ($feedpolicies as $feedpolicy) {
            $feedpolicyuserModel->delete($feedpolicy);
        }

        // Remove the user from all groups
        /** @var GroupModel $groupModel */
        $groupModel = MidasLoader::loadModel('Group');
        $groups = $user->getGroups();
        foreach ($groups as $group) {
            $groupModel->removeUser($group, $user);
        }

        // Remove references to this user as the uploader of item revisions (replace with superadmin)
        /** @var SettingModel $settingModel */
        $settingModel = MidasLoader::loadModel('Setting');
        $adminId = $settingModel->getValueByName('adminuser');

        /** @var ItemRevisionModel $itemRevisionModel */
        $itemRevisionModel = MidasLoader::loadModel('ItemRevision');
        $itemRevisions = $user->getItemrevisions();
        foreach ($itemRevisions as $revision) {
            $revision->setUserId($adminId);
            $itemRevisionModel->save($revision);
        }

        // Delete the user record
        parent::delete($user);
    }

    /** plus one view */
    public function incrementViewCount($userDao)
    {
        if (!$userDao instanceof UserDao) {
            throw new Zend_Exception("Error in param userDao when incrementing view count.");
        }
        $user = Zend_Registry::get('userSession');
        if (isset($user)) {
            if (isset($user->viewedUsers[$userDao->getKey()])) {
                return;
            } else {
                $user->viewedUsers[$userDao->getKey()] = true;
            }
        }
        $userDao->view++;
        $this->save($userDao);
    }

    /**
     * Users who existed prior to the great salting and hashing switch will be
     * transparently converted to the new system the next time they log in
     */
    public function convertLegacyPasswordHash($userDao, $password)
    {
        /** @var RandomComponent $randomComponent */
        $randomComponent = MidasLoader::loadComponent('Random');
        $userSalt = $randomComponent->generateString(32);
        $instanceSalt = Zend_Registry::get('configGlobal')->password->prefix;
        $hashedPassword = hash('sha256', $instanceSalt.$userSalt.$password);
        $this->storePasswordHash($hashedPassword);
        $userDao->setHashAlg('sha256');
        $userDao->setSalt($userSalt);
        $this->save($userDao);

        return $hashedPassword;
    }

    /**
     * Change a user's password by generating a new salt and re-hashing
     */
    public function changePassword($userDao, $password)
    {
        $instanceSalt = Zend_Registry::get('configGlobal')->password->prefix;

        /** @var RandomComponent $randomComponent */
        $randomComponent = MidasLoader::loadComponent('Random');
        $userSalt = $randomComponent->generateString(32);
        $hashedPassword = hash('sha256', $instanceSalt.$userSalt.$password);
        $this->storePasswordHash($hashedPassword);

        $userDao->setHashAlg('sha256');
        $userDao->setSalt($userSalt);
        $this->save($userDao);
    }

    /** create user */
    public function createUser($email, $password, $firstname, $lastname, $admin = 0, $salt = null)
    {
        if (!is_string($email) || empty($email) || !is_string($firstname) || empty($firstname) || !is_string(
                $lastname
            ) || empty($lastname) || !is_numeric($admin)
        ) {
            throw new Zend_Exception("Error in Parameters when creating a user.");
        }

        // Check if the user already exists based on the email address
        if ($this->getByEmail($email) !== false) {
            throw new Zend_Exception("User already exists.");
        }

        /** @var UserDao $userDao */
        $userDao = MidasLoader::newDao('UserDao');
        $userDao->setFirstname(ucfirst($firstname));
        $userDao->setLastname(ucfirst($lastname));
        $userDao->setEmail(strtolower($email));
        $userDao->setCreation(date('Y-m-d H:i:s'));
        $userDao->setHashAlg('sha256');
        $userDao->setAdmin($admin);
        if ($password) {
            if ($salt !== null) {
                throw new Zend_Exception('You must not pass a salt if you pass a plaintext password');
            }
            // Generate a random salt for this new user
            $instanceSalt = Zend_Registry::get('configGlobal')->password->prefix;

            /** @var RandomComponent $randomComponent */
            $randomComponent = MidasLoader::loadComponent('Random');
            $userSalt = $randomComponent->generateString(32);
            $hashedPassword = hash('sha256', $instanceSalt.$userSalt.$password);
            $userDao->setSalt($userSalt);
            $this->storePasswordHash($hashedPassword);
        } else {
            if (!is_string($salt)) {
                throw new Zend_Exception('Salt must be set to a string if no password is passed');
            }
            $userDao->setSalt($salt);
        }
        $this->save($userDao); // save the record before Gravatar lookup to shorten critical section

        // check Gravatar
        $useGravatar = Zend_Registry::get('configGlobal')->gravatar;
        if ($useGravatar) {
            $gravatarUrl = $this->getGravatarUrl($email);
            if ($gravatarUrl != false) {
                $userDao->setThumbnail($gravatarUrl);
            }
        }

        /** @var GroupModel $groupModel */
        $groupModel = MidasLoader::loadModel('Group');

        /** @var FolderModel $folderModel */
        $folderModel = MidasLoader::loadModel('Folder');

        /** @var FolderpolicygroupModel $folderpolicygroupModel */
        $folderpolicygroupModel = MidasLoader::loadModel('Folderpolicygroup');

        /** @var FolderpolicyuserModel $folderpolicyuserModel */
        $folderpolicyuserModel = MidasLoader::loadModel('Folderpolicyuser');

        /** @var FeedModel $feedModel */
        $feedModel = MidasLoader::loadModel('Feed');

        /** @var FeedpolicygroupModel $feedpolicygroupModel */
        $feedpolicygroupModel = MidasLoader::loadModel('Feedpolicygroup');

        /** @var FeedpolicyuserModel $feedpolicyuserModel */
        $feedpolicyuserModel = MidasLoader::loadModel('Feedpolicyuser');
        $anonymousGroup = $groupModel->load(MIDAS_GROUP_ANONYMOUS_KEY);

        $folderGlobal = $folderModel->createFolder('user_'.$userDao->getKey(), '', MIDAS_FOLDER_USERPARENT);
        $folderPrivate = $folderModel->createFolder('Private', '', $folderGlobal);
        $folderPublic = $folderModel->createFolder('Public', '', $folderGlobal);

        $folderpolicygroupModel->createPolicy($anonymousGroup, $folderPublic, MIDAS_POLICY_READ);

        $folderpolicyuserModel->createPolicy($userDao, $folderPrivate, MIDAS_POLICY_ADMIN);
        $folderpolicyuserModel->createPolicy($userDao, $folderGlobal, MIDAS_POLICY_ADMIN);
        $folderpolicyuserModel->createPolicy($userDao, $folderPublic, MIDAS_POLICY_ADMIN);

        $userDao->setFolderId($folderGlobal->getKey());

        $this->save($userDao);
        $this->getLogger()->debug(__METHOD__." Registration: ".$userDao->getFullName()." ".$userDao->getKey());

        $feed = $feedModel->createFeed($userDao, MIDAS_FEED_CREATE_USER, $userDao);
        $feedpolicygroupModel->createPolicy($anonymousGroup, $feed, MIDAS_POLICY_READ);
        $feedpolicyuserModel->createPolicy($userDao, $feed, MIDAS_POLICY_ADMIN);

        Zend_Registry::get('notifier')->callback('CALLBACK_CORE_NEW_USER_ADDED', array('userDao' => $userDao));

        return $userDao;
    }

    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param  string $email The email address
     * @param  string $s Size in pixels, defaults to 80px [ 1 - 512 ]
     * @param  string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     * @param  string $r Maximum rating (inclusive) [ g | pg | r | x ]
     * @param  boole $img True to return a complete IMG tag False for just the URL
     * @param  array $atts Optional, additional key/value attributes to include in the IMG tag
     * @return String containing either just a URL or a complete image tag
     * @source http://gravatar.com/site/implement/images/php/
     */
    public function getGravatarUrl($email, $s = 32, $d = '404', $r = 'g', $img = false, $atts = array())
    {
        $url = 'https://secure.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=".$s."&d=".$d."&r=".$r;
        if ($img) {
            $url = '<img src="'.$url.'"';
            foreach ($atts as $key => $val) {
                $url .= ' '.$key.'="'.$val.'"';
            }
            $url .= ' />';
        }

        $header = get_headers($url, 1);
        if (strpos($header[0], '404 Not Found') != false) {
            return false;
        }

        return $url;
    }
}
