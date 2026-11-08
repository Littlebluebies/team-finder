<?php

namespace app\controllers;

use Yii;
use app\models\LoginForm;
use app\models\RegisterForm;
use yii\filters\VerbFilter;
use yii\rest\Controller;
use yii\web\UnauthorizedHttpException;

class AuthController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'register' => ['POST'],
                    'login' => ['POST'],
                ],
            ],
        ]);
    }

    public function actionRegister()
    {
        $form = new RegisterForm();
        $form->load(Yii::$app->request->getBodyParams(), '');
        $result = $form->register();
        if ($result === null) {
            return $form;
        }

        Yii::$app->response->statusCode = 201;
        return $result;
    }

    public function actionLogin()
    {
        $form = new LoginForm();
        $form->load(Yii::$app->request->getBodyParams(), '');
        if (!$form->validate()) {
            return $form;
        }

        $user = $form->authenticate();
        if ($user === null) {
            throw new UnauthorizedHttpException('Invalid email or password.');
        }

        $token = $user->issueAccessToken();
        $user->save(false, ['access_token_hash', 'access_token_expires_at']);
        return ['token' => $token, 'user' => $user->toAuthArray()];
    }
}
