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

$config = Yii::$app->params['notifications'] ?? [];
check(!empty($config['dry_run']), 'notification_smoke requires dry_run = true');

$userIds = [];
$postIds = [];

$postPayload = [
    'title' => 'หาคนร่วมทีม notification',
    'description' => 'ทดสอบ notification flow',
    'category' => 'course',
    'slots' => 2,
    'deadline' => '2026-12-31',
    'skill_ids' => [1],
];

try {
    $owner = registerUser($baseUrl, [1]);
    $ownerToken = $owner['token'];
    $userIds[] = $owner['user']['id'];

    $applicant = registerUser($baseUrl, [2, 3]);
    $applicantToken = $applicant['token'];
    $userIds[] = $applicant['user']['id'];

    // 401: every new endpoint requires a bearer token.
    [$status] = request('GET', $baseUrl . '/notifications');
    check($status === 401, 'Notifications without token did not return 401');
    [$status] = request('PATCH', $baseUrl . '/notifications', []);
    check($status === 401, 'Mark read without token did not return 401');
    [$status] = request('GET', $baseUrl . '/me/line/authorize-url');
    check($status === 401, 'Authorize URL without token did not return 401');
    [$status] = request('POST', $baseUrl . '/me/line', ['code' => 'x', 'state' => 'y']);
    check($status === 401, 'LINE link without token did not return 401');
    [$status] = request('DELETE', $baseUrl . '/me/line');
    check($status === 401, 'LINE unlink without token did not return 401');

    [$status, $post] = request('POST', $baseUrl . '/posts', $postPayload, $ownerToken);
    check($status === 201, 'Setup post did not return 201: ' . json_encode($post));
    $postId = (int)$post['id'];
    $postIds[] = $postId;

    [$status, $application] = request('POST', $baseUrl . "/posts/{$postId}/applications", [
        'message' => 'สนใจร่วมทีมครับ',
    ], $applicantToken);
    check($status === 201, 'Apply did not return 201: ' . json_encode($application));
    $applicationId = (int)$application['id'];

    // New application creates exactly one notification for the post owner.
    $rows = Yii::$app->db->createCommand(
        'SELECT * FROM notification WHERE application_id = :id AND type = :type',
        [':id' => $applicationId, ':type' => 'application_received']
    )->queryAll();
    check(count($rows) === 1, 'Owner notification row count differs');
    check((int)$rows[0]['user_id'] === (int)$owner['user']['id'], 'Owner notification recipient differs');
    check($rows[0]['email_sent_at'] !== null, 'Dry-run email was not marked sent');

    [$status, $list] = request('GET', $baseUrl . '/notifications', null, $ownerToken);
    check($status === 200, 'Owner notification list did not return 200');
    check(count($list['items']) === 1, 'Owner notification item count differs');
    check($list['unread'] === 1, 'Owner unread count differs');
    check($list['items'][0]['type'] === 'application_received', 'Owner notification type differs');
    check($list['items'][0]['created_at'] !== null, 'Notification created_at missing');
    check(!isset($list['items'][0]['line_id']), 'Notification payload leaked line_id');
    check(!isset($list['items'][0]['line_user_id']), 'Notification payload leaked line_user_id');
    $notificationId = (int)$list['items'][0]['id'];

    [$status, $applicantList] = request('GET', $baseUrl . '/notifications', null, $applicantToken);
    check($status === 200, 'Applicant notification list did not return 200');
    check($applicantList['items'] === [], 'Applicant saw someone else notification');
    check($applicantList['unread'] === 0, 'Applicant unread count differs');

    // 422: malformed ids.
    [$status] = request('PATCH', $baseUrl . '/notifications', ['ids' => 'nope'], $ownerToken);
    check($status === 422, 'Non-array ids did not return 422');
    [$status] = request('PATCH', $baseUrl . '/notifications', ['ids' => []], $ownerToken);
    check($status === 422, 'Empty ids did not return 422');

    // Mark one read.
    [$status, $marked] = request('PATCH', $baseUrl . '/notifications', ['ids' => [$notificationId]], $ownerToken);
    check($status === 200, 'Mark one read did not return 200');
    check($marked['unread'] === 0, 'Mark one read did not clear unread');

    // Accept creates the applicant notification.
    [$status] = request('PATCH', $baseUrl . "/applications/{$applicationId}", [
        'status' => 'accepted',
    ], $ownerToken);
    check($status === 200, 'Accept did not return 200');

    [$status, $applicantList] = request('GET', $baseUrl . '/notifications', null, $applicantToken);
    check($status === 200, 'Applicant list after accept did not return 200');
    check($applicantList['unread'] === 1, 'Accepted unread count differs');
    check($applicantList['items'][0]['type'] === 'application_accepted', 'Accepted notification type differs');

    // Reject flow on a second post.
    $secondPayload = $postPayload;
    $secondPayload['title'] = 'หาคนร่วมทีม notification reject';
    [$status, $secondPost] = request('POST', $baseUrl . '/posts', $secondPayload, $ownerToken);
    check($status === 201, 'Second post did not return 201');
    $secondPostId = (int)$secondPost['id'];
    $postIds[] = $secondPostId;

    [$status, $secondApplication] = request('POST', $baseUrl . "/posts/{$secondPostId}/applications", [
        'message' => 'ขอสมัครครับ',
    ], $applicantToken);
    check($status === 201, 'Second apply did not return 201');

    [$status] = request('PATCH', $baseUrl . '/applications/' . (int)$secondApplication['id'], [
        'status' => 'rejected',
    ], $ownerToken);
    check($status === 200, 'Reject did not return 200');

    [$status, $applicantList] = request('GET', $baseUrl . '/notifications', null, $applicantToken);
    check($status === 200, 'Applicant list after reject did not return 200');
    check($applicantList['unread'] === 2, 'Rejected unread count differs');
    check($applicantList['items'][0]['type'] === 'application_rejected', 'Rejected notification type differs');

    // Mark all read.
    [$status, $marked] = request('PATCH', $baseUrl . '/notifications', [], $applicantToken);
    check($status === 200, 'Mark all read did not return 200');
    check($marked['unread'] === 0, 'Mark all read did not clear unread');

    // LINE endpoints without real credentials.
    [$status] = request('POST', $baseUrl . '/me/line', ['code' => 'abc', 'state' => 'wrong'], $applicantToken);
    check($status === 422, 'Invalid LINE state did not return 422');

    [$status, $unlinked] = request('DELETE', $baseUrl . '/me/line', null, $applicantToken);
    check($status === 200, 'LINE unlink did not return 200');
    check($unlinked['linked'] === false, 'LINE unlink response differs');

    [$status, $me] = request('GET', $baseUrl . '/me', null, $applicantToken);
    check($status === 200, 'Me did not return 200');
    check($me['line_linked'] === false, 'line_linked differs');
    check(array_key_exists('line_linked_at', $me), 'line_linked_at missing');
    check(!array_key_exists('line_user_id', $me), 'Me leaked line_user_id');

    if ($config['line']['channel_id'] === '') {
        [$status] = request('GET', $baseUrl . '/me/line/authorize-url', null, $applicantToken);
        check($status === 503, 'Unconfigured LINE login did not return 503');
    }

    echo "Notification smoke test passed\n";
} finally {
    if ($postIds !== []) {
        Yii::$app->db->createCommand()->delete('application', ['post_id' => $postIds])->execute();
        Yii::$app->db->createCommand()->delete('post', ['id' => $postIds])->execute();
    }
    if ($userIds !== []) {
        Yii::$app->db->createCommand()->delete('user', ['id' => $userIds])->execute();
    }
}
