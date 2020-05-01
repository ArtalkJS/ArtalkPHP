<?php
namespace app;

use Lazer\Classes\Database as Lazer;

class ArtalkServer {
  use components\Action;
  use components\AdminAction;
  use components\Table;
  use components\Http;
  use components\Permission;

  public static $conf;
  public $version;

  public function __construct()
  {
    $this::$conf = require(CONFIG_FILE_PATH);
    $this->version = '(unknow)';

    $composerFile = __DIR__ . '/../composer.json';
    if (file_exists($composerFile)) {
      $composerJson = @file_get_contents(__DIR__ . '/../composer.json');
      $composerJson = @json_decode($composerJson, true);
      $this->version = $composerJson['version'] ?? '(unknow)';
    }

    $this->allowOriginControl();
    $this->initTables();

    $actionName = $_GET['action'] ?? $_POST['action'] ?? null;
    $methodName = "action{$actionName}";
    if (method_exists($this, $methodName)) {
      $result = $this->{$methodName}();
    } else {
      // action 参数不正确显示
      if (!$this->wantsJson()) {
        header('Content-Type: text/plain');
        echo "Artalk Server Php v{$this->version}\n\n > https://github.com/qwqcode/ArtalkServerPhp\n > https://artalk.js.org\n > https://github.com/qwqcode/Artalk\n > https://qwqaq.com";
        return;
      } else {
        $result = $this->error('这是哪？我要干什么？现在几点？蛤？什么鬼！？（╯‵□′）╯︵┴─┴');
      }
    }

    $this->response($result);
  }
}
