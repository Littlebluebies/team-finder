<?php

namespace app\controllers;

use Yii;
use app\models\Notification;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\VerbFilter;
use yii\rest\Controller;
use yii\web\UnprocessableEntityHttpException;

class NotificationController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
        ];
        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'index' => ['GET'],
                'update' => ['PATCH'],
            ],
        ];
        return $behaviors;
    }

    public function actionIndex()
    {
        $userId = (int)Yii::$app->user->id;
        $items = Notification::find()
            ->with(['application.post.owner', 'application.applicant'])
            ->where(['user_id' => $userId])
            ->orderBy(['notification.id' => SORT_DESC])
            ->limit(50)
            ->all();

        return [
            'items' => array_map(static function ($notification) {
                return $notification->toApiArray();
            }, $items),
            'unread' => (int)Notification::find()
                ->where(['user_id' => $userId, 'read_at' => null])
                ->count(),
        ];
    }

    public function actionUpdate()
    {
        $userId = (int)Yii::$app->user->id;
        $body = Yii::$app->request->getBodyParams();
        $ids = is_array($body) ? ($body['ids'] ?? null) : null;

        $condition = ['user_id' => $userId, 'read_at' => null];
        if ($ids !== null) {
            if (!is_array($ids) || $ids === []) {
                throw new UnprocessableEntityHttpException('ids must be a non-empty array.');
            }
            $clean = [];
            foreach ($ids as $id) {
                if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                    throw new UnprocessableEntityHttpException('ids must contain integers.');
                }
                $clean[] = (int)$id;
            }
            $condition['id'] = $clean;
        }

        Notification::updateAll(['read_at' => date('Y-m-d H:i:s')], $condition);

        return [
            'unread' => (int)Notification::find()
                ->where(['user_id' => $userId, 'read_at' => null])
                ->count(),
        ];
    }
}
