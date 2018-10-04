<?php
define('LAZER_DATA_PATH', __DIR__ . '/../data/');

require(__DIR__ . '/../vendor/autoload.php');
require(__DIR__ . '/../ArtalkServer.php');

new ArtalkServer(require(__DIR__ . '/../Config.php'));
