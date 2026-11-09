# Notification System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** แจ้งเตือน 3 เหตุการณ์ (สมัครใหม่ / รับ / ปฏิเสธ) ผ่าน in-app, email และ LINE โดยไม่เปลี่ยน response ของ API เดิมและไม่ขยายการเปิดเผย LINE ID

**Architecture:** เขียนแถว `notification` หลัง commit ของธุรกรรมหลัก แล้วส่ง email/LINE แบบ best-effort พร้อมบันทึกผลรายช่องทาง; retry ผ่าน console command; LINE ใช้ OAuth code exchange เก็บ Messaging API `userId` ใน `user.line_user_id`

**Tech Stack:** PHP 8.2, Yii2 2.0.53, MySQL/MariaDB, `yiisoft/yii2-symfonymailer`, React 19, Vite, CSS ธรรมดา

**Spec:** `docs/superpowers/specs/2026-11-09-notifications-design.md`

## Global Constraints

- ห้ามเพิ่ม dependency อื่นนอกจาก `yiisoft/yii2-symfonymailer`; frontend ห้ามเพิ่ม npm package
- ทุก endpoint ใหม่ต้องใช้ `HttpBearerAuth`; 401 เมื่อไม่มี/ผิด token
- ห้ามคืน `line_user_id`, `line_link_state_hash`, หรือ state ดิบออก API ทุกกรณี
- ห้ามมี `line_id` ของผู้ใช้อื่นใน notification payload, email, LINE message, หรือ log
- `docs/api.md` ต้องอัปเดตใน change set เดียวกับ API
- ข้อความ UI เป็นภาษาไทย; ใช้ token/สไตล์จาก `DESIGN.md`; CSS ธรรมดา
- ห้าม commit/push ก่อนได้คำยืนยันสดใหม่จากผู้ใช้ทุกครั้ง (AGENTS.md)
- ห้าม commit `backend/config/notifications-local.php` หรือ secret ใด ๆ
- Timezone `Asia/Bangkok`; รูปแบบเวลา `YYYY-MM-DD HH:MM:SS`

---

### Task 1: Dependency + Notification Config

**Files:**
- Modify: `backend/composer.json` (ผ่าน `composer require`)
- Create: `backend/config/notifications.php`
- Create: `backend/config/notifications-local.example.php`
- Modify: `backend/config/web.php`
- Modify: `backend/config/console.php`
- Modify: `.gitignore`

**Interfaces:**
- Produces: `Yii::$app->params['notifications']` เป็น array มี key `app_url`, `dry_run`, `mail.{dsn,from,from_name}`, `line.{channel_id,channel_secret,access_token,redirect_uri}`
- Produces: component `mailer` (`yii\symfonymailer\Mailer`) ใช้ได้ทั้ง web และ console

- [ ] **Step 1: ติดตั้ง mailer**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\backend
composer require yiisoft/yii2-symfonymailer:^2.0
```

Expected: `composer.json` มี `"yiisoft/yii2-symfonymailer": "^2.0"` และ `vendor/yiisoft/yii2-symfonymailer` ถูกสร้าง

- [ ] **Step 2: สร้าง config กลาง**

Create `backend/config/notifications.php`:
```php
<?php

$local = is_file(__DIR__ . '/notifications-local.php')
    ? require __DIR__ . '/notifications-local.php'
    : [];

return array_replace_recursive([
    'app_url' => 'http://localhost:5173',
    'dry_run' => true,
    'mail' => [
        'dsn' => '',
        'from' => 'no-reply@team-finder.local',
        'from_name' => 'Team Finder',
    ],
    'line' => [
        'channel_id' => '',
        'channel_secret' => '',
        'access_token' => '',
        'redirect_uri' => 'http://localhost:5173/line/callback',
    ],
], $local);
```

Create `backend/config/notifications-local.example.php`:
```php
<?php

return [
    // คัดลอกเป็น notifications-local.php แล้วใส่ค่าจริง (ไฟล์นั้นถูก gitignore)
    'app_url' => 'http://localhost:5173',
    'dry_run' => false,
    'mail' => [
        'dsn' => 'smtp://USER:PASSWORD@smtp.example.com:587',
        'from' => 'no-reply@example.com',
        'from_name' => 'Team Finder',
    ],
    'line' => [
        // LINE Login channel
        'channel_id' => '2000000000',
        'channel_secret' => 'REPLACE_ME',
        // Messaging API channel access token (คนละตัวกับ channel secret)
        'access_token' => 'REPLACE_ME',
        'redirect_uri' => 'http://localhost:5173/line/callback',
    ],
];
```

- [ ] **Step 3: ignore ไฟล์ local**

แก้ `.gitignore` ต่อจาก `backend/config/db-local.php`:
```gitignore
backend/config/db-local.php
backend/config/notifications-local.php
```

- [ ] **Step 4: เพิ่ม mailer + params ใน web config**

ใน `backend/config/web.php` หลัง `<?php` เพิ่ม:
```php
$notifications = require __DIR__ . '/notifications.php';

$mailer = [
    'class' => yii\symfonymailer\Mailer::class,
    'viewPath' => '@app/views/mail',
    'useFileTransport' => false,
];
if ($notifications['mail']['dsn'] !== '') {
    $mailer['transport'] = ['dsn' => $notifications['mail']['dsn']];
}
```

ใน `components` เพิ่มก่อน `'log'`:
```php
        'mailer' => $mailer,
```

หลังปิด `'components' => [...]` เพิ่ม:
```php
    'params' => [
        'notifications' => $notifications,
    ],
```

- [ ] **Step 5: เพิ่ม mailer + params ใน console config**

ใน `backend/config/console.php` หลัง `<?php` เพิ่ม:
```php
$notifications = require __DIR__ . '/notifications.php';

$mailer = [
    'class' => yii\symfonymailer\Mailer::class,
    'viewPath' => '@app/views/mail',
    'useFileTransport' => false,
];
if ($notifications['mail']['dsn'] !== '') {
    $mailer['transport'] = ['dsn' => $notifications['mail']['dsn']];
}
```

ใน `components` เพิ่ม:
```php
        'mailer' => $mailer,
```

หลัง `'components'` เพิ่ม:
```php
    'params' => [
        'notifications' => $notifications,
    ],
```

- [ ] **Step 6: ตรวจว่า config โหลดได้**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\backend
php -r 'require "vendor/autoload.php"; require "vendor/yiisoft/yii2/Yii.php"; new yii\console\Application(require "config/console.php"); var_export(Yii::$app->params["notifications"]["dry_run"]);'
```

Expected: `true`

- [ ] **Step 7: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add backend/composer.json backend/composer.lock backend/config/notifications.php backend/config/notifications-local.example.php backend/config/web.php backend/config/console.php .gitignore
git commit -m "feat(backend): add notification config and mailer"
```

---

### Task 2: Migration + Notification Model

**Files:**
- Create: `backend/migrations/m261109_000001_add_notifications.php`
- Create: `backend/models/Notification.php`
- Modify: `backend/models/User.php:67-82`
- Test: `backend/tests/notification_smoke.php` (สร้างภายหลังใน Task 8)

**Interfaces:**
- Produces: ตาราง `notification` และคอลัมน์ `user.line_user_id`, `user.line_linked_at`, `user.line_link_state_hash`, `user.line_link_state_expires_at`
- Produces: `app\models\Notification` พร้อม constants `TYPE_RECEIVED`, `TYPE_ACCEPTED`, `TYPE_REJECTED` และ `toApiArray(): array`
- Produces: `User::toAuthArray()` มี key `line_linked` (bool) และ `line_linked_at` (string|null)

- [ ] **Step 1: สร้าง migration**

Create `backend/migrations/m261109_000001_add_notifications.php`:
```php
<?php

