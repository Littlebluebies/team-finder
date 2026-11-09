<?php

namespace app\commands;

use Yii;
use app\models\Notification;
use app\services\NotificationService;
use yii\console\Controller;
use yii\console\ExitCode;

class NotificationController extends Controller
{
    public $limit = 100;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['limit']);
    }

    public function actionRetry()
    {
        $config = Yii::$app->params['notifications'] ?? [];
        if (!empty($config['dry_run'])) {
            $this->stdout("dry_run is enabled; nothing to retry.\n");
            return ExitCode::OK;
        }

        $notifications = Notification::find()
            ->with(['application.post', 'application.applicant', 'user'])
            ->where(['<', 'attempts', 5])
            ->andWhere(['>', 'created_at', date('Y-m-d H:i:s', time() - 7 * 86400)])
            ->andWhere(['or', ['email_sent_at' => null], ['line_sent_at' => null]])
            ->orderBy(['notification.id' => SORT_ASC])
            ->limit((int)$this->limit)
            ->all();

        $retried = 0;
        $skipped = 0;
        foreach ($notifications as $notification) {
            $needsEmail = $notification->email_sent_at === null;
            $needsLine = $notification->line_sent_at === null
                && $notification->user->line_user_id !== null;
            if (!$needsEmail && !$needsLine) {
                $skipped++;
                continue;
            }

            $notification->attempts = (int)$notification->attempts + 1;
            $notification->save(false, ['attempts']);
            NotificationService::deliver($notification);
            $retried++;
        }

        $this->stdout("Retried {$retried} notification(s); skipped {$skipped}.\n");
        return ExitCode::OK;
    }
}
