<?php

namespace app\controllers;

use Yii;
use app\models\Application;
use app\models\Post;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\VerbFilter;
use yii\rest\Controller;
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class ApplicationController extends Controller
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
                'create' => ['POST'],
                'index' => ['GET'],
                'update' => ['PATCH'],
            ],
        ];
        return $behaviors;
    }

    public function actionCreate($id)
    {
        $post = $this->findPost($id);
        $user = Yii::$app->user->getIdentity();

        $application = new Application();
        $application->post_id = $post->id;
        $application->user_id = $user->id;
        $application->status = 'pending';

        $body = Yii::$app->request->getBodyParams();
        $application->message = is_array($body) && isset($body['message']) ? $body['message'] : null;

        if ((int)$post->owner_id === (int)$user->id) {
            $application->addError('post_id', 'You cannot apply to your own post.');
            return $application;
        }
        if ($post->status !== 'open') {
            $application->addError('post_id', 'This post is closed.');
            return $application;
        }
        if (Application::find()->where(['post_id' => $post->id, 'user_id' => $user->id])->exists()) {
            throw new ConflictHttpException('You already applied to this post.');
        }
        if (!$application->validate()) {
            return $application;
        }

        $application->save(false);
        Yii::$app->response->statusCode = 201;
        return $application->toApiArray(false);
    }

    public function actionIndex($id)
    {
        $post = $this->findPost($id);
        if ((int)$post->owner_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Only the post owner can view applications.');
        }

        return array_map(static function ($application) {
            return $application->toApiArray($application->status === 'accepted');
        }, Application::find()
            ->with('applicant')
            ->where(['post_id' => $post->id])
            ->orderBy(['application.id' => SORT_ASC])
            ->all());
    }

    public function actionUpdate($id)
    {
        $application = Application::find()
            ->with(['post', 'applicant'])
            ->where(['id' => (int)$id])
            ->one();
        if ($application === null) {
            throw new NotFoundHttpException();
        }
        if ((int)$application->post->owner_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Only the post owner can update applications.');
        }

        $body = Yii::$app->request->getBodyParams();
        $status = is_array($body) && isset($body['status']) ? $body['status'] : null;
        if (!in_array($status, ['accepted', 'rejected'], true)) {
            $application->addError('status', 'Status must be accepted or rejected.');
            return $application;
        }
        if ($application->status !== 'pending') {
            throw new ConflictHttpException('Application has already been processed.');
        }

        $post = $application->post;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $application->status = $status;
            $application->save(false);

            if ($status === 'accepted') {
                $accepted = (int)Application::find()
                    ->where(['post_id' => $post->id, 'status' => 'accepted'])
                    ->count();
                if ($accepted >= (int)$post->slots) {
                    $post->status = 'closed';
                    $post->save(false);
                }
            }

            $transaction->commit();
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }

        return $application->toApiArray($status === 'accepted');
    }

    private function findPost($id)
    {
        $post = Post::find()->where(['id' => (int)$id])->one();
        if ($post === null) {
            throw new NotFoundHttpException();
        }
        return $post;
    }
}
