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

$userIds = [];
$postIds = [];

try {
    $user = registerUser($baseUrl, [1, 2]);
    $token = $user['token'];
    $userId = (int)$user['user']['id'];
    $userIds[] = $userId;

    $other = registerUser($baseUrl, [3]);
    $otherToken = $other['token'];
    $userIds[] = $other['user']['id'];

    // 401: every /me endpoint requires a token.
    foreach (['/me', '/me/posts', '/me/applications'] as $path) {
        [$status] = request('GET', $baseUrl . $path);
        check($status === 401, "GET {$path} without token did not return 401");
    }
    [$status] = request('PUT', $baseUrl . '/me', ['display_name' => 'x']);
    check($status === 401, 'PUT /me without token did not return 401');

    // 200: own profile includes own line_id only.
    [$status, $profile] = request('GET', $baseUrl . '/me', null, $token);
    check($status === 200, 'GET /me did not return 200: ' . json_encode($profile));
    check((int)$profile['id'] === $userId, 'GET /me id differs');
    check($profile['line_id'] === 'smoke.test', 'GET /me line_id differs');
    check(array_column($profile['skills'], 'id') === [1, 2], 'GET /me skills differ');

    // 200: update profile and replace skills in one transaction.
    [$status, $updated] = request('PUT', $baseUrl . '/me', [
        'display_name' => 'Updated Name',
        'faculty' => 'Updated Faculty',
        'major' => 'Updated Major',
        'year' => 4,
        'line_id' => 'updated.line',
        'bio' => 'hello bio',
        'skill_ids' => [3, 4],
    ], $token);
    check($status === 200, 'PUT /me did not return 200: ' . json_encode($updated));
    check($updated['display_name'] === 'Updated Name', 'PUT /me display_name differs');
    check((int)$updated['year'] === 4, 'PUT /me year differs');
    check($updated['line_id'] === 'updated.line', 'PUT /me line_id differs');
    check($updated['bio'] === 'hello bio', 'PUT /me bio differs');
    check(array_column($updated['skills'], 'id') === [3, 4], 'PUT /me skills differ');

    [$status, $profile] = request('GET', $baseUrl . '/me', null, $token);
    check($status === 200, 'GET /me after update did not return 200');
    check($profile['display_name'] === 'Updated Name', 'Profile update was not persisted');
    check(array_column($profile['skills'], 'id') === [3, 4], 'Skill update was not persisted');

    // 422: validation failures.
    [$status, $errors] = request('PUT', $baseUrl . '/me', [
        'display_name' => 'x', 'faculty' => 'f', 'major' => 'm',
        'year' => 0, 'line_id' => 'l', 'skill_ids' => [1],
    ], $token);
    check($status === 422, 'Invalid year did not return 422');
    check(in_array('year', array_column($errors, 'field'), true), 'Year error missing');

    [$status, $errors] = request('PUT', $baseUrl . '/me', [
        'faculty' => 'f', 'major' => 'm', 'year' => 2, 'line_id' => 'l', 'skill_ids' => [1],
    ], $token);
    check($status === 422, 'Missing display_name did not return 422');
    check(in_array('display_name', array_column($errors, 'field'), true), 'display_name error missing');

    [$status, $errors] = request('PUT', $baseUrl . '/me', [
        'display_name' => 'x', 'faculty' => 'f', 'major' => 'm',
        'year' => 2, 'line_id' => 'l', 'skill_ids' => [999999],
    ], $token);
    check($status === 422, 'Unknown skill did not return 422');
    check(in_array('skill_ids', array_column($errors, 'field'), true), 'skill_ids error missing');

    [$status] = request('PUT', $baseUrl . '/me', '{broken-json', $token);
    check($status === 400, 'Malformed profile JSON did not return 400');

    // Setup: post owned by $user with one pending application from $other.
    [$status, $post] = request('POST', $baseUrl . '/posts', [
        'title' => 'โพสต์ของฉัน',
        'description' => 'ทดสอบ /api/me/posts',
        'category' => 'course',
        'slots' => 2,
        'deadline' => '2026-12-31',
        'skill_ids' => [1],
    ], $token);
    check($status === 201, 'Setup post did not return 201: ' . json_encode($post));
    $postId = (int)$post['id'];
    $postIds[] = $postId;

    [$status, $application] = request('POST', $baseUrl . "/posts/{$postId}/applications", [
        'message' => 'ขอสมัครครับ',
    ], $otherToken);
    check($status === 201, 'Setup application did not return 201');
    $applicationId = (int)$application['id'];

    // 200: /me/posts includes own posts with pending_count.
    [$status, $myPosts] = request('GET', $baseUrl . '/me/posts', null, $token);
    check($status === 200, 'GET /me/posts did not return 200');
    check(count($myPosts) === 1, 'GET /me/posts count differs');
    check((int)$myPosts[0]['id'] === $postId, 'GET /me/posts id differs');
    check($myPosts[0]['is_owner'] === true, 'GET /me/posts is_owner differs');
    check((int)$myPosts[0]['pending_count'] === 1, 'GET /me/posts pending_count differs');
    check((int)$myPosts[0]['accepted_count'] === 0, 'GET /me/posts accepted_count differs');

    // Closed posts stay visible in /me/posts.
    [$status] = request('PATCH', $baseUrl . "/posts/{$postId}", ['status' => 'closed'], $token);
    check($status === 200, 'Closing setup post did not return 200');
    [$status, $myPosts] = request('GET', $baseUrl . '/me/posts', null, $token);
    check($status === 200, 'GET /me/posts after close did not return 200');
    check($myPosts[0]['status'] === 'closed', 'Closed post missing from /me/posts');

    // 200: /me/applications hides owner line_id while pending.
    [$status, $myApplications] = request('GET', $baseUrl . '/me/applications', null, $otherToken);
    check($status === 200, 'GET /me/applications did not return 200');
    check(count($myApplications) === 1, 'GET /me/applications count differs');
    check($myApplications[0]['status'] === 'pending', 'GET /me/applications status differs');
    check(!isset($myApplications[0]['post']['owner']['line_id']), 'Pending application leaked owner line_id');

    // Accept: owner line_id appears for the applicant.
    [$status] = request('PATCH', $baseUrl . "/applications/{$applicationId}", ['status' => 'accepted'], $token);
    check($status === 200, 'Accept did not return 200');
    [$status, $myApplications] = request('GET', $baseUrl . '/me/applications', null, $otherToken);
    check($status === 200, 'GET /me/applications after accept did not return 200');
    check($myApplications[0]['status'] === 'accepted', 'Accepted application status differs');
    check(isset($myApplications[0]['post']['owner']['line_id']), 'Accepted application did not expose owner line_id');

    // Rejected applications never expose owner line_id.
    [$status, $secondPost] = request('POST', $baseUrl . '/posts', [
        'title' => 'โพสต์ reject',
        'description' => 'ทดสอบ rejected line_id',
        'category' => 'personal',
        'slots' => 2,
        'deadline' => '2026-12-31',
        'skill_ids' => [1],
    ], $token);
    check($status === 201, 'Second post did not return 201');
    $secondPostId = (int)$secondPost['id'];
    $postIds[] = $secondPostId;

    [$status, $secondApplication] = request('POST', $baseUrl . "/posts/{$secondPostId}/applications", [
        'message' => 'ขอสมัครอีกครั้ง',
    ], $otherToken);
    check($status === 201, 'Second application did not return 201');
    [$status] = request('PATCH', $baseUrl . '/applications/' . (int)$secondApplication['id'], ['status' => 'rejected'], $token);
    check($status === 200, 'Reject did not return 200');

    [$status, $myApplications] = request('GET', $baseUrl . '/me/applications', null, $otherToken);
    check($status === 200, 'GET /me/applications after reject did not return 200');
    $rejected = null;
    foreach ($myApplications as $item) {
        if ((int)$item['post']['id'] === $secondPostId) {
            $rejected = $item;
        }
    }
    check($rejected !== null, 'Rejected application missing from /me/applications');
    check($rejected['status'] === 'rejected', 'Rejected application status differs');
    check(!isset($rejected['post']['owner']['line_id']), 'Rejected application leaked owner line_id');

    echo "Me smoke test passed\n";
} finally {
    if ($postIds !== []) {
        Yii::$app->db->createCommand()->delete('application', ['post_id' => $postIds])->execute();
        Yii::$app->db->createCommand()->delete('post', ['id' => $postIds])->execute();
    }
    if ($userIds !== []) {
        Yii::$app->db->createCommand()->delete('user', ['id' => $userIds])->execute();
    }
}
