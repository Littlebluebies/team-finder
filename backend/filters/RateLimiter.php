<?php

namespace app\filters;

use Yii;
use yii\web\TooManyRequestsHttpException;

/**
 * Fixed-window rate limiter keyed by route + client IP.
 * // ponytail: file-cache counter, single server only; switch to Redis + X-Forwarded-For when behind a proxy.
 */
class RateLimiter
{
    public static function check($route)
    {
        $config = Yii::$app->params['rateLimit'] ?? [];
        $window = (int)($config['window'] ?? 60);
        $authRoutes = $config['auth_routes'] ?? [];
        $limit = in_array($route, $authRoutes, true)
            ? (int)($config['auth'] ?? 5)
            : (int)($config['default'] ?? 120);

        $key = 'ratelimit-' . sha1($route . '|' . Yii::$app->request->getUserIP());
        $count = Yii::$app->cache->get($key);
        $count = $count === false ? 1 : (int)$count + 1;
        Yii::$app->cache->set($key, $count, $window);

        if ($count > $limit) {
            Yii::$app->response->headers->set('Retry-After', (string)$window);
            throw new TooManyRequestsHttpException('Rate limit exceeded.');
        }
    }
}
