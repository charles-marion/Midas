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
 * Item thumbnail DAO for the thumbnailcreator module.
 *
 * @method int getItemthumbnailId()
 * @method void setItemthumbnailId(int $itemthumbnailId)
 * @method int getItemId()
 * @method void setItemId(int $itemId)
 * @method int getThumbnailId()
 * @method void setThumbnailId(int $thumbnailId)
 * @method ItemDao getItem()
 * @method void setItem(ItemDao $item)
 * @method void setDagFilename(string $dagFilename)
 * @package Modules\Thumbnailcreator\DAO
 */
class Thumbnailcreator_ItemthumbnailDao extends Thumbnailcreator_AppDao
{
    /** @var string */
    public $_model = 'Itemthumbnail';

    /** @var string */
    public $_module = 'thumbnailcreator';
}
