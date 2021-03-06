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

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

define('BASE_PATH', realpath(dirname(__FILE__).'/../'));
define('APPLICATION_ENV', 'testing');
define('APPLICATION_PATH', BASE_PATH.'/core');
define('LIBRARY_PATH', BASE_PATH.'/library');
define('TESTS_PATH', BASE_PATH.'/tests');

require_once BASE_PATH.'/vendor/autoload.php';
require_once BASE_PATH.'/core/include.php';

date_default_timezone_set('UTC');

Zend_Session::$_unitTestEnabled = true;
Zend_Session::start();

require_once 'ControllerTestCase.php';
require_once 'DatabaseTestCase.php';

Zend_Registry::set('logger', null);

$configGlobal = new Zend_Config_Ini(APPLICATION_CONFIG, 'global', true);
$configGlobal->environment = 'testing';
Zend_Registry::set('configGlobal', $configGlobal);

$config = new Zend_Config_Ini(APPLICATION_CONFIG, 'testing');
Zend_Registry::set('config', $config);

$databaseIni = getenv('MIDAS_DATABASE_INI');

if (!file_exists($databaseIni)) {
    if (file_exists(BASE_PATH.'/tests/configs/lock.mysql.ini')) {
        $databaseIni = BASE_PATH.'/tests/configs/lock.mysql.ini';
    } elseif (file_exists(BASE_PATH.'/tests/configs/lock.pgsql.ini')) {
        $databaseIni = BASE_PATH.'/tests/configs/lock.pgsql.ini';
    } elseif (file_exists(BASE_PATH.'/tests/configs/lock.sqlite.ini')) {
        $databaseIni = BASE_PATH.'/tests/configs/lock.sqlite.ini';
    } else {
        echo 'Error: database ini file not found.';
        exit(1);
    }
}

$configDatabase = new Zend_Config_Ini($databaseIni, 'testing');

if (empty($configDatabase->database->params->driver_options)) {
    $driverOptions = array();
} else {
    $driverOptions = $configDatabase->database->params->driver_options->toArray();
}
$params = array(
    'dbname' => $configDatabase->database->params->dbname,
    'username' => $configDatabase->database->params->username,
    'password' => $configDatabase->database->params->password,
    'driver_options' => $driverOptions,
);
if (empty($configDatabase->database->params->unix_socket)) {
    $params['host'] = $configDatabase->database->params->host;
    $params['port'] = $configDatabase->database->params->port;
} else {
    $params['unix_socket'] = $configDatabase->database->params->unix_socket;
}
$db = Zend_Db::factory($configDatabase->database->adapter, $params);
Zend_Db_Table::setDefaultAdapter($db);
Zend_Registry::set('dbAdapter', $db);
Zend_Registry::set('configDatabase', $configDatabase);

require_once BASE_PATH.'/core/controllers/components/UpgradeComponent.php';
$upgradeComponent = new UpgradeComponent();
$db = Zend_Registry::get('dbAdapter');
$dbtype = Zend_Registry::get('configDatabase')->database->adapter;

$upgradeComponent->initUpgrade('core', $db, $dbtype);
$version = Zend_Registry::get('configDatabase')->version;
if (!isset($version)) {
    if (Zend_Registry::get('configDatabase')->database->adapter === 'PDO_MYSQL'
    ) {
        $type = 'mysql';
    } elseif (Zend_Registry::get('configDatabase')->database->adapter === 'PDO_PGSQL'
    ) {
        $type = 'pgsql';
    } elseif (Zend_Registry::get('configDatabase')->database->adapter === 'PDO_SQLITE'
    ) {
        $type = 'sqlite';
    } else {
        echo 'Error';
        exit;
    }
    $MyDirectory = opendir(BASE_PATH."/core/database/".$type);
    while ($Entry = @readdir($MyDirectory)) {
        if (strpos($Entry, '.sql') != false) {
            $sqlFile = BASE_PATH."/core/database/".$type."/".$Entry;
        }
    }

    $version = str_replace('.sql', '', basename($sqlFile));
}
$upgradeComponent->upgrade($version, true);

$logger = Zend_Log::factory(
    array(
        array(
            'writerName' => 'Stream',
            'writerParams' => array('stream' => LOGS_PATH.'/testing.log'),
            'filterName' => 'Priority',
            'filterParams' => array('priority' => Zend_Log::INFO),
        ),
    )
);

Zend_Registry::set('logger', $logger);

if (file_exists(BASE_PATH.'/tests/configs/lock.mysql.ini')) {
    rename(BASE_PATH.'/tests/configs/lock.mysql.ini', BASE_PATH.'/tests/configs/mysql.ini');
}
if (file_exists(BASE_PATH.'/tests/configs/lock.pgsql.ini')) {
    rename(BASE_PATH.'/tests/configs/lock.pgsql.ini', BASE_PATH.'/tests/configs/pgsql.ini');
}
if (file_exists(BASE_PATH.'/tests/configs/lock.sqlite.ini')) {
    rename(BASE_PATH.'/tests/configs/lock.sqlite.ini', BASE_PATH.'/tests/configs/sqlite.ini');
}