use yii\db\Migration;

class m261109_000001_add_notifications extends Migration
{
    public function safeUp()
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->addColumn('user', 'line_user_id', $this->string(64)->null());
        $this->addColumn('user', 'line_linked_at', $this->dateTime()->null());
        $this->addColumn('user', 'line_link_state_hash', $this->char(64)->null());
        $this->addColumn('user', 'line_link_state_expires_at', $this->dateTime()->null());
        $this->createIndex('uq_user_line_user_id', 'user', 'line_user_id', true);

        $this->createTable('notification', [
            'id' => $this->primaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'type' => $this->string(32)->notNull(),
            'application_id' => $this->integer()->unsigned()->notNull(),
            'read_at' => $this->dateTime()->null(),
            'email_sent_at' => $this->dateTime()->null(),
            'line_sent_at' => $this->dateTime()->null(),
            'email_error' => $this->string(255)->null(),
            'line_error' => $this->string(255)->null(),
            'attempts' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $options);

        $this->createIndex('uq_notification_event', 'notification', ['user_id', 'type', 'application_id'], true);
        $this->createIndex('idx_notification_user', 'notification', ['user_id', 'read_at']);
        $this->addCheck(
            'ck_notification_type',
            'notification',
            "type IN ('application_received','application_accepted','application_rejected')"
        );
        $this->addForeignKey('fk_notification_user', 'notification', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_notification_application', 'notification', 'application_id', 'application', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('notification');
        $this->dropIndex('uq_user_line_user_id', 'user');
        $this->dropColumn('user', 'line_link_state_expires_at');
        $this->dropColumn('user', 'line_link_state_hash');
        $this->dropColumn('user', 'line_linked_at');
        $this->dropColumn('user', 'line_user_id');
    }
}
```

- [ ] **Step 2: รัน migration บน DB ว่าง**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\backend
php yii migrate --interactive=0
```

Expected: `m261109_000001_add_notifications` ถูก apply โดยไม่มี error

- [ ] **Step 3: ตรวจ down/up**

Run:
```powershell
php yii migrate/down 1 --interactive=0
php yii migrate --interactive=0
```

Expected: down ลบตารางและคอลัมน์ได้, up สร้างกลับได้

- [ ] **Step 4: สร้าง model**

Create `backend/models/Notification.php`:
```php
<?php

namespace app\models;

use yii\db\ActiveRecord;

class Notification extends ActiveRecord
{
    public const TYPE_RECEIVED = 'application_received';
    public const TYPE_ACCEPTED = 'application_accepted';
    public const TYPE_REJECTED = 'application_rejected';

    public static function tableName()
    {
        return 'notification';
    }

    public function getApplication()
    {
        return $this->hasOne(Application::class, ['id' => 'application_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function toApiArray()
    {
        $application = $this->application;
        $post = $application->post;
        $actor = $this->type === self::TYPE_RECEIVED
            ? $application->applicant
            : $post->owner;

        return [
            'id' => (int)$this->id,
            'type' => $this->type,
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at,
            'post' => [
                'id' => (int)$post->id,
                'title' => $post->title,
            ],
            'actor' => [
                'display_name' => $actor->display_name,
            ],
        ];
    }
}
```

- [ ] **Step 5: เพิ่ม `line_linked` และ `line_linked_at` ใน `User::toAuthArray()`**

ใน `backend/models/User.php` หลังบรรทัด `'line_id' => $this->line_id,` เพิ่ม:
```php
            'line_linked' => $this->line_user_id !== null,
            'line_linked_at' => $this->line_linked_at,
```

- [ ] **Step 6: ตรวจว่า model โหลดได้**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\backend
php -r "require 'vendor/autoload.php'; require 'vendor/yiisoft/yii2/Yii.php'; new yii\console\Application(require 'config/console.php'); echo app\models\Notification::TYPE_ACCEPTED;"
```

Expected: `application_accepted`

- [ ] **Step 7: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add backend/migrations/m261109_000001_add_notifications.php backend/models/Notification.php backend/models/User.php
git commit -m "feat(backend): add notification schema and model"
```

---

### Task 3: LINE Client + LINE Endpoints

**Files:**
- Create: `backend/services/LineClient.php`
- Create: `backend/controllers/LineController.php`
- Modify: `backend/config/web.php` (url rules)

**Interfaces:**
- Consumes: `Yii::$app->params['notifications']['line']`
- Produces: `LineClient::buildAuthorizeUrl(string $state): string`
- Produces: `LineClient::exchangeCode(string $code): ?string`
- Produces: `LineClient::push(string $userId, string $text): ?string` (null = สำเร็จ, string = error)
- Produces: routes `GET /api/me/line/authorize-url`, `POST /api/me/line`, `DELETE /api/me/line`

- [ ] **Step 1: สร้าง LineClient**

Create `backend/services/LineClient.php`:
```php
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
```

- [ ] **Step 2: สร้าง LineController**

Create `backend/controllers/LineController.php`:
```php
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
```

- [ ] **Step 3: เพิ่ม routes**

ใน `backend/config/web.php` หลัง `'GET api/me/applications' => 'me/applications',` เพิ่ม:
```php
                'GET api/notifications' => 'notification/index',
                'PATCH api/notifications' => 'notification/update',
                'GET api/me/line/authorize-url' => 'line/authorize-url',
                'POST api/me/line' => 'line/link',
                'DELETE api/me/line' => 'line/unlink',
```

- [ ] **Step 4: รีสตาร์ท backend แล้วตรวจ 401/503**

หยุด `php yii serve` เดิมแล้วรันใหม่:
```powershell
cd C:\Users\mooab\Projects\team-finder\backend
php yii serve --port=8080
```

Run:
```powershell
curl.exe -s -o NUL -w "%{http_code}" http://localhost:8080/api/me/line/authorize-url
```

Expected: `401`

- [ ] **Step 5: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add backend/services/LineClient.php backend/controllers/LineController.php backend/config/web.php
git commit -m "feat(backend): add LINE account linking"
```

---

### Task 4: Notification Service + Email Template

**Files:**
- Create: `backend/services/NotificationService.php`
- Create: `backend/views/mail/notification.php`

**Interfaces:**
- Consumes: `LineClient::push()` จาก Task 3
- Consumes: `Notification` constants จาก Task 2
- Produces: `NotificationService::dispatch(Application $application, string $type): void`
- Produces: `NotificationService::deliver(Notification $notification): void`

- [ ] **Step 1: สร้าง service**

Create `backend/services/NotificationService.php`:
```php
<?php

namespace app\services;

use Yii;
use app\models\Application;
use app\models\Notification;
use yii\db\IntegrityException;

class NotificationService
{
    public static function dispatch(Application $application, $type)
    {
        try {
            $notification = self::store($application, $type);
            if ($notification !== null) {
                self::deliver($notification);
            }
        } catch (\Throwable $exception) {
            Yii::error('Notification dispatch failed: ' . $exception->getMessage(), 'notification');
        }
    }

