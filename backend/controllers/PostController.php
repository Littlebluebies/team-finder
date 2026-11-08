<?php

namespace app\controllers;

use Yii;
use app\models\Post;
use app\models\PostForm;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\VerbFilter;
use yii\rest\Controller;
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
            ],
        ];
        return $behaviors;
    }

    public function actionIndex()
    {
        return array_map(static function ($post) {
            return $post->toApiArray();
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
        return $post->toApiArray();
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
        return $post->toApiArray();
    }
}
