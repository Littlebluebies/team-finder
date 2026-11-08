<?php

return [
    'id' => 'team-finder-console',
    'basePath' => dirname(__DIR__),
    'timeZone' => 'Asia/Bangkok',
    'controllerNamespace' => 'app\commands',
    'components' => [
        'db' => require __DIR__ . '/db.php',
    ],
];