    public static function deliver(Notification $notification)
    {
        $config = Yii::$app->params['notifications'] ?? [];
        $application = $notification->application;
        $post = $application->post;
        $recipient = $notification->user;

        if (!empty($config['dry_run'])) {
            if ($notification->email_sent_at === null) {
                $notification->email_sent_at = date('Y-m-d H:i:s');
            }
            if ($notification->line_sent_at === null && $recipient->line_user_id !== null) {
                $notification->line_sent_at = date('Y-m-d H:i:s');
            }
            $notification->save(false, ['email_sent_at', 'line_sent_at']);
            return;
        }

        if ($notification->email_sent_at === null) {
            self::deliverEmail($notification, $config, $application, $post, $recipient);
        }
        if ($notification->line_sent_at === null && $recipient->line_user_id !== null) {
            self::deliverLine($notification, $config, $post, $recipient);
        }
    }

    private static function store(Application $application, $type)
    {
        $recipientId = $type === Notification::TYPE_RECEIVED
            ? (int)$application->post->owner_id
            : (int)$application->user_id;

        $condition = [
            'user_id' => $recipientId,
            'type' => $type,
            'application_id' => (int)$application->id,
        ];

        $existing = Notification::findOne($condition);
        if ($existing !== null) {
            return $existing;
        }

        $notification = new Notification();
        $notification->setAttributes($condition, false);
        try {
            $notification->save(false);
        } catch (IntegrityException $exception) {
            return Notification::findOne($condition);
        }
        return $notification;
    }

    private static function deliverEmail(Notification $notification, array $config, Application $application, $post, $recipient)
    {
        if ($config['mail']['dsn'] === '') {
            $notification->email_error = 'mail not configured';
            $notification->save(false, ['email_error']);
            return;
        }

        try {
            $sent = Yii::$app->mailer
                ->compose(['html' => 'notification'], [
                    'notification' => $notification,
                    'application' => $application,
                    'post' => $post,
                    'appUrl' => rtrim($config['app_url'], '/'),
                    'messageExcerpt' => self::messageExcerpt($notification, $application),
                ])
                ->setFrom([$config['mail']['from'] => $config['mail']['from_name']])
                ->setTo($recipient->email)
                ->setSubject(self::subject($notification->type, $post->title))
                ->send();

            if ($sent) {
                $notification->email_sent_at = date('Y-m-d H:i:s');
                $notification->email_error = null;
                $notification->save(false, ['email_sent_at', 'email_error']);
            } else {
                $notification->email_error = 'mailer returned false';
                $notification->save(false, ['email_error']);
            }
        } catch (\Throwable $exception) {
            $notification->email_error = mb_substr($exception->getMessage(), 0, 255);
            $notification->save(false, ['email_error']);
            Yii::error('Notification email failed: ' . $exception->getMessage(), 'notification');
        }
    }

    private static function deliverLine(Notification $notification, array $config, $post, $recipient)
    {
        $client = new LineClient([
            'channelId' => $config['line']['channel_id'],
            'channelSecret' => $config['line']['channel_secret'],
            'accessToken' => $config['line']['access_token'],
            'redirectUri' => $config['line']['redirect_uri'],
        ]);

        $link = rtrim($config['app_url'], '/') . '/posts/' . (int)$post->id;
        $error = $client->push(
            $recipient->line_user_id,
            self::lineText($notification->type, $post->title, $link)
        );

        if ($error === null) {
            $notification->line_sent_at = date('Y-m-d H:i:s');
            $notification->line_error = null;
            $notification->save(false, ['line_sent_at', 'line_error']);
        } else {
            $notification->line_error = mb_substr($error, 0, 255);
            $notification->save(false, ['line_error']);
        }
    }

    private static function messageExcerpt(Notification $notification, Application $application)
    {
        if ($notification->type !== Notification::TYPE_RECEIVED) {
            return null;
        }
        return mb_substr(trim($application->message), 0, 200);
    }

    private static function subject($type, $title)
    {
        return match ($type) {
            Notification::TYPE_RECEIVED => "มีผู้สมัครใหม่ในประกาศ “{$title}”",
            Notification::TYPE_ACCEPTED => "ใบสมัครของคุณได้รับการตอบรับ — “{$title}”",
            default => "ผลการพิจารณาใบสมัคร — “{$title}”",
        };
    }

