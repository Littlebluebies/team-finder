<?php

$local = is_file(__DIR__ . '/db-local.php')
    ? require __DIR__ . '/db-local.php'
    : [];

return array_replace([
    'class' => yii\db\Connection::class,
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=team_finder',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'attributes' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
], $local);
