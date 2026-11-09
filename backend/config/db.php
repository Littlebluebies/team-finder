<?php

$local = is_file(__DIR__ . '/db-local.php')
    ? require __DIR__ . '/db-local.php'
    : [];

// Railway injects MYSQL* variables; a local db-local.php (dev) always wins.
$env = [];
if (getenv('MYSQLHOST') !== false && getenv('MYSQLHOST') !== '') {
    $env = [
        'dsn' => 'mysql:host=' . getenv('MYSQLHOST')
            . ';port=' . (getenv('MYSQLPORT') ?: '3306')
            . ';dbname=' . getenv('MYSQLDATABASE'),
        'username' => getenv('MYSQLUSER'),
        'password' => getenv('MYSQLPASSWORD'),
    ];
}

return array_replace([
    'class' => yii\db\Connection::class,
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=team_finder',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'attributes' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Keep created_at consistent with the app timezone in any container.
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
    ],
], $env, $local);
