<?php
namespace app\components;
use Gregwar\Captcha\CaptchaBuilder;

/**
 * 权限管理
 */
trait Permission
{
  private function getUserKey() {
    return md5($this->getUserNick().$this->getUserEmail().$this->getUserIp());
  }

  private function getUserEmail() {
    return trim($_POST['email'] ?? '');
  }

  private function getUserNick() {
    return trim($_POST['nick'] ?? '');
  }

  private function getUserPassword() {
    return trim($_POST['password'] ?? '');
  }

  private function isAdmin($nick, $email)
  {
    if (empty($this->getAdminUsers()))
      return false;

    if (empty($this->findAdminUser($nick, $email)))
      return false;

    return true;
  }

  private function checkAdminPassword($nick, $email, $password)
  {
    $password = trim($password);
    $user = $this->findAdminUser($nick, $email);
    if (!empty($user) && $password === trim($user['password'])) {
      return true;
    } else {
      return false;
    }
  }

  private function getAdminUsers() {
    return _config()['admin_users'] ?? [];
  }

  private function findAdminUser($nick, $email)
  {
    $nick = trim($nick);
    $email = trim($email);

    $adminUsers = $this->getAdminUsers();
    if (empty($adminUsers)) {
      return null;
    }

    $user = [];
    foreach ($adminUsers as $i => $item) {
      if (strtolower($item['nick']) === strtolower($nick) || strtolower($item['email']) === strtolower($email)) {
        $user = $item;
        break;
      }
    }

    return $user;
  }
}
