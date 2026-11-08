<?php

$env = $_SERVER['YII_ENV'] ?? getenv('YII_ENV') ?: 'dev';
$debug = $_SERVER['YII_DEBUG'] ?? getenv('YII_DEBUG') ?: ($env === 'dev' ? '1' : '0');

defined('YII_ENV') or define('YII_ENV', $env);
defined('YII_DEBUG') or define('YII_DEBUG', filter_var($debug, FILTER_VALIDATE_BOOLEAN));

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

$config = require dirname(__DIR__) . '/config/web.php';
(new yii\web\Application($config))->run();
