<?php
use Lazer\Classes\Database as Lazer;

class ArtalkServer {
  private $conf;
  
  public function __construct($conf)
  {
    $this->conf = $conf;
  
    $this->allowOriginControl();
    $this->initTables();
    
    $actionName = $_GET['action'] ?? $_POST['action'] ?? null;
    $methodName = "action{$actionName}";
    if (method_exists($this, $methodName)) {
      $result = $this->{$methodName}();
    } else {
      $result = $this->error('这是哪？我要干什么？现在几点？蛤？什么鬼！？（╯‵□′）╯︵┴─┴');
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
  }
  
  private function initTables()
  {
    // comments
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
      ]);
    }
  }
  
  private function allowOriginControl()
  {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $allowOrigin = isset($this->conf['allow_origin']) ? $this->conf['allow_origin'] : [];
    if (in_array($origin, $allowOrigin)){
      header('Access-Control-Allow-Origin:' . $origin);
    }
  }
  
  private function getUserIP()
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
  
  private function urlValidator($value, $httpType = 'https|http')
  {
    if (is_string($value) && strlen($value) < 2000) {
      if (preg_match('/^(' . $httpType . '):\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)(?::\d{1,5})?(?:$|[?\/#])/i', $value)) {
        return true;
      }
    }
    
    return false;
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
  
  private static function getCommentsTable()
  {
    return Lazer::table('comments');
  }
  
  /**
   * ==================
   *  Actions
   * ==================
   */
  private function actionCommentAdd()
  {
    $content = trim($_POST['content'] ?? '');
    $nick = trim($_POST['nick'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $rid = intval(trim($_POST['rid'] ?? 0));
    $pageKey = trim($_POST['page_key'] ?? '');
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($nick) || empty($email) || empty($content) || empty($pageKey)) {
      return $this->error('请求数据残缺');
    }
    if (!empty($link) && !$this->urlValidator($link)) {
      return $this->error('link 不是 URL');
    }
    
    $commentData = [
      'content' => $content,
      'nick' => $nick,
      'email' => $email,
      'link' => $link,
      'page_key' => $pageKey,
      'rid' => $rid,
      'ua' => $ua,
      'date' => date("Y-m-d H:i:s"),
      'ip' => $this->getUserIP()
    ];
    $comment = self::getCommentsTable();
    $comment->set($commentData);
    $comment->save();
  
    $commentData['id'] = $comment->lastId();
    return $this->success('评论成功', ['comment' => $this->frontendCommentData($commentData)]);
  }
  
  private function actionCommentGet()
  {
    $pageKey = trim($_POST['page_key'] ?? '');
    if (empty($pageKey)) {
      return $this->error('page_key 不能为空');
    }
    
    $commentsRaw = self::getCommentsTable()
      ->where('page_key', '=', $pageKey)
      ->orderBy('date', 'DESC')
      ->findAll()
      ->asArray();
    
    $comments = [];
    foreach ($commentsRaw as $item) {
      $comments[] = $this->frontendCommentData($item);
    }
    
    return $this->success('获取成功', ['comments' => $comments]);
  }
  
  private function frontendCommentData($rawComment)
  {
    $comment = [];
    $showField = ['id', 'content', 'nick', 'link', 'page_key', 'rid', 'ua', 'date'];
    foreach ($rawComment as $key => $value) {
      if (in_array($key, $showField)) {
        $comment[$key] = $value;
      }
    }
  
    $comment['email_encrypted'] = md5(strtolower(trim($rawComment['email'])));
    return $comment;
  }
}
