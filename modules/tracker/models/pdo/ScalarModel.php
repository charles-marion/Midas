<?php
/*=========================================================================
MIDAS Server
Copyright (c) Kitware SAS. 20 rue de la Villette. All rights reserved.
69328 Lyon, FRANCE.

See Copyright.txt for details.
This software is distributed WITHOUT ANY WARRANTY; without even
the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
PURPOSE.  See the above copyright notices for more information.
=========================================================================*/
require_once BASE_PATH.'/modules/tracker/models/base/ScalarModelBase.php';

/**
 * Scalar PDO Model
 */
class Tracker_ScalarModel extends Tracker_ScalarModelBase
{
  /**
   * Return all associated items
   */
  public function getAssociatedItems($scalar)
    {
    // TODO return a hash array where key is the label and value is the result item
    return array();
    }

  /**
   * Return other values that are from the same submission (same submit_time and same producer)
   */
  public function getOtherValuesFromSubmission($scalar)
    {
    return array();
    }

  /**
   * Delete the scalar (deletes all result item associations as well)
   */
  public function delete($scalar)
    {
    // TODO delete from tracker_scalar2item where scalar_id=$scalar->getKey()
    parent::delete($scalar);
    }

  /**
   * Helper function used to overwrite trend points with identical timestamps
   */
  public function deleteByTrendAndTimestamp($trendId, $timestamp)
    {
    // We do not need to protect against sql injection here because we only call this with a known valid timestamp value
    Zend_Registry::get('dbAdapter')->delete($this->_name, 'trend_id = '.$trendId.' AND submit_time = \''.$timestamp.'\'');
    }
}
