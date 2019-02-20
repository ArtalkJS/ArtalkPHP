<?php
namespace app\components;
use Gregwar\Captcha\CaptchaBuilder;

trait Permission
{
  /**
   * 是否需要验证码
   *
   * @return bool
   */
  private function isNeedCaptcha()
  {
    $ip = $this->getUserIp();
    $comments = self::getCommentsTable()
      ->where('ip', '=', $ip)
      ->orderBy('date', 'DESC')
      ->findAll()
      ->asArray();
  
    // 时间范围内，评论次数统计
    $inTimeRangeCount = 0;
    foreach ($comments as $item) {
      if (strtotime($item['date'])+_config()['captcha']['timeout'] >= time()) {
        $inTimeRangeCount++;
      } else {
        break;
      }
    }
    
    if ($inTimeRangeCount >= _config()['captcha']['limit']) {
      // 若超过限制评论次数
      return true;
    } else {
      return false;
    }
  }
  
  /**
   * 获取验证码的值
   */
  private function getCaptchaStr()
  {
    $ip = $this->getUserIp();
    $captcha = self::getCaptchaTable()
      ->where('ip', '=', $ip)
      ->find();
    if (!empty($captcha) && !empty($captcha->str)) {
      return $captcha->str;
    } else {
      return null;
    }
  }
  
  /**
   * 检验验证码
   *
   * @param $str
   * @return bool
   */
  private function checkCaptcha($str)
  {
    $rightStr = $this->getCaptchaStr();
    return (!empty($rightStr) && strtolower($str) === strtolower($rightStr));
  }
  
  /**
   * 刷新并获得验证码图片
   */
  private function refreshGetCaptcha()
  {
    $builder = new CaptchaBuilder;
    $builder->setBackgroundColor(255, 255, 255);
    $builder->build();
    
    $ip = $this->getUserIp();
    $captcha = self::getCaptchaTable()
      ->where('ip', '=', $ip)
      ->find();
    
    if (empty($captcha)) {
      $captcha = self::getCaptchaTable();
    }
  
    $captcha->set([
      'ip' => $ip,
      'str' => $builder->getPhrase()
    ]);
    $captcha->save();
    
    return $builder->inline();
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
}
