<?php
namespace app\components;

use app\ArtalkServer;
use app\Utils;

trait AdminAction
{
  public function actionAdminCheck()
  {
    $nick = trim($_POST['nick'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($nick == '') return $this->error('昵称 不能为空');
    if ($email == '') return $this->error('邮箱 不能为空');
    if ($password == '') return $this->error('密码 不能为空');

    if (!$this->isAdmin($nick, $email)) {
      return $this->error('无需管理员权限');
    }
    if ($this->checkAdminPassword($nick, $email, $password)) {
      return $this->success('密码正确');
    } else {
      return $this->error('密码错误');
    }
  }

  public function NeedAdmin () {
    $nick = trim($_POST['nick'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$this->isAdmin($nick, $email) || !$this->checkAdminPassword($nick, $email, $password)) {
      $this->response($this->error('需要管理员身份', ['need_password' => true]));
      exit();
    }
  }

  /** 评论折叠 */
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

  /** 评论删除 */
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
    } catch (Exception $ex) {
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
        } catch (Exception $ex) {}
      }
    };
    $QueryAndDelChild($id);

    return $this->success('评论已删除', [
      'del_total' => $delTotal
    ]);
  }
}
