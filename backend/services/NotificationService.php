<?php

namespace app\services;

use Yii;
use app\models\Application;
use app\models\Notification;
use yii\db\IntegrityException;

class NotificationService
{
    public static function dispatch(Application $application, $type)
    {
        try {
            $notification = self::store($application, $type);
            if ($notification !== null) {
                self::deliver($notification);
            }
        } catch (\Throwable $exception) {
            Yii::error('Notification dispatch failed: ' . $exception->getMessage(), 'notification');
        }
    }

    public static function deliver(Notification $notification)
    {
        $config = Yii::$app->params['notifications'] ?? [];
        $application = $notification->application;
        $post = $application->post;
        $recipient = $notification->user;

        if (!empty($config['dry_run'])) {
            if ($notification->email_sent_at === null) {
                $notification->email_sent_at = date('Y-m-d H:i:s');
            }
            if ($notification->line_sent_at === null && $recipient->line_user_id !== null) {
                $notification->line_sent_at = date('Y-m-d H:i:s');
            }
            $notification->save(false, ['email_sent_at', 'line_sent_at']);
            return;
        }

        if ($notification->email_sent_at === null) {
            self::deliverEmail($notification, $config, $application, $post, $recipient);
        }
        if ($notification->line_sent_at === null && $recipient->line_user_id !== null) {
            self::deliverLine($notification, $config, $post, $recipient);
        }
    }

    private static function store(Application $application, $type)
    {
        $recipientId = $type === Notification::TYPE_RECEIVED
            ? (int)$application->post->owner_id
            : (int)$application->user_id;

        $condition = [
            'user_id' => $recipientId,
            'type' => $type,
            'application_id' => (int)$application->id,
        ];

        $existing = Notification::findOne($condition);
        if ($existing !== null) {
            return $existing;
        }

        $notification = new Notification();
        $notification->setAttributes($condition, false);
        try {
            $notification->save(false);
        } catch (IntegrityException $exception) {
            return Notification::findOne($condition);
        }
        return $notification;
    }

    private static function deliverEmail(Notification $notification, array $config, Application $application, $post, $recipient)
    {
        if ($config['mail']['dsn'] === '') {
            $notification->email_error = 'mail not configured';
            $notification->save(false, ['email_error']);
            return;
        }

        try {
            $sent = Yii::$app->mailer
                ->compose(['html' => 'notification'], [
                    'notification' => $notification,
                    'application' => $application,
                    'post' => $post,
                    'appUrl' => rtrim($config['app_url'], '/'),
                    'messageExcerpt' => self::messageExcerpt($notification, $application),
                ])
                ->setFrom([$config['mail']['from'] => $config['mail']['from_name']])
                ->setTo($recipient->email)
                ->setSubject(self::subject($notification->type, $post->title))
                ->send();

            if ($sent) {
                $notification->email_sent_at = date('Y-m-d H:i:s');
                $notification->email_error = null;
                $notification->save(false, ['email_sent_at', 'email_error']);
            } else {
                $notification->email_error = 'mailer returned false';
                $notification->save(false, ['email_error']);
            }
        } catch (\Throwable $exception) {
            $notification->email_error = mb_substr($exception->getMessage(), 0, 255);
            $notification->save(false, ['email_error']);
            Yii::error('Notification email failed: ' . $exception->getMessage(), 'notification');
        }
    }

    private static function deliverLine(Notification $notification, array $config, $post, $recipient)
    {
        $client = new LineClient([
            'channelId' => $config['line']['channel_id'],
            'channelSecret' => $config['line']['channel_secret'],
            'accessToken' => $config['line']['access_token'],
            'redirectUri' => $config['line']['redirect_uri'],
        ]);

        $link = rtrim($config['app_url'], '/') . '/posts/' . (int)$post->id;
        $error = $client->push(
            $recipient->line_user_id,
            self::lineText($notification->type, $post->title, $link)
        );

        if ($error === null) {
            $notification->line_sent_at = date('Y-m-d H:i:s');
            $notification->line_error = null;
            $notification->save(false, ['line_sent_at', 'line_error']);
        } else {
            $notification->line_error = mb_substr($error, 0, 255);
            $notification->save(false, ['line_error']);
        }
    }

    private static function messageExcerpt(Notification $notification, Application $application)
    {
        if ($notification->type !== Notification::TYPE_RECEIVED) {
            return null;
        }
        return mb_substr(trim($application->message), 0, 200);
    }

    private static function subject($type, $title)
    {
        return match ($type) {
            Notification::TYPE_RECEIVED => "มีผู้สมัครใหม่ในประกาศ “{$title}”",
            Notification::TYPE_ACCEPTED => "ใบสมัครของคุณได้รับการตอบรับ — “{$title}”",
            default => "ผลการพิจารณาใบสมัคร — “{$title}”",
        };
    }

    private static function lineText($type, $title, $link)
    {
        return match ($type) {
            Notification::TYPE_RECEIVED => "📬 มีผู้สมัครใหม่\nประกาศ: {$title}\n{$link}",
            Notification::TYPE_ACCEPTED => "🎉 ใบสมัครได้รับการตอบรับ\nประกาศ: {$title}\n{$link}",
            default => "📄 ผลการพิจารณาใบสมัคร\nประกาศ: {$title}\n{$link}",
        };
    }
}
