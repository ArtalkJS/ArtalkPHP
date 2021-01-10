<?php
namespace app\components;

use app\ArtalkServer;
use app\Utils;

/**
 * 管理员操作
 */
trait AdminAction
{
  /**
   * Action: AdminCheck
   * Desc  : 管理员权限检验
   */
  public function actionAdminCheck()
  {
    $nick = $this->getUserNick();
    $email = $this->getUserEmail();
    $password = $this->getUserPassword();
    if ($nick == '') return $this->error('昵称 不能为空');
    if ($email == '') return $this->error('邮箱 不能为空');
    if ($password == '') return $this->error('密码 不能为空');

    if ($this->isNeedCaptcha()) {
      $imgData = $this->refreshGetCaptcha(); // 生成新的验证码
      return $this->error('需要验证码', ['need_captcha' => true, 'img_data' => $imgData]);
    }

    $this->logAction(); // 记录一次 IP 操作

    if (!$this->isAdmin($nick, $email)) {
      return $this->error('非管理员无需进行身份认证');
    }

    if ($this->checkAdminPassword($nick, $email, $password)) {
      $this->actionLogMarkAdmin(); // 标记为管理员
      return $this->success('密码正确');
    } else {
      return $this->error('密码错误');
    }
  }

  public function NeedAdmin () {
    $nick = $this->getUserNick();
    $email = $this->getUserEmail();
    $password = $this->getUserPassword();

    if ($this->isNeedCaptcha()) {
      $imgData = $this->refreshGetCaptcha(); // 生成新的验证码
      return $this->error('需要验证码', ['need_captcha' => true, 'img_data' => $imgData]); // 防止密码被暴力破解 (TODO: 密码错误逐渐提高限制)
    }

    $this->logAction(); // 记录一次 IP 操作

    if (!$this->isAdmin($nick, $email)) {
      $this->response($this->error('需要管理员身份', ['need_password' => true]));
      exit();
    }

    if (!$this->checkAdminPassword($nick, $email, $password)) {
      $this->response($this->error('需要管理员身份', ['need_password' => true]));
      exit();
    }
  }

  /**
   * Action: CommentCollapse
   * Desc  : 评论折叠
   */
  public function actionCommentCollapse()
  {
    $this->NeedAdmin();

    $id = intval(trim($_POST['id'] ?? 0));
    if (empty($id)) return $this->error('id 不能为空');
    $isCollapsed = boolval(trim($_POST['is_collapsed'] ?? 1)); // 1为折叠，0为取消折叠

    $commentTable = self::getCommentsTable();
    $comment = $commentTable->where('id', '=', $id)->find();

    if ($comment->count() === 0) {
      return $this->error("未找到 ID 为 {$id} 的评论项");
    }

    $comment->is_collapsed = $isCollapsed;
    $comment->save();

    return $this->success($isCollapsed ? '评论已折叠' : '评论已取消折叠', [
      'id' => $id,
      'is_collapsed' => $isCollapsed
    ]);
  }

  /**
   * Action: CommentDel
   * Desc  : 评论删除
   */
  public function actionCommentDel()
  {
    $this->NeedAdmin();

    // 评论项 ID
    $id = intval(trim($_POST['id'] ?? 0));
    if (empty($id)) return $this->error('id 不能为空');

    $commentTable = self::getCommentsTable();

    if ($commentTable->where('id', '=', $id)->find()->count() === 0) {
      return $this->error("未找到 ID 为 {$id} 的评论项，或已删除");
    }

    $delTotal = 0;

    try {
      $commentTable->where('id', '=', $id)->find()->delete();
      $delTotal++;
    } catch (\Exception $ex) {
      return $this->error('删除评论时出现错误'.$ex);
    }

    // 删除所有子评论
    $QueryAndDelChild = function ($parentId) use (&$commentTable, &$QueryAndDelChild, &$delTotal) {
      $comments = $commentTable
        ->where('rid', '=', $parentId)
        ->findAll();

      foreach ($comments as $item) {
        $QueryAndDelChild($item->id);
        try {
          $commentTable->where('id', '=', $item->id)->find()->delete();
          $delTotal++;
        } catch (\Exception $ex) {}
      }
    };
    $QueryAndDelChild($id);

    return $this->success('评论已删除', [
      'del_total' => $delTotal
    ]);
  }

  /**
   * Action: SetPage
   * Desc  : 设置页面配置数据
   */
  public function actionSetPage()
  {
    $this->NeedAdmin();

    // 评论项 ID
    $pageKey = trim($_POST['page_key'] ?? '');
    if ($pageKey == '') return $this->error('page_key 不能为空');
    $isCloseComment = boolval(trim($_POST['is_close_comment'] ?? 1)); // 1为关闭评论，0为打开评论

    $page = self::getPagesTable()->where('page_key', '=', $pageKey)->find();
    if ($page->count() === 0) {
      $page = self::getPagesTable();
      $page->page_key = $pageKey;
    }
    $page->is_close_comment = $isCloseComment;
    $page->save();

    $page = self::getPagesTable()->where('page_key', '=', $pageKey)->findAll()->asArray();
    return $this->success('页面已更新', $page[0] ?? []);
  }
}
