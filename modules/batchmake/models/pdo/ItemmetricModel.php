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

require_once BASE_PATH.'/modules/batchmake/models/base/ItemmetricModelBase.php';

/** Batchmake_ItemmetricModel */
class Batchmake_ItemmetricModel extends Batchmake_ItemmetricModelBase
{
    /**
     * Get all rows stored.
     *
     * @return array
     */
    public function getAll()
    {
        $rowsetDAOs = $this->database->getAll('Itemmetric', 'batchmake');

        return $rowsetDAOs;
    }
}
