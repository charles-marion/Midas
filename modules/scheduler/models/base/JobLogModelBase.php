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

/** Job base model class for the scheduler module */
class Scheduler_JobLogModelBase extends Scheduler_AppModel
{
    /** constructor */
    public function __construct()
    {
        parent::__construct();
        $this->_name = 'scheduler_job_log';
        $this->_daoName = 'JobLogDao';
        $this->_key = 'log_id';

        $this->_mainData = array(
            'log_id' => array('type' => MIDAS_DATA),
            'job_id' => array('type' => MIDAS_DATA),
            'date' => array('type' => MIDAS_DATA),
            'log' => array('type' => MIDAS_DATA),
            'job' => array(
                'type' => MIDAS_MANY_TO_ONE,
                'model' => 'Job',
                'module' => 'scheduler',
                'parent_column' => 'job_id',
                'child_column' => 'job_id',
            ),
        );
        $this->initialize(); // required
    }

    /** save logs */
    public function saveLog($jobDao, $text)
    {
        /** @var Scheduler_JobLogDao $joblog */
        $joblog = MidasLoader::newDao('JobLogDao', 'scheduler');
        $joblog->setJobId($jobDao->getKey());
        $joblog->setDate(date('Y-m-d H:i:s'));
        $joblog->setLog($text);

        $this->save($joblog);
    }
}
