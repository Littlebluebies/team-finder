<?php

return [
    // คัดลอกเป็น notifications-local.php แล้วใส่ค่าจริง (ไฟล์นั้นถูก gitignore)
    'app_url' => 'http://localhost:5173',
    'dry_run' => false,
    'mail' => [
        'dsn' => 'smtp://USER:PASSWORD@smtp.example.com:587',
        'from' => 'no-reply@example.com',
        'from_name' => 'Team Finder',
    ],
    'line' => [
        // LINE Login channel
        'channel_id' => '2000000000',
        'channel_secret' => 'REPLACE_ME',
        // Messaging API channel access token (คนละตัวกับ channel secret)
        'access_token' => 'REPLACE_ME',
        'redirect_uri' => 'http://localhost:5173/line/callback',
    ],
];
