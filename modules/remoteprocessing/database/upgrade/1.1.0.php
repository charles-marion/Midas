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

/** Upgrade the remoteprocessing module to version 1.1.0. */
class Remoteprocessing_Upgrade_1_1_0 extends MIDASUpgrade
{
    /** @var string */
    public $moduleName = 'remoteprocessing';

    /** Post database upgrade. */
    public function postUpgrade()
    {
        /** @var RandomComponent $randomComponent */
        $randomComponent = MidasLoader::loadComponent('Random');
        $securityKey = $randomComponent->generateString(32);

        /** @var SettingModel $settingModel */
        $settingModel = MidasLoader::loadModel('Setting');
        $configPath = LOCAL_CONFIGS_PATH.DIRECTORY_SEPARATOR.$this->moduleName.'.local.ini';

        if (file_exists($configPath)) {
            $config = new Zend_Config_Ini($configPath, 'global');
            $settingModel->setConfig(MIDAS_REMOTEPROCESSING_SECURITY_KEY_KEY, $config->get('securitykey', $securityKey), $this->moduleName);
            $showButton =  $config->get('showbutton');

            if ($showButton === 'true') {
                $showButton = 1;
            } elseif ($showButton === 'false') {
                $showButton = 0;
            } else {
                $showButton = MIDAS_REMOTEPROCESSING_SHOW_BUTTON_DEFAULT_VALUE;
            }

            $settingModel->setConfig(MIDAS_REMOTEPROCESSING_SHOW_BUTTON_KEY, $showButton, $this->moduleName);

            $config = new Zend_Config_Ini($configPath, null, true);
            unset($config->securitykey->securitykey);
            unset($config->showbutton->showbutton);

            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config);
            $writer->setFilename($configPath);
            $writer->write();
        } else {
            $settingModel->setConfig(MIDAS_REMOTEPROCESSING_SECURITY_KEY_KEY, $securityKey, $this->moduleName);
            $settingModel->setConfig(MIDAS_REMOTEPROCESSING_SHOW_BUTTON_KEY, MIDAS_REMOTEPROCESSING_SHOW_BUTTON_DEFAULT_VALUE, $this->moduleName);
        }
    }
}
