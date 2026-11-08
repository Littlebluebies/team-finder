<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(require dirname(__DIR__) . '/config/console.php');

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectIntegrityFailure(callable $callback, $message) {
    try {
        $callback();
    } catch (yii\db\IntegrityException $exception) {
        echo "PASS: {$message}\n";
        return;
    }
    throw new RuntimeException("Expected integrity failure: {$message}");
}

$db = Yii::$app->db;
$expectedTables = ['application', 'post', 'post_skill', 'skill', 'user', 'user_skill'];
$actualTables = $db->schema->getTableNames();
foreach ($expectedTables as $table) {
    check(in_array($table, $actualTables, true), "Missing table {$table}");
}
echo "PASS: six required tables exist\n";

$skills = $db->createCommand('SELECT id, name FROM skill ORDER BY id')->queryAll();
check(array_column($skills, 'name') === [
    'ตัดต่อ', 'เขียนเว็บ', 'ถ่ายภาพ', 'UI/UX', 'เขียนคอนเทนต์', 'วิเคราะห์ข้อมูล',
], 'Skill seed or ordering differs');
echo "PASS: skill seed is exact and ordered\n";

$tx = $db->beginTransaction();
try {
    $db->createCommand()->insert('user', [
        'email' => 'schema-smoke@example.com',
        'password_hash' => 'not-a-production-password',
        'display_name' => 'Schema Smoke',
        'faculty' => 'Test',
        'major' => 'Test',
        'year' => 1,
        'line_id' => 'schema.smoke',
    ])->execute();
    $userId = (int)$db->lastInsertID;

    expectIntegrityFailure(function () use ($db) {
        $db->createCommand()->insert('user', [
            'email' => 'SCHEMA-SMOKE@example.com',
            'password_hash' => 'x',
            'display_name' => 'Duplicate',
            'faculty' => 'Test',
            'major' => 'Test',
            'year' => 1,
            'line_id' => 'duplicate',
        ])->execute();
    }, 'email uniqueness is case-insensitive');

    $db->createCommand()->insert('post', [
        'owner_id' => $userId,
        'title' => 'Schema test',
        'description' => 'Schema test',
        'category' => 'course',
        'slots' => 1,
        'deadline' => '2026-12-31',
    ])->execute();
    $postId = (int)$db->lastInsertID;

    $db->createCommand()->insert('application', [
        'post_id' => $postId,
        'user_id' => $userId,
        'message' => 'First',
    ])->execute();

    expectIntegrityFailure(function () use ($db, $postId, $userId) {
        $db->createCommand()->insert('application', [
            'post_id' => $postId,
            'user_id' => $userId,
            'message' => 'Duplicate',
        ])->execute();
    }, 'application uniqueness is enforced');

    expectIntegrityFailure(function () use ($db) {
        $db->createCommand()->insert('user_skill', [
            'user_id' => 99999999,
            'skill_id' => 1,
        ])->execute();
    }, 'foreign keys reject missing parents');

    echo "PASS: constraints reject invalid data\n";
} finally {
    $tx->rollBack();
}

echo "Schema smoke test passed\n";
