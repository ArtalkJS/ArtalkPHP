<?php
namespace lib;

trait Permission
{
  private function allowOriginControl()
  {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $allowOrigin = isset($this->conf['allow_origin']) ? $this->conf['allow_origin'] : [];
    if (in_array($origin, $allowOrigin)){
      header('Access-Control-Allow-Origin:' . $origin);
    }
  }
  
  private function isNeedAdminCheck($nick, $email)
  {
    $adminUsers = $this->conf['admin_users'] ?? [];
    if (empty($adminUsers)) {
      return false;
    }
    
    if (is_null($user = $this->findAdminUser($nick, $email, null, false))) {
      return false;
    }
  
    $sessionId = $_SESSION['admin_user_id'];
    if (isset($sessionId) && !empty($sessionUser = $adminUsers[$sessionId])) {
      if (strtolower($sessionUser['nick']) == strtolower($user['nick'])
        && strtolower($sessionUser['email']) == strtolower($user['email'])) {
        return false;
      }
    }
    
    return true;
  }
  
  private function findAdminUser($nick, $email, $password, $needPassword = true)
  {
    $nick = trim($nick);
    $email = trim($email);
    $password = trim($password);
    
    $adminUsers = $this->conf['admin_users'] ?? [];
    if (empty($adminUsers)) {
      return null;
    }
    
    $userKey = null;
    foreach ($adminUsers as $key => $user) {
      if (strtolower($user['nick']) == strtolower($nick) || strtolower($user['email']) == strtolower($email)) {
        if (!$needPassword) {
          $userKey = $key;
          continue;
        }
        
        if ($user['password'] == $password) {
          $userKey = $key;
        }
      }
    }
    
    return $userKey;
  }
}