    private static function lineText($type, $title, $link)
    {
        return match ($type) {
            Notification::TYPE_RECEIVED => "📬 มีผู้สมัครใหม่\nประกาศ: {$title}\n{$link}",
            Notification::TYPE_ACCEPTED => "🎉 ใบสมัครได้รับการตอบรับ\nประกาศ: {$title}\n{$link}",
            default => "📄 ผลการพิจารณาใบสมัคร\nประกาศ: {$title}\n{$link}",
        };
    }
}
```

- [ ] **Step 2: สร้าง email template**

Create `backend/views/mail/notification.php`:
```php
<?php
/** @var app\models\Notification $notification */
/** @var app\models\Application $application */
/** @var app\models\Post $post */
/** @var string $appUrl */
/** @var string|null $messageExcerpt */

$title = htmlspecialchars($post->title, ENT_QUOTES, 'UTF-8');
$link = $appUrl . '/posts/' . (int)$post->id;
$actorName = $notification->type === \app\models\Notification::TYPE_RECEIVED
    ? $application->applicant->display_name
    : $post->owner->display_name;
$actor = htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8');
$headline = match ($notification->type) {
    \app\models\Notification::TYPE_RECEIVED => 'มีผู้สมัครใหม่',
    \app\models\Notification::TYPE_ACCEPTED => 'ใบสมัครของคุณได้รับการตอบรับ',
    default => 'ผลการพิจารณาใบสมัคร',
};
$body = match ($notification->type) {
    \app\models\Notification::TYPE_RECEIVED => "{$actor} สมัครเข้าร่วมประกาศของคุณ",
    \app\models\Notification::TYPE_ACCEPTED => "คุณได้รับเลือกให้เข้าร่วมทีมโดย {$actor}",
    default => "ใบสมัครของคุณไม่ผ่านการพิจารณาโดย {$actor}",
};
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body style="margin:0;padding:24px;background:#f7f7f3;color:#16170f;font-family:'Leelawadee UI','Noto Sans Thai',system-ui,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e3e4db;">
    <tr>
      <td style="padding:20px 24px;border-bottom:1px solid #e3e4db;font:700 11px/1 monospace;letter-spacing:0.2em;">
        TEAM FINDER
      </td>
    </tr>
    <tr>
      <td style="padding:28px 24px 8px;">
        <h1 style="margin:0;font-size:22px;line-height:1.3;font-weight:600;"><?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?></h1>
        <p style="margin:12px 0 0;color:#4b4e43;font-size:14px;line-height:1.7;"><?= $body ?></p>
        <p style="margin:16px 0 0;font-size:14px;line-height:1.7;"><strong>ประกาศ:</strong> <?= $title ?></p>
        <?php if ($messageExcerpt !== null && $messageExcerpt !== ''): ?>
          <p style="margin:12px 0 0;padding:12px 14px;background:#f2f3ea;border-left:3px solid #d8f24a;color:#4b4e43;font-size:13px;line-height:1.6;white-space:pre-line;"><?= htmlspecialchars($messageExcerpt, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td style="padding:20px 24px 32px;">
        <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" style="display:inline-block;padding:11px 18px;background:#16170f;color:#ffffff;font-size:13px;font-weight:600;text-decoration:none;">เปิดประกาศ</a>
      </td>
    </tr>
    <tr>
      <td style="padding:14px 24px;border-top:1px solid #e3e4db;color:#6f7266;font-size:11px;line-height:1.5;">
        อีเมลนี้ส่งจาก Team Finder เพราะคุณมีบัญชีในระบบ
      </td>
    </tr>
  </table>
</body>
</html>
```

- [ ] **Step 3: ตรวจ syntax**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\backend
php -l services/NotificationService.php
php -l views/mail/notification.php
```

Expected: `No syntax errors detected` ทั้งสองไฟล์

- [ ] **Step 4: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add backend/services/NotificationService.php backend/views/mail/notification.php
git commit -m "feat(backend): add notification delivery service"
```

---

### Task 5: Wire Notification Triggers

**Files:**
- Modify: `backend/controllers/ApplicationController.php:1-13,64-66,124-132`

**Interfaces:**
- Consumes: `NotificationService::dispatch()` จาก Task 4
- Produces: notification row ถูกสร้างหลัง application ถูกบันทึก/commit

- [ ] **Step 1: เพิ่ม imports**

ใน `backend/controllers/ApplicationController.php` หลัง `use app\models\Application;` เพิ่ม:
```php
use app\models\Notification;
use app\services\NotificationService;
```

- [ ] **Step 2: dispatch หลังสมัครสำเร็จ**

แก้ `actionCreate` จาก:
```php
        $application->save(false);
        Yii::$app->response->statusCode = 201;
        return $application->toApiArray(false);
```
เป็น:
```php
        $application->save(false);
        NotificationService::dispatch($application, Notification::TYPE_RECEIVED);
        Yii::$app->response->statusCode = 201;
        return $application->toApiArray(false);
```

- [ ] **Step 3: dispatch หลัง commit ของ accept/reject**

ไม่ต้องแก้บล็อก `try/catch` เดิม ให้เพิ่มโค้ดหลังบล็อกนั้น ก่อน `return $application->toApiArray(...)`:
```php
        NotificationService::dispatch(
            $application,
            $status === 'accepted' ? Notification::TYPE_ACCEPTED : Notification::TYPE_REJECTED
        );

```

ผลลัพธ์ท้าย action ต้องเป็น:
```php
        NotificationService::dispatch(
            $application,
            $status === 'accepted' ? Notification::TYPE_ACCEPTED : Notification::TYPE_REJECTED
        );

        return $application->toApiArray($status === 'accepted');
```

- [ ] **Step 4: ตรวจ syntax**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\backend
php -l controllers/ApplicationController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: ทดสอบมือด้วย dry-run**

รัน `application_smoke.php` เพื่อยืนยันว่า flow เดิมไม่พัง:
```powershell
php tests/application_smoke.php
```

Expected: `Application smoke test passed`

notification row จะถูกตรวจละเอียดใน Task 8

- [ ] **Step 6: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add backend/controllers/ApplicationController.php
git commit -m "feat(backend): dispatch notifications on application events"
```

---

### Task 6: Notification API Endpoints

**Files:**
- Create: `backend/controllers/NotificationController.php`
- Modify: `backend/config/web.php` (มี routes แล้วใน Task 3)

**Interfaces:**
- Consumes: `Notification::toApiArray()` จาก Task 2
- Produces: `GET /api/notifications` → `{items: array, unread: int}`
- Produces: `PATCH /api/notifications` → `{unread: int}`

- [ ] **Step 1: สร้าง controller**

Create `backend/controllers/NotificationController.php`:
```php
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
```

- [ ] **Step 2: ตรวจ syntax และ 401**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\backend
php -l controllers/NotificationController.php
curl.exe -s -o NUL -w "%{http_code}" http://localhost:8080/api/notifications
```

Expected: `No syntax errors detected` และ `401`

- [ ] **Step 3: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add backend/controllers/NotificationController.php
git commit -m "feat(backend): add notification list and read endpoints"
```

---

### Task 7: Retry Command

**Files:**
- Create: `backend/commands/NotificationController.php`

**Interfaces:**
- Consumes: `NotificationService::deliver()` จาก Task 4
- Produces: `php yii notification/retry --limit=100`

- [ ] **Step 1: สร้าง console controller**

Create `backend/commands/NotificationController.php`:
```php
<?php

namespace app\commands;

use Yii;
use app\models\Notification;
use app\services\NotificationService;
use yii\console\Controller;
use yii\console\ExitCode;

class NotificationController extends Controller
{
    public $limit = 100;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['limit']);
    }

    public function actionRetry()
    {
        $config = Yii::$app->params['notifications'] ?? [];
        if (!empty($config['dry_run'])) {
            $this->stdout("dry_run is enabled; nothing to retry.\n");
            return ExitCode::OK;
        }

        $notifications = Notification::find()
            ->with(['application.post', 'application.applicant', 'user'])
            ->where(['<', 'attempts', 5])
            ->andWhere(['>', 'created_at', date('Y-m-d H:i:s', time() - 7 * 86400)])
            ->andWhere(['or', ['email_sent_at' => null], ['line_sent_at' => null]])
            ->orderBy(['notification.id' => SORT_ASC])
            ->limit((int)$this->limit)
            ->all();

        $retried = 0;
        $skipped = 0;
        foreach ($notifications as $notification) {
            $needsEmail = $notification->email_sent_at === null;
            $needsLine = $notification->line_sent_at === null
                && $notification->user->line_user_id !== null;
            if (!$needsEmail && !$needsLine) {
                $skipped++;
                continue;
            }

            $notification->attempts = (int)$notification->attempts + 1;
            $notification->save(false, ['attempts']);
            NotificationService::deliver($notification);
            $retried++;
        }

        $this->stdout("Retried {$retried} notification(s); skipped {$skipped}.\n");
        return ExitCode::OK;
    }
}
```

- [ ] **Step 2: ทดสอบใน dry-run**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\backend
php yii notification/retry
```

Expected: `dry_run is enabled; nothing to retry.` และ exit code 0

- [ ] **Step 3: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add backend/commands/NotificationController.php
git commit -m "feat(backend): add notification retry command"
```

---

### Task 8: Backend Smoke Test

**Files:**
- Create: `backend/tests/notification_smoke.php`

**Interfaces:**
- Consumes: ทุก endpoint จาก Task 3 และ Task 6
- Produces: สคริปต์ทดสอบที่รันซ้ำได้และล้างข้อมูลตัวเอง

- [ ] **Step 1: เขียน test**

Create `backend/tests/notification_smoke.php`:
```php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(require dirname(__DIR__) . '/config/console.php');

require __DIR__ . '/helpers.php';

$baseUrl = getenv('API_URL') ?: 'http://localhost:8080/api';
$host = parse_url($baseUrl, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('API smoke tests may target localhost only.');
}

$config = Yii::$app->params['notifications'] ?? [];
check(!empty($config['dry_run']), 'notification_smoke requires dry_run = true');

$userIds = [];
$postIds = [];

$postPayload = [
    'title' => 'หาคนร่วมทีม notification',
    'description' => 'ทดสอบ notification flow',
    'category' => 'course',
    'slots' => 2,
    'deadline' => '2026-12-31',
    'skill_ids' => [1],
];

try {
    $owner = registerUser($baseUrl, [1]);
    $ownerToken = $owner['token'];
    $userIds[] = $owner['user']['id'];

    $applicant = registerUser($baseUrl, [2, 3]);
    $applicantToken = $applicant['token'];
    $userIds[] = $applicant['user']['id'];

    // 401: every new endpoint requires a bearer token.
    [$status] = request('GET', $baseUrl . '/notifications');
    check($status === 401, 'Notifications without token did not return 401');
    [$status] = request('PATCH', $baseUrl . '/notifications', []);
    check($status === 401, 'Mark read without token did not return 401');
    [$status] = request('GET', $baseUrl . '/me/line/authorize-url');
    check($status === 401, 'Authorize URL without token did not return 401');
    [$status] = request('POST', $baseUrl . '/me/line', ['code' => 'x', 'state' => 'y']);
    check($status === 401, 'LINE link without token did not return 401');
    [$status] = request('DELETE', $baseUrl . '/me/line');
    check($status === 401, 'LINE unlink without token did not return 401');

    [$status, $post] = request('POST', $baseUrl . '/posts', $postPayload, $ownerToken);
    check($status === 201, 'Setup post did not return 201: ' . json_encode($post));
    $postId = (int)$post['id'];
    $postIds[] = $postId;

    [$status, $application] = request('POST', $baseUrl . "/posts/{$postId}/applications", [
        'message' => 'สนใจร่วมทีมครับ',
    ], $applicantToken);
    check($status === 201, 'Apply did not return 201: ' . json_encode($application));
    $applicationId = (int)$application['id'];

    // New application creates exactly one notification for the post owner.
    $rows = Yii::$app->db->createCommand(
        'SELECT * FROM notification WHERE application_id = :id AND type = :type',
        [':id' => $applicationId, ':type' => 'application_received']
    )->queryAll();
    check(count($rows) === 1, 'Owner notification row count differs');
    check((int)$rows[0]['user_id'] === (int)$owner['user']['id'], 'Owner notification recipient differs');
    check($rows[0]['email_sent_at'] !== null, 'Dry-run email was not marked sent');

    [$status, $list] = request('GET', $baseUrl . '/notifications', null, $ownerToken);
    check($status === 200, 'Owner notification list did not return 200');
    check(count($list['items']) === 1, 'Owner notification item count differs');
    check($list['unread'] === 1, 'Owner unread count differs');
    check($list['items'][0]['type'] === 'application_received', 'Owner notification type differs');
    check(!isset($list['items'][0]['line_id']), 'Notification payload leaked line_id');
    $notificationId = (int)$list['items'][0]['id'];

    [$status, $applicantList] = request('GET', $baseUrl . '/notifications', null, $applicantToken);
    check($status === 200, 'Applicant notification list did not return 200');
    check($applicantList['items'] === [], 'Applicant saw someone else notification');
    check($applicantList['unread'] === 0, 'Applicant unread count differs');

    // 422: malformed ids.
    [$status] = request('PATCH', $baseUrl . '/notifications', ['ids' => 'nope'], $ownerToken);
    check($status === 422, 'Non-array ids did not return 422');
    [$status] = request('PATCH', $baseUrl . '/notifications', ['ids' => []], $ownerToken);
    check($status === 422, 'Empty ids did not return 422');

    // Mark one read.
    [$status, $marked] = request('PATCH', $baseUrl . '/notifications', ['ids' => [$notificationId]], $ownerToken);
    check($status === 200, 'Mark one read did not return 200');
    check($marked['unread'] === 0, 'Mark one read did not clear unread');

    // Accept creates the applicant notification.
    [$status] = request('PATCH', $baseUrl . "/applications/{$applicationId}", [
        'status' => 'accepted',
    ], $ownerToken);
    check($status === 200, 'Accept did not return 200');

    [$status, $applicantList] = request('GET', $baseUrl . '/notifications', null, $applicantToken);
    check($status === 200, 'Applicant list after accept did not return 200');
    check($applicantList['unread'] === 1, 'Accepted unread count differs');
    check($applicantList['items'][0]['type'] === 'application_accepted', 'Accepted notification type differs');

    // Reject flow on a second post.
    $secondPayload = $postPayload;
    $secondPayload['title'] = 'หาคนร่วมทีม notification reject';
    [$status, $secondPost] = request('POST', $baseUrl . '/posts', $secondPayload, $ownerToken);
    check($status === 201, 'Second post did not return 201');
    $secondPostId = (int)$secondPost['id'];
    $postIds[] = $secondPostId;

    [$status, $secondApplication] = request('POST', $baseUrl . "/posts/{$secondPostId}/applications", [
        'message' => 'ขอสมัครครับ',
    ], $applicantToken);
    check($status === 201, 'Second apply did not return 201');

    [$status] = request('PATCH', $baseUrl . '/applications/' . (int)$secondApplication['id'], [
        'status' => 'rejected',
    ], $ownerToken);
    check($status === 200, 'Reject did not return 200');

    [$status, $applicantList] = request('GET', $baseUrl . '/notifications', null, $applicantToken);
    check($status === 200, 'Applicant list after reject did not return 200');
    check($applicantList['unread'] === 2, 'Rejected unread count differs');
    check($applicantList['items'][0]['type'] === 'application_rejected', 'Rejected notification type differs');

    // Mark all read.
    [$status, $marked] = request('PATCH', $baseUrl . '/notifications', [], $applicantToken);
    check($status === 200, 'Mark all read did not return 200');
    check($marked['unread'] === 0, 'Mark all read did not clear unread');

    // LINE endpoints without real credentials.
    [$status] = request('POST', $baseUrl . '/me/line', ['code' => 'abc', 'state' => 'wrong'], $applicantToken);
    check($status === 422, 'Invalid LINE state did not return 422');

    [$status, $unlinked] = request('DELETE', $baseUrl . '/me/line', null, $applicantToken);
    check($status === 200, 'LINE unlink did not return 200');
    check($unlinked['linked'] === false, 'LINE unlink response differs');

    [$status, $me] = request('GET', $baseUrl . '/me', null, $applicantToken);
    check($status === 200, 'Me did not return 200');
    check($me['line_linked'] === false, 'line_linked differs');
    check(!array_key_exists('line_user_id', $me), 'Me leaked line_user_id');

    if ($config['line']['channel_id'] === '') {
        [$status] = request('GET', $baseUrl . '/me/line/authorize-url', null, $applicantToken);
        check($status === 503, 'Unconfigured LINE login did not return 503');
    }

    echo "Notification smoke test passed\n";
} finally {
    if ($postIds !== []) {
        Yii::$app->db->createCommand()->delete('application', ['post_id' => $postIds])->execute();
        Yii::$app->db->createCommand()->delete('post', ['id' => $postIds])->execute();
    }
    if ($userIds !== []) {
        Yii::$app->db->createCommand()->delete('user', ['id' => $userIds])->execute();
    }
}
```

- [ ] **Step 2: รัน test**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\backend
php tests/notification_smoke.php
```

Expected: `Notification smoke test passed`

- [ ] **Step 3: รัน regression test เดิม**

Run:
```powershell
php tests/application_smoke.php
```

Expected: `Application smoke test passed`

- [ ] **Step 4: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add backend/tests/notification_smoke.php
git commit -m "test(backend): cover notification flows"
```

---

### Task 9: Documentation

**Files:**
- Modify: `docs/api.md`
- Modify: `README.md`
- Modify: `AGENTS.md`

**Interfaces:**
- Produces: contract และวิธีตั้งค่าที่ตรงกับโค้ดจริง

- [ ] **Step 1: เพิ่ม API contract**

ต่อท้าย `docs/api.md`:
```markdown
## Notifications

ทุก endpoint ในส่วนนี้ต้องใช้ `Authorization: Bearer <token>`

### `GET /api/notifications`

คืน 50 รายการล่าสุดของผู้ใช้ปัจจุบัน เรียงใหม่ไปเก่า:

```json
{
  "items": [
    {
      "id": 12,
      "type": "application_received",
      "is_read": false,
      "created_at": "2026-11-09 14:02:11",
      "post": { "id": 22, "title": "หาคนทำคลิป" },
      "actor": { "display_name": "สมชาย ใจดี" }
    }
  ],
  "unread": 3
}
```

`type` เป็นหนึ่งใน `application_received`, `application_accepted`, `application_rejected`
`actor` คือผู้สมัครสำหรับ `application_received` และเป็นเจ้าของโพสต์สำหรับอีกสองประเภท
response ต้องไม่มี `line_id` หรือ `line_user_id`

### `PATCH /api/notifications`

Body: `{ "ids": [1, 2] }` หรือ `{}`

- ไม่ส่ง `ids` = mark read ทั้งหมด
- `ids` ต้องเป็น array ของ integer ที่ไม่ว่าง; id ของผู้ใช้อื่นถูก ignore
- `ids: []` หรือชนิดข้อมูลผิดคืน 422

คืน `{ "unread": 0 }`

### `GET /api/me/line/authorize-url`

คืน `{ "url": "https://access.line.me/oauth2/v2.1/authorize?..." }`
ถ้ายังไม่ตั้งค่า LINE Login คืน 503

### `POST /api/me/line`

Body: `{ "code": "...", "state": "..." }`

- state ผิด/หมดอายุ/ไม่ใช่ของผู้ใช้ คืน 422
- LINE ปฏิเสธหรือแลก token ไม่สำเร็จ คืน 502
- สำเร็จคืน `{ "linked": true, "linked_at": "2026-11-09 15:00:00" }`

### `DELETE /api/me/line`

คืน `{ "linked": false }` เสมอ

### `line_linked` และ `line_linked_at`

response ของ register/login/me มี `line_linked` (boolean) และ `line_linked_at` (string|null) เพิ่มขึ้น; ไม่มี `line_user_id`
```

- [ ] **Step 2: เพิ่มวิธีตั้งค่าใน README**

ต่อท้าย `README.md`:
```markdown
## Notifications (email + LINE)

คัดลอก `backend/config/notifications-local.example.php` เป็น
`backend/config/notifications-local.php` แล้วใส่ค่าจริง ไฟล์นี้ถูก gitignore

- `mail.dsn`: SMTP DSN เช่น `smtp://user:pass@smtp.example.com:587`
- `line.channel_id` / `line.channel_secret`: จาก LINE Login channel
- `line.access_token`: channel access token ของ Messaging API (คนละตัวกับ channel secret)
- `line.redirect_uri`: ต้องตรงกับที่ลงทะเบียนใน LINE Developers Console
- `dry_run`: `true` = ไม่ยิงเน็ต (ใช้ใน dev/test), `false` = ส่งจริง

เมื่อยังไม่มี credential ให้คง `dry_run => true` ระบบยังสร้าง in-app notification ได้ปกติ

Retry ของที่ส่งไม่สำเร็จ:
```powershell
cd backend
php yii notification/retry --limit=100
```
```

- [ ] **Step 3: เพิ่มกฎใน AGENTS.md**

ในหัวข้อ `## LINE ID Privacy` ต่อท้าย bullet สุดท้าย:
```markdown
- Never include another user's `line_id` in notification payloads, emails, or LINE messages.
```

- [ ] **Step 4: ตรวจว่าไม่มี secret**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder
git status --short
git check-ignore -v backend/config/notifications-local.php
```

Expected: ไม่มี `notifications-local.php` ใน status และ check-ignore พิมพ์ว่าไฟล์ถูก ignore

- [ ] **Step 5: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add docs/api.md README.md AGENTS.md
git commit -m "docs: document notification API and setup"
```

---

### Task 10: Frontend API + Badge + Routes

**Files:**
- Create: `frontend/src/api/notifications.js`
- Create: `frontend/src/components/NotificationBadge.jsx`
- Modify: `frontend/src/App.jsx`

**Interfaces:**
- Consumes: `apiFetch()` จาก `frontend/src/api/client.js`
- Produces: `fetchNotifications()`, `markNotificationsRead(ids?)`, `getLineAuthorizeUrl()`, `linkLine(code, state)`, `unlinkLine()`
- Produces: route `/me/notifications` และ `/line/callback`, badge ใน topbar

- [ ] **Step 1: สร้าง API module**

Create `frontend/src/api/notifications.js`:
```js
import { apiFetch } from './client'

export function fetchNotifications() {
  return apiFetch('/notifications', { auth: true })
}

export function markNotificationsRead(ids) {
  return apiFetch('/notifications', {
    method: 'PATCH',
    auth: true,
    body: ids ? { ids } : {},
  })
}

export function getLineAuthorizeUrl() {
  return apiFetch('/me/line/authorize-url', { auth: true })
}

export function linkLine(code, state) {
  return apiFetch('/me/line', {
    method: 'POST',
    auth: true,
    body: { code, state },
  })
}

export function unlinkLine() {
  return apiFetch('/me/line', { method: 'DELETE', auth: true })
}
```

- [ ] **Step 2: สร้าง badge**

Create `frontend/src/components/NotificationBadge.jsx`:
```jsx
import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { fetchNotifications } from '../api/notifications'

export default function NotificationBadge() {
  const location = useLocation()
  const [unread, setUnread] = useState(0)

  useEffect(() => {
    let active = true
    fetchNotifications()
      .then((data) => {
        if (active) setUnread(data.unread)
      })
      .catch(() => {
        if (active) setUnread(0)
      })
    return () => { active = false }
  }, [location.pathname])

  return (
    <Link
      className="notification-badge"
      to="/me/notifications"
      aria-label={`การแจ้งเตือน ${unread} รายการที่ยังไม่อ่าน`}
    >
      การแจ้งเตือน
      {unread > 0 && (
        <span className="notification-count">{unread > 9 ? '9+' : unread}</span>
      )}
    </Link>
  )
}
```

- [ ] **Step 3: เพิ่ม route และ badge ใน App**

ใน `frontend/src/App.jsx` เพิ่ม imports:
```jsx
import NotificationBadge from './components/NotificationBadge'
import NotificationsPage from './pages/NotificationsPage'
import LineCallbackPage from './pages/LineCallbackPage'
```

ใน `<nav className="topbar-nav">` หลังลิงก์ `ใบสมัครของฉัน` เพิ่ม:
```jsx
            <NotificationBadge />
```

หลัง route `/me/applications` เพิ่ม:
```jsx
        <Route path="/me/notifications" element={token ? <NotificationsPage /> : <Navigate to="/login" replace />} />
        <Route path="/line/callback" element={token ? <LineCallbackPage /> : <Navigate to="/login" replace />} />
```

- [ ] **Step 4: ตรวจ lint (จะยัง fail เพราะยังไม่มี page)**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\frontend
npm run lint
```

Expected: ถ้า oxlint ตรวจ import จะ fail ด้วย unresolved import `NotificationsPage` / `LineCallbackPage` — แสดงว่า wiring ถูกและจะถูกแก้ใน Task 11–12; ถ้า lint ผ่าน ให้ไปต่อได้

- [ ] **Step 5: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add frontend/src/api/notifications.js frontend/src/components/NotificationBadge.jsx frontend/src/App.jsx
git commit -m "feat(frontend): add notification badge and routes"
```

---

### Task 11: Notifications Page

**Files:**
- Create: `frontend/src/pages/NotificationsPage.jsx`

**Interfaces:**
- Consumes: `fetchNotifications()`, `markNotificationsRead(ids)` จาก Task 10
- Produces: หน้า `/me/notifications`

- [ ] **Step 1: สร้างหน้า**

Create `frontend/src/pages/NotificationsPage.jsx`:
```jsx
import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { fetchNotifications, markNotificationsRead } from '../api/notifications'
import { ApiError } from '../api/client'

const LABELS = {
  application_received: 'มีผู้สมัครใหม่',
  application_accepted: 'ใบสมัครได้รับการตอบรับ',
  application_rejected: 'ใบสมัครไม่ผ่าน',
}

function relativeTime(value) {
  const timestamp = new Date(value.replace(' ', 'T')).getTime()
  const minutes = Math.floor((Date.now() - timestamp) / 60000)
  if (minutes < 1) return 'เมื่อสักครู่'
  if (minutes < 60) return `${minutes} นาทีที่แล้ว`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours} ชั่วโมงที่แล้ว`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days} วันที่แล้ว`
  return new Date(value.replace(' ', 'T')).toLocaleDateString('th-TH')
}

export default function NotificationsPage() {
  const navigate = useNavigate()
  const [items, setItems] = useState([])
  const [unread, setUnread] = useState(0)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')

  useEffect(() => {
    let active = true
    fetchNotifications()
      .then((data) => {
        if (!active) return
        setItems(data.items)
        setUnread(data.unread)
      })
      .catch((error) => {
        if (!active) return
        setMessage(error instanceof ApiError
          ? error.message
          : 'โหลดการแจ้งเตือนไม่สำเร็จ กรุณาลองใหม่')
      })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [])

  async function openNotification(item) {
    if (!item.is_read) {
      try {
        const data = await markNotificationsRead([item.id])
        setUnread(data.unread)
        setItems((current) => current.map((entry) => (
          entry.id === item.id ? { ...entry, is_read: true } : entry
        )))
      } catch {
        // ponytail: เปิดประกาศต่อได้แม้ mark read ล้มเหลว
      }
    }
    navigate(`/posts/${item.post.id}`)
  }

  async function markAll() {
    setBusy(true)
    try {
      const data = await markNotificationsRead()
      setUnread(data.unread)
      setItems((current) => current.map((entry) => ({ ...entry, is_read: true })))
    } catch (error) {
      setMessage(error instanceof ApiError ? error.message : 'ทำรายการไม่สำเร็จ')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="shell page-shell">
      <div className="page-head">
        <h1>การแจ้งเตือน</h1>
        <button
          className="button secondary"
          type="button"
          onClick={markAll}
          disabled={busy || unread === 0}
        >
          {busy ? 'กำลังบันทึก…' : 'อ่านทั้งหมด'}
        </button>
      </div>

      {message && <div className="alert" role="alert">{message}</div>}

      {loading ? (
        <p>กำลังโหลดการแจ้งเตือน…</p>
      ) : items.length === 0 ? (
        <div className="notification-empty">
          <p>ยังไม่มีการแจ้งเตือน</p>
          <p><Link to="/">ไปที่กระดานประกาศ</Link></p>
        </div>
      ) : (
        <ul className="notification-list">
          {items.map((item) => (
            <li
              key={item.id}
              className={item.is_read ? 'notification-item' : 'notification-item unread'}
            >
              <button className="notification-link" type="button" onClick={() => openNotification(item)}>
                <span className="notification-title">{LABELS[item.type] || item.type}</span>
                <span className="notification-meta">
                  {item.post.title} · {item.actor.display_name} · {relativeTime(item.created_at)}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}
```

- [ ] **Step 2: ตรวจ lint บางส่วน**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\frontend
npm run lint
```

Expected: ถ้า oxlint ตรวจ import จะ fail เฉพาะ unresolved import `LineCallbackPage` จาก Task 12; ถ้า lint ผ่าน ให้ไปต่อได้

- [ ] **Step 3: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add frontend/src/pages/NotificationsPage.jsx
git commit -m "feat(frontend): add notification center page"
```

---

### Task 12: Profile LINE Card + Callback Page

**Files:**
- Create: `frontend/src/pages/LineCallbackPage.jsx`
- Modify: `frontend/src/pages/ProfilePage.jsx`

**Interfaces:**
- Consumes: `getLineAuthorizeUrl()`, `linkLine()`, `unlinkLine()` จาก Task 10
- Consumes: `GET /api/me` คืน `line_linked`
- Produces: การ์ดเชื่อม LINE ในโปรไฟล์และหน้า callback

- [ ] **Step 1: สร้าง callback page**

Create `frontend/src/pages/LineCallbackPage.jsx`:
```jsx
import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { linkLine } from '../api/notifications'
import { ApiError } from '../api/client'

export default function LineCallbackPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const started = useRef(false)
  const [status, setStatus] = useState('loading')
  const [message, setMessage] = useState('กำลังเชื่อมต่อ LINE…')

  useEffect(() => {
    if (started.current) return
    started.current = true

    const error = params.get('error')
    const code = params.get('code')
    const state = params.get('state')

    if (error || !code || !state) {
      setStatus('error')
      setMessage(error === 'access_denied'
        ? 'คุณยกเลิกการเชื่อมต่อ LINE'
        : 'ไม่พบข้อมูลยืนยันจาก LINE')
      return
    }

    linkLine(code, state)
      .then(() => {
        setStatus('success')
        setMessage('เชื่อมต่อ LINE สำเร็จ กำลังกลับไปที่โปรไฟล์…')
        setTimeout(() => navigate('/me', { replace: true }), 1500)
      })
      .catch((err) => {
        setStatus('error')
        setMessage(err instanceof ApiError
          ? err.message
          : 'เชื่อมต่อ LINE ไม่สำเร็จ กรุณาลองใหม่')
      })
  }, [params, navigate])

  return (
    <main className="shell page-shell">
      <h1>เชื่อมต่อ LINE</h1>
      <div className={status === 'error' ? 'alert' : 'alert alert-success'} role="status">
        {message}
      </div>
      {status !== 'loading' && (
        <p><Link to="/me">กลับไปที่โปรไฟล์</Link></p>
      )}
    </main>
  )
}
```

- [ ] **Step 2: เพิ่ม state และฟังก์ชันใน ProfilePage**

ใน `frontend/src/pages/ProfilePage.jsx` แก้ import:
```jsx
import { ApiError, getFieldErrors } from '../api/client'
import { getLineAuthorizeUrl, unlinkLine } from '../api/notifications'
```

เพิ่ม state หลัง `const [submitting, setSubmitting] = useState(false)`:
```jsx
  const [lineLinked, setLineLinked] = useState(false)
  const [lineLinkedAt, setLineLinkedAt] = useState('')
  const [lineBusy, setLineBusy] = useState(false)
  const [lineMessage, setLineMessage] = useState('')
```

ใน `useEffect` หลัง `setSelectedSkills(profile.skills.map((skill) => skill.id))` เพิ่ม:
```jsx
        setLineLinked(Boolean(profile.line_linked))
        setLineLinkedAt(profile.line_linked_at || '')
```

เพิ่มฟังก์ชันก่อน `if (loading)`:
```jsx
  async function connectLine() {
    setLineBusy(true)
    setLineMessage('')
    try {
      const data = await getLineAuthorizeUrl()
      window.location.assign(data.url)
    } catch (error) {
      setLineMessage(error instanceof ApiError ? error.message : 'เปิด LINE ไม่สำเร็จ')
      setLineBusy(false)
    }
  }

  async function disconnectLine() {
    setLineBusy(true)
    setLineMessage('')
    try {
      await unlinkLine()
      setLineLinked(false)
      setLineLinkedAt('')
      setLineMessage('ยกเลิกการเชื่อมต่อ LINE แล้ว')
    } catch (error) {
      setLineMessage(error instanceof ApiError ? error.message : 'ยกเลิกไม่สำเร็จ')
    } finally {
      setLineBusy(false)
    }
  }
```

หลัง `</section>` ของ form card ก่อน `</main>` เพิ่ม:
```jsx
      <section className="card form-card notification-settings">
        <h2>แจ้งเตือนผ่าน LINE</h2>
        <p className="post-info">อีเมลแจ้งเตือนเปิดตลอด; LINE ต้องเชื่อมบัญชีก่อน</p>
        {lineMessage && <div className="alert" role="alert">{lineMessage}</div>}
        <div className="line-link-state">
          {lineLinked ? (
            <>
              <p className="post-info">
                เชื่อมต่อแล้ว{lineLinkedAt ? ` · ${lineLinkedAt}` : ''}
              </p>
              <button className="button secondary" type="button" onClick={disconnectLine} disabled={lineBusy}>
                {lineBusy ? 'กำลังดำเนินการ…' : 'ยกเลิกการเชื่อมต่อ'}
              </button>
            </>
          ) : (
            <button className="button" type="button" onClick={connectLine} disabled={lineBusy}>
              {lineBusy ? 'กำลังเปิด LINE…' : 'เชื่อมต่อ LINE'}
            </button>
          )}
        </div>
      </section>
```

- [ ] **Step 3: ตรวจ lint**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\frontend
npm run lint
```

Expected: ผ่าน

- [ ] **Step 4: ขอยืนยันแล้ว commit**

Ask the user for fresh confirmation, then:
```powershell
git add frontend/src/pages/LineCallbackPage.jsx frontend/src/pages/ProfilePage.jsx
git commit -m "feat(frontend): add LINE linking UI"
```

---

### Task 13: Styles + Full Verification

**Files:**
- Modify: `frontend/src/index.css`

**Interfaces:**
- Produces: สไตล์ของ badge, notification list, LINE card ที่เข้ากับ `DESIGN.md`

- [ ] **Step 1: เพิ่ม CSS**

ต่อท้าย `frontend/src/index.css`:
```css
/* ---------- notifications ---------- */

.notification-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.notification-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border: 1px solid var(--ink);
  border-radius: 999px;
  background: var(--accent);
  color: var(--ink);
  font: 600 10px/1 var(--mono);
  font-variant-numeric: tabular-nums;
}

