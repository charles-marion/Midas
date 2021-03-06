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
 * Item comment DAO for the comments module.
 *
 * @method int getCommentId()
 * @method void setCommentId(int $commentId)
 * @method int getUserId()
 * @method void setUserId(int $userId)
 * @method int getItemId()
 * @method void setItemId int $itemId)
 * @method string getComment()
 * @method void setComment(string $comment)
 * @method string getDate()
 * @method void setDate(string $date)
 * @method UserDao getUser()
 * @method void setUser(UserDao $user)
 * @method ItemDao getItem()
 * @method void setItem(ItemDao $item)
 * @package Modules\Comments\DAO
 */
class Comments_ItemcommentDao extends Comments_AppDao
{
    /** @var string */
    public $_model = 'Itemcomment';

    /** @var string */
    public $_module = 'comments';
}
