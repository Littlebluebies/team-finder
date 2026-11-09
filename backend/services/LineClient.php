<?php

namespace app\services;

use yii\base\BaseObject;
use yii\helpers\Json;

class LineClient extends BaseObject
{
    public $channelId = '';
    public $channelSecret = '';
    public $accessToken = '';
    public $redirectUri = '';

    public function isLoginConfigured()
    {
        return $this->channelId !== '' && $this->channelSecret !== '' && $this->redirectUri !== '';
    }

    public function isPushConfigured()
    {
        return $this->accessToken !== '';
    }

    public function buildAuthorizeUrl($state)
    {
        return 'https://access.line.me/oauth2/v2.1/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->channelId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'scope' => 'profile openid',
            'bot_prompt' => 'aggressive',
        ]);
    }

    public function exchangeCode($code)
    {
        [$status, $body] = $this->postForm('https://api.line.me/oauth2/v2.1/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->channelId,
            'client_secret' => $this->channelSecret,
        ]);
        if ($status !== 200) {
            return null;
        }

        $payload = Json::decode($body);
        $idToken = $payload['id_token'] ?? null;
        if (!is_string($idToken)) {
            return null;
        }

        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return null;
        }
        $segment = strtr($parts[1], '-_', '+/');
        $segment .= str_repeat('=', (4 - strlen($segment) % 4) % 4);
        $decoded = base64_decode($segment, true);
        if ($decoded === false) {
            return null;
        }
        $claims = Json::decode($decoded);
        return isset($claims['sub']) && is_string($claims['sub']) ? $claims['sub'] : null;
    }

    public function push($userId, $text)
    {
        if (!$this->isPushConfigured()) {
            return 'LINE push is not configured';
        }

        [$status, $body] = $this->postJson('https://api.line.me/v2/bot/message/push', [
            'to' => $userId,
            'messages' => [['type' => 'text', 'text' => $text]],
        ]);
        if ($status === 200) {
            return null;
        }
        return $this->describeError($status, $body);
    }

    private function describeError($status, $body)
    {
        $decoded = json_decode($body, true);
        $message = is_array($decoded) && isset($decoded['message'])
            ? $decoded['message']
            : 'request failed';
        return "LINE {$status}: {$message}";
    }

    private function postForm($url, array $payload)
    {
        return $this->send($url, [
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
    }

    private function postJson($url, array $payload)
    {
        return $this->send($url, [
            CURLOPT_POSTFIELDS => Json::encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
            ],
        ]);
    }

    private function send($url, array $options)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $options + [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return [0, $error];
        }
        return [$status, (string)$body];
    }
}
