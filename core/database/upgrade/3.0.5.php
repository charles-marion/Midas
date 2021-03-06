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

/** Upgrade the core to version 3.0.5. */
class Upgrade_3_0_5 extends MIDASUpgrade
{
    /** Upgrade a MySQL database. */
    public function mysql()
    {
        $this->db->query("ALTER TABLE community MODIFY folder_id bigint(20) NULL DEFAULT NULL;");
        $this->db->query("ALTER TABLE community MODIFY publicfolder_id bigint(20) NULL DEFAULT NULL;");
        $this->db->query("ALTER TABLE community MODIFY privatefolder_id bigint(20) NULL DEFAULT NULL;");
    }

    /** Upgrade a PostgreSQL database. */
    public function pgsql()
    {
        $this->db->query("ALTER TABLE community ALTER COLUMN folder_id DROP NOT NULL;");
        $this->db->query("ALTER TABLE community ALTER COLUMN publicfolder_id DROP NOT NULL;");
        $this->db->query("ALTER TABLE community ALTER COLUMN privatefolder_id DROP NOT NULL;");
    }
}
