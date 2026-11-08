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
    $owner = registerUser($baseUrl, [1]);
    $ownerToken = $owner['token'];
    $userIds[] = $owner['user']['id'];

    $applicant = registerUser($baseUrl, [2, 3]);
    $applicantToken = $applicant['token'];
    $userIds[] = $applicant['user']['id'];

    $third = registerUser($baseUrl, [4]);
    $thirdToken = $third['token'];
    $userIds[] = $third['user']['id'];

    $postPayload = [
        'title' => 'หาคนร่วมทีม Day 3',
        'description' => 'ทดสอบ application flow',
        'category' => 'course',
        'slots' => 1,
        'deadline' => '2026-12-31',
        'skill_ids' => [1],
    ];

    [$status, $post] = request('POST', $baseUrl . '/posts', $postPayload, $ownerToken);
    check($status === 201, 'Setup post did not return 201: ' . json_encode($post));
    $postId = (int)$post['id'];
    $postIds[] = $postId;

    // 401: every application endpoint requires a valid bearer token.
    [$status] = request('POST', $baseUrl . "/posts/{$postId}/applications", ['message' => 'hi']);
    check($status === 401, 'Apply without token did not return 401');
    [$status] = request('GET', $baseUrl . "/posts/{$postId}/applications");
    check($status === 401, 'Owner list without token did not return 401');
    [$status] = request('PATCH', $baseUrl . '/applications/1', ['status' => 'accepted']);
    check($status === 401, 'Application update without token did not return 401');
    [$status] = request('PATCH', $baseUrl . "/posts/{$postId}", ['status' => 'closed']);
    check($status === 401, 'Close post without token did not return 401');

    // 201: apply with a real message.
    [$status, $application] = request('POST', $baseUrl . "/posts/{$postId}/applications", [
        'message' => 'สนใจร่วมทีมครับ',
    ], $applicantToken);
    check($status === 201, 'Apply did not return 201: ' . json_encode($application));
    check($application['status'] === 'pending', 'New application status differs');
    check(!isset($application['applicant']['line_id']), 'Pending application leaked line_id');
    $applicationId = (int)$application['id'];

    // 409: duplicate application.
    [$status] = request('POST', $baseUrl . "/posts/{$postId}/applications", [
        'message' => 'สมัครซ้ำ',
    ], $applicantToken);
    check($status === 409, 'Duplicate application did not return 409');

    // 422: own post and missing message.
    [$status] = request('POST', $baseUrl . "/posts/{$postId}/applications", [
        'message' => 'สมัครโพสต์ตัวเอง',
    ], $ownerToken);
    check($status === 422, 'Applying to own post did not return 422');

    [$status, $errors] = request('POST', $baseUrl . "/posts/{$postId}/applications", [], $thirdToken);
    check($status === 422, 'Missing message did not return 422');
    check(in_array('message', array_column($errors, 'field'), true), 'Message error missing');

    // 403: non-owner cannot read the applicant list.
    [$status] = request('GET', $baseUrl . "/posts/{$postId}/applications", null, $applicantToken);
    check($status === 403, 'Non-owner list did not return 403');

    // 200: owner list omits line_id while pending.
    [$status, $list] = request('GET', $baseUrl . "/posts/{$postId}/applications", null, $ownerToken);
    check($status === 200, 'Owner list did not return 200');
    check(count($list) === 1, 'Owner list count differs');
    check($list[0]['status'] === 'pending', 'Owner list status differs');
    check(!isset($list[0]['applicant']['line_id']), 'Owner list leaked pending line_id');

    // Detail: viewer_application and is_owner are exposed, owner line_id is not.
    [$status, $detail] = request('GET', $baseUrl . "/posts/{$postId}", null, $applicantToken);
    check($status === 200, 'Applicant detail did not return 200');
    check($detail['is_owner'] === false, 'Applicant is_owner differs');
    check($detail['viewer_application']['status'] === 'pending', 'Viewer application status differs');
    check(!isset($detail['owner']['line_id']), 'Applicant detail leaked owner line_id');

    [$status, $ownerDetail] = request('GET', $baseUrl . "/posts/{$postId}", null, $ownerToken);
    check($status === 200, 'Owner detail did not return 200');
    check($ownerDetail['is_owner'] === true, 'Owner is_owner differs');
    check($ownerDetail['viewer_application'] === null, 'Owner viewer_application should be null');

    // 403: another user cannot accept/reject.
    [$status] = request('PATCH', $baseUrl . "/applications/{$applicationId}", [
        'status' => 'accepted',
    ], $thirdToken);
    check($status === 403, 'Non-owner update did not return 403');

    // 422: invalid status.
    [$status] = request('PATCH', $baseUrl . "/applications/{$applicationId}", [
        'status' => 'maybe',
    ], $ownerToken);
    check($status === 422, 'Invalid application status did not return 422');

    // 200: accept; slots=1 means the post closes automatically.
    [$status, $accepted] = request('PATCH', $baseUrl . "/applications/{$applicationId}", [
        'status' => 'accepted',
    ], $ownerToken);
    check($status === 200, 'Accept did not return 200: ' . json_encode($accepted));
    check($accepted['status'] === 'accepted', 'Accepted status differs');
    check(isset($accepted['applicant']['line_id']), 'Accepted application did not expose line_id');

    [$status, $list] = request('GET', $baseUrl . "/posts/{$postId}/applications", null, $ownerToken);
    check($status === 200, 'Owner list after accept did not return 200');
    check($list[0]['applicant']['line_id'] !== null, 'Owner list accepted line_id missing');

    [$status, $detail] = request('GET', $baseUrl . "/posts/{$postId}", null, $applicantToken);
    check($status === 200, 'Applicant detail after accept did not return 200');
    check($detail['status'] === 'closed', 'Post did not close when slots filled');
    check(isset($detail['owner']['line_id']), 'Accepted applicant did not see owner line_id');
    check($detail['viewer_application']['status'] === 'accepted', 'Accepted viewer status differs');

    // 422: closed post rejects new applications.
    [$status] = request('POST', $baseUrl . "/posts/{$postId}/applications", [
        'message' => 'มาสาย',
    ], $thirdToken);
    check($status === 422, 'Applying to closed post did not return 422');

    // 200: owner can close an already closed post idempotently.
    [$status, $closed] = request('PATCH', $baseUrl . "/posts/{$postId}", [
        'status' => 'closed',
    ], $ownerToken);
    check($status === 200, 'Close post did not return 200');
    check($closed['status'] === 'closed', 'Closed post status differs');

    // Reject flow on a second post; rejected applications never expose line_id.
    $secondPayload = $postPayload;
    $secondPayload['title'] = 'หาคนร่วมทีม reject flow';
    $secondPayload['slots'] = 2;
    [$status, $secondPost] = request('POST', $baseUrl . '/posts', $secondPayload, $ownerToken);
    check($status === 201, 'Second post did not return 201');
    $secondPostId = (int)$secondPost['id'];
    $postIds[] = $secondPostId;

    [$status, $secondApplication] = request('POST', $baseUrl . "/posts/{$secondPostId}/applications", [
        'message' => 'ขอสมัครครับ',
    ], $applicantToken);
    check($status === 201, 'Second apply did not return 201');
    $secondApplicationId = (int)$secondApplication['id'];

    [$status, $rejected] = request('PATCH', $baseUrl . "/applications/{$secondApplicationId}", [
        'status' => 'rejected',
    ], $ownerToken);
    check($status === 200, 'Reject did not return 200');
    check($rejected['status'] === 'rejected', 'Rejected status differs');
    check(!isset($rejected['applicant']['line_id']), 'Rejected application leaked line_id');

    [$status, $detail] = request('GET', $baseUrl . "/posts/{$secondPostId}", null, $applicantToken);
    check($status === 200, 'Rejected applicant detail did not return 200');
    check(!isset($detail['owner']['line_id']), 'Rejected applicant saw owner line_id');

    // 404: unknown post and unknown application.
    [$status] = request('POST', $baseUrl . '/posts/999999/applications', [
        'message' => 'ไม่มีโพสต์นี้',
    ], $applicantToken);
    check($status === 404, 'Unknown post apply did not return 404');
    [$status] = request('PATCH', $baseUrl . '/applications/999999', [
        'status' => 'accepted',
    ], $ownerToken);
    check($status === 404, 'Unknown application update did not return 404');

    // 400: malformed JSON.
    [$status] = request('POST', $baseUrl . "/posts/{$postId}/applications", '{broken-json', $thirdToken);
    check($status === 400, 'Malformed application JSON did not return 400');

    echo "Application smoke test passed\n";
} finally {
    if ($postIds !== []) {
        Yii::$app->db->createCommand()->delete('application', ['post_id' => $postIds])->execute();
        Yii::$app->db->createCommand()->delete('post', ['id' => $postIds])->execute();
    }
    if ($userIds !== []) {
        Yii::$app->db->createCommand()->delete('user', ['id' => $userIds])->execute();
    }
}
