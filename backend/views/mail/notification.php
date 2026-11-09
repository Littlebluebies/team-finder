<?php
/** @var app\models\Notification $notification */
/** @var app\models\Application $application */
/** @var app\models\Post $post */
/** @var string $appUrl */
/** @var string|null $messageExcerpt */

$title = htmlspecialchars($post->title, ENT_QUOTES, 'UTF-8');
$link = $appUrl . '/posts/' . (int)$post->id;
$actorName = $notification->type === \app\models\Notification::TYPE_RECEIVED
    ? $application->applicant->display_name
    : $post->owner->display_name;
$actor = htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8');
$headline = match ($notification->type) {
    \app\models\Notification::TYPE_RECEIVED => 'มีผู้สมัครใหม่',
    \app\models\Notification::TYPE_ACCEPTED => 'ใบสมัครของคุณได้รับการตอบรับ',
    default => 'ผลการพิจารณาใบสมัคร',
};
$body = match ($notification->type) {
    \app\models\Notification::TYPE_RECEIVED => "{$actor} สมัครเข้าร่วมประกาศของคุณ",
    \app\models\Notification::TYPE_ACCEPTED => "คุณได้รับเลือกให้เข้าร่วมทีมโดย {$actor}",
    default => "ใบสมัครของคุณไม่ผ่านการพิจารณาโดย {$actor}",
};
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body style="margin:0;padding:24px;background:#f7f7f3;color:#16170f;font-family:'Leelawadee UI','Noto Sans Thai',system-ui,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e3e4db;">
    <tr>
      <td style="padding:20px 24px;border-bottom:1px solid #e3e4db;font:700 11px/1 monospace;letter-spacing:0.2em;">
        TEAM FINDER
      </td>
    </tr>
    <tr>
      <td style="padding:28px 24px 8px;">
        <h1 style="margin:0;font-size:22px;line-height:1.3;font-weight:600;"><?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?></h1>
        <p style="margin:12px 0 0;color:#4b4e43;font-size:14px;line-height:1.7;"><?= $body ?></p>
        <p style="margin:16px 0 0;font-size:14px;line-height:1.7;"><strong>ประกาศ:</strong> <?= $title ?></p>
        <?php if ($messageExcerpt !== null && $messageExcerpt !== ''): ?>
          <p style="margin:12px 0 0;padding:12px 14px;background:#f2f3ea;border-left:3px solid #d8f24a;color:#4b4e43;font-size:13px;line-height:1.6;white-space:pre-line;"><?= htmlspecialchars($messageExcerpt, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td style="padding:20px 24px 32px;">
        <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" style="display:inline-block;padding:11px 18px;background:#16170f;color:#ffffff;font-size:13px;font-weight:600;text-decoration:none;">เปิดประกาศ</a>
      </td>
    </tr>
    <tr>
      <td style="padding:14px 24px;border-top:1px solid #e3e4db;color:#6f7266;font-size:11px;line-height:1.5;">
        อีเมลนี้ส่งจาก Team Finder เพราะคุณมีบัญชีในระบบ
      </td>
    </tr>
  </table>
</body>
</html>
