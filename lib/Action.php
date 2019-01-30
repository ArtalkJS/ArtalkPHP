<?php
namespace lib;

trait Action
{
  public function actionCommentAdd()
  {
    $content = trim($_POST['content'] ?? '');
    $nick = trim($_POST['nick'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $rid = intval(trim($_POST['rid'] ?? 0));
    $pageKey = trim($_POST['page_key'] ?? '');
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  
    if (empty($pageKey)) return $this->error('pageKey 不能为空');
    if (empty($nick)) return $this->error('昵称不能为空');
    if (empty($email)) return $this->error('邮箱不能为空');
    if (empty($content)) return $this->error('内容不能为空');
  
    if ($this->isNeedAdminCheck($nick, $email)) {
      return $this->error('需要验证管理员身份');
    }
    if (!empty($link) && !$this->urlValidator($link)) {
      return $this->error('链接不是 URL');
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
    return $this->success('评论成功', ['comment' => $this->beautifyCommentData($commentData)]);
  }
  
  public function actionCommentGet()
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
      $comments[] = $this->beautifyCommentData($item);
    }
    
    return $this->success('获取成功', ['comments' => $comments]);
  }
  
  private function beautifyCommentData($rawComment)
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
  
  public function actionLoginAsAdmin()
  {
    $nick = trim($_POST['nick'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
  
    if (!is_null($userKey = $this->findAdminUser($nick, $email, $password))) {
      $_SESSION['admin_user_id'] = $userKey;
      return $this->success('登录成功');
    } else {
      return $this->error('登录失败');
    }
  }
}
