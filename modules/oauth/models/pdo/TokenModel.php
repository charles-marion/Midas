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

require_once BASE_PATH.'/modules/oauth/models/base/TokenModelBase.php';

/** pdo model implementation */
class Oauth_TokenModel extends Oauth_TokenModelBase
{
    /**
     * Retrieve a token DAO based on the token value. Checking if it has expired
     * is left up to the caller.
     *
     * @param string $token
     * @return false|Oauth_TokenDao
     * @throws Zend_Exception
     */
    public function getByToken($token)
    {
        $row = $this->database->fetchRow(
            $this->database->select()->setIntegrityCheck(false)->where('token = ?', $token)
        );

        return $this->initDao('Token', $row, $this->moduleName);
    }

    /**
     * Return all token DAOs for the given user.
     *
     * @param UserDao $userDao
     * @param bool $onlyValid
     * @return array
     * @throws Zend_Exception
     */
    public function getByUser($userDao, $onlyValid = true)
    {
        $sql = $this->database->select()->setIntegrityCheck(false)->where(
            'user_id = ?',
            $userDao->getKey()
        )->where('expiration_date > ? OR type = '.MIDAS_OAUTH_TOKEN_TYPE_REFRESH, date('Y-m-d H:i:s'));
        $rows = $this->database->fetchAll($sql);
        $daos = array();
        foreach ($rows as $row) {
            $daos[] = $this->initDao('Token', $row, $this->moduleName);
        }

        return $daos;
    }

    /**
     * Expire all existing tokens for the given user and client.
     *
     * @param UserDao $userDao user DAO
     * @param Oauth_ClientDao $clientDao client DAO
     */
    public function expireTokens($userDao, $clientDao)
    {
        $data = array('expiration_date' => date('Y-m-d H:i:s'));
        $this->database->getDB()->update(
            'oauth_token',
            $data,
            'user_id = '.$userDao->getKey().' AND client_id = '.$clientDao->getKey()
        );
    }

    /** Remove expired access tokens from the database. */
    public function cleanExpired()
    {
        $sql = $this->database->select()->setIntegrityCheck(false)->where(
            'expiration_date < ?',
            date('Y-m-d H:i:s')
        )->where(
            'type = ?',
            MIDAS_OAUTH_TOKEN_TYPE_ACCESS
        );
        $rows = $this->database->fetchAll($sql);
        foreach ($rows as $row) {
            $tmpDao = $this->initDao('Token', $row, $this->moduleName);
            $this->delete($tmpDao);
            $tmpDao = null; // mark for memory reclamation
        }
    }
}
