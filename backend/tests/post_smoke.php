<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(require dirname(__DIR__) . '/config/console.php');

use app\models\Post;
use app\models\User;

require __DIR__ . '/helpers.php';

$baseUrl = getenv('API_URL') ?: 'http://localhost:8080/api';
$host = parse_url($baseUrl, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('API smoke tests may target localhost only.');
}

$userIds = [];
$postIds = [];

try {
    $registered = registerUser($baseUrl);
    $token = $registered['token'];
    $ownerId = $registered['user']['id'];
    $userIds[] = $ownerId;

    $other = registerUser($baseUrl, [3, 4]);
    $otherToken = $other['token'];
    $userIds[] = $other['user']['id'];

    // 401: protected endpoints reject missing and invalid tokens.
    [$status] = request('GET', $baseUrl . '/posts');
    check($status === 401, 'Feed without token did not return 401');
    [$status] = request('POST', $baseUrl . '/posts', ['title' => 'x']);
    check($status === 401, 'Create without token did not return 401');
    [$status] = request('GET', $baseUrl . '/posts', null, 'invalid-token-' . bin2hex(random_bytes(16)));
    check($status === 401, 'Feed with invalid token did not return 401');

    // 201: create with valid payload.
    $payload = [
        'title' => 'หาทีมแข่งhackathon',
        'description' => 'ต้องการคนถ่ายภาพและเขียนคอนเทนต์',
        'category' => 'contest',
        'slots' => 2,
        'deadline' => '2026-12-31',
        'skill_ids' => [3, 5],
    ];
    [$status, $created] = request('POST', $baseUrl . '/posts', $payload, $token);
    check($status === 201, 'Create post did not return 201: ' . json_encode($created));
    check((int)$created['owner']['id'] === $ownerId, 'Owner id differs');
    check($created['status'] === 'open', 'Created post status differs');
    check($created['title'] === $payload['title'], 'Created post title differs');
    check($created['deadline'] === $payload['deadline'], 'Created post deadline differs');
    check(array_column($created['skills'], 'id') === [3, 5], 'Created post skills differ');
    check(!isset($created['owner']['line_id']), 'Owner line_id leaked');
    $postIds[] = $created['id'];

    // 422: validation failures.
    [$status, $errors] = request('POST', $baseUrl . '/posts', ['description' => 'no title'], $token);
    check($status === 422, 'Missing title did not return 422');
    check(in_array('title', array_column($errors, 'field'), true), 'Title error missing');

    $badCategory = $payload;
    $badCategory['category'] = 'work';
    [$status] = request('POST', $baseUrl . '/posts', $badCategory, $token);
    check($status === 422, 'Invalid category did not return 422');

    $badSlots = $payload;
    $badSlots['slots'] = 0;
    [$status] = request('POST', $baseUrl . '/posts', $badSlots, $token);
    check($status === 422, 'Zero slots did not return 422');

    $badSkills = $payload;
    $badSkills['skill_ids'] = [3, 3];
    [$status] = request('POST', $baseUrl . '/posts', $badSkills, $token);
    check($status === 422, 'Duplicate skill ids did not return 422');

    [$status] = request('POST', $baseUrl . '/posts', '{broken-json', $token);
    check($status === 400, 'Malformed JSON did not return 400');

    // 200: feed returns created post first (newest first).
    [$status, $feed] = request('GET', $baseUrl . '/posts', null, $otherToken);
    check($status === 200, 'Feed did not return 200');
    check(is_array($feed) && $feed !== [], 'Feed is empty');
    $feedIds = array_column($feed, 'id');
    check($feedIds[0] === (int)$created['id'], 'Feed is not newest first');
    $feedItem = $feed[0];
    check($feedItem['owner']['display_name'] === 'Smoke User', 'Feed owner display name differs');
    check(!isset($feedItem['owner']['line_id']), 'Feed owner line_id leaked');

    // 200: detail by id.
    [$status, $detail] = request('GET', $baseUrl . '/posts/' . $created['id'], null, $otherToken);
    check($status === 200, 'Detail did not return 200');
    check($detail['title'] === $payload['title'], 'Detail title differs');
    check(array_column($detail['skills'], 'id') === [3, 5], 'Detail skills differ');
    check((int)$detail['accepted_count'] === 0, 'Detail accepted_count differs');

    // Feed filters: category, skill, q, repeated skill, include_closed.
    $secondPayload = [
        'title' => 'หาทีมทำโปรเจกต์ส่วนตัว',
        'description' => 'ต้องการคนเขียนเว็บ',
        'category' => 'personal',
        'slots' => 1,
        'deadline' => '2026-12-31',
        'skill_ids' => [4],
    ];
    [$status, $second] = request('POST', $baseUrl . '/posts', $secondPayload, $token);
    check($status === 201, 'Second post did not return 201');
    $postIds[] = $second['id'];

    [$status, $feed] = request('GET', $baseUrl . '/posts?category=contest', null, $otherToken);
    check($status === 200, 'Category filter did not return 200');
    check(array_column($feed, 'id') === [(int)$created['id']], 'Category filter differs');

    [$status, $feed] = request('GET', $baseUrl . '/posts?skill=4', null, $otherToken);
    check($status === 200, 'Skill filter did not return 200');
    check(array_column($feed, 'id') === [(int)$second['id']], 'Skill filter differs');

    [$status, $feed] = request('GET', $baseUrl . '/posts?skill=3&skill=5', null, $otherToken);
    check($status === 200, 'Repeated skill filter did not return 200');
    check(array_column($feed, 'id') === [(int)$created['id']], 'Repeated skill filter differs');

    [$status, $feed] = request('GET', $baseUrl . '/posts?' . http_build_query(['q' => 'โปรเจกต์ส่วนตัว']), null, $otherToken);
    check($status === 200, 'Keyword filter did not return 200');
    check(array_column($feed, 'id') === [(int)$second['id']], 'Keyword filter differs');

    [$status, $feed] = request('GET', $baseUrl . '/posts?' . http_build_query(['q' => 'ไม่มีข้อความนี้']), null, $otherToken);
    check($status === 200, 'Empty keyword filter did not return 200');
    check($feed === [], 'Empty keyword filter should be empty');

    [$status] = request('PATCH', $baseUrl . '/posts/' . $second['id'], ['status' => 'closed'], $token);
    check($status === 200, 'Closing second post did not return 200');
    [$status, $feed] = request('GET', $baseUrl . '/posts', null, $otherToken);
    check($status === 200, 'Feed after close did not return 200');
    check(!in_array((int)$second['id'], array_column($feed, 'id'), true), 'Closed post leaked into default feed');
    [$status, $feed] = request('GET', $baseUrl . '/posts?include_closed=1', null, $otherToken);
    check($status === 200, 'include_closed did not return 200');
    check(in_array((int)$second['id'], array_column($feed, 'id'), true), 'Closed post missing with include_closed=1');

    // Past deadline: effective status is closed and applications are rejected.
    $expiredPayload = $payload;
    $expiredPayload['title'] = 'โพสต์เลยกำหนด';
    $expiredPayload['deadline'] = '2020-01-01';
    [$status, $expired] = request('POST', $baseUrl . '/posts', $expiredPayload, $token);
    check($status === 201, 'Expired post did not return 201');
    check($expired['status'] === 'closed', 'Expired post status should be closed');
    $postIds[] = $expired['id'];
    [$status, $feed] = request('GET', $baseUrl . '/posts', null, $otherToken);
    check($status === 200, 'Feed with expired post did not return 200');
    check(!in_array((int)$expired['id'], array_column($feed, 'id'), true), 'Expired post leaked into default feed');
    [$status] = request('POST', $baseUrl . '/posts/' . $expired['id'] . '/applications', ['message' => 'สายแล้ว'], $otherToken);
    check($status === 422, 'Applying to expired post did not return 422');

    // 404: unknown post id.
    [$status] = request('GET', $baseUrl . '/posts/999999', null, $token);
    check($status === 404, 'Unknown post id did not return 404');

    // Strict parsing: bare controller paths stay closed.
    [$status] = request('GET', preg_replace('/\/api$/', '', $baseUrl) . '/post/index', null, $token);
    check($status === 404, 'Bare controller path /post/index did not return 404');
    [$status] = request('DELETE', $baseUrl . '/posts');
    check($status === 404, 'DELETE /api/posts did not return 404 (no rule)');

    echo "Post smoke test passed\n";
} finally {
    foreach ($postIds as $postId) {
        $post = Post::findOne($postId);
        if ($post !== null) {
            $post->delete();
        }
    }
    foreach ($userIds as $userId) {
        $user = User::findOne($userId);
        if ($user !== null) {
            $user->delete();
        }
    }
}
