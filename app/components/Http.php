<?php
namespace app\components;

trait Http
{
  /**
   * 运行跨域请求域名控制
   */
  private function allowOriginControl()
  {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $allowOrigin = isset(_config()['allow_origin']) ? _config()['allow_origin'] : [];
    if (in_array('*', $allowOrigin)) {
      header('Access-Control-Allow-Origin:*');
      return;
    }
    if (in_array($origin, $allowOrigin)){
      header('Access-Control-Allow-Origin:' . $origin);
    }
  }

  public static function getUserIP()
  {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }

    return $ip;
  }

  public function wantsJson()
  {
    return strtolower(@$_SERVER['CONTENT_TYPE']) === 'application/json';
  }

  private function success($msg = null, $data = null)
  {
    return [
      'success' => true,
      'msg' => $msg,
      'data' => $data
    ];
  }

  private function error($msg = null, $data = null)
  {
    return [
      'success' => false,
      'msg' => $msg,
      'data' => $data
    ];
  }

  private function response($data)
  {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
  }
}
