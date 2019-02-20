<?php
use app\ArtalkServer;

define('CONFIG_FILE_PATH', __DIR__ . '/../Config.php');
define('LAZER_DATA_PATH', __DIR__ . '/../data/');

require(__DIR__ . '/../vendor/autoload.php');
require(__DIR__ . '/../app/ArtalkServer.php');

new ArtalkServer();
