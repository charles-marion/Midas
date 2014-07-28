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

/**
 * Controller for the Advanced Search feature
 */
class Solr_AdvancedController extends Solr_AppController
  {
  public $_models = array('Item', 'Setting');
  public $_moduleForms = array('Config');
  public $_moduleComponents = array('Solr');

  /** Render the advanced search page */
  function indexAction()
    {
    $this->view->header = 'Advanced Search';
    }

  /**
   * Submit an advanced search query.  Responds with JSON results.
   * @param query The Lucene query to perform
   * @param solrOffset The offset into the actual solr results
   * @param displayOffset The offset of actually displayed items
   * @param limit The limit of the result set
   */
  function submitAction()
    {
    $this->disableLayout();
    $this->disableView();

    $query = $this->_getParam('query');
    $limit = (int)$this->_getParam('limit');
    $solrOffset = (int)$this->_getParam('solrOffset');
    $displayOffset = (int)$this->_getParam('displayOffset');

    $itemIds = array();
    try
      {
      $index = $this->ModuleComponent->Solr->getSolrIndex();

      UtilityComponent::beginIgnoreWarnings(); //underlying library can generate warnings, we need to eat them
      $response = $index->search($query, $solrOffset, $limit * 5, array('fl' => '*,score')); //extend limit to allow some room for policy filtering
      UtilityComponent::endIgnoreWarnings();

      $totalResults = $response->response->numFound;
      foreach($response->response->docs as $doc)
        {
        $itemIds[] = $doc->key;
        }
      }
    catch(Exception $e)
      {
      echo JsonComponent::encode(array('status' => 'error', 'message' => 'Syntax error in query '.$e->getMessage()));
      return;
      }

    $items = array();
    $count = 0;
    foreach($itemIds as $itemId)
      {
      $solrOffset++;
      $item = $this->Item->load($itemId);
      if($item && $this->Item->policyCheck($item, $this->userSession->Dao))
        {
        $items[] = array('name' => $item->getName(), 'id' => $item->getKey());
        $count++;
        if($count >= $limit)
          {
          break;
          }
        }
      }
    $displayOffset += $count;
    echo JsonComponent::encode(array(
      'status' => 'ok',
      'totalResults' => $totalResults,
      'solrOffset' => $solrOffset,
      'displayOffset' => $displayOffset,
      'items' => $items));
    }
  } // end class
