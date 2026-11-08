<?php

return [
    'id' => 'team-finder-api',
    'basePath' => dirname(__DIR__),
    'timeZone' => 'Asia/Bangkok',
    'components' => [
        'request' => [
            'enableCsrfCookie' => false,
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
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                'POST api/auth/register' => 'auth/register',
                'POST api/auth/login' => 'auth/login',
                'GET api/skills' => 'skill/index',
                'POST api/posts' => 'post/create',
                'GET api/posts' => 'post/index',
                'GET api/posts/<id:\d+>' => 'post/view',
                'POST api/posts/<id:\d+>/applications' => 'application/create',
                'GET api/posts/<id:\d+>/applications' => 'application/index',
                'PATCH api/applications/<id:\d+>' => 'application/update',
                'PATCH api/posts/<id:\d+>' => 'post/close',
                'GET api/me' => 'me/view',
                'PUT api/me' => 'me/update',
                'GET api/me/posts' => 'me/posts',
                'GET api/me/applications' => 'me/applications',
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
