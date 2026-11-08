<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(require dirname(__DIR__) . '/config/console.php');

use app\models\User;

require __DIR__ . '/helpers.php';

$baseUrl = getenv('API_URL') ?: 'http://localhost:8080/api';
$host = parse_url($baseUrl, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('API smoke tests may target localhost only.');
}

$email = 'api-' . bin2hex(random_bytes(8)) . '@example.com';
$password = 'correct-horse-123';
$payload = [
    'email' => $email,
    'password' => $password,
    'display_name' => 'API Smoke',
    'faculty' => 'Test',
    'major' => 'Test',
    'year' => 3,
    'line_id' => 'api.smoke',
    'skill_ids' => [1, 2],
];
$userId = null;

try {
    [$status, $skills] = request('GET', $baseUrl . '/skills');
    check($status === 200, 'GET skills did not return 200');
    check(array_column($skills, 'name') === [
        'ตัดต่อ', 'เขียนเว็บ', 'ถ่ายภาพ', 'UI/UX', 'เขียนคอนเทนต์', 'วิเคราะห์ข้อมูล',
    ], 'Skills response differs');

    [$status, $registered] = request('POST', $baseUrl . '/auth/register', $payload);
    check($status === 201, 'Register did not return 201: ' . json_encode($registered));
    check(strlen($registered['token']) >= 64, 'Register token missing');
    check(!isset($registered['user']['password']), 'Password leaked');
    check(!isset($registered['user']['password_hash']), 'Password hash leaked');
    check(!isset($registered['user']['access_token_hash']), 'Token hash leaked');
    $firstToken = $registered['token'];
    $userId = $registered['user']['id'];

    [$status, $duplicate] = request('POST', $baseUrl . '/auth/register', $payload);
    check($status === 422, 'Duplicate email did not return 422');
    check(in_array('email', array_column($duplicate, 'field'), true), 'Email error missing');

    $invalidSkills = $payload;
    $invalidSkills['email'] = 'other-' . bin2hex(random_bytes(4)) . '@example.com';
    $invalidSkills['skill_ids'] = [999999];
    [$status] = request('POST', $baseUrl . '/auth/register', $invalidSkills);
    check($status === 422, 'Unknown skill did not return 422');

    $missingName = $payload;
    $missingName['email'] = 'missing-' . bin2hex(random_bytes(4)) . '@example.com';
    unset($missingName['display_name']);
    [$status, $missingErrors] = request('POST', $baseUrl . '/auth/register', $missingName);
    check($status === 422, 'Missing display name did not return 422');
    check(in_array('display_name', array_column($missingErrors, 'field'), true), 'Display name error missing');

    [$status, $wrongLogin] = request('POST', $baseUrl . '/auth/login', [
        'email' => $email,
        'password' => 'wrong-password',
    ]);
    check($status === 401, 'Wrong password did not return 401');
    check($wrongLogin['message'] === 'Invalid email or password.', 'Login error differs');

    [$status] = request('POST', $baseUrl . '/auth/register', '{broken-json');
    check($status === 400, 'Malformed JSON did not return 400');

    [$status, $loggedIn] = request('POST', $baseUrl . '/auth/login', [
        'email' => $email,
        'password' => $password,
    ]);
    check($status === 200, 'Valid login did not return 200');
    $secondToken = $loggedIn['token'];
    check($secondToken !== $firstToken, 'Login did not rotate token');
    check(User::findIdentityByAccessToken($firstToken) === null, 'Old token still resolves');
    $identity = User::findIdentityByAccessToken($secondToken);
    check($identity && (int)$identity->id === $userId, 'New token does not resolve');

    echo "API smoke test passed\n";
} finally {
    if ($userId !== null) {
        $user = User::findOne($userId);
        if ($user !== null) {
            $user->delete();
        }
    }
}
