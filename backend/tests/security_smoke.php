<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(require dirname(__DIR__) . '/config/console.php');

require __DIR__ . '/helpers.php';

$baseUrl = getenv('API_URL') ?: 'http://localhost:8080/api';
$host = parse_url($baseUrl, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('API smoke tests may target localhost only.');
}

// Reset the shared file-cache counters before counting.
check(Yii::$app->cache->flush(), 'Could not flush cache');

$userId = null;

try {
    // 401: logout requires a bearer token.
    [$status] = request('POST', $baseUrl . '/auth/logout');
    check($status === 401, 'Logout without token did not return 401');

    $user = registerUser($baseUrl, [1]);
    $userId = (int)$user['user']['id'];

    // 200: logout revokes the current token server-side.
    [$status, $result] = request('POST', $baseUrl . '/auth/logout', null, $user['token']);
    check($status === 200, 'Logout did not return 200: ' . json_encode($result));
    check($result['logged_out'] === true, 'Logout response differs');
    [$status] = request('GET', $baseUrl . '/me', null, $user['token']);
    check($status === 401, 'Revoked token still worked');

    // 429: sixth login attempt inside the window is blocked (auth limit = 5/min).
    Yii::$app->cache->flush();
    $email = 'nonexistent-' . bin2hex(random_bytes(8)) . '@example.com';
    $statuses = [];
    for ($i = 0; $i < 6; $i++) {
        [$status] = request('POST', $baseUrl . '/auth/login', [
            'email' => $email,
            'password' => 'wrong-password-1',
        ]);
        $statuses[] = $status;
    }
    check($statuses[4] === 401, 'Fifth login attempt should still be 401: ' . json_encode($statuses));
    check($statuses[5] === 429, 'Sixth login attempt did not return 429: ' . json_encode($statuses));

    // Buckets are per route: skills still works after the login bucket is burnt.
    [$status] = request('GET', $baseUrl . '/skills');
    check($status === 200, 'Skills after auth rate limit did not return 200');

    echo "Security smoke test passed\n";
} finally {
    if ($userId !== null) {
        Yii::$app->db->createCommand()->delete('user', ['id' => $userId])->execute();
    }
}
