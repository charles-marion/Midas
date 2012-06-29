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

/** User Controller */
class UserController extends AppController
  {
  public $_models = array('User', 'Folder', 'Folderpolicygroup', 'Folderpolicyuser', 'Group', 'Feed', 'Feedpolicygroup', 'Feedpolicyuser', 'Group', 'Item', 'Community');
  public $_daos = array('User', 'Folder', 'Folderpolicygroup', 'Folderpolicyuser', 'Group');
  public $_components = array('Date', 'Filter', 'Sortdao');
  public $_forms = array('User');

  /** Init Controller */
  function init()
    {
    $actionName = Zend_Controller_Front::getInstance()->getRequest()->getActionName();
    if(isset($actionName) && is_numeric($actionName))
      {
      $this->_forward('userpage', null, null, array('user_id' => $actionName));
      }
    } // end init()

  /** Index */
  function indexAction()
    {
    $this->view->header = $this->t("Users");
    $this->view->activemenu = 'user'; // set the active menu

    $order = $this->_getParam('order');
    $offset = $this->_getParam('offset');

    if(!isset($order))
      {
      $order = 'view';
      }
    if(!isset($offset))
      {
      $offset = 0;
      }

    if($this->logged && $this->userSession->Dao->isAdmin())
      {
      $users = $this->User->getAll(false, 100, $order, $offset);
      $this->view->isAdmin = true;
      }
    else
      {
      $users = $this->User->getAll(true, 100, $order, $offset, $this->userSession->Dao);
      $this->view->isAdmin = false;
      }

    $this->view->order = $order;
    $this->view->offset = $offset;
    $this->view->users = $users;
    $this->view->nUsers = $this->User->getCountAll();
    } //end index

  /** Recover the password (ajax) */
  function recoverpasswordAction()
    {
    if($this->logged)
      {
      throw new Zend_Exception('Shouldn\'t be logged in');
      }
    $this->disableLayout();
    $email = $this->_getParam('email');
    if(isset($email))
      {
      $this->disableView();
      $user = $this->User->getByEmail($email);

       // Check ifthe email is already registered
      if(!$user)
        {
        echo JsonComponent::encode(array(false, $this->t('No user registered with that email.')));
        exit;
        }

      $notifications = Zend_Registry::get('notifier')->callback('CALLBACK_CORE_RESET_PASSWORD', array('user' => $user));
      foreach($notifications as $module => $result)
        {
        if($result['status'] === true)
          {
          echo JsonComponent::encode(array(true, $result['message']));
          return;
          }
        }

      // Create a new password
      $keychars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
      $length = 10;

      /** make_seed_recoverpass */
      function make_seed_recoverpass()
        {
        list($usec, $sec) = explode(' ', microtime());
        return (float) $sec + ((float) $usec * 100000);
        }
      srand(make_seed_recoverpass());

      $pass = "";
      $max = strlen($keychars) - 1;
      for($i = 0; $i <= $length; $i++)
        {
        $pass .= substr($keychars, rand(0, $max), 1);
        }
      $encrypted = md5($pass);

      $passwordPrefix = Zend_Registry::get('configGlobal')->password->prefix;
      $salted = $pass;
      if(isset($passwordPrefix) && !empty($passwordPrefix))
        {
        $salted = $passwordPrefix.$pass;
        }

      $user->setPassword(md5($salted));

      // Send the email
      $url = $this->getServerURL().$this->view->webroot;

      $text = "Hello,<br><br> You have asked for a new password for MIDAS.<br>";
      $text .= "Please go to this page to log in to MIDAS and change your password:<br>";
      $text .= "<a href=\"".$url."\">".$url."</a><br>";
      $text .= "Your new password is: ".$pass."<br>";
      $text .= "<br><br>Generated by MIDAS";

      if($this->isTestingEnv() || mail("$email", "MIDAS: Password request", "$text", "From: \nReply-To: no-reply\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0\nContent-type: text/html; charset = UTF-8"))
        {
        $this->User->save($user);
        echo JsonComponent::encode(array(true, $this->t('An Email has been sent to').' '.$email));
        }
      else
        {
        $alert = "Problem during process !";
        echo JsonComponent::encode(array(false, $this->t('Problem during process !')));
        }
      }
    } // end recoverpassword

  /**
   * Logout a user.
   * @param noRedirect Set this parameter if you are calling logout with ajax
   *                   and do not want the controller to redirect
   */
  function logoutAction()
    {
    $this->userSession->Dao = null;
    Zend_Session::ForgetMe();
    setcookie('midasUtil', null, time() + 60 * 60 * 24 * 30, '/'); //30 days
    $noRedirect = $this->_getParam('noRedirect');
    if(isset($noRedirect))
      {
      $this->disableView();
      $this->disableLayout();
      return;
      }
    $this->_redirect('/');
    } //end logoutAction

  /** Set user's starting guide value */
  function startingguideAction()
    {
    $this->disableLayout();
    $this->disableView();
    if($this->logged && isset($_POST['value']))
      {
      $value = 0;
      if($_POST['value'] == 1)
        {
        $value = 1;
        }
      $this->userSession->Dao->setDynamichelp($value);
      $user = $this->User->load($this->userSession->Dao->getKey());
      $user->setDynamichelp($value);
      $this->User->save($user);
      }
    }

  /**
   * Function for registering a new user; provides an ajax response; does not attempt to redirect,
   * instead post-register action is left up to the client
   */
  function ajaxregisterAction()
    {
    $adminCreate = $this->_getParam('adminCreate');
    $adminCreate = isset($adminCreate);

    if($adminCreate)
      {
      $this->requireAdminPrivileges();
      }
    $this->disableView();
    $this->disableLayout();
    if(!$adminCreate &&
       isset(Zend_Registry::get('configGlobal')->closeregistration) &&
       Zend_Registry::get('configGlobal')->closeregistration == "1")
      {
      echo JsonComponent::encode(array('status' => 'error', 'message' => 'New user registration is disabled.'));
      return;
      }
    $form = $this->Form->User->createRegisterForm();
    if($this->_request->isPost() && $form->isValid($this->getRequest()->getPost()))
      {
      if($this->User->getByEmail(strtolower($form->getValue('email'))) !== false)
        {
        echo JsonComponent::encode(array('status' => 'error', 'message' => 'That email is already registered', 'alreadyRegistered' => true));
        return;
        }
      $email = $form->getValue('email');
      $newUser = $this->User->createUser(
      trim($form->getValue('email')),
      $form->getValue('password1'),
      trim($form->getValue('firstname')),
      trim($form->getValue('lastname')));

      if($adminCreate)
        {
        $subject = 'Midas user registration';
        $headers = "From: \nReply-To: no-reply\nX-Mailer: PHP/".phpversion()."\nMIME-Version: 1.0\nContent-type: text/html; charset = UTF-8";
        $url = 'http://'.$_SERVER['HTTP_HOST'].'/'.$this->view->webroot;
        $body = "An administrator has created a user account for you at the following Midas instance:<br/><br/>";
        $body .= '<a href="'.$url.'">'.$url.'</a><br/><br/>';
        $body .= "Log in using this email address (".$email.") and your initial password:<br/><br/>";
        $body .= '<b>'.$form->getValue('password1').'</b><br/><br/>';
        $body .= "-Midas administrators";
        if($this->isTestingEnv() || mail($email, $subject, $body, $headers))
          {
          echo JsonComponent::encode(array('status' => 'ok', 'message' => 'User created successfully'));
          }
        else
          {
          echo JsonComponent::encode(array('status' => 'warning',
                                           'message' => 'User created, but sending of email failed',
                                           'validValues' => $form->getValidValues($this->getRequest()->getPost())));
          }
        }
      else
        {
        $this->userSession->Dao = $newUser;
        echo JsonComponent::encode(array('status' => 'ok', 'message' => 'User registered successfully'));
        }
      }
    else
      {
      echo JsonComponent::encode(array('status' => 'error',
                                       'message' => 'Registration failed',
                                       'validValues' => $form->getValidValues($this->getRequest()->getPost())));
      }
    }

  /** Register a user */
  function registerAction()
    {
    if(isset(Zend_Registry::get('configGlobal')->closeregistration) && Zend_Registry::get('configGlobal')->closeregistration == "1")
      {
      throw new Zend_Exception('New user registration is disabled.');
      }
    $form = $this->Form->User->createRegisterForm();
    if($this->_request->isPost() && $form->isValid($this->getRequest()->getPost()))
      {
      if($this->User->getByEmail(strtolower($form->getValue('email'))) !== false)
        {
        throw new Zend_Exception("User already exists.");
        }

      $this->userSession->Dao = $this->User->createUser(trim($form->getValue('email')), $form->getValue('password1'), trim($form->getValue('firstname')), trim($form->getValue('lastname')));

      $this->_redirect("/feed?first=true");
      }
    $this->view->form = $this->getFormAsArray($form);
    $this->disableLayout();
    $this->view->jsonRegister = JsonComponent::encode(array(
      'MessageNotValid' => $this->t('The e-mail is not valid'), 'MessageNotAvailable' => $this->t('That email is already registered'), 'MessagePassword' => $this->t('Password too short'), 'MessagePasswords' => $this->t('The passwords are not the same'), 'MessageLastname' => $this->t('Please set your lastname'), 'MessageTerms' => $this->t('Please validate the terms of service'), 'MessageFirstname' => $this->t('Please set your firstname')
    ));

    } //end register

  /**
   * Function for logging in; provides an ajax response; does not attempt to redirect,
   * instead post-login action is left up to the client.
   * Does not currently support LDAP login.
   */
  function ajaxloginAction()
    {
    $this->disableView();
    $this->disableLayout();

    $form = $this->Form->User->createLoginForm();
    if(!$form->isValid($this->getRequest()->getPost()))
      {
      echo JsonComponent::encode(array('status' => 'error', 'message' => 'Invalid login form'));
      return;
      }
    $userDao = $this->User->getByEmail($form->getValue('email'));
    $passwordPrefix = Zend_Registry::get('configGlobal')->password->prefix;
    if($userDao !== false && md5($passwordPrefix.$form->getValue('password')) == $userDao->getPassword())
      {
      setcookie('midasUtil', $userDao->getKey().'-'.md5($userDao->getPassword()), time() + 60 * 60 * 24 * 30, '/'); //30 days
      Zend_Session::start();
      $user = new Zend_Session_Namespace('Auth_User');
      $user->setExpirationSeconds(60 * Zend_Registry::get('configGlobal')->session->lifetime);
      $user->Dao = $userDao;
      $user->lock();
      $this->getLogger()->info(__METHOD__ . " Log in : " . $userDao->getFullName());
      echo JsonComponent::encode(array('status' => 'ok', 'message' => 'Login successful'));
      }
    else
      {
      echo JsonComponent::encode(array('status' => 'error', 'message' => 'Login failed'));
      }
    }

  /** Login action */
  function loginAction()
    {
    $this->Form->User->uri = $this->getRequest()->getRequestUri();
    $form = $this->Form->User->createLoginForm();
    $this->view->form = $this->getFormAsArray($form);
    $this->disableLayout();
    if($this->_request->isPost())
      {
      $this->disableView();
      $previousUri = $this->_getParam('previousuri');
      if($form->isValid($this->getRequest()->getPost()))
        {
        try
          {
          $notifications = array(); //initialize first in case of exception
          $notifications = Zend_Registry::get('notifier')->callback('CALLBACK_CORE_AUTHENTICATION', array(
            'email' => $form->getValue('email'),
            'password' => $form->getValue('password')));
          }
        catch(Zend_Exception $exc)
          {
          $this->getLogger()->crit($exc->getMessage());
          }
        $authModule = false;
        foreach($notifications as $module => $user)
          {
          if($user)
            {
            $userDao = $user;
            $authModule = true;
            break;
            }
          }

        if(!$authModule)
          {
          $userDao = $this->User->getByEmail($form->getValue('email'));
          }

        $passwordPrefix = Zend_Registry::get('configGlobal')->password->prefix;
        if($authModule || $userDao !== false && md5($passwordPrefix.$form->getValue('password')) == $userDao->getPassword())
          {
          $remember = $form->getValue('remerberMe');
          if(isset($remember) && $remember == 1)
            {
            if(!$this->isTestingEnv())
              {
              setcookie('midasUtil', $userDao->getKey().'-'.md5($userDao->getPassword()), time() + 60 * 60 * 24 * 30, '/'); //30 days
              }
            }
          else
            {
            if(!$this->isTestingEnv())
              {
              setcookie('midasUtil', null, time() + 60 * 60 * 24 * 30, '/'); //30 days
              Zend_Session::start();
              $user = new Zend_Session_Namespace('Auth_User');
              $user->setExpirationSeconds(60 * Zend_Registry::get('configGlobal')->session->lifetime);
              $user->Dao = $userDao;
              $url = $form->getValue('url');
              $user->lock();
              }
            }
          $this->getLogger()->info(__METHOD__ . " Log in : " . $userDao->getFullName());

          if(isset($previousUri) && strpos($previousUri, $this->view->webroot) !== false && strpos($previousUri, 'logout') === false)
            {
            $redirect = $previousUri.'?first=true';
            }
          else
            {
            $redirect = $this->view->webroot.'/feed?first=true';
            }
          echo JsonComponent::encode(array(
            'status' => true,
            'redirect' => $redirect));
          }
        else
          {
          echo JsonComponent::encode(array(
            'status' => false,
            'message' => 'Invalid email or password'));
          }
        }
      else
        {
        echo JsonComponent::encode(array(
          'status' => false,
          'message' => 'Invalid login'));
        }
      }
    } // end method login


  /** Term of service */
  public function termofserviceAction()
    {
    if($this->getRequest()->isXmlHttpRequest())
      {
      $this->_helper->layout->disableLayout();
      }
    } // end term of service


  /**
   * Test whether a given user already exists or not.
   * @param entry The email/login to test.
   * @return Echoes "true" or "false".
   */
  public function userexistsAction()
    {
    $this->disableLayout();
    $this->disableView();
    $entry = $this->_getParam('entry');
    if(!is_string($entry))
      {
      echo 'false';
      return;
      }

    $notifications = Zend_Registry::get('notifier')->callback('CALLBACK_CORE_CHECK_USER_EXISTS',
      array('entry' => $entry));
    foreach($notifications as $module => $value)
      {
      if($value === true)
        {
        echo 'true';
        return;
        }
      }

    $userDao = $this->User->getByEmail(strtolower($entry));
    if($userDao)
      {
      echo 'true';
      }
    else
      {
      echo 'false';
      }
    } //end valid entry

  /** Settings page action */
  public function settingsAction()
    {
    if(!$this->logged)
      {
      $this->disableView();
      return false;
      }
    $this->disableLayout();

    $userId = $this->_getParam('userId');
    if(isset($userId) && $userId != $this->userSession->Dao->getKey() && !$this->userSession->Dao->isAdmin())
      {
      throw new Zend_Exception(MIDAS_ADMIN_PRIVILEGES_REQUIRED);
      }
    else if(isset($userId))
      {
      $userDao = $this->User->load($userId);
      }
    else
      {
      $userDao = $this->userSession->Dao;
      }

    if(empty($userDao) || $userDao == false)
      {
      throw new Zend_Exception("Unable to load user");
      }

    $notifications = Zend_Registry::get('notifier')->callback('CALLBACK_CORE_ALLOW_PASSWORD_CHANGE',
      array('user' => $userDao, 'currentUser' => $this->userSession->Dao));
    $this->view->allowPasswordChange = true;

    foreach($notifications as $module => $allow)
      {
      if($allow['allow'] === false)
        {
        $this->view->allowPasswordChange = false;
        break;
        }
      }

    $defaultValue = array();
    $defaultValue['email'] = $userDao->getEmail();
    $defaultValue['firstname'] = $userDao->getFirstname();
    $defaultValue['lastname'] = $userDao->getLastname();
    $defaultValue['company'] = $userDao->getCompany();
    $defaultValue['privacy'] = $userDao->getPrivacy();
    $defaultValue['city'] = $userDao->getCity();
    $defaultValue['country'] = $userDao->getCountry();
    $defaultValue['website'] = $userDao->getWebsite();
    $defaultValue['biography'] = $userDao->getBiography();
    $accountForm = $this->Form->User->createAccountForm($defaultValue);
    $this->view->accountForm = $this->getFormAsArray($accountForm);
    $this->view->prependFields = array();
    $this->view->appendFields = array();

    $moduleFields = Zend_Registry::get('notifier')->callback('CALLBACK_CORE_USER_PROFILE_FIELDS',
      array('user' => $userDao, 'currentUser' => $this->userSession->Dao));
    foreach($moduleFields as $module => $field)
      {
      if(isset($field['position']) && $field['position'] == 'top')
        {
        $this->view->prependFields[] = $field;
        }
      else
        {
        $this->view->appendFields[] = $field;
        }
      }

    if($this->_request->isPost())
      {
      $this->_helper->viewRenderer->setNoRender();
      $submitPassword = $this->_getParam('modifyPassword');
      $modifyAccount = $this->_getParam('modifyAccount');
      $modifyPicture = $this->_getParam('modifyPicture');
      $modifyPictureGravatar = $this->_getParam('modifyPictureGravatar');
      if(isset($submitPassword) && $this->logged)
        {
        if(!$this->view->allowPasswordChange)
          {
          throw new Zend_Exception('Changing password is disallowed for this user');
          }
        $oldPass = $this->_getParam('oldPassword');
        $newPass = $this->_getParam('newPassword');
        $passwordPrefix = Zend_Registry::get('configGlobal')->password->prefix;
        $userDao = $this->User->load($userDao->getKey());
        if($userDao != false && ((!$userDao->isAdmin() && $this->userSession->Dao->isAdmin()) || md5($passwordPrefix.$oldPass) == $userDao->getPassword()))
          {
          $userDao->setPassword(md5($passwordPrefix.$newPass));
          $this->User->save($userDao);
          if(!isset($userId))
            {
            $this->userSession->Dao = $userDao;
            }
          echo JsonComponent::encode(array(true, $this->t('Changes saved')));
          Zend_Registry::get('notifier')->callback('CALLBACK_CORE_PASSWORD_CHANGED', array('userDao' => $userDao));
          }
        else
          {
          echo JsonComponent::encode(array(false, $this->t('The old password is incorrect')));
          }
        }

      if(isset($modifyAccount) && $this->logged)
        {
        $newEmail = trim($this->_getParam('email'));
        $firtname = trim($this->_getParam('firstname'));
        $lastname = trim($this->_getParam('lastname'));
        $company = trim($this->_getParam('company'));
        $privacy = $this->_getParam('privacy');
        $city = $this->_getParam('city');
        $country = $this->_getParam('country');
        $website = $this->_getParam('website');
        $biography = $this->_getParam('biography');

        if(!$accountForm->isValid($this->getRequest()->getPost()))
          {
          echo JsonComponent::encode(array(false, 'Invalid form value'));
          return;
          }

        $userDao = $this->User->load($userDao->getKey());

        if(!isset($privacy) || ($privacy != MIDAS_USER_PRIVATE && $privacy != MIDAS_USER_PUBLIC))
          {
          echo JsonComponent::encode(array(false, 'Error: invalid privacy flag'));
          return;
          }
        if(!isset($lastname) || !isset($firtname) || empty($lastname) || empty($firtname))
          {
          echo JsonComponent::encode(array(false, 'Error: First and last name required'));
          return;
          }
        if($newEmail != $userDao->getEmail())
          {
          $existingUser = $this->User->getByEmail($newEmail);
          if($existingUser)
            {
            echo JsonComponent::encode(array(false, 'Error: that email address belongs to another account'));
            return;
            }
          $userDao->setEmail($newEmail);
          }
        $userDao->setFirstname($firtname);
        $userDao->setLastname($lastname);
        if(isset($company))
          {
          $userDao->setCompany($company);
          }
        if(isset($city))
          {
          $userDao->setCity($city);
          }
        if(isset($country))
          {
          $userDao->setCountry($country);
          }
        if(isset($website))
          {
          $userDao->setWebsite($website);
          }
        if(isset($biography))
          {
          $userDao->setBiography($biography);
          }
        $userDao->setPrivacy($privacy);
        if($this->userSession->Dao->isAdmin() && $this->userSession->Dao->getKey() != $userDao->getKey())
          {
          $adminStatus = (bool)$this->_getParam('adminStatus');
          $userDao->setAdmin($adminStatus ? 1 : 0);
          }
        $this->User->save($userDao);
        if(!isset($userId))
          {
          $this->userSession->Dao = $userDao;
          }
        try
          {
          Zend_Registry::get('notifier')->callback('CALLBACK_CORE_USER_SETTINGS_CHANGED', array(
            'user' => $userDao,
            'currentUser' => $this->userSession->Dao,
            'fields' => $this->_getAllParams()));
          }
        catch(Exception $e)
          {
          echo JsonComponent::encode(array(false, $e->getMessage()));
          return;
          }
        echo JsonComponent::encode(array(true, $this->t('Changes saved')));
        }
      if(isset($modifyPicture) && $this->logged)
        {
        if($this->isTestingEnv())
          {
          //simulate file upload
          $path = BASE_PATH.'/tests/testfiles/search.png';
          $size = filesize($path);
          $mime = 'image/png';
          }
        else
          {
          $mime = $_FILES['file']['type'];
          $upload = new Zend_File_Transfer();
          $upload->receive();
          $path = $upload->getFileName();
          $size =  $upload->getFileSize();
          }

        if(!empty($path) && file_exists($path) && $size > 0)
          {
          if(file_exists($path) && $mime == 'image/jpeg')
            {
            try
              {
              $src = imagecreatefromjpeg($path);
              }
            catch(Exception $exc)
              {
              echo JsonComponent::encode(array(false, 'Error: Unable to read jpg file'));
              return;
              }
            }
          else if(file_exists($path) && $mime == 'image/png')
            {
            try
              {
              $src = imagecreatefrompng($path);
              }
            catch(Exception $exc)
              {
              echo JsonComponent::encode(array(false, 'Error: Unable to read png file'));
              return;
              }
            }
          else if(file_exists($path) && $mime == 'image/gif')
            {
            try
              {
              $src = imagecreatefromgif($path);
              }
            catch(Exception $exc)
              {
              echo JsonComponent::encode(array(false, 'Error: Unable to read gif file'));
              return;
              }
            }
          else
            {
            echo JsonComponent::encode(array(false, 'Error: wrong format'));
            return;
            }

          $tmpPath = BASE_PATH.'/data/thumbnail/'.rand(1, 1000);
          if(!file_exists(BASE_PATH.'/data/thumbnail/'))
            {
            throw new Zend_Exception("Thumbnail path does not exist: ".BASE_PATH.'/data/thumbnail/');
            }
          if(!file_exists($tmpPath))
            {
            mkdir($tmpPath);
            }
          $tmpPath .= '/'.rand(1, 1000);
          if(!file_exists($tmpPath))
            {
            mkdir($tmpPath);
            }
          $destionation = $tmpPath."/".rand(1, 1000).'.jpeg';
          while(file_exists($destionation))
            {
            $destionation = $tmpPath."/".rand(1, 1000).'.jpeg';
            }
          $pathThumbnail = $destionation;

          list ($x, $y) = getimagesize($path);  //--- get size of img ---
          $thumb = 32;  //--- max. size of thumb ---
          if($x > $y)
            {
            $tx = $thumb;  //--- landscape ---
            $ty = round($thumb / $x * $y);
            }
          else
            {
            $tx = round($thumb / $y * $x);  //--- portrait ---
            $ty = $thumb;
            }

          $thb = imagecreatetruecolor($tx, $ty);  //--- create thumbnail ---
          imagecopyresampled($thb, $src, 0, 0, 0, 0, $tx, $ty, $x, $y);
          imagejpeg($thb, $pathThumbnail, 80);
          imagedestroy($thb);
          imagedestroy($src);
          if(file_exists($pathThumbnail))
            {
            $userDao = $this->User->load($userDao->getKey());
            $oldThumbnail = $userDao->getThumbnail();
            if(!empty($oldThumbnail) && file_exists(BASE_PATH.'/'.$oldThumbnail))
              {
              unlink(BASE_PATH.'/'.$oldThumbnail);
              }
            $userDao->setThumbnail(substr($pathThumbnail, strlen(BASE_PATH) + 1));
            $this->User->save($userDao);
            if(!isset($userId))
              {
              $this->userSession->Dao = $userDao;
              }
            echo JsonComponent::encode(array(true, $this->t('Changes saved'), $this->view->webroot.'/'.$userDao->getThumbnail()));
            }
          else
            {
            echo JsonComponent::encode(array(false, 'Error'));
            return;
            }
          }
        if(isset($modifyPictureGravatar) && $this->logged)
          {
          $gravatarUrl = $this->User->getGravatarUrl($userDao->getEmail());
          if($gravatarUrl != false)
            {
            $userDao = $this->User->load($userDao->getKey());
            $oldThumbnail = $userDao->getThumbnail();
            if(!empty($oldThumbnail) && file_exists(BASE_PATH.'/'.$oldThumbnail))
              {
              unlink(BASE_PATH.'/'.$oldThumbnail);
              }
            $userDao->setThumbnail($gravatarUrl);
            $this->User->save($userDao);
            if(!isset($userId))
              {
              $this->userSession->Dao = $userDao;
              }
            echo JsonComponent::encode(array(true, $this->t('Changes saved'), $userDao->getThumbnail()));
            }
          else
            {
            echo JsonComponent::encode(array(false, 'Error'));
            }
          }
        }
      }

    $communities = array();
    $groups = $userDao->getGroups();
    foreach($groups as $group)
      {
      $community = $group->getCommunity();
      if(!isset($communities[$community->getKey()]))
        {
        $community->groups = array();
        $communities[$community->getKey()] = $community;
        }
      $communities[$community->getKey()]->groups[] = $group;
      }
    $this->Component->Sortdao->field = 'name';
    $this->Component->Sortdao->order = 'asc';
    usort($communities, array($this->Component->Sortdao, 'sortByName'));

    $this->view->isGravatar = $this->User->getGravatarUrl($userDao->getEmail());

    $this->view->communities = $communities;
    $this->view->user = $userDao;
    $this->view->currentUser = $this->userSession->Dao;
    $this->view->thumbnail = $userDao->getThumbnail();
    $this->view->jsonSettings = array();
    $this->view->jsonSettings['accountErrorFirstname'] = $this->t('Please set your firstname');
    $this->view->jsonSettings['accountErrorLastname'] = $this->t('Please set your lastname');
    $this->view->jsonSettings['passwordErrorShort'] = $this->t('Password too short');
    $this->view->jsonSettings['passwordErrorMatch'] = $this->t('The passwords are not the same');
    $this->view->jsonSettings = JsonComponent::encode($this->view->jsonSettings);

    $this->view->customTabs = Zend_Registry::get('notifier')->callback('CALLBACK_CORE_GET_CONFIG_TABS', array());
    }

  /** User page action*/
  public function userpageAction()
    {
    $this->view->Date = $this->Component->Date;
    $user_id = $this->_getParam("user_id");

    if(!isset($user_id) && !$this->logged)
      {
      $this->view->header = $this->t(MIDAS_LOGIN_REQUIRED);
      $this->_helper->viewRenderer->setNoRender();
      return false;
      }
    elseif(!isset($user_id))
      {
      $userDao = $this->userSession->Dao;
      $this->view->activemenu = 'myprofile'; // set the active menu
      }
    else
      {
      $userDao = $this->User->load($user_id);
      if($userDao->getPrivacy() == MIDAS_USER_PRIVATE &&
        (!$this->logged || $this->userSession->Dao->getKey() != $userDao->getKey()) &&
        (!isset($this->userSession->Dao) || !$this->userSession->Dao->isAdmin()))
        {
        throw new Zend_Exception("Permission error");
        }
      }

    if(!$userDao instanceof UserDao)
      {
      throw new Zend_Controller_Action_Exception("Unable to find user", 404);
      }

    $this->view->user = $userDao;
    $userCommunities = $this->User->getUserCommunities($userDao);
    $filteredCommunities = array();
    foreach($userCommunities as $community)
      {
      if($this->Community->policyCheck($community, $this->userSession->Dao, MIDAS_POLICY_READ))
        {
        $filteredCommunities[] = $community;
        }
      }

    // If this is the user's own page (or admin user), show any pending community invitations
    if($this->logged &&
      ($this->userSession->Dao->getKey() == $userDao->getKey() || $this->userSession->Dao->isAdmin()))
      {
      $invitations = $userDao->getInvitations();
      $communityInvitations = array();
      foreach($invitations as $invitation)
        {
        $community = $this->Community->load($invitation->getCommunityId());
        if($community)
          {
          $communityInvitations[] = $community;
          }
        }
      $this->view->communityInvitations = $communityInvitations;
      }

    $this->view->userCommunities = $filteredCommunities;
    $this->view->folders = array();
    if(!empty($this->userSession->Dao) && ($userDao->getKey() == $this->userSession->Dao->getKey() || $this->userSession->Dao->isAdmin()))
      {
      $this->view->ownedItems = $this->Item->getOwnedByUser($userDao);
      $this->view->shareItems = $this->Item->getSharedToUser($userDao);
      }
    else
      {
      $this->User->incrementViewCount($userDao);
      }

    $this->view->mainFolder = $userDao->getFolder();
    $this->view->folders = $this->Folder->getChildrenFoldersFiltered($this->view->mainFolder, $this->userSession->Dao, MIDAS_POLICY_READ);
    $this->view->privateFolderId = $userDao->getPrivatefolderId();
    $this->view->publicFolderId = $userDao->getPublicfolderId();
    $this->view->items = $this->Folder->getItemsFiltered($this->view->mainFolder, $this->userSession->Dao, MIDAS_POLICY_READ);
    $this->view->feeds = $this->Feed->getFeedsByUser($this->userSession->Dao, $userDao);

    $this->view->isViewAction = ($this->logged && ($this->userSession->Dao->getKey() == $userDao->getKey() || $this->userSession->Dao->isAdmin()));
    $this->view->currentUser = $this->userSession->Dao;
    $this->view->isAdmin = $this->logged && $this->userSession->Dao->isAdmin();
    $this->view->information = array();

    $this->view->disableFeedImages = true;
    $this->view->moduleTabs = Zend_Registry::get('notifier')->callback('CALLBACK_CORE_GET_USER_TABS', array('user' => $userDao));
    $this->view->moduleActions = Zend_Registry::get('notifier')->callback('CALLBACK_CORE_GET_USER_ACTIONS', array('user' => $userDao));
    }

  /** Manage files page action*/
  public function manageAction()
    {
    $this->view->Date = $this->Component->Date;
    $userId = $this->_getParam('userId');

    if(!isset($userId) && !$this->logged)
      {
      $this->view->header = $this->t(MIDAS_LOGIN_REQUIRED);
      $this->_helper->viewRenderer->setNoRender();
      return false;
      }
    elseif(!isset($userId))
      {
      $userDao = $this->userSession->Dao;
      $this->view->activemenu = 'user'; // set the active menu
      }
    else
      {
      $userDao = $this->User->load($userId);
      if(!$this->userSession->Dao->isAdmin() && $this->userSession->Dao->getKey() != $userId)
        {
        throw new Zend_Exception("Permission error");
        }
      }

    if(!$userDao instanceof UserDao)
      {
      throw new Zend_Exception("Unable to find user");
      }

    // Get all the communities this user can see
    $communities = array();
    if($userDao->isAdmin())
      {
      $communities = $this->Community->getAll();
      }
    else
      {
      $communities = $this->Community->getPublicCommunities();
      }
    // Get community folders this user can at least read
    $communityFolders = array();
    foreach($communities as $communityDao)
      {
      $tmpfolders = $this->Folder->getChildrenFoldersFiltered($communityDao->getFolder(), $userDao, MIDAS_POLICY_READ);
      $communityID = $communityDao->getKey();
      $communityFolders[$communityID] = $tmpfolders;
      }

    $this->view->user = $userDao;
    $this->view->mainFolder = $userDao->getFolder();
    $this->view->folders = $this->Folder->getChildrenFoldersFiltered($this->view->mainFolder, $userDao, MIDAS_POLICY_READ);
    $this->view->privateFolderId = $userDao->getPrivatefolderId();
    $this->view->publicFolderId = $userDao->getPublicfolderId();
    $this->view->items = $this->Folder->getItemsFiltered($this->view->mainFolder, $userDao, MIDAS_POLICY_READ);
    $this->view->userCommunities = $communities;
    $this->view->userCommunityFolders = $communityFolders;
    }

  /** Render the dialog related to user deletion */
  public function deletedialogAction()
    {
    $this->disableLayout();
    $userId = $this->_getParam('userId');

    if(!$this->logged)
      {
      throw new Zend_Exception('Must be logged in');
      }
    if(!isset($userId))
      {
      throw new Zend_Exception('Must set a userId parameter');
      }
    $user = $this->User->load($userId);
    if(!$user)
      {
      throw new Zend_Exception('Invalid user id');
      }
    if($this->userSession->Dao->getKey() != $user->getKey())
      {
      $this->requireAdminPrivileges();
      $this->view->deleteSelf = false;
      }
    else
      {
      $this->view->deleteSelf = true;
      }
    $this->view->user = $user;
    }

  /** Delete a user */
  public function deleteAction()
    {
    ignore_user_abort(true);

    if(!$this->logged)
      {
      throw new Zend_Exception('Must be logged in');
      }
    $userId = $this->_getParam('userId');

    if(!isset($userId))
      {
      throw new Zend_Exception('Must set a userId parameter');
      }
    $user = $this->User->load($userId);
    if(!$user)
      {
      throw new Zend_Exception('Invalid user id');
      }
    if($user->isAdmin())
      {
      throw new Zend_Exception('Cannot delete an admin user');
      }

    if($this->userSession->Dao->getKey() != $user->getKey())
      {
      $this->requireAdminPrivileges();
      }
    else
      {
      // log out if user is deleting his or her own account
      $this->userSession->Dao = null;
      Zend_Session::ForgetMe();
      if(!$this->isTestingEnv())
        {
        setcookie('midasUtil', null, time() + 60 * 60 * 24 * 30, '/');
        }
      }
    $this->_helper->viewRenderer->setNoRender();
    $this->disableLayout();

    $name = $user->getFirstname().' '.$user->getLastname();
    $this->User->delete($user);
    $this->getLogger()->info('User '.$name.' successfully deleted');
    echo JsonComponent::encode(array(true, 'User '.$name.' successfully deleted'));
    }
  }//end class
