<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(require dirname(__DIR__) . '/config/console.php');

use app\models\RegisterForm;
use app\models\User;

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$email = 'model-' . bin2hex(random_bytes(8)) . '@example.com';
$form = new RegisterForm();
$form->load([
    'email' => $email,
    'password' => 'correct-horse-123',
    'display_name' => 'Model Smoke',
    'faculty' => 'Test',
    'major' => 'Test',
    'year' => 3,
    'line_id' => 'model.smoke',
    'skill_ids' => [1, 2],
], '');

$userId = null;
try {
    $result = $form->register();
    check(is_array($result), 'Registration failed: ' . json_encode($form->errors));
    check(strlen($result['token']) >= 64, 'Raw token is too short');
    check(!isset($result['user']['password']), 'Password leaked');
    check(!isset($result['user']['password_hash']), 'Password hash leaked');
    check(!isset($result['user']['access_token_hash']), 'Token hash leaked');
    check(count($result['user']['skills']) === 2, 'Skills were not saved');
    $userId = $result['user']['id'];

    $identity = User::findIdentityByAccessToken($result['token']);
    check($identity && (int)$identity->id === $userId, 'Valid token did not resolve identity');

    $identity->access_token_expires_at = date('Y-m-d H:i:s', time() - 60);
    $identity->save(false, ['access_token_expires_at']);
    check(User::findIdentityByAccessToken($result['token']) === null, 'Expired token resolved identity');

    $duplicate = new RegisterForm();
    $duplicate->load([
        'email' => $email,
        'password' => 'correct-horse-123',
        'display_name' => 'Duplicate',
        'faculty' => 'Test',
        'major' => 'Test',
        'year' => 2,
        'line_id' => 'duplicate',
        'skill_ids' => [1],
    ], '');
    check($duplicate->register() === null && $duplicate->hasErrors('email'), 'Duplicate email was accepted');

    echo "Auth model smoke test passed\n";
} finally {
    if ($userId !== null) {
        $user = User::findOne($userId);
        if ($user !== null) {
            $user->delete();
        }
    }
}
