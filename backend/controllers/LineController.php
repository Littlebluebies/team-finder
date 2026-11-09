<?php

namespace app\controllers;

use Yii;
use app\services\LineClient;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\VerbFilter;
use yii\rest\Controller;
use yii\web\UnprocessableEntityHttpException;

class LineController extends Controller
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
                'authorize-url' => ['GET'],
                'link' => ['POST'],
                'unlink' => ['DELETE'],
            ],
        ];
        return $behaviors;
    }

    public function actionAuthorizeUrl()
    {
        $client = $this->client();
        if (!$client->isLoginConfigured()) {
            Yii::$app->response->statusCode = 503;
            return ['message' => 'LINE login is not configured.'];
        }

        $user = Yii::$app->user->getIdentity();
        $state = Yii::$app->security->generateRandomString(64);
        $user->line_link_state_hash = hash('sha256', $state);
        $user->line_link_state_expires_at = date('Y-m-d H:i:s', time() + 600);
        $user->save(false, ['line_link_state_hash', 'line_link_state_expires_at']);

        return ['url' => $client->buildAuthorizeUrl($state)];
    }

    public function actionLink()
    {
        $body = Yii::$app->request->getBodyParams();
        $code = is_array($body) ? ($body['code'] ?? null) : null;
        $state = is_array($body) ? ($body['state'] ?? null) : null;

        $user = Yii::$app->user->getIdentity();
        if (!is_string($code) || $code === '' || !is_string($state) || $state === '') {
            throw new UnprocessableEntityHttpException('code and state are required.');
        }
        if (
            $user->line_link_state_hash === null
            || $user->line_link_state_expires_at === null
            || strtotime($user->line_link_state_expires_at) < time()
            || !hash_equals($user->line_link_state_hash, hash('sha256', $state))
        ) {
            throw new UnprocessableEntityHttpException('Invalid or expired LINE state.');
        }

        $client = $this->client();
        if (!$client->isLoginConfigured()) {
            Yii::$app->response->statusCode = 503;
            return ['message' => 'LINE login is not configured.'];
        }

        $lineUserId = $client->exchangeCode($code);
        if ($lineUserId === null) {
            Yii::$app->response->statusCode = 502;
            return ['message' => 'LINE authorization failed.'];
        }

        $user->line_user_id = $lineUserId;
        $user->line_linked_at = date('Y-m-d H:i:s');
        $user->line_link_state_hash = null;
        $user->line_link_state_expires_at = null;
        $user->save(false, [
            'line_user_id',
            'line_linked_at',
            'line_link_state_hash',
            'line_link_state_expires_at',
        ]);

        return ['linked' => true, 'linked_at' => $user->line_linked_at];
    }

    public function actionUnlink()
    {
        $user = Yii::$app->user->getIdentity();
        $user->line_user_id = null;
        $user->line_linked_at = null;
        $user->save(false, ['line_user_id', 'line_linked_at']);

        return ['linked' => false];
    }

    private function client()
    {
        $config = Yii::$app->params['notifications']['line'];
        return new LineClient([
            'channelId' => $config['channel_id'],
            'channelSecret' => $config['channel_secret'],
            'accessToken' => $config['access_token'],
            'redirectUri' => $config['redirect_uri'],
        ]);
    }
}
