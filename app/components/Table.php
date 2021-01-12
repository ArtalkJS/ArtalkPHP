<?php
namespace app\components;
use Lazer\Classes\Database as Lazer;

trait Table
{
  private function initTables()
  {
    // comments 评论数据
    try{
      \Lazer\Classes\Helpers\Validate::table('comments')->exists();
    } catch(\Lazer\Classes\LazerException $e){
      Lazer::create('comments', [
        'id' => 'integer',
        'content' => 'string',
        'nick' => 'string',
        'email' => 'string',
        'link' => 'string',
        'ua' => 'string',
        'page_key' => 'string',
        'rid' => 'integer',
        'ip' => 'string',
        'date' => 'string',
        'is_collapsed' => 'boolean',
        'is_pending' => 'boolean',
      ]);
    }
    // captcha 验证码数据
    try {
      \Lazer\Classes\Helpers\Validate::table('captcha')->exists();
    } catch(\Lazer\Classes\LazerException $e){
      Lazer::create('captcha', [
        'ip' => 'string',
        'str' => 'string',
      ]);
    }
    // action_logs 操作记录
    try {
      \Lazer\Classes\Helpers\Validate::table('action_logs')->exists();
    } catch(\Lazer\Classes\LazerException $e){
      Lazer::create('action_logs', [
        'user' => 'string', // 用户唯一标识
        'ip' => 'string', // IP 地址
        'count' => 'integer', // 操作次数
        'last_time' => 'string', // 最后一次操作时间
        'ua' => 'string', // UserAgent
        'is_admin' => 'boolean', // 是否为管理员
        'is_ban' => 'boolean', // 是否被封禁
        'note' => 'string' // 备注
      ]);
    }
    // pages 页面配置数据
    try {
      \Lazer\Classes\Helpers\Validate::table('pages')->exists();
    } catch(\Lazer\Classes\LazerException $e){
      Lazer::create('pages', [
        'page_key' => 'string',
        'is_close_comment' => 'boolean',
      ]);
    }
  }

  public static function getCommentsTable()
  {
    return Lazer::table('comments');
  }

  public static function getCaptchaTable()
  {
    return Lazer::table('captcha');
  }

  public static function getActionLogsTable()
  {
    return Lazer::table('action_logs');
  }


  public static function getPagesTable()
  {
    return Lazer::table('pages');
  }
}
