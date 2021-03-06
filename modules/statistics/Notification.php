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

/** Notification manager for the statistics module */
class Statistics_Notification extends MIDAS_Notification
{
    public $moduleName = 'statistics';
    public $_models = array('Setting');
    public $_moduleModels = array('Download', 'IpLocation');
    public $_moduleComponents = array('Report');

    /** init notification process */
    public function init()
    {
        $this->addCallBack('CALLBACK_CORE_GET_FOOTER_LAYOUT', 'getFooter');
        $this->addCallBack('CALLBACK_CORE_GET_USER_MENU', 'getUserMenu');
        $this->addCallBack('CALLBACK_CORE_ITEM_VIEW_ACTIONMENU', 'getItemMenuLink');
        $this->addCallBack('CALLBACK_CORE_PLUS_ONE_DOWNLOAD', 'addDownload');
        $this->addCallBack('CALLBACK_CORE_USER_DELETED', 'handleUserDeleted');

        $this->addTask('TASK_STATISTICS_SEND_REPORT', 'sendReport', 'Send a daily report');
        $this->addTask('TASK_STATISTICS_PERFORM_GEOLOCATION', 'performGeolocation', 'Perform geolocation based on IP');
    }

    /** send the batch report to admins */
    public function sendReport()
    {
        echo $this->ModuleComponent->Report->generate();
        $this->ModuleComponent->Report->send();
    }

    /** add download stat */
    public function addDownload($params)
    {
        $item = $params['item'];
        $user = $this->userSession->Dao;
        $this->Statistics_Download->addDownload($item, $user);
    }

    /** perform download geolocation by ip address */
    public function performGeolocation($params)
    {
        return $this->Statistics_IpLocation->performGeolocation($params['apikey']);
    }

    /** user Menu link */
    public function getUserMenu()
    {
        if ($this->logged && $this->userSession->Dao->getAdmin() == 1) {
            $fc = Zend_Controller_Front::getInstance();
            $moduleWebroot = $fc->getBaseUrl().'/statistics';

            return array($this->t('Statistics') => $moduleWebroot);
        } else {
            return null;
        }
    }

    /** Get the link to place in the item action menu */
    public function getItemMenuLink($params)
    {
        $webroot = Zend_Controller_Front::getInstance()->getBaseUrl();

        return '<li><a href="'.$webroot.'/'.$this->moduleName.'/item?id='.$params['item']->getKey(
        ).'"><img alt="" src="'.$webroot.'/modules/'.$this->moduleName.'/public/images/chart_bar.png" /> '.$this->t(
            'Statistics'
        ).'</a></li>';
    }

    /** get layout footer */
    public function getFooter()
    {
        $url = $this->Setting->getValueByName(STATISTICS_PIWIK_URL_KEY, $this->moduleName);
        $id = $this->Setting->getValueByName(STATISTICS_PIWIK_SITE_ID_KEY, $this->moduleName);

        $baseUrl = Zend_Controller_Front::getInstance()->getBaseUrl();
        $js = $baseUrl.'/modules/'.$this->moduleName.'/public/js/statistics.notify.js';
        $html = '<script type="text/javascript" src="'.htmlspecialchars($js, ENT_QUOTES, 'UTF-8').'"></script>';

        return "
      <!-- Piwik -->
      <script type=\"text/javascript\">
      var pkBaseURL = '".htmlspecialchars($url, ENT_QUOTES, 'UTF-8')."/';
      document.write(unescape(\"%3Cscript src='\" + pkBaseURL + \"piwik.js' type='text/javascript'%3E%3C/script%3E\"));
      </script><script type=\"text/javascript\">
      try {
      var piwikTracker = Piwik.getTracker(pkBaseURL + \"piwik.php\", ".htmlspecialchars($id, ENT_QUOTES, 'UTF-8').");
      piwikTracker.trackPageView();
      piwikTracker.enableLinkTracking();
    } catch ( err ) {}
      </script><noscript><p><img src=\"".htmlspecialchars($url, ENT_QUOTES, 'UTF-8')."/piwik.php?idsite=".htmlspecialchars($id, ENT_QUOTES, 'UTF-8')."\" style=\"border:0\" alt=\"\" /></p></noscript>
      <!-- End Piwik Tracking Code -->
      ".$html;
    }

    /**
     * If a user is deleted, we should remove references to them in the
     * statistics_download table
     *
     * @param userDao the user dao that is about to be deleted
     */
    public function handleUserDeleted($params)
    {
        if (!isset($params['userDao'])) {
            throw new Zend_Exception('Error: userDao parameter required');
        }
        $user = $params['userDao'];

        /** @var Statistics_DownloadModel $downloadModel */
        $downloadModel = MidasLoader::loadModel('Download', $this->moduleName);
        $downloadModel->removeUserReferences($user->getKey());
    }
}
