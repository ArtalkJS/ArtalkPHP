<?php
namespace app\components;

use app\ArtalkServer;
use app\Utils;

/**
 * 基本操作
 */
trait Action
{
  /**
   * Action: CommentGet
   * Desc  : 评论新增
   */
  public function actionCommentAdd()
  {
    $content = trim($_POST['content'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $rid = intval(trim($_POST['rid'] ?? 0));
    $pageKey = trim($_POST['page_key'] ?? '');
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $nick = $this->getUserNick();
    $email = $this->getUserEmail();
    $password = $this->getUserPassword();

    if ($nick == '') return $this->error('昵称不能为空');
    if ($email == '') return $this->error('邮箱不能为空');

    if ($this->isNeedCaptcha()) {
      $imgData = $this->refreshGetCaptcha(); // 生成新的验证码
      return $this->error('需要验证码', ['need_captcha' => true, 'img_data' => $imgData]);
    }

    $this->logAction(); // 记录一次 IP 操作

    if ($this->isAdmin($nick, $email) && !$this->checkAdminPassword($nick, $email, $password)) {
      return $this->error('需要管理员身份', ['need_password' => true]);
    }

    if ($rid !== 0) {
      $replyComment = self::getCommentsTable()->where('id', '=', $rid)->find();
      if ($replyComment->count() === 0) return $this->error('回复评论已被删除');
      if ($replyComment->is_collapsed || ($this->isParentCommentCollapsed($replyComment))) {
        return $this->error('禁止回复被折叠的评论');
      }
    }

    if ($pageKey == '') return $this->error('page_key 不能为空');
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
    $comment->is_pending = false;

    if (_config()['moderation']['pending_default']) {
      $comment->is_pending = true; // 默认待审状态
    }

    $comment->save();

    $lastId = $comment->lastId();
    $comment = self::getCommentsTable()->where('id', '=', $lastId);
    $commentArr = @$comment->findAll()->asArray()[0];
    $comment1 = self::getCommentsTable()->where('id', '=', $lastId)->find();

    try {
      Utils::sendEmailToCommenter($commentArr); // 发送邮件通知
    } catch (\Exception $e) {
      return $this->error('通知邮件发送失败，请联系网站管理员', ['error-msg' => $e->getMessage(), 'error-detail' => $e->getTraceAsString()]);
    }

    return $this->success('评论成功', ['comment' => $this->beautifyCommentData($comment1)]);
  }

  /**
   * Action: CommentGet
   * Desc  : 评论获取
   */
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

    $condList = [
      'page_key' => $pageKey,
      'is_pending' => 0,
    ];

    $commentTable = self::getCommentsTable();
    $comments = $this->getComments($condList, $offset, $limit);

    // 管理员信息
    $adminUsers = $this->getAdminUsers();
    $adminNicks = [];
    $adminEncryptedEmails = [];
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
      'total_parents' => $this->countComments($condList, true),
      'total' => $this->countComments($condList),
      'admin_nicks' => $adminNicks,
      'admin_encrypted_emails' => $adminEncryptedEmails,
      'page' => $pageData
    ]);
  }

  /**
   * Action: CommentGetV2
   * Desc  : 回复评论获取
   */
  public function actionCommentGetV2()
  {
    $type = trim($_POST['type'] ?? '');
    if (!in_array($type, ['all', 'mentions', 'mine', 'pending'])) {
      return $this->error('type 未知');
    }

    $nick = $this->getUserNick();
    $email = $this->getUserEmail();
    if ($nick == '') return $this->error('昵称 不能为空');
    if ($email == '') return $this->error('邮箱 不能为空');

    // 分页
    $offset = intval(trim($_POST['offset'] ?? 0));
    $limit = intval(trim($_POST['limit'] ?? 0));
    if ($offset < 0) $offset = 0;
    if ($limit <= 0) $limit = 15;

    if ($this->isAdmin($nick, $email)) {
      // 管理员
      $this->NeedAdmin();
    }

    $condList = [
      'nick' => $nick,
      'email' => $email,
    ]; // default
    $queryChildren = true; // 是否查找子评论

    // Type: "all" 全部
    if ($type == 'all') {
      if ($this->isAdmin($nick, $email)) { // 管理员
        $condList = [];
      } else {
        $condList = [
          'nick' => $nick,
          'email' => $email,
        ];
      }
    }

    // Type: "mentions" 提及
    if ($type == 'mentions') {
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

      $condList = [
        'rid' => $idList,
        'nick:not' => $nick,
        'email:not' => $email,
      ];
      $queryChildren = false;
    }

    // Type: "mine" 我的
    if ($type == 'mine') {
      $condList = [
        'nick' => $nick,
        'email' => $email
      ];
      $queryChildren = false;
    }

    // Type: "pending" 待审
    if ($type == 'pending') {
      $queryChildren = false;
      if ($this->isAdmin($nick, $email)) { // 管理员
        $condList = [];
      } else {
        $condList = [
          'nick' => $nick,
          'email' => $email
        ];
      }

      $condList = array_merge($condList, [
        'is_pending' => 1,
      ]);
    }

    $comments = $this->getComments($condList, $offset, $limit, $queryChildren);

    return $this->success('获取成功', [
      'comments' => $comments,
      'total' => $this->countComments($condList),
      'total_parents' => $this->countComments($condList, true),
      'offset' => $offset,
      'limit' => $limit,
    ]);
  }

  /**
   * Action: CaptchaCheck
   * Desc  : 验证码检验
   */
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

  /** =========================================================== */
  /** ------------------------- Helpers ------------------------- */
  /** =========================================================== */
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

  /** 获取评论 */
  private function getComments($condList, $offset, $limit, $queryChildren = true) {
    $comments = [];
    $QueryAllChildren = function ($parentId) use (&$pageKey, &$comments, &$QueryAllChildren) {
      $rawComments = self::getCommentsTable()
        ->where('rid', '=', $parentId)
        ->orderBy('date', 'ASC')
        ->findAll();

      foreach ($rawComments as $item) {
        $comments[] = $this->beautifyCommentData($item);
        $QueryAllChildren($item->id);
      }
    };

    if ($queryChildren) {
      $commentsRaw = self::getCommentsTable()->where('rid', '=', 0);
    } else {
      $commentsRaw = self::getCommentsTable();
    }

    $this->applyCondList($commentsRaw, $condList);

    $commentsRaw = $commentsRaw
      ->orderBy('date', 'DESC')
      ->limit($limit, $offset)
      ->findAll();

    foreach ($commentsRaw as $item) {
      $comments[] = $this->beautifyCommentData($item);

      // Child Comments
      if ($queryChildren)
        $QueryAllChildren($item->id);
    }

    return $comments;
  }

  /** 获取评论数 */
  private function countComments($condList, $onlyParent = false) {
    $comments = self::getCommentsTable();
    $this->applyCondList($comments, $condList);
    if ($onlyParent)
      $comments = $comments->where('rid', '=', 0);
    return $comments->findAll()->count();
  }

  private function applyCondList(&$query, $condList) {
    if (empty($condList)) return;
    foreach ($condList as $key => $val) {
      $w = '=';
      $keyParse = explode(':', $key);
      $realKey = reset($keyParse);

      if (end($keyParse) == 'not')
        $w = '!=';
      if (is_array($val))
        $w = 'IN';

      $query = $query
        ->where($realKey, $w, $val);
    }
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
}
