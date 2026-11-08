<?php

namespace app\controllers;

use Yii;
use app\models\Application;
use app\models\Post;
use app\models\ProfileForm;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\VerbFilter;
use yii\rest\Controller;

class MeController extends Controller
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
                'view' => ['GET'],
                'update' => ['PUT'],
                'posts' => ['GET'],
                'applications' => ['GET'],
            ],
        ];
        return $behaviors;
    }

    public function actionView()
    {
        return Yii::$app->user->getIdentity()->toAuthArray();
    }

    public function actionUpdate()
    {
        $form = new ProfileForm();
        $form->load(Yii::$app->request->getBodyParams(), '');
        $user = $form->update(Yii::$app->user->getIdentity());
        if ($user === null) {
            return $form;
        }
        return $user->toAuthArray();
    }

    public function actionPosts()
    {
        $user = Yii::$app->user->getIdentity();
        $posts = Post::find()
            ->with(['owner', 'skills'])
            ->where(['owner_id' => $user->id])
            ->orderBy(['post.id' => SORT_DESC])
            ->all();

        return array_map(static function ($post) use ($user) {
            $data = $post->toApiArray($user);
            $data['pending_count'] = (int)Application::find()
                ->where(['post_id' => $post->id, 'status' => 'pending'])
                ->count();
            return $data;
        }, $posts);
    }

    public function actionApplications()
    {
        $user = Yii::$app->user->getIdentity();
        return array_map(static function ($application) {
            return $application->toMyArray();
        }, Application::find()
            ->with(['post.owner'])
            ->where(['user_id' => $user->id])
            ->orderBy(['application.id' => SORT_DESC])
            ->all());
    }
}