.notification-list {
  margin: 24px 0 0;
  padding: 0;
  list-style: none;
}
.notification-item { border-bottom: 1px solid var(--rule); }
.notification-item:first-child { border-top: 1px solid var(--rule); }
.notification-link {
  position: relative;
  display: grid;
  gap: 4px;
  width: 100%;
  padding: 16px 12px 16px 28px;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
  transition: background-color 90ms steps(2, end);
}
.notification-link:hover { background: var(--wash); }
.notification-item.unread .notification-link::before {
  content: "";
  position: absolute;
  left: 8px;
  top: 22px;
  width: 8px;
  height: 8px;
  border: 1px solid var(--ink);
  background: var(--accent);
}
.notification-title {
  font-size: 14px;
  font-weight: 600;
  line-height: 1.4;
}
.notification-meta {
  color: var(--muted);
  font-size: 11px;
  font-variant-numeric: tabular-nums;
  line-height: 1.5;
}
.notification-empty {
  margin-top: 24px;
  padding: 24px 0;
  border-top: 1px solid var(--rule);
  color: var(--muted);
  font-size: 13px;
  line-height: 1.6;
}
.notification-settings { margin-top: 32px; }
.line-link-state {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-top: 16px;
}

@media (max-width: 560px) {
  .notification-link { padding-left: 24px; }
}
```

- [ ] **Step 2: รัน lint + build**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\frontend
npm run lint
npm run build
```

