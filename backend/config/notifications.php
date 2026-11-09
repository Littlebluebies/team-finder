<?php

$local = is_file(__DIR__ . '/notifications-local.php')
    ? require __DIR__ . '/notifications-local.php'
    : [];

return array_replace_recursive([
    'app_url' => 'http://localhost:5173',
    'dry_run' => true,
    'mail' => [
        'dsn' => '',
        'from' => 'no-reply@team-finder.local',
        'from_name' => 'Team Finder',
    ],
    'line' => [
        'channel_id' => '',
        'channel_secret' => '',
        'access_token' => '',
        'redirect_uri' => 'http://localhost:5173/line/callback',
    ],
], $local);
