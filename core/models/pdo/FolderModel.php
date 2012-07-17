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

require_once BASE_PATH.'/core/models/base/FolderModelBase.php';

/**
 * \class FolderModel
 * \brief Pdo Model
 */
class FolderModel extends FolderModelBase
{

  /** This method will remove all the items that are not locaded in the folder from the input array $items
   * @return
   */
  function filterItemsByFolder($items, $parent)
    {
    if(!is_array($items))
      {
      $items = array($items);
      }

    if(!$parent instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }

    foreach($items as $key => $item)
      {
      if(!$item instanceof ItemDao)
        {
        throw new Zend_Exception("Should be an item.");
        }

      $remove = true;
      foreach($item->getFolders() as $folder)
        {
        if($this->isFolderInFolder($folder, $parent))
          {
          $remove = false;
          break;
          }
        }
      if($remove)
        {
        unset($items[$key]);
        }
      }

    return $items;
    }

  /** check if folder is a children of parent*/
  function isFolderInFolder($folder, $parent)
    {
    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }
    if(!$parent instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }
    if($folder->getLeftIndice() >= $parent->getLeftIndice() && $folder->getRightIndice() <= $parent->getRightIndice() )
      {
      return true;
      }
    else
      {
      return false;
      }
    }
  /** get All*/
  function getAll()
    {
    $rowset = $this->database->fetchAll($this->database->select()->order(array('folder_id DESC')));
    $results = array();
    foreach($rowset as $row)
      {
      $results[] = $this->initDao('Folder', $row);
      }
    return $results;
    }

  /** get by uuid*/
  function getByUuid($uuid)
    {
    $row = $this->database->fetchRow($this->database->select()->where('uuid = ?', $uuid));
    $dao = $this->initDao(ucfirst($this->_name), $row);
    return $dao;
    }

  /** get by name */
  function getByName($name)
    {
    $row = $this->database->fetchRow($this->database->select()->where('name = ?', $name));
    $dao = $this->initDao(ucfirst($this->_name), $row);
    return $dao;
    }

  /** check ifthe policy is valid*/
  function policyCheck($folderDao, $userDao = null, $policy = 0)
    {
    if(!$folderDao instanceof  FolderDao || !is_numeric($policy))
      {
      throw new Zend_Exception("Error in params when checking Folder Policy.");
      }
    if($userDao == null)
      {
      $userId = -1;
      }
    else if(!$userDao instanceof UserDao)
      {
      throw new Zend_Exception("Should be an user.");
      }
    else
      {
      $userId = $userDao->getUserId();
      if($userDao->isAdmin())
        {
        return true;
        }
      }

    $subqueryUser = $this->database->select()
                          ->setIntegrityCheck(false)
                          ->from(array('p' => 'folderpolicyuser'),
                                 array('folder_id'))
                          ->where('policy >= ?', $policy)
                          ->where('p.folder_id = ?', $folderDao->getKey())
                          ->where('user_id = ? ', $userId);

    $subqueryGroup = $this->database->select()
                    ->setIntegrityCheck(false)
                    ->from(array('p' => 'folderpolicygroup'),
                           array('folder_id'))
                    ->where('policy >= ?', $policy)
                    ->where('p.folder_id = ?', $folderDao->getKey())
                    ->where('( '.$this->database->getDB()->quoteInto('group_id = ? ', MIDAS_GROUP_ANONYMOUS_KEY).' OR
                              group_id IN (' .new Zend_Db_Expr(
                              $this->database->select()
                                   ->setIntegrityCheck(false)
                                   ->from(array('u2g' => 'user2group'),
                                          array('group_id'))
                                   ->where('u2g.user_id = ?', $userId)
                                   .'))' ));

    $sql = $this->database->select()
            ->union(array($subqueryUser, $subqueryGroup));
    $row = $this->database->fetchRow($sql);
    if($row == null)
      {
      return false;
      }
    return true;
    }//end policyCheck

  /**
   * Get the total number of folders and items contained within this folder,
   * irrespective of policies
   */
  public function getRecursiveChildCount($folder)
    {
    $sql = $this->database->select()
                ->setIntegrityCheck(false)
                ->from(array('f' => 'folder'), array('count' => 'count(*)'))
                ->where('left_indice > ?', $folder->getLeftIndice())
                ->where('right_indice < ?', $folder->getRightIndice());
    $row = $this->database->fetchRow($sql);
    $folderChildCount = $row['count'];

    $sql = $this->database->select()
                ->setIntegrityCheck(false)
                ->from(array('i2f' => 'item2folder'), array('count' => 'count(*)'))
                ->where('i2f.folder_id = '.$folder->getKey().' OR i2f.folder_id IN (' .new Zend_Db_Expr(
                  $this->database->select()
                       ->setIntegrityCheck(false)
                       ->from(array('f' => 'folder'), array('folder_id'))
                       ->where('left_indice > ?', $folder->getLeftIndice())
                       ->where('right_indice < ?', $folder->getRightIndice())).')');
    $row = $this->database->fetchRow($sql);
    $itemChildCount = $row['count'];

    return $folderChildCount + $itemChildCount;
    }

  /** get the size and the number of item in a folder*/
  public function getSizeFiltered($folders, $userDao = null, $policy = 0)
    {
    $isAdmin = false;
    if(!is_array($folders))
      {
      $folders = array($folders);
      }
    if($userDao == null)
      {
      $userId = -1;
      }
    else if(!$userDao instanceof UserDao)
      {
      throw new Zend_Exception("Should be an user.");
      }
    else
      {
      $userId = $userDao->getUserId();
      if($userDao->isAdmin())
        {
        $isAdmin = true;
        }
      }
    foreach($folders as $key => $folder)
      {
      if(!$folder instanceof FolderDao)
        {
        throw new Zend_Exception("Should be a folder");
        }
      $subqueryUser = $this->database->select()
                      ->setIntegrityCheck(false)
                      ->from(array('f' => 'folder'), array('folder_id'));
      if(!$isAdmin)
        {
        $subqueryUser             ->join(array('fpu' => 'folderpolicyuser'), '
                            f.folder_id = fpu.folder_id AND '.$this->database->getDB()->quoteInto('fpu.policy >= ?', $policy).'
                               AND '.$this->database->getDB()->quoteInto('user_id = ? ', $userId).' ', array());
        }
      $subqueryUser                 ->where('left_indice > ?', $folder->getLeftIndice())
                      ->where('right_indice < ?', $folder->getRightIndice());

      $subqueryGroup = $this->database->select()
                    ->setIntegrityCheck(false)
                    ->from(array('f' => 'folder'), array('folder_id'));
      if(!$isAdmin)
        {
        $subqueryGroup      ->join(array('fpg' => 'folderpolicygroup'), '
                                f.folder_id = fpg.folder_id  AND '.$this->database->getDB()->quoteInto('fpg.policy >= ?', $policy).'
                                   AND ( '.$this->database->getDB()->quoteInto('group_id = ? ', MIDAS_GROUP_ANONYMOUS_KEY).' OR
                                        group_id IN (' .new Zend_Db_Expr(
                                        $this->database->select()
                                             ->setIntegrityCheck(false)
                                             ->from(array('u2g' => 'user2group'),
                                                    array('group_id'))
                                             ->where('u2g.user_id = ?', $userId)
                                             ) .'))', array());
        }

      $subqueryGroup  ->where('left_indice > ?', $folder->getLeftIndice())
                    ->where('right_indice < ?', $folder->getRightIndice());

      $subSqlFolders = $this->database->select()
              ->union(array($subqueryUser, $subqueryGroup));

      $sql = $this->database->select()
                ->distinct()
                ->setIntegrityCheck(false)
                ->from(array('i' => 'item'))
                ->join(array('i2f' => 'item2folder'),
                         '( '.$this->database->getDB()->quoteInto('i2f.folder_id IN (?)', $subSqlFolders).'
                          OR i2f.folder_id = '.$folder->getKey().'
                          )
                          AND i2f.item_id = i.item_id', array() );
      if(!$isAdmin)
        {
        $sql    ->joinLeft(array('ip' => 'itempolicyuser'), '
                          i.item_id = ip.item_id AND '.$this->database->getDB()->quoteInto('policy >= ?', $policy).'
                             AND '.$this->database->getDB()->quoteInto('user_id = ? ', $userId).' ', array())
                ->joinLeft(array('ipg' => 'itempolicygroup'), '
                                i.item_id = ipg.item_id AND '.$this->database->getDB()->quoteInto('ipg.policy >= ?', $policy).'
                                   AND ( '.$this->database->getDB()->quoteInto('group_id = ? ', MIDAS_GROUP_ANONYMOUS_KEY).' OR
                                        group_id IN (' .new Zend_Db_Expr(
                                        $this->database->select()
                                             ->setIntegrityCheck(false)
                                             ->from(array('u2g' => 'user2group'), array('group_id'))
                                             ->where('u2g.user_id = ?', $userId)
                                             ) .'))', array())
                ->where(
                 '(
                  ip.item_id is not null or
                  ipg.item_id is not null)'
                  )
                ->group('i.item_id');
        }

      $sql = $this->database->select()
                ->setIntegrityCheck(false)
                ->from(array('i' => $sql), array('sum' => 'sum(i.sizebytes)', 'count' => 'count(i.item_id)'));

      $row = $this->database->fetchRow($sql);
      $folders[$key]->count = $row['count'];
      $folders[$key]->size = $row['sum'];
      if($folders[$key]->size == null)
        {
        $folders[$key]->size = 0;
        }
      }
    return $folders;
    }

  /** get the total size for a folder (with no filtered results) */
  public function getSize($folder)
    {
    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder");
      }
    $folders = $this->database->select()
                    ->setIntegrityCheck(false)
                    ->from(array('f' => 'folder'), array('folder_id'))
                    ->where('left_indice > ?', $folder->getLeftIndice())
                    ->where('right_indice < ?', $folder->getRightIndice());

    $sql = $this->database->select()
                ->distinct()
                ->setIntegrityCheck(false)
                ->from(array('i' => 'item'))
                ->join(array('i2f' => 'item2folder'),
                  '( '.$this->database->getDB()->quoteInto('i2f.folder_id IN (?)', $folders).'
                  OR i2f.folder_id = '.$folder->getKey().'
                  )
                  AND i2f.item_id = i.item_id', array());

    $sql = $this->database->select()
                ->setIntegrityCheck(false)
                ->from(array('i' => $sql), array('sum' => 'sum(i.sizebytes)'));

    $row = $this->database->fetchRow($sql);
    return $row['sum'];
    }

  /** Get the folder tree */
  function getAllChildren($folder, $userDao, $admin = false, $policy = 0)
    {
    $isAdmin = false;
    if($userDao == null)
      {
      $userId = -1;
      }
    else if(!$userDao instanceof UserDao)
      {
      throw new Zend_Exception("Should be an user.");
      }
    else
      {
      $userId = $userDao->getUserId();
      if($userDao->isAdmin())
        {
        $isAdmin = true;
        }
      }

    if($admin)
      {
      $isAdmin = true;
      }

    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder");
      }
    $subqueryUser = $this->database->select()
                    ->setIntegrityCheck(false)
                    ->from(array('f' => 'folder'));
    if(!$isAdmin)
      {
      $subqueryUser             ->join(array('fpu' => 'folderpolicyuser'), '
                          f.folder_id = fpu.folder_id AND '.$this->database->getDB()->quoteInto('fpu.policy >= ?', $policy).'
                             AND '.$this->database->getDB()->quoteInto('user_id = ? ', $userId).' ', array());
      }
    $subqueryUser                 ->where('left_indice > ?', $folder->getLeftIndice())
                    ->where('right_indice < ?', $folder->getRightIndice());

    $subqueryGroup = $this->database->select()
                  ->setIntegrityCheck(false)
                  ->from(array('f' => 'folder'));
    if(!$isAdmin)
      {
      $subqueryGroup      ->join(array('fpg' => 'folderpolicygroup'), '
                              f.folder_id = fpg.folder_id  AND '.$this->database->getDB()->quoteInto('fpg.policy >= ?', $policy).'
                                 AND ( '.$this->database->getDB()->quoteInto('group_id = ? ', MIDAS_GROUP_ANONYMOUS_KEY).' OR
                                      group_id IN (' .new Zend_Db_Expr(
                                      $this->database->select()
                                           ->setIntegrityCheck(false)
                                           ->from(array('u2g' => 'user2group'),
                                                  array('group_id'))
                                           ->where('u2g.user_id = ?', $userId)
                                           ) .'))', array());
      }

    $subqueryGroup  ->where('left_indice > ?', $folder->getLeftIndice())
                  ->where('right_indice < ?', $folder->getRightIndice());

    $subSqlFolders = $this->database->select()
            ->union(array($subqueryUser, $subqueryGroup));


    $rowset = $this->database->fetchAll($subSqlFolders);

    $folders = array();

    foreach($rowset as $row)
      {
      $folders[] = $this->initDao('Folder', $row);
      }

    return $folders;
    }

  /**
   * Custom delete function.
   * Pass a progressDao with pre-computed maximum to keep track of delete progress
   */
  function delete($folder, $progressDao = null)
    {
    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder");
      }
    if(!$folder->saved)
      {
      throw new Zend_Exception("The dao should be saved first ...");
      }
    $key = $folder->getKey();
    if(!isset($key))
      {
      throw new Zend_Exception("Unable to find the key");
      }

    if($progressDao && !isset($this->Progress))
      {
      $this->Progress = MidasLoader::loadModel('Progress');
      }

    $items = $folder->getItems();
    foreach($items as $item)
      {
      if($progressDao)
        {
        $message = 'Removing item '.$item->getName();
        $this->Progress->updateProgress($progressDao, $progressDao->getCurrent() + 1, $message);
        }
      $this->removeItem($folder, $item);
      }

    $children = $folder->getFolders();
    foreach($children as $child)
      {
      $this->delete($child, $progressDao);
      }

    if($progressDao)
      {
      $message = 'Removing folder '.$folder->getName();
      $this->Progress->updateProgress($progressDao, $progressDao->getCurrent() + 1, $message);
      }

    $policy_group_model = MidasLoader::loadModel('Folderpolicygroup');
    $policiesGroup = $folder->getFolderpolicygroup();
    foreach($policiesGroup as $policy)
      {
      $policy_group_model->delete($policy);
      }

    $policy_user_model = MidasLoader::loadModel('Folderpolicyuser');
    $policiesUser = $folder->getFolderpolicyuser();
    foreach($policiesUser as $policy)
      {
      $policy_user_model->delete($policy);
      }

    $leftIndice = $folder->getLeftIndice();
    $this->database->getDB()->update('folder', array('left_indice' => new Zend_Db_Expr('left_indice - 2')),
                          array('left_indice >= ?' => $leftIndice));
    $this->database->getDB()->update('folder', array('right_indice' => new Zend_Db_Expr('right_indice - 2')),
                          array('right_indice >= ?' => $leftIndice));

    parent::delete($folder);
    unset($folder->folder_id);
    $folder->saved = false;
    return true;
    } //end delete

  /** move a folder*/
  public function move($folder, $parent)
    {
    if($folder->getKey() == $parent->getKey())
      {
      throw new Zend_Exception("Folder == Parent");
      }

    $tmpParent = $parent->getParent();
    $currentParent = $folder->getParent();
    if($currentParent->getKey() == $parent->getKey())
      {
      return; //no-op
      }

    while($tmpParent != false)
      {
      if($tmpParent->getKey() == $folder->getKey())
        {
        throw new Zend_Exception("Parent is a child of Folder");
        }
      $tmpParent = $tmpParent->getParent();
      }

    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Error in parameter folder when moving folder.");
      }
    if(!$parent instanceof  FolderDao)
      {
      throw new Zend_Exception("Error in parameter parent when moving folder.");
      }

    // Check ifa folder with the same name already exists for the same parent
    if($this->getFolderExists($folder->getName(), $parent))
      {
      throw new Zend_Exception('This name is already used');
      }

    $node_id = $folder->getKey();
    $node_pos_left = $folder->getLeftIndice();
    $node_pos_right = $folder->getRightIndice();
    $parent_id = $parent->getKey();

    $parent_pos_right = $parent->getRightIndice();
    $node_size = $node_pos_right - $node_pos_left + 1;

    // step 1: temporary "remove" moving node
    $this->database->getDB()->update('folder', array('left_indice' => new Zend_Db_Expr('0 - left_indice'), 'right_indice' => new Zend_Db_Expr('0 - right_indice')),
                          'left_indice >= '.$node_pos_left.' AND right_indice <= '.$node_pos_right);

    // step 2: decrease left and/or right position values of currently 'lower' items (and parents)
    $this->database->getDB()->update('folder', array('left_indice' => new Zend_Db_Expr('left_indice - '.$node_size)),
                          array('left_indice > ?' => $node_pos_right));
    $this->database->getDB()->update('folder', array('right_indice' => new Zend_Db_Expr('right_indice - '.$node_size)),
                          array('right_indice > ?' => $node_pos_right));

    // step 3: increase left and/or right position values of future 'lower' items (and parents)
    $cond = ($parent_pos_right > $node_pos_right ? $parent_pos_right - $node_size : $parent_pos_right);
    $this->database->getDB()->update('folder', array('left_indice' => new Zend_Db_Expr('left_indice + '.$node_size)),
                          array('left_indice >= ?' => $cond));
    $this->database->getDB()->update('folder', array('right_indice' => new Zend_Db_Expr('right_indice + '.$node_size)),
                          array('right_indice >= ?' => $cond));

   // step 4: move node (ant it's subnodes) and update it's parent item id
    $cond = ($parent_pos_right > $node_pos_right) ? $parent_pos_right - $node_pos_right - 1 : $parent_pos_right - $node_pos_right - 1 + $node_size;
    $this->database->getDB()->update('folder', array('left_indice' => new Zend_Db_Expr('0 - left_indice + '.$cond), 'right_indice' => new Zend_Db_Expr('0 - right_indice + '.$cond)),
                          'left_indice <= '.(0 - $node_pos_left).' AND right_indice >= '.(0 - $node_pos_right));

    $folder = $this->load($folder->getKey());
    $folder->setParentId($parent->getKey());
    parent::save($folder);
    }//end move

  /** Custom save function*/
  public function save($folder)
    {
    if(!$folder instanceof  FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }

    if(!isset($folder->uuid) || empty($folder->uuid))
      {
      $folder->setUuid(uniqid() . md5(mt_rand()));
      }
    $name = $folder->getName();
    if(empty($name) && $name !== '0')
      {
      throw new Zend_Exception("Please set a name.");
      }
    if($folder->getParentId() <= 0)
      {
      $rightParent = 0;
      }
    else
      {
      $parentFolder = $folder->getParent();
      if(!$parentFolder)
        {
        return false; //deleting orphaned folder
        }
      $rightParent = $parentFolder->getRightIndice();
      }
    $data = array();
    foreach($this->_mainData as $key => $var)
      {
      if(isset($folder->$key))
        {
        $data[$key] = $folder->$key;
        }
      if($key == 'right_indice')
        {
        $folder->$key = $rightParent + 1;
        $data[$key] = $rightParent + 1;
        }
      else if($key == 'left_indice')
        {
        $data[$key] = $rightParent;
        }
      else if($key == 'description')
        {
        $data[$key] = UtilityComponent::filterHtmlTags($folder->getDescription());
        }
      }

    if(isset($data['folder_id']))
      {
      $key = $data['folder_id'];
      unset($data['folder_id']);
      unset($data['left_indice']);
      unset($data['right_indice']);
      $data['date_update'] = date('c');
      $this->database->update($data, array('folder_id = ?' => $key));
      return $key;
      }
    else
      {
      if(!isset($data['date_creation']) || empty($data['date_creation']))
        {
        $data['date_creation'] = date('c');
        }
      $data['date_update'] = date('c');

      $this->database->getDB()->update('folder', array('right_indice' => new Zend_Db_Expr('2 + right_indice')),
                          array('right_indice >= ?' => $rightParent));
      $this->database->getDB()->update('folder', array('left_indice' => new Zend_Db_Expr('2 + left_indice')),
                          array('left_indice >= ?' => $rightParent));

      $insertedid = $this->database->insert($data);
      if(!$insertedid)
        {
        return false;
        }
      $folder->folder_id = $insertedid;
      $folder->saved = true;

      return true;
      }
    } // end method save

  /** Get community if the folder is the main folder of one*/
  function getCommunity($folder)
    {
    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }
    $dao = $this->initDao('Community', $this->database->fetchRow($this->database->select()
                                                           ->setIntegrityCheck(false)
                                                           ->from('community')
                                                           ->where('folder_id = ?', $folder->getFolderId())));
    return $dao;
    }

  /** Get user if the folder is the main folder of one */
  function getUser($folder)
    {
    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }
    $dao = $this->initDao('User', $this->database->fetchRow($this->database->select()
                                                           ->setIntegrityCheck(false)
                                                           ->from('user')
                                                           ->where('folder_id = ?', $folder->getFolderId())));
    return $dao;
    }

  /** Returns ifa folder exists based on the name and description */
  function getFolderExists($name, $parent)
    {
    $dao = $this->initDao('Folder', $this->database->fetchRow($this->database->select()
                                                           ->setIntegrityCheck(false)
                                                           ->from('folder')
                                                           ->where('name = ?', $name)
                                                           ->where('parent_id = ?', $parent->getKey())));
    return $dao;
    }


  /**
   * Get child items of a folder filtered by policy check for the provided user
   * @param folder The parent folder
   * @param userDao The user requesting the folder children (default anonymous)
   * @param policy What policy to filter by (default MIDAS_POLICY_READ)
   * @param sortfield What field to sort the results by (name | date | size, default = name)
   * @param sortdir Sort direction (asc | desc, default = asc)
   */
  function getItemsFiltered($folder, $userDao = null, $policy = 0, $sortfield = 'name', $sortdir = 'asc')
    {
    $isAdmin = false;
    if(is_array($folder))
      {
      $folderIds = array();
      foreach($folder as $f)
        {
        if(!$f instanceof FolderDao)
          {
          throw new Zend_Exception("Should be a folder.");
          }
        $folderIds[] = $f->getKey();
        }
      }
    else if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }
    else
      {
      $folderIds = array($folder->getKey());
      }
    if($userDao == null)
      {
      $userId = -1;
      }
    else if(!$userDao instanceof UserDao)
      {
      throw new Zend_Exception("Should be an user.");
      }
    else
      {
      $userId = $userDao->getUserId();
      if($userDao->isAdmin())
        {
        $isAdmin = true;
        }
      }

    if($isAdmin)
      {
      $sql = $this->database->select()
                  ->setIntegrityCheck(false)
                  ->from(array('f' => 'item'))
                  ->join(array('i' => 'item2folder'),
                    $this->database->getDB()->quoteInto('i.folder_id IN (?)', $folderIds).'
                    AND i.item_id = f.item_id', array('i.folder_id'));
      }
    else
      {
      $subqueryUser = $this->database->select()
                           ->setIntegrityCheck(false)
                           ->from(array('f' => 'item'))
                           ->join(array('p' => 'itempolicyuser'),
                              'f.item_id = p.item_id',
                               array('p.policy'))
                           ->join(array('i' => 'item2folder'),
                              $this->database->getDB()->quoteInto('i.folder_id IN (?)', $folderIds).'
                              AND i.item_id = p.item_id', array('i.folder_id'))
                           ->where('policy >= ?', $policy)
                           ->where('user_id = ? ', $userId);

      $subqueryGroup = $this->database->select()
                            ->setIntegrityCheck(false)
                            ->from(array('f' => 'item'))
                            ->join(array('p' => 'itempolicygroup'),
                              'f.item_id = p.item_id',
                              array('p.policy'))
                            ->join(array('i' => 'item2folder'),
                                  $this->database->getDB()->quoteInto('i.folder_id IN (?)', $folderIds).'
                                  AND i.item_id = p.item_id', array('i.folder_id'))
                            ->where('policy >= ?', $policy)
                            ->where('( '.$this->database->getDB()->quoteInto('p.group_id = ? ', MIDAS_GROUP_ANONYMOUS_KEY).' OR
                                p.group_id IN (' .new Zend_Db_Expr(
                                $this->database->select()
                                     ->setIntegrityCheck(false)
                                     ->from(array('u2g' => 'user2group'),
                                            array('group_id'))
                                     ->where('u2g.user_id = ?', $userId)
                                     .'))' ));

      $sql = $this->database->select()->union(array($subqueryUser, $subqueryGroup));
      }

    $rowset = $this->database->fetchAll($sql);
    $return = array();
    $policyArray = array();
    foreach($rowset as $keyRow => $row)
      {
      if($isAdmin)
        {
        $policyArray[$row['item_id']] = MIDAS_POLICY_ADMIN;
        }
      else if(!isset($policyArray[$row['item_id']]) || (isset($policyArray[$row['item_id']]) && $row['policy'] > $policyArray[$row['item_id']]))
        {
        $policyArray[$row['item_id']] = $row['policy'];
        }
      }

    $listNamesArray = array();

    foreach($rowset as $keyRow => $row)
      {
      if(isset($policyArray[$row['item_id']]))
        {
        $item = $this->initDao('Item', $row);
        $folders = $item->getFolders();

        foreach($folders as $folder)
          {
          if(in_array($folder->getKey(), $folderIds))
            {
            $tmpDao = clone $item;
            $tmpDao->policy = $policyArray[$row['item_id']];
            $tmpDao->parent_id = $folder->getKey();
            $return[] = $tmpDao;
            }
          }
        unset($policyArray[$row['item_id']]);
        }
      }
    $this->Component->Sortdao->field = $sortfield;
    $this->Component->Sortdao->order = $sortdir;
    $sortFn = 'sortByName';
    if($sortfield == 'date')
      {
      $sortFn = 'sortByDate';
      }
    else if($sortfield == 'sizebytes')
      {
      $sortFn = 'sortByNumber';
      }
    usort($return, array($this->Component->Sortdao, $sortFn));

    return $return;
    }

  /**
   * Get child folders of a folder filtered by policy check for the provided user
   * @param folder The parent folder
   * @param userDao The user requesting the folder children (default anonymous)
   * @param policy What policy to filter by (default MIDAS_POLICY_READ)
   * @param sortfield What field to sort the results by (name | date, default = name)
   * @param sortdir Sort direction (asc | desc, default = asc)
   */
  function getChildrenFoldersFiltered($folder, $userDao = null, $policy = 0, $sortfield = 'name', $sortdir = 'asc')
    {
    $isAdmin = false;
    if(is_array($folder))
      {
      $folderIds = array();
      foreach($folder as $f)
        {
        if(!$f instanceof FolderDao)
          {
          throw new Zend_Exception("Should be a folder.");
          }
        $folderIds[] = $f->getKey();
        }
      }
    else if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }
    else
      {
      $folderIds = array($folder->getKey());
      }
    if($userDao == null)
      {
      $userId = -1;
      }
    else if(!$userDao instanceof UserDao)
      {
      throw new Zend_Exception("Should be an user.");
      }
    else
      {
      $userId = $userDao->getUserId();
      if($userDao->isAdmin())
        {
        $isAdmin = true;
        }
      }

    $subqueryUser = $this->database->select()
                          ->setIntegrityCheck(false)
                          ->from(array('f' => 'folder'))
                          ->join(array('p' => 'folderpolicyuser'),
                                'f.folder_id = p.folder_id',
                                 array('p.policy'))
                          ->where('f.parent_id IN (?)', $folderIds)
                          ->where('policy >= ?', $policy)
                          ->where('user_id = ? ', $userId);

    $subqueryGroup = $this->database->select()
                    ->setIntegrityCheck(false)
                    ->from(array('f' => 'folder'))
                    ->join(array('p' => 'folderpolicygroup'),
                          'f.folder_id = p.folder_id',
                           array('p.policy'))
                    ->where('f.parent_id IN (?)', $folderIds)
                    ->where('policy >= ?', $policy)
                    ->where('( '.$this->database->getDB()->quoteInto('group_id = ? ', MIDAS_GROUP_ANONYMOUS_KEY).' OR
                              group_id IN (' .new Zend_Db_Expr(
                              $this->database->select()
                                   ->setIntegrityCheck(false)
                                   ->from(array('u2g' => 'user2group'),
                                          array('group_id'))
                                   ->where('u2g.user_id = ?', $userId)
                                   .'))' ));
    $sql = $this->database->select()
            ->union(array($subqueryUser, $subqueryGroup));

    if($isAdmin)
      {
      $sql = $this->database->select()
                          ->setIntegrityCheck(false)
                          ->from(array('f' => 'folder'))
                          ->where('f.parent_id IN (?)', $folderIds);
      }

    $rowset = $this->database->fetchAll($sql);
    $return = array();
    $policyArray = array();
    foreach($rowset as $keyRow => $row)
      {
      if($isAdmin)
        {
        $policyArray[$row['folder_id']] = MIDAS_POLICY_ADMIN;
        }
      else if(!isset($policyArray[$row['folder_id']]) || (isset($policyArray[$row['folder_id']]) && $row['policy'] > $policyArray[$row['folder_id']]))
        {
        $policyArray[$row['folder_id']] = $row['policy'];
        }
      }

    foreach($rowset as $keyRow => $row)
      {
      if(isset($policyArray[$row['folder_id']]))
        {
        $tmpDao = $this->initDao('Folder', $row);
        $tmpDao->policy = $policyArray[$row['folder_id']];
        $return[] = $tmpDao;
        unset($policyArray[$row['folder_id']]);
        }
      }

    $this->Component->Sortdao->field = $sortfield;
    $this->Component->Sortdao->order = $sortdir;
    $sortFn = 'sortByName';
    if($sortfield == 'date')
      {
      $sortFn = 'sortByDate';
      }
    usort($return, array($this->Component->Sortdao, $sortFn));
    return $return;
    }

  /** Get the child folder
   *  @return FolderDao
   */
  function getFolderByName($folder, $foldername)
    {
    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }
    $row = $this->database->fetchRow($this->database->select()->from($this->_name)
                                          ->where('parent_id=?', $folder->getFolderId())
                                          ->where('name=?', $foldername));
    return $this->initDao('Folder', $row);
    } // end function getFolderByName

  /** Add an item to a folder
   * @return void
   */
  function addItem($folder, $item, $update = true)
    {
    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }
    if(!$item instanceof ItemDao)
      {
      throw new Zend_Exception("Should be an item.");
      }
    $itemModel = MidasLoader::loadModel('Item');
    // Update item name to avoid duplicated names within the same folder
    if($update)
      {
      $item->setName($itemModel->updateItemName($item->getName(), $folder));
      }
    $this->database->link('items', $folder, $item);
    } // end function addItem

  /** Remove an item from a folder
   * @return void
   */
  function removeItem($folder, $item)
    {
    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }
    if(!$item instanceof ItemDao)
      {
      throw new Zend_Exception("Should be an item.");
      }
    $this->database->removeLink('items', $folder, $item);
    if(count($item->getFolders()) == 0)
      {
      $itemModel = MidasLoader::loadModel('Item');
      $itemModel->delete($item);
      }
    } // end function addItem

  /** Return an item by its name
   * @return ItemDao*/
  function getItemByName($folder, $itemname)
    {
    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception("Should be a folder.");
      }
    $row = $this->database->fetchRow($this->database->select()->setIntegrityCheck(false)
                                          ->from('item')
                                          ->join('item2folder', 'item2folder.item_id = item.item_id')
                                          ->where('item2folder.folder_id=?', $folder->getFolderId())
                                          ->where('item.name=?', $itemname));
    return $this->initDao('Item', $row);
    } // end function getChildIdFromName

  /** Return a list of folders corresponding to the search
   * @return Array of FolderDao */
  function getFoldersFromSearch($search, $userDao, $limit = 14, $group = true, $order = 'view')
    {
    if(Zend_Registry::get('configDatabase')->database->adapter == 'PDO_PGSQL')
      {
      $group = false; //Postgresql don't like the sql request with group by
      }
    $isAdmin = false;
    if($userDao == null)
      {
      $userId = -1;
      }
    else if(!$userDao instanceof UserDao)
      {
      throw new Zend_Exception("Should be an user.");
      }
    else
      {
      $userId = $userDao->getUserId();
      if($userDao->isAdmin())
        {
        $isAdmin = true;
        }
      }

    $sql = $this->database->select();
    if($group)
      {
      $sql->from(array('f' => 'folder'), array('folder_id', 'name', 'count(*)'))->distinct();
      }
    else
      {
      $sql->from(array('f' => 'folder'))->distinct();
      }

    if(!$isAdmin)
      {
      $sql->joinLeft(array('fpu' => 'folderpolicyuser'), '
                    f.folder_id = fpu.folder_id AND '.$this->database->getDB()->quoteInto('fpu.policy >= ?', MIDAS_POLICY_READ).'
                       AND '.$this->database->getDB()->quoteInto('fpu.user_id = ? ', $userId).' ', array())
          ->joinLeft(array('fpg' => 'folderpolicygroup'), '
                         f.folder_id = fpg.folder_id AND '.$this->database->getDB()->quoteInto('fpg.policy >= ?', MIDAS_POLICY_READ).'
                             AND ( '.$this->database->getDB()->quoteInto('fpg.group_id = ? ', MIDAS_GROUP_ANONYMOUS_KEY).' OR
                                  fpg.group_id IN (' .new Zend_Db_Expr(
                                  $this->database->select()
                                       ->setIntegrityCheck(false)
                                       ->from(array('u2g' => 'user2group'),
                                              array('group_id'))
                                       ->where('u2g.user_id = ?', $userId)
                                       ) .'))', array())
          ->where(
           '(
            fpu.folder_id is not null or
            fpg.folder_id is not null)'
            );
      }
    $sql->setIntegrityCheck(false)
          ->where($this->database->getDB()->quoteInto('name LIKE ?', '%'.$search.'%'))
          ->where('name != ?', "Public")
          ->where('name != ?', "Private")
          ->limit($limit);

    if($group)
      {
      $sql->group('f.name');
      }

    switch($order)
      {
      case 'name':
        $sql->order(array('f.name ASC'));
        break;
      case 'date':
        $sql->order(array('f.date_update ASC'));
        break;
      case 'view':
      default:
        $sql->order(array('f.view DESC'));
        break;
      }
    $rowset = $this->database->fetchAll($sql);
    $return = array();
    foreach($rowset as $row)
      {
      $tmpDao = $this->initDao('Folder', $row);
      if(isset($row['count(*)']))
        {
        $tmpDao->count = $row['count(*)'];
        }
      $return[] = $tmpDao;
      unset($tmpDao);
      }
    return $return;
    } // end getFolderFromSearch()

  /**
   * Returns whether the folder is able to be deleted.
   * Any folder can be deleted unless it is a base, Public, or Private Folder
   * of a User or Community.
   */
  function isDeleteable($folder)
    {
    if(!$folder instanceof FolderDao)
      {
      throw new Zend_Exception('Should be a folder.');
      }
    $id = $folder->getFolderId();
    if($this->database->fetchRow($this->database->select()->setIntegrityCheck(false)
                                      ->from('community')
                                      ->where('folder_id=?', $id)
                                      ->orwhere('publicfolder_id=?', $id)
                                      ->orwhere('privatefolder_id=?', $id)))
      {
      return false;
      }

    if($this->database->fetchRow($this->database->select()->setIntegrityCheck(false)
                                      ->from('user')
                                      ->where('folder_id=?', $id)
                                      ->orwhere('publicfolder_id=?', $id)
                                      ->orwhere('privatefolder_id=?', $id)))
      {
      return false;
      }
    return true;
    }

  /**
   * Will return all root folder daos.  There is a root folder for each
   * user, and one for each community.
   */
  function getRootFolders()
    {
    $sql = $this->database->select()->setIntegrityCheck(false)
                          ->where('parent_id < ?', 0);
    $rowset = $this->database->fetchAll($sql);
    $rootFolders = array();
    foreach($rowset as $row)
      {
      $rootFolders[] = $this->initDao('Folder', $row);
      }
    return $rootFolders;
    }

  /**
   * Call this to delete all folders whose parent folder no longer exists.
   * After orphans are deleted, the tree indices will be recomputed so they
   * will be consistent.  As such, server should not be written to
   * during this operation.
   */
  function deleteOrphanedFolders()
    {
    $sql = $this->database->select()->setIntegrityCheck(false)
                   ->from(array('f' => 'folder'), array('folder_id'))
                   ->where('f.parent_id > ?', 0)
                   ->where('(NOT f.parent_id IN ('.new Zend_Db_Expr(
                            $this->database->select()->setIntegrityCheck(false)
                                 ->from(array('subf' => 'folder'), array('folder_id'))
                           .'))' ));
    $rowset = $this->database->fetchAll($sql);
    $ids = array();
    foreach($rowset as $row)
      {
      $ids[] = $row['folder_id'];
      }
    foreach($ids as $id)
      {
      $folder = $this->load($id);
      if(!$folder)
        {
        continue;
        }
      $this->delete($folder);
      }
    // Wipe the current tree indices from all rows
    $this->database->update(array('left_indice' => 0, 'right_indice' => 0), array());

    // Recompute the indices for all rows
    $rootFolders = $this->getRootFolders();
    foreach($rootFolders as $rootFolder)
      {
      $this->_recomputeSubtree($rootFolder);
      }
    }

  /**
   * Will recompute the left and right indices of the subtree of the given node.
   */
  protected function _recomputeSubtree($folder)
    {
    if($folder->getParentId() <= 0)
      {
      $rightParent = 0;
      }
    else
      {
      $parentFolder = $folder->getParent();
      $rightParent = $parentFolder->getRightIndice();
      }

    $folder->setLeftIndice($rightParent);
    $folder->setRightIndice($rightParent + 1);

    $this->database->getDB()->update('folder',
      array('right_indice' => new Zend_Db_Expr('2 + right_indice')),
      array('right_indice >= ? AND left_indice != right_indice' => $rightParent));
    $this->database->getDB()->update('folder',
      array('left_indice' => new Zend_Db_Expr('2 + left_indice')),
      array('left_indice >= ? AND left_indice != right_indice' => $rightParent));

    parent::save($folder);

    $children = $folder->getFolders();
    foreach($children as $child)
      {
      $this->_recomputeSubtree($child);
      }
    }
} // end class