Expected: ทั้งสองคำสั่งผ่าน

- [ ] **Step 3: ทดสอบใน browser ที่ 390px**

เปิด dev server (ถ้ายังไม่รัน):
```powershell
cd C:\Users\mooab\Projects\team-finder\frontend
npm run dev
```

ตรวจด้วย DevTools device emulation ที่ 390×844:
1. badge แสดงจำนวนถูกต้องและไม่ล้น topbar
2. `/me/notifications` แสดงการ์ด, unread มีจุด, ปุ่ม "อ่านทั้งหมด" disabled เมื่อ unread = 0
3. คลิกการ์ดแล้วไป `/posts/{id}` และ badge ลดลง
4. `/me` แสดงการ์ด LINE พร้อมปุ่มเชื่อมต่อ
5. `document.documentElement.scrollWidth === 390` ทุกหน้า

Expected: ผ่านทุกข้อ

- [ ] **Step 4: รัน backend tests รอบสุดท้าย**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder\backend
php tests/application_smoke.php
php tests/notification_smoke.php
php yii notification/retry
```

Expected: smoke tests ผ่านทั้งคู่ และ retry พิมพ์ `dry_run is enabled; nothing to retry.`

- [ ] **Step 5: ตรวจไฟล์ที่ไม่ควร commit**

Run:
```powershell
cd C:\Users\mooab\Projects\team-finder
git status --short
```

Expected: ไม่มี `backend/config/notifications-local.php`, `backend/vendor/`, หรือ `frontend/node_modules/`

- [ ] **Step 6: สรุปและขอยืนยัน commit**

รายงานผลลัพธ์ทั้งหมดให้ผู้ใช้ แล้วขอคำยืนยันก่อน:
```powershell
git add frontend/src/index.css
git commit -m "style(frontend): style notification center"
```

---

## Self-Review Notes

- ทุก requirement ใน spec มี task รองรับ: schema (Task 2), LINE link (Task 3), delivery (Task 4), triggers (Task 5), in-app API (Task 6), retry (Task 7), tests (Task 8), docs (Task 9), UI (Task 10–13)
- ชื่อที่ใช้ตรงกันทุก task: `Notification::TYPE_*`, `NotificationService::dispatch/deliver`, `LineClient::push/exchangeCode/buildAuthorizeUrl`
- ไม่มี placeholder; ทุก step มีคำสั่งหรือโค้ดจริง
- จุดที่ต้องมี credential จริง (SMTP, LINE OA) ถูกกันด้วย `dry_run` และระบุเป็น blocked ใน spec
