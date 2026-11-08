<?php

namespace app\controllers;

use Yii;
use app\models\Post;
use app\models\PostForm;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\VerbFilter;
use yii\rest\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class PostController extends Controller
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
                'view' => ['GET'],
                'create' => ['POST'],
                'close' => ['PATCH'],
            ],
        ];
        return $behaviors;
    }

    public function actionIndex()
    {
        $viewer = Yii::$app->user->getIdentity();
        return array_map(static function ($post) use ($viewer) {
            return $post->toApiArray($viewer);
        }, Post::find()->with(['owner', 'skills'])->orderBy(['post.id' => SORT_DESC])->all());
    }

    public function actionView($id)
    {
        $post = Post::find()
            ->with(['owner', 'skills'])
            ->where(['post.id' => (int)$id])
            ->one();
        if ($post === null) {
            throw new NotFoundHttpException();
        }
        return $post->toApiArray(Yii::$app->user->getIdentity(), true);
    }

    public function actionCreate()
    {
        $form = new PostForm();
        $form->load(Yii::$app->request->getBodyParams(), '');
        $post = $form->create(Yii::$app->user->getIdentity());
        if ($post === null) {
            return $form;
        }

        Yii::$app->response->statusCode = 201;
        return $post->toApiArray(Yii::$app->user->getIdentity());
    }

    public function actionClose($id)
    {
        $post = Post::find()->where(['id' => (int)$id])->one();
        if ($post === null) {
            throw new NotFoundHttpException();
        }
        if ((int)$post->owner_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException('Only the post owner can close this post.');
        }

        $body = Yii::$app->request->getBodyParams();
        $status = is_array($body) && isset($body['status']) ? $body['status'] : null;
        if ($status !== 'closed') {
            $post->addError('status', 'Status must be closed.');
            return $post;
        }

        $post->status = 'closed';
        $post->save(false);
        return $post->toApiArray(Yii::$app->user->getIdentity());
    }
}
