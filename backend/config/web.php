<?php

return [
    'id' => 'team-finder-api',
    'basePath' => dirname(__DIR__),
    'timeZone' => 'Asia/Bangkok',
    'components' => [
        'request' => [
            'parsers' => [
                'application/json' => yii\web\JsonParser::class,
            ],
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
        ],
        'user' => [
            'identityClass' => app\models\User::class,
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'db' => require __DIR__ . '/db.php',
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'POST api/auth/register' => 'auth/register',
                'POST api/auth/login' => 'auth/login',
                'GET api/skills' => 'skill/index',
            ],
        ],
        'log' => [
            'targets' => [
                [
                    'class' => yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
    ],
];
