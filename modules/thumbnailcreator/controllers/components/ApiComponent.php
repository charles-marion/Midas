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

/** Component for api methods */
class Thumbnailcreator_ApiComponent extends AppComponent
{

  /**
   * Helper function for verifying keys in an input array
   */
  private function _checkKeys($keys, $values)
    {
    foreach($keys as $key)
      {
      if(!array_key_exists($key, $values))
        {
        throw new Exception('Parameter '.$key.' must be set', MIDAS_INVALID_PARAMETER);
        }
      }
    }

  /**
   * Create a big thumbnail for the given bitstream with the given width. It is used as the main image of the given item and shown in the item view page.
   * @param bitstreamId The bitstream to create the thumbnail from
   * @param itemId The item to set the thumbnail on
   * @param width (Optional) The width in pixels to resize to (aspect ratio will be preserved). Defaults to 575
   * @return The ItemthumbnailDao obejct that was created
   */
  public function createBigThumbnail($args)
    {
    $this->_checkKeys(array('itemId', 'bitstreamId'), $args);

    $componentLoader = new MIDAS_ComponentLoader();
    $imComponent = $componentLoader->loadComponent('Imagemagick', 'thumbnailcreator');
    $authComponent = $componentLoader->loadComponent('Authentication', 'api');
    $userDao = $authComponent->getUser($args,
                                       Zend_Registry::get('userSession')->Dao);

    $itemId = $args['itemId'];
    $bitstreamId = $args['bitstreamId'];
    $width = '575';
    if(isset($args['width']))
      {
      $width = $args['width'];
      }

    $modelLoad = new MIDAS_ModelLoader();
    $bitstreamModel = $modelLoad->loadModel('Bitstream');
    $itemModel = $modelLoad->loadModel('Item');
    $itemthumbnailModel = $modelLoad->loadModel('Itemthumbnail', 'thumbnailcreator');

    $bitstream = $bitstreamModel->load($bitstreamId);
    $item = $itemModel->load($itemId);

    if(!$itemModel->policyCheck($item, $userDao, MIDAS_POLICY_WRITE))
      {
      throw new Exception('You didn\'t log in or you don\'t have the write permission for the given item.', MIDAS_INVALID_POLICY);
      }

    $itemThumbnail = $itemthumbnailModel->getByItemId($item->getKey());
    if(!$itemThumbnail)
      {
      $itemthumbnailModel->loadDaoClass('ItemthumbnailDao', 'thumbnailcreator');
      $itemThumbnail = new Thumbnailcreator_ItemthumbnailDao();
      $itemThumbnail->setItemId($item->getKey());
      }
    else
      {
      $oldThumb = $bitstreamModel->load($itemThumbnail->getThumbnailId());
      $bitstreamModel->delete($oldThumb);
      }

    try
      {
      $thumbnail = $imComponent->createThumbnailFromPath($bitstream->getName(), $bitstream->getFullPath(), (int)$width, 0, false);
      if(!file_exists($thumbnail))
        {
        throw new Exception('Could not create thumbnail from the bitstream', MIDAS_INTERNAL_ERROR);
        }

      $assetstoreModel = $modelLoad->loadModel('Assetstore');
      $thumb = $bitstreamModel->createThumbnail($assetstoreModel->getDefault(), $thumbnail);
      $itemThumbnail->setThumbnailId($thumb->getKey());
      $itemthumbnailModel->save($itemThumbnail);
      return $itemThumbnail->toArray();
      }
    catch(Exception $e)
      {
      throw new Exception($e->getMessage(), MIDAS_INTERNAL_ERROR);
      }
    }


/**
   * Create a 100x100 small thumbnail for the given item. It is used for preview purpose and displayed in the 'preview' and 'thumbnails' sidebar sections.
   * @param itemId The item to set the thumbnail on
   * @return The Item obejct (with the new thumbnail_id) and the path where the newly created thumbnail is stored
   */

  public function createSmallThumbnail($args)
    {
    $this->_checkKeys(array('itemId'), $args);
    $itemId = $args['itemId'];

    $componentLoader = new MIDAS_ComponentLoader();
    $imComponent = $componentLoader->loadComponent('Imagemagick', 'thumbnailcreator');
    $authComponent = $componentLoader->loadComponent('Authentication', 'api');
    $userDao = $authComponent->getUser($args,
                                       Zend_Registry::get('userSession')->Dao);

    $modelLoader = new MIDAS_ModelLoader;
    $itemModel = $modelLoader->loadModel('Item');
    $item = $itemModel->load($itemId);
    if(!$itemModel->policyCheck($item, $userDao, MIDAS_POLICY_WRITE))
      {
      throw new Exception('You didn\'t log in or you don\'t have the write permission for the given item.', MIDAS_INVALID_POLICY);
      }
    $revision = $itemModel->getLastRevision($item);
    $bitstreams = $revision->getBitstreams();
    if(count($bitstreams) < 1)
      {
      throw new Exception('The head revision of the item does not contain any bitstream', MIDAS_INVALID_PARAMETER);
      }
    $bitstream = $bitstreams[0];
    $name = $bitstream->getName();
    $fullPath = $bitstream->getFullPath();

    try
      {
      $pathThumbnail = $imComponent->createThumbnailFromPath($name, $fullPath, 100, 100, true);
      }
    catch(Exception $e)
      {
      throw new Exception($e->getMessage(), MIDAS_INTERNAL_ERROR);
      }

    if(file_exists($pathThumbnail))
      {
      $itemModel->replaceThumbnail($item, $pathThumbnail);
      }

    $return = $item->toArray();
    $return['pathToCreatedThumbnail'] = $pathThumbnail;
    return $return;
    }

} // end class