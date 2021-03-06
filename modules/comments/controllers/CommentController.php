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

/** Controller for adding and removing comments */
class Comments_CommentController extends Comments_AppController
{
    public $_models = array('Item');
    public $_moduleModels = array('Itemcomment');

    /**
     * Add a comment on an item
     *
     * @param itemId The item id to add the comment to
     * @param comment The text of the comment
     * @throws Zend_Exception
     */
    public function addAction()
    {
        if (!$this->logged) {
            throw new Zend_Exception('Must be logged in to comment on an item');
        }

        $itemId = $this->getParam('itemId');
        if (!isset($itemId) || !$itemId) {
            throw new Zend_Exception('Must set itemId parameter');
        }
        $item = $this->Item->load($itemId);
        if (!$item) {
            throw new Zend_Exception('Not a valid itemId');
        }
        $comment = $this->getParam('comment');

        $this->disableView();
        $this->disableLayout();

        /** @var Comments_ItemcommentModel $itemCommentModel */
        $itemCommentModel = MidasLoader::loadModel('Itemcomment', $this->moduleName);
        $itemCommentModel->addComment($this->userSession->Dao, $item, $comment);

        echo JsonComponent::encode(array('status' => 'ok', 'message' => 'Comment added'));
    }

    /**
     * Used to refresh the list of comments on the page
     *
     * @param itemId The item id whose comments to get
     * @param limit Max number of comments to display at once
     * @param offset Offset count for pagination
     * @throws Zend_Exception
     */
    public function getAction()
    {
        $itemId = $this->getParam('itemId');
        if (!isset($itemId) || !$itemId) {
            throw new Zend_Exception('Must set itemId parameter');
        }
        $item = $this->Item->load($itemId);
        if (!$item) {
            throw new Zend_Exception('Not a valid itemId');
        }
        $limit = $this->getParam('limit');
        $offset = $this->getParam('offset');

        $this->disableView();
        $this->disableLayout();

        /** @var Comments_CommentComponent $commentComponent */
        $commentComponent = MidasLoader::loadComponent('Comment', $this->moduleName);
        list($comments, $total) = $commentComponent->getComments($item, $limit, $offset);

        echo JsonComponent::encode(array('status' => 'ok', 'comments' => $comments, 'total' => $total));
    }

    /**
     * Used to delete a comment
     *
     * @param commentId Id of the comment to delete
     * @throws Zend_Exception
     */
    public function deleteAction()
    {
        if (!$this->logged) {
            throw new Zend_Exception('Must be logged in to delete an item');
        }
        $commentId = $this->getParam('commentId');
        if (!isset($commentId) || !$commentId) {
            throw new Zend_Exception('Must set commentId parameter');
        }
        $comment = $this->Comments_Itemcomment->load($commentId);
        if (!$comment) {
            throw new Zend_Exception('Not a valid commentId');
        }

        $this->disableView();
        $this->disableLayout();

        if ($this->userSession->Dao->isAdmin() || $this->userSession->Dao->getKey() == $comment->getUserId()
        ) {
            $this->Comments_Itemcomment->delete($comment);
            $retVal = array('status' => 'ok', 'message' => "Comment deleted");
        } else {
            $retVal = array('status' => 'error', 'message' => "Cannot delete comment (permission denied)");
        }
        echo JsonComponent::encode($retVal);
    }
}
