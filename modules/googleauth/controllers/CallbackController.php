<?php


class Googleauth_CallbackController extends Googleauth_AppController
{
  public $_models = array('Setting');
  public $_moduleModels = array('User');

  /**
   * This action gets called into as the OAuth callback after the user
   * successfully authenticates with Google and approves the scope. A code
   * is passed that can be used to make authorized requests later.
   */
  function indexAction()
    {
    $this->disableLayout();
    $this->disableView();

    $code = $this->_getParam('code');

    if (!$code)
      {
      $error = $this->_getParam('error');
      throw new Zend_Exception('Failed to log in with Google OAuth: '.$error);
      }

    $info = $this->_getUserInfo($code);

    $user = $this->_createOrGetUser($info);

    echo json_encode($user);
    }

  /**
   * Use the authorization code to get an access token, then use that access
   * token to request the user's email and profile info. Returns the necessary
   * user info in an array.
   */
  protected function _getUserInfo($code)
    {
    $clientId = $this->Setting->getValueByName('client_id', $this->moduleName);
    $clientSecret = $this->Setting->getValueByName('client_secret', $this->moduleName);
    $scheme = (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS']) ?
        'https://' : 'http://';
    $redirectUri = $scheme.$_SERVER['HTTP_HOST'].
                   Zend_Controller_Front::getInstance()->getBaseUrl().
                   '/'.$this->moduleName.'/callback';
    $headers = array(
      'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
      'Connection: Keep-Alive'
    );
    $postData = join('&', array(
      'grant_type=authorization_code',
      'code='.$code,
      'client_id='.$clientId,
      'client_secret='.$clientSecret,
      'redirect_uri='.$redirectUri
    ));

    // Make the request for the access token
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_PORT, 443);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($curl);

    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($httpStatus != 200)
      {
      throw new Zend_Exception('Access token request failed: '.$resp);
      }

    $resp = json_decode($resp);
    $accessToken = $resp->access_token;
    $tokenType = $resp->token_type;

    // Use the access token to request info about the user
    $headers = array(
      'Authorization: '.$tokenType.' '.$accessToken
    );

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'https://www.googleapis.com/plus/v1/people/me');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_PORT, 443);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($curl);
    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($httpStatus != 200)
      {
      throw new Zend_Exception('Get Google user info request failed: '.$resp);
      }
    $resp = json_decode($resp);

    // Now that we have the user info, we use that to log in
    $googlePersonId = $resp->id;
    $firstName = $resp->name->givenName;
    $lastName = $resp->name->familyName;

    foreach($resp->emails as $emailEntry)
      {
      $email = $emailEntry->value;
      break;
      }

    return array(
      'googlePersonId' => $googlePersonId,
      'firstName' => $firstName,
      'lastName' => $lastName,
      'email' => $email);
    }

  protected function _createOrGetUser($info)
    {
    $personId = $info['googlePersonId'];
    $existing = $this->Googleauth_User->getByGooglePersonId($personId);

    if (!$existing)
      {
      // TODO register a new user with the given info
      $this->Googleauth_User->createGoogleUser($user, $personId)
      }
    else
      {
      $user = $this->User->load($existing->getUserId());
      }
    }
}//end class
