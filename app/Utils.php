<?php
namespace app;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Utils
{
  public static function urlValidator($value, $httpType = 'https|http')
  {
    if (is_string($value) && strlen($value) < 2000) {
      if (preg_match('/^(' . $httpType . '):\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)(?::\d{1,5})?(?:$|[?\/#])/i', $value)) {
        return true;
      }
    }

    return false;
  }

  public static function urlAddQuery($url, $query)
  {
    $pound = '';
    $poundPos = -1;

    //Is there a #?
    if (($poundPos = strpos($url, "#")) !== false) {
      $pound = substr($url, $poundPos);
      $url = substr($url, 0, $poundPos);
    }

    $separator = (parse_url($url, PHP_URL_QUERY) == NULL) ? '?' : '&';
    $url .= $separator . $query . $pound;

    return $url;
  }

  /**
   * 发送邮件通知被回复者
   *
   * @param $replyComment array 回复者的 comment
   * @return void
   * @throws \Exception
   */
  public static function sendEmailToCommenter($replyComment)
  {
    if (empty(_config()['email']) || empty(_config()['email']['on']) || (bool)_config()['email']['on'] === false) {
      return;
    }

    $senderFuncName = 'sendEmailBy'.strtoupper(_config()['email']['sender_type']);
    if (method_exists(__CLASS__, $senderFuncName)) {
        $sendEmail = function ($title, $content, $toAddr) use (&$senderFuncName) {
          forward_static_call_array([__CLASS__, $senderFuncName], [$title, $content, $toAddr]);
        };
    } else {
      throw new \Exception('配置 email.sender_type 有误，请联系管理员');
    }

    $replyComment = $replyComment ?? [];
    // 邮件内容生成
    $mailTitle = _config()['email']['mail_title'];
    $mailTitleToAdmin = _config()['email']['mail_title_to_admin'];

    $mailTplPath = __DIR__.'/../email-tpl/'._config()['email']['mail_tpl_name'];
    if (file_exists($mailTplPath)) {
      $mailTplRaw = file_get_contents($mailTplPath);
    } else {
      throw new \Exception('邮件模板文件不存在：'.$mailTplPath);
    }

    $rid = intval($replyComment['rid']);
    $comment = $replyComment; /** @var array 被回复者的 comment */
    if (!empty($rid)) {
      $commentFind = ArtalkServer::getCommentsTable()
        ->where('id', '=', $rid)
        ->find()
        ->asArray()[0] ?? [];
      if (!empty($commentFind)) {
        $comment = $commentFind;
      }
    }

    $replacement = [];
    foreach ($comment as $key => $item) {
      $replacement['{{comment.'.$key.'}}'] = $item;
    }
    foreach ($replyComment as $key => $item) {
      $replacement['{{reply.'.$key.'}}'] = $item;
    }

    $replacement['{{reply_link}}'] = self::urlAddQuery($replyComment['page_key'], 'artalk_comment='.$replyComment['id']);
    $replacement['{{reply.content_html}}'] = '@'.$replyComment['nick'].':<br/>'.$replyComment['content'];
    $replacement['{{conf.site_name}}'] = _config()['site_name'];
    $mailContent = str_replace(array_keys($replacement), array_values($replacement), $mailTplRaw);

    // 邮件发送
    $adminAddr = _config()['email']['admin_addr'] ?? null;
    if (!empty($rid) && $comment['email'] !== $replyComment['email']) {
      $sendEmail($mailTitle, $mailContent, $comment['email']);
    }
    if (empty($rid) && !empty($adminAddr) && $replyComment['email'] !== $adminAddr) {
      $sendEmail($mailTitleToAdmin, $mailContent, $adminAddr);
    }

    return;
  }

  public static function sendEmailBySMTP($title, $content, $toAddr)
  {
    $mail = new PHPMailer(true); // Passing `true` enables exceptions
    try {
      // Server settings
      //$mail->SMTPDebug = 2;
      $mail->isSMTP();
      $mail->Host = _config()['email']['smtp']['Host'];
      $mail->Port = _config()['email']['smtp']['Port'];
      $mail->SMTPAuth = _config()['email']['smtp']['SMTPAuth'];
      $mail->Username = _config()['email']['smtp']['Username'];
      $mail->Password = _config()['email']['smtp']['Password'];
      $mail->SMTPSecure = _config()['email']['smtp']['SMTPSecure'];
      $mail->CharSet = 'UTF-8';

      // Recipients
      $mail->setFrom(_config()['email']['smtp']['FromAddr'], _config()['email']['smtp']['FromName']);
      $mail->addAddress($toAddr); // Add a recipient

      // Content
      $mail->isHTML(true); // Set email format to HTML
      $mail->Subject = $title;
      $mail->Body    = $content;

      $mail->send();
      return ['success' => true, 'msg' => 'Message has been sent'];
    } catch (Exception $e) {
      return ['success' => false, 'msg' => 'Message could not be sent. Mailer Error: '.$mail->ErrorInfo];
    }
  }

  public static function sendEmailByALI_DM($title, $content, $toAddr)
  {
    return json_encode(Utils::requestAli('http://dm.aliyuncs.com', [
      'Action' => 'SingleSendMail',
      'AccountName' => _config()['email']['ali_dm']['AccountName'],
      'ReplyToAddress' => 'true',
      'AddressType' => 1,
      'ToAddress' => $toAddr,
      'Subject' => $title,
      'HtmlBody' => $content
    ]), true);
  }

  public static function curl($url)
  {
    $ch = \curl_init();
    \curl_setopt($ch, CURLOPT_URL, $url);
    \curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result= \curl_exec($ch);
    return $result;
  }

  public static function percentEncode($value=null)
  {
    $en = urlencode($value);
    $en = str_replace('+', '%20', $en);
    $en = str_replace('*', '%2A', $en);
    $en = str_replace('%7E', '~', $en);
    return $en;
  }

  public static function aliSign($params, $accessSecret, $method="GET")
  {
    ksort($params);
    $stringToSign = strtoupper($method).'&'.self::percentEncode('/').'&';

    $tmp = '';
    foreach($params as $key=>$val){
      $tmp .= '&'.self::percentEncode($key).'='.self::percentEncode($val);
    }
    $tmp = trim($tmp, '&');
    $stringToSign = $stringToSign.self::percentEncode($tmp);

    $key  = $accessSecret.'&';
    $hmac = hash_hmac('sha1', $stringToSign, $key, true);

    return base64_encode($hmac);
  }

  public static function requestAli($baseUrl, $requestParams)
  {
    $publicParams = [
      'Format'        =>  'JSON',
      'Version'       =>  '2015-11-23',
      'AccessKeyId'   =>  _config()['email']['ali_dm']['AccessKeyId'],
      'Timestamp'     =>  gmdate('Y-m-d\TH:i:s\Z'),
      'SignatureMethod'   =>  'HMAC-SHA1',
      'SignatureVersion'  =>  '1.0',
      'SignatureNonce'    =>  substr(md5(rand(1, 99999999)), rand(1, 9), 14),
    ];

    $params = array_merge($publicParams, $requestParams);
    $params['Signature'] =  self::aliSign($params, _config()['email']['ali_dm']['AccessKeySecret']);
    $uri = http_build_query($params);
    $url = $baseUrl.'/?'.$uri;

    return self::curl($url);
  }
}
