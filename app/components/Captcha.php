<?php
namespace app\components;
use Gregwar\Captcha\CaptchaBuilder;

/**
 * 验证码
 */
trait Captcha
{
  private function isCaptchaOn() {
    return _config()['captcha']['on'] == true;
  }

  // 获取评论超时
  private function getCaptchaTimeout() {
    return _config()['captcha']['timeout'] ?? 4*60; // 单位：秒
  }


  // 获取评论时间内次数限制
  private function getCaptchaLimit() {
    return _config()['captcha']['limit'] ?? 3;
  }

  /** 检测验证码功能是否可用 */
  private function checkCaptchaSupported() {
    if (!$this->isCaptchaOn())
      return;

    if (!function_exists('imagettfbbox')) {
      $this->response($this->error('验证码功能已开启，但 GD 库不存在或不完整'));
      exit();
    }
  }

  /**
   * 是否需要验证码
   *
   * @return bool
   */
  private function isNeedCaptcha()
  {
    if (!$this->isCaptchaOn()) // 总开关
      return false;

    $this->checkCaptchaSupported();

    if ($this->checkCaptcha(trim($_POST['captcha'] ?? '')))
      return false; // 若验证码是正确的，则不需要再次验证

    // 操作次数统计
    $timeout = $this->getCaptchaTimeout();
    $limit = $this->getCaptchaLimit();

    $actionLog = $this->getActionLog();
    if (!empty($actionLog) && $actionLog->is_admin) {
      return false; // 已被标记为管理员的 IP 无需验证码
    }

    if ($limit == 0) {
      return true; // 一直需要验证码
    }

    $isInTime = (strtotime($actionLog->last_time)+$timeout >= time()); // 在超时内
    if ($isInTime && $actionLog->count >= $limit) { // 超过操作限制次数
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
    if (!$this->isCaptchaOn()) // 总开关
      return null;

    $this->checkCaptchaSupported();

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

  /**
   * 获取 IP 操作记录
   */
  private function getActionLog()
  {
    $user = $this->getUserKey();
    $actionLog = self::getActionLogsTable()
      ->where('user', '=', $user)
      ->find();

    if (!empty($actionLog)) {
      return $actionLog;
    } else {
      return null;
    }
  }

  /**
   * 添加一次 IP 操作记录（用于限制操作频率）
   */
  private function logAction()
  {
    $this->refreshGetCaptcha(); // 刷新验证码

    $ip = $this->getUserIp();
    $user = $this->getUserKey();
    $count = 0;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isBan = false;
    $note = '';

    $actionLog = self::getActionLogsTable()
      ->where('user', '=', $user)
      ->find();

    if (!empty($actionLog)) {
      $count = $actionLog->count;
      $isBan = $actionLog->is_ban;
      $note  = $actionLog->note;
      $isInTime = (strtotime($actionLog->last_time)+$this->getCaptchaTimeout() >= time()); // 在超时内
      if (!$isInTime) {
        $count = 0; // 在超时结束后，重置计数
      }
    } else {
      $actionLog = self::getActionLogsTable();
    }

    $actionLog->set([
      'user' => $user,
      'ip' => $ip,
      'count' => $count+1,
      'last_time' => date("Y-m-d H:i:s"),
      'ua' => $ua,
      'is_ban' => $isBan,
      'note' => $note,
    ]);
    $actionLog->save();
  }

  /**
   * 标记该 IP 为管理员，放开限制
   */
  private function actionLogMarkAdmin()
  {
    $user = $this->getUserKey();
    $ip = $this->getUserIp();

    $actionLog = self::getActionLogsTable()
      ->where('user', '=', $user)
      ->find();

      if (!empty($actionLog)) {
        $actionLog->set([
          'is_admin' => true
        ]);
        $actionLog->save();
      }
  }
}
