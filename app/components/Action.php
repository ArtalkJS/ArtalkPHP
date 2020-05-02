<?php
namespace app\components;

use app\ArtalkServer;
use app\Utils;

trait Action
{
  public function actionCaptchaCheck()
  {
    if (!empty(trim($_POST['refresh'] ?? ''))) {
      $imgData = $this->refreshGetCaptcha();
      return $this->success('验证码刷新成功', ['img_data' => $imgData]);
    }

    $captcha = trim($_POST['captcha'] ?? '');
    if ($captcha == '') return $this->error('验证码 不能为空');

    if ($this->checkCaptcha($captcha)) {
      return $this->success('验证码正确');
    } else {
      $imgData = $this->refreshGetCaptcha();
      return $this->error('验证码错误', ['img_data' => $imgData]);
    }
  }

  public function actionCommentAdd()
  {
    $content = trim($_POST['content'] ?? '');
    $nick = trim($_POST['nick'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $rid = intval(trim($_POST['rid'] ?? 0));
    $pageKey = trim($_POST['page_key'] ?? '');
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $password = trim($_POST['password'] ?? '');
    $captcha = trim($_POST['captcha'] ?? '');

    if ($this->isAdmin($nick, $email) && !$this->checkAdminPassword($nick, $email, $password)) {
      return $this->error('需要管理员身份', ['need_password' => true]);
    }
    if (!$this->isAdmin($nick, $email) && $this->isNeedCaptcha() && !$this->checkCaptcha($captcha)) {
      $imgData = $this->refreshGetCaptcha(); // 生成新的验证码
      return $this->error('需要验证码', ['need_captcha' => true, 'img_data' => $imgData]);
    }

    if ($rid !== 0) {
      $replyComment = self::getCommentsTable()->where('id', '=', $rid)->find();
      if ($replyComment->count() === 0) return $this->error('回复评论已被删除');
      if ($replyComment->is_collapsed || ($this->isParentCommentCollapsed($replyComment))) {
        return $this->error('禁止回复被折叠的评论');
      }
    }

    if ($pageKey == '') return $this->error('page_key 不能为空');
    if ($nick == '') return $this->error('昵称不能为空');
    if ($email == '') return $this->error('邮箱不能为空');
    if ($content == '') return $this->error('内容不能为空');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return $this->error('邮箱格式错误');
    if ($link !== '' && !Utils::urlValidator($link)) return $this->error('网址格式错误');

    // 获取页面数据
    $page = self::getPagesTable()->where('page_key', '=', $pageKey)->findAll()->asArray();
    if (isset($page[0])) {
      $page = $page[0];
      // 评论已关闭
      if (!$this->isAdmin($nick, $email) && !empty($page['is_close_comment']) && $page['is_close_comment'] === true) {
        return $this->error('评论已关闭');
      }
    }

    $comment = self::getCommentsTable();
    $comment->content = $content;
    $comment->nick = $nick;
    $comment->email = $email;
    $comment->link = $link;
    $comment->page_key = $pageKey;
    $comment->rid = $rid;
    $comment->ua = $ua;
    $comment->date = date("Y-m-d H:i:s");
    $comment->ip = $this->getUserIP();
    $comment->is_collapsed = false;
    $comment->save();

    $lastId = $comment->lastId();
    $comment = self::getCommentsTable()->where('id', '=', $lastId);
    $commentArr = @$comment->findAll()->asArray()[0];
    $comment1 = self::getCommentsTable()->where('id', '=', $lastId)->find();

    $this->refreshGetCaptcha(); // 刷新验证码
    try {
      Utils::sendEmailToCommenter($commentArr); // 发送邮件通知
    } catch (\Exception $e) {
      return $this->error('通知邮件发送失败，请联系网站管理员', ['error-msg' => $e->getMessage(), 'error-detail' => $e->getTraceAsString()]);
    }

    return $this->success('评论成功', ['comment' => $this->beautifyCommentData($comment1)]);
  }

  public function actionCommentGet()
  {
    $pageKey = trim($_POST['page_key'] ?? '');
    if ($pageKey == '') {
      return $this->error('page_key 不能为空');
    }

    $offset = intval(trim($_POST['offset'] ?? 0));
    $limit = intval(trim($_POST['limit'] ?? 0));
    if ($offset < 0) $offset = 0;
    if ($limit <= 0) $limit = 15;

    $commentTable = self::getCommentsTable();

    $comments = [];
    $QueryAllChildren = function ($parentId) use (&$commentTable, &$pageKey, &$comments, &$QueryAllChildren) {
      $rawComments = $commentTable
        ->where('page_key', '=', $pageKey)
        ->where('rid', '=', $parentId)
        ->orderBy('date', 'ASC')
        ->findAll();

      foreach ($rawComments as $item) {
        $comments[] = $this->beautifyCommentData($item);
        $QueryAllChildren($item->id);
      }
    };

    $commentsRaw = $commentTable
      ->where('page_key', '=', $pageKey)
      ->where('rid', '=', 0)
      ->orderBy('date', 'DESC')
      ->limit($limit, $offset)
      ->findAll();

    foreach ($commentsRaw as $item) {
      $comments[] = $this->beautifyCommentData($item);

      // Child Comments
      $QueryAllChildren($item->id);
    }

    // 管理员信息
    $adminUsers = $this->getAdminUsers();
    $adminNicks = [];
    $adminEmails = [];
    foreach ($adminUsers as $admin) {
      $adminNicks[] = $admin['nick'];
      $adminEncryptedEmails[] = md5($admin['email']);
    }

    // 页面数据
    $page = self::getPagesTable()->where('page_key', '=', $pageKey)->findAll()->asArray();
    $pageData = [];
    if (!isset($page[0])) {
      $page = self::getPagesTable();
      $page->page_key = $pageKey;
      $page->is_close_comment = false;
      $page->save();
      $pageData = $page->where('page_key', '=', $pageKey)->findAll()->asArray()[0];
    } else {
      $pageData = $page[0];
    }

    return $this->success('获取成功', [
      'comments' => $comments,
      'offset' => $offset,
      'limit' => $limit,
      'total_parents' => $commentTable->where('page_key', '=', $pageKey)->where('rid', '=', 0)->findAll()->count(),
      'total' => $commentTable->where('page_key', '=', $pageKey)->findAll()->count(),
      'admin_nicks' => $adminNicks,
      'admin_encrypted_emails' => $adminEncryptedEmails,
      'page' => $pageData
    ]);
  }

  private function beautifyCommentData($commentObj)
  {
    $comment = [];
    $showField = ['id', 'content', 'nick', 'link', 'page_key', 'rid', 'ua', 'date', 'is_collapsed'];
    foreach ($showField as $field) {
      if (isset($commentObj->{$field}))
        $comment[$field] = $commentObj->{$field};
    }

    $comment['email_encrypted'] = md5(strtolower(trim($commentObj->email)));
    $findAdminUser = $this->findAdminUser($commentObj->nick ?? null, $commentObj->email ?? null);
    if (!empty($findAdminUser)) {
      $comment['badge'] = [];
      $comment['badge']['name'] = $findAdminUser['badge_name'] ?? '管理员';
      $comment['badge']['color'] = $findAdminUser['badge_color'] ?? '#ffa928';
      $comment['is_admin'] = true;
    }

    return $comment;
  }

  /** 父评论是否有被折叠 */
  private function isParentCommentCollapsed($srcComment)
  {
    if ($srcComment->is_collapsed ?? false) return true;
    if ($srcComment->rid === 0) return false;

    $pComment = self::getCommentsTable()->where('id', '=', $srcComment->rid)->find();
    if ($pComment->count() === 0) return false;
    if ($pComment->is_collapsed) return true;
    else return $this->isParentCommentCollapsed($pComment); // 继续寻找
  }

  private function getRootComment($srcComment)
  {
    if ($srcComment->rid === 0) return $srcComment;

    $pComment = self::getCommentsTable()->where('id', '=', $srcComment->rid)->find();
    if ($pComment->count() === 0) return null;

    if ($pComment->rid === 0) return $pComment; // root comment
    else return $this->getRootComment($pComment); // 继续寻找
  }

  public function actionCommentReplyGet()
  {
    $nick = trim($_POST['nick'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($nick == '') return $this->error('昵称 不能为空');
    if ($email == '') return $this->error('邮箱 不能为空');

    $replyRaw = self::getCommentsTable();

    if (!$this->isAdmin($nick, $email)) {
      $myComments = self::getCommentsTable()
        ->where('nick', '=', $nick)
        ->andWhere('email', '=', $email)
        ->orderBy('date', 'DESC')
        ->findAll()
        ->asArray();

      $idList = [];
      foreach ($myComments as $item) {
        $idList[] = $item['id'];
      }

      $replyRaw = $replyRaw->where('rid', 'IN', $idList);
    }

    $replyRaw = $replyRaw
      ->orderBy('date', 'DESC')
      ->findAll();

    $reply = [];
    foreach ($replyRaw as $item) {
      $reply[] = $this->beautifyCommentData($item);
    }

    return $this->success('获取成功', ['reply_comments' => $reply]);
  }

  /*public function actionTest()
  {
    // 测试过后记得清理
    return '';
  }*/
}
