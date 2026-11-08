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
        $request = Yii::$app->request;
        $query = Post::find()->with(['owner', 'skills']);

        $category = (string)$request->getQueryParam('category', '');
        if (in_array($category, ['course', 'contest', 'personal'], true)) {
            $query->andWhere(['post.category' => $category]);
        }

        $keyword = trim((string)$request->getQueryParam('q', ''));
        if ($keyword !== '') {
            $query->andWhere(['or',
                ['like', 'post.title', $keyword],
                ['like', 'post.description', $keyword],
            ]);
        }

        $skillIds = $this->querySkillIds();
        if ($skillIds !== []) {
            $query->innerJoin('post_skill ps', 'ps.post_id = post.id')
                ->andWhere(['ps.skill_id' => $skillIds])
                ->distinct();
        }

        if ($request->getQueryParam('include_closed') !== '1') {
            $query->andWhere(['post.status' => 'open'])
                ->andWhere(['>=', 'post.deadline', date('Y-m-d')]);
        }

        $viewer = Yii::$app->user->getIdentity();
        return array_map(static function ($post) use ($viewer) {
            return $post->toApiArray($viewer);
        }, $query->orderBy(['post.id' => SORT_DESC])->all());
    }

    private function querySkillIds()
    {
        $ids = [];
        $param = Yii::$app->request->getQueryParam('skill');
        if (is_array($param)) {
            $ids = $param;
        } elseif (is_string($param) && $param !== '') {
            $ids = explode(',', $param);
        }

        // ponytail: support repeated ?skill=1&skill=2 which PHP collapses to the last value.
        preg_match_all('/(?:^|&)skill(?:\[\])?=([^&]*)/', Yii::$app->request->getQueryString(), $matches);
        foreach ($matches[1] as $value) {
            $ids[] = urldecode($value);
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn($id) => $id > 0
        )));
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
