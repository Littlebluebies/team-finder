<?php

$notifications = require __DIR__ . '/notifications.php';

$mailer = [
    'class' => yii\symfonymailer\Mailer::class,
    'viewPath' => '@app/views/mail',
    'useFileTransport' => false,
];
if ($notifications['mail']['dsn'] !== '') {
    $mailer['transport'] = ['dsn' => $notifications['mail']['dsn']];
}

return [
    'id' => 'team-finder-console',
    'basePath' => dirname(__DIR__),
    'timeZone' => 'Asia/Bangkok',
    'controllerNamespace' => 'app\commands',
    'components' => [
        'db' => require __DIR__ . '/db.php',
        'mailer' => $mailer,
    ],
    'params' => [
        'notifications' => $notifications,
    ],
];
