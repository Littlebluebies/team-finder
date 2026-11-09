<?php

$local = is_file(__DIR__ . '/notifications-local.php')
    ? require __DIR__ . '/notifications-local.php'
    : [];

// Environment variables (deployment) sit between defaults and the local file.
$env = array_filter([
    'app_url' => getenv('APP_URL') ?: null,
    'dry_run' => getenv('DRY_RUN') !== false ? getenv('DRY_RUN') !== '' && getenv('DRY_RUN') !== 'false' : null,
    'mail' => [
        'dsn' => getenv('MAIL_DSN') ?: null,
        'from' => getenv('MAIL_FROM') ?: null,
        'from_name' => getenv('MAIL_FROM_NAME') ?: null,
    ],
    'line' => [
        'channel_id' => getenv('LINE_CHANNEL_ID') ?: null,
        'channel_secret' => getenv('LINE_CHANNEL_SECRET') ?: null,
        'access_token' => getenv('LINE_ACCESS_TOKEN') ?: null,
        'redirect_uri' => getenv('LINE_REDIRECT_URI') ?: null,
    ],
]);

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
], $env, $local);
