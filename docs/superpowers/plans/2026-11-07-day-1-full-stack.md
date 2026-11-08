# Team Finder Day 1 Full-Stack Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a new Yii2/MySQL and React/Vite project where a student can register with skills, log in, log out, and use only the real Day 1 API.

**Architecture:** A small API-only Yii2 application serves JSON under `/api` and uses six InnoDB tables. A Vite React SPA calls that API through a same-origin development proxy; authentication uses opaque bearer tokens whose SHA-256 hashes and expiries are stored in MySQL. Backend is completed and smoke-tested before frontend work begins.

**Tech Stack:** PHP 8.2, Yii2 ~2.0.53, MySQL/MariaDB with `utf8mb4`, React, Vite, React Router, native Fetch, plain CSS, Node 20, npm 10.

**Spec:** `docs/superpowers/specs/2026-11-07-day-1-full-stack-design.md`

## Global Constraints

- Project root is `C:\Users\mooab\Projects\team-finder`; all paths below are relative to that root.
- Implement backend Day 1 first, then frontend Day 1; do not expand into the five-day feature set.
- Only `POST /api/auth/register`, `POST /api/auth/login`, and `GET /api/skills` are added.
- Use exactly six tables: `user`, `skill`, `user_skill`, `post`, `post_skill`, `application`.
- Seed exactly these skills in ID order: ตัดต่อ, เขียนเว็บ, ถ่ายภาพ, UI/UX, เขียนคอนเทนต์, วิเคราะห์ข้อมูล.
- Raw tokens are at least 64 characters, expire after seven days, rotate on every successful login, and are stored only as SHA-256 hashes.
- Passwords use Yii2 Security hashing; validation and authorization decisions belong to backend.
- Another user's `line_id` may be exposed only for the related `accepted` application; Day 1 returns only the authenticated user's own `line_id` in register/login responses.
- Use `Asia/Bangkok`, `YYYY-MM-DD HH:MM:SS` for datetimes, and `YYYY-MM-DD` for deadlines.
- `docs/api.md` is the API source of truth after implementation; update it whenever implementation differs.
- Use native Fetch instead of Axios, plain CSS instead of a UI framework, and standalone PHP smoke tests instead of adding a PHP test framework.
- Do not add mock APIs, feed/post/application/profile endpoints, rate limiting, deployment configuration, or Git mutations.
- Never commit `.env`, secret-bearing environment files, or `backend/config/db-local.php`; example files contain non-secret development placeholders only.
- Do not run `git init`, `git add`, `git commit`, `git push`, or other Git mutations without a fresh explicit confirmation from the user.

---

## File Map

- `.gitignore`: excludes dependencies, build output, runtime files, local configuration, and secrets.
- `backend/composer.json`: minimal Yii2 runtime dependency and application autoloading.
- `backend/config/web.php`: JSON API, URL rules, stateless identity, timezone.
- `backend/config/console.php`: console application for migrations and smoke tests.
- `backend/config/db.php` and `db-local.example.php`: safe default development DB configuration and local override mechanism.
- `backend/migrations/m261107_000001_create_team_finder_schema.php`: all schema, indexes, foreign keys, checks, and skill seed.
- `backend/models/User.php`: ActiveRecord, token identity, skill relation, and safe auth response.
- `backend/models/Skill.php`: skill ActiveRecord.
- `backend/models/RegisterForm.php`: registration validation and transactional persistence.
- `backend/models/LoginForm.php`: login input validation and password authentication.
- `backend/controllers/AuthController.php`: register/login HTTP actions.
- `backend/controllers/SkillController.php`: public ordered skill list.
- `backend/tests/schema_smoke.php`: schema, seed, unique, and foreign-key checks.
- `backend/tests/auth_model_smoke.php`: registration and identity checks without HTTP.
- `backend/tests/api_smoke.php`: localhost-only end-to-end API checks and token rotation checks.
- `frontend/src/api/client.js`: base URL, JSON, bearer token, API errors, and 401 behavior.
- `frontend/src/api/auth.js` and `skills.js`: endpoint-specific functions.
- `frontend/src/App.jsx`: token state and route decisions.
- `frontend/src/components/AuthLinks.jsx`: shared login/register navigation without circular imports.
- `frontend/src/pages/RegisterPage.jsx` and `LoginPage.jsx`: accessible forms.
- `frontend/src/index.css`: responsive shared styling.
- `AGENTS.md`, `README.md`, `docs/api.md`, `docs/handoff.md`: durable project rules, setup, contract, and observed handoff state.

### Task 1: Minimal Yii2 Application and Secret Guardrails

**Files:**
- Create: `.gitignore`
- Create: `backend/composer.json`
- Create: `backend/config/web.php`
- Create: `backend/config/console.php`
- Create: `backend/config/db.php`
- Create: `backend/config/db-local.example.php`
- Create: `backend/web/index.php`
- Create: `backend/yii`
- Create: `backend/runtime/.gitignore`
- Create: `backend/web/assets/.gitignore`

**Interfaces:**
- Consumes: PHP 8.2 and Composer already available on PATH.
- Produces: Yii console entry point, web entry point, DB component, JSON routes named `auth/register`, `auth/login`, and `skill/index`.

- [ ] **Step 1: Create root ignore rules before any local configuration**

Create `.gitignore`:

```gitignore
.env
.env.*
!.env.example

backend/config/db-local.php
backend/vendor/
backend/runtime/*
!backend/runtime/.gitignore
backend/web/assets/*
!backend/web/assets/.gitignore

frontend/node_modules/
frontend/dist/

*.log
.DS_Store
Thumbs.db
```

Create `backend/runtime/.gitignore` and `backend/web/assets/.gitignore` with:

```gitignore
*
!.gitignore
```

- [ ] **Step 2: Declare only the backend runtime dependency**

Create `backend/composer.json`:

```json
{
  "name": "team-finder/backend",
  "description": "Team Finder Yii2 JSON API",
  "type": "project",
  "require": {
    "php": ">=8.2",
    "yiisoft/yii2": "~2.0.53"
  },
  "autoload": {
    "psr-4": {
      "app\\": ""
    }
  },
  "config": {
    "allow-plugins": {
      "yiisoft/yii2-composer": true
    },
    "sort-packages": true
  },
  "repositories": [
    {
      "type": "composer",
      "url": "https://asset-packagist.org"
    }
  ]
}
```

Run from `backend`:

```powershell
composer install --no-interaction --prefer-dist
php -r "require 'vendor/autoload.php'; echo 'Composer autoload OK'.PHP_EOL;"
```

Expected: `Composer autoload OK` and `backend/composer.lock` exists.

- [ ] **Step 3: Add DB defaults and local override**

Create `backend/config/db-local.example.php`:

```php
<?php

return [
    // Local development placeholders only; override in ignored db-local.php.
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=team_finder',
    'username' => 'root',
    'password' => '',
];
```

Create `backend/config/db.php`:

```php
<?php

$local = is_file(__DIR__ . '/db-local.php')
    ? require __DIR__ . '/db-local.php'
    : [];

return array_replace([
    'class' => yii\db\Connection::class,
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=team_finder',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'attributes' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
], $local);
```

Create the ignored local file from the example:

```powershell
Copy-Item config/db-local.example.php config/db-local.php
```

- [ ] **Step 4: Configure web and console applications**

Create `backend/config/web.php`:

```php
<?php

return [
    'id' => 'team-finder-api',
    'basePath' => dirname(__DIR__),
    'timeZone' => 'Asia/Bangkok',
    'components' => [
        'request' => [
            'parsers' => [
                'application/json' => yii\web\JsonParser::class,
            ],
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
        ],
        'user' => [
            'identityClass' => app\models\User::class,
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'db' => require __DIR__ . '/db.php',
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'POST api/auth/register' => 'auth/register',
                'POST api/auth/login' => 'auth/login',
                'GET api/skills' => 'skill/index',
            ],
        ],
        'log' => [
            'targets' => [
                [
                    'class' => yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
    ],
];
```

Create `backend/config/console.php`:

```php
<?php

return [
    'id' => 'team-finder-console',
    'basePath' => dirname(__DIR__),
    'timeZone' => 'Asia/Bangkok',
    'controllerNamespace' => 'app\commands',
    'components' => [
        'db' => require __DIR__ . '/db.php',
    ],
];
```

Create `backend/web/index.php`:

```php
<?php

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

$config = require dirname(__DIR__) . '/config/web.php';
(new yii\web\Application($config))->run();
```

Create `backend/yii`:

```php
#!/usr/bin/env php
<?php

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/config/console.php';
$application = new yii\console\Application($config);
$exitCode = $application->run();
exit($exitCode);
```

- [ ] **Step 5: Verify the minimal application boots**

Run from `backend`:

```powershell
php yii help
php -r "require 'vendor/autoload.php'; require 'vendor/yiisoft/yii2/Yii.php'; new yii\console\Application(require 'config/console.php'); echo Yii::`$app->timeZone.PHP_EOL;"
```

Expected: Yii command help appears and the second command prints `Asia/Bangkok`. The API controllers intentionally do not exist yet; no HTTP success is claimed in this task.

### Task 2: Six-Table Migration and Database Constraints

**Files:**
- Create: `backend/migrations/m261107_000001_create_team_finder_schema.php`
- Create: `backend/tests/schema_smoke.php`
- Modify locally, never commit: `backend/config/db-local.php`

**Interfaces:**
- Consumes: `backend/config/db.php` from Task 1.
- Produces: six tables, six skills, foreign keys, uniqueness constraints, and `Yii::$app->db` access used by models and tests.

- [ ] **Step 1: Write the schema smoke test before the migration**

Create `backend/tests/schema_smoke.php`:

```php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(require dirname(__DIR__) . '/config/console.php');

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectIntegrityFailure(callable $callback, $message) {
    try {
        $callback();
    } catch (yii\db\IntegrityException $exception) {
        echo "PASS: {$message}\n";
        return;
    }
    throw new RuntimeException("Expected integrity failure: {$message}");
}

$db = Yii::$app->db;
$expectedTables = ['application', 'post', 'post_skill', 'skill', 'user', 'user_skill'];
$actualTables = $db->schema->getTableNames();
foreach ($expectedTables as $table) {
    check(in_array($table, $actualTables, true), "Missing table {$table}");
}
echo "PASS: six required tables exist\n";

$skills = $db->createCommand('SELECT id, name FROM skill ORDER BY id')->queryAll();
check(array_column($skills, 'name') === [
    'ตัดต่อ', 'เขียนเว็บ', 'ถ่ายภาพ', 'UI/UX', 'เขียนคอนเทนต์', 'วิเคราะห์ข้อมูล',
], 'Skill seed or ordering differs');
echo "PASS: skill seed is exact and ordered\n";

$tx = $db->beginTransaction();
try {
    $db->createCommand()->insert('user', [
        'email' => 'schema-smoke@example.com',
        'password_hash' => 'not-a-production-password',
        'display_name' => 'Schema Smoke',
        'faculty' => 'Test',
        'major' => 'Test',
        'year' => 1,
        'line_id' => 'schema.smoke',
    ])->execute();
    $userId = (int)$db->lastInsertID;

    expectIntegrityFailure(function () use ($db) {
        $db->createCommand()->insert('user', [
            'email' => 'SCHEMA-SMOKE@example.com',
            'password_hash' => 'x',
            'display_name' => 'Duplicate',
            'faculty' => 'Test',
            'major' => 'Test',
            'year' => 1,
            'line_id' => 'duplicate',
        ])->execute();
    }, 'email uniqueness is case-insensitive');

    $db->createCommand()->insert('post', [
        'owner_id' => $userId,
        'title' => 'Schema test',
        'description' => 'Schema test',
        'category' => 'course',
        'slots' => 1,
        'deadline' => '2026-12-31',
    ])->execute();
    $postId = (int)$db->lastInsertID;

    $db->createCommand()->insert('application', [
        'post_id' => $postId,
        'user_id' => $userId,
        'message' => 'First',
    ])->execute();

    expectIntegrityFailure(function () use ($db, $postId, $userId) {
        $db->createCommand()->insert('application', [
            'post_id' => $postId,
            'user_id' => $userId,
            'message' => 'Duplicate',
        ])->execute();
    }, 'application uniqueness is enforced');

    expectIntegrityFailure(function () use ($db) {
        $db->createCommand()->insert('user_skill', [
            'user_id' => 99999999,
            'skill_id' => 1,
        ])->execute();
    }, 'foreign keys reject missing parents');

    echo "PASS: constraints reject invalid data\n";
} finally {
    $tx->rollBack();
}

echo "Schema smoke test passed\n";
```

Before running the red test, start MySQL through the existing XAMPP installation. If the service is not running, ask the user to start the MySQL module in XAMPP Control Panel rather than installing another database. Create the database from `backend` using the default local XAMPP credentials:

```powershell
php -r '$pdo = new PDO("mysql:host=127.0.0.1;port=3306", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); $pdo->exec("CREATE DATABASE IF NOT EXISTS team_finder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); echo "Database ready\n";'
```

If local credentials differ, update only ignored `config/db-local.php` and use those credentials for this local command. Then run:

```powershell
php tests/schema_smoke.php
```

Expected before migration: FAIL with a missing-table error after the database connection succeeds.

- [ ] **Step 2: Create the complete migration**

Create `backend/migrations/m261107_000001_create_team_finder_schema.php`:

```php
<?php

use yii\db\Migration;

class m261107_000001_create_team_finder_schema extends Migration
{
    public function safeUp()
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->createTable('user', [
            'id' => $this->primaryKey()->unsigned(),
            'email' => $this->string(190)->notNull(),
            'password_hash' => $this->string(255)->notNull(),
            'display_name' => $this->string(100)->notNull(),
            'faculty' => $this->string(150)->notNull(),
            'major' => $this->string(150)->notNull(),
            'year' => $this->tinyInteger()->unsigned()->notNull(),
            'line_id' => $this->string(50)->notNull(),
            'bio' => $this->text()->null(),
            'access_token_hash' => $this->char(64)->null(),
            'access_token_expires_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $options);
        $this->createIndex('uq_user_email', 'user', 'email', true);
        $this->createIndex('uq_user_access_token_hash', 'user', 'access_token_hash', true);
        $this->addCheck('ck_user_year', 'user', 'year BETWEEN 1 AND 8');

        $this->createTable('skill', [
            'id' => $this->primaryKey()->unsigned(),
            'name' => $this->string(100)->notNull(),
        ], $options);
        $this->createIndex('uq_skill_name', 'skill', 'name', true);
        $this->batchInsert('skill', ['id', 'name'], [
            [1, 'ตัดต่อ'],
            [2, 'เขียนเว็บ'],
            [3, 'ถ่ายภาพ'],
            [4, 'UI/UX'],
            [5, 'เขียนคอนเทนต์'],
            [6, 'วิเคราะห์ข้อมูล'],
        ]);

        $this->createTable('post', [
            'id' => $this->primaryKey()->unsigned(),
            'owner_id' => $this->integer()->unsigned()->notNull(),
            'title' => $this->string(200)->notNull(),
            'description' => $this->text()->notNull(),
            'category' => $this->string(20)->notNull(),
            'slots' => $this->smallInteger()->unsigned()->notNull(),
            'deadline' => $this->date()->notNull(),
            'status' => $this->string(10)->notNull()->defaultValue('open'),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $options);
        $this->createIndex('idx_post_owner', 'post', 'owner_id');
        $this->addCheck('ck_post_category', 'post', "category IN ('course','contest','personal')");
        $this->addCheck('ck_post_status', 'post', "status IN ('open','closed')");
        $this->addCheck('ck_post_slots', 'post', 'slots > 0');
        $this->addForeignKey('fk_post_owner', 'post', 'owner_id', 'user', 'id', 'RESTRICT', 'CASCADE');

        $this->createTable('user_skill', [
            'user_id' => $this->integer()->unsigned()->notNull(),
            'skill_id' => $this->integer()->unsigned()->notNull(),
            'PRIMARY KEY(user_id, skill_id)',
        ], $options);
        $this->createIndex('idx_user_skill_skill', 'user_skill', 'skill_id');
        $this->addForeignKey('fk_user_skill_user', 'user_skill', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_user_skill_skill', 'user_skill', 'skill_id', 'skill', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('post_skill', [
            'post_id' => $this->integer()->unsigned()->notNull(),
            'skill_id' => $this->integer()->unsigned()->notNull(),
            'PRIMARY KEY(post_id, skill_id)',
        ], $options);
        $this->createIndex('idx_post_skill_skill', 'post_skill', 'skill_id');
        $this->addForeignKey('fk_post_skill_post', 'post_skill', 'post_id', 'post', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_post_skill_skill', 'post_skill', 'skill_id', 'skill', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('application', [
            'id' => $this->primaryKey()->unsigned(),
            'post_id' => $this->integer()->unsigned()->notNull(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'message' => $this->text()->notNull(),
            'status' => $this->string(10)->notNull()->defaultValue('pending'),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $options);
        $this->createIndex('uq_application_post_user', 'application', ['post_id', 'user_id'], true);
        $this->createIndex('idx_application_user', 'application', 'user_id');
        $this->addCheck('ck_application_status', 'application', "status IN ('pending','accepted','rejected')");
        $this->addForeignKey('fk_application_post', 'application', 'post_id', 'post', 'id', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('fk_application_user', 'application', 'user_id', 'user', 'id', 'RESTRICT', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('application');
        $this->dropTable('post_skill');
        $this->dropTable('user_skill');
        $this->dropTable('post');
        $this->dropTable('skill');
        $this->dropTable('user');
    }
}
```

- [ ] **Step 3: Run migration and make the schema test pass**

```powershell
php yii migrate --interactive=0
php tests/schema_smoke.php
php yii migrate --interactive=0
```

Expected: first migration applies one migration, schema smoke prints all PASS lines, and the second migration run reports no new migrations without duplicating skills.

### Task 3: Registration Model and Bearer Identity

**Files:**
- Create: `backend/models/User.php`
- Create: `backend/models/Skill.php`
- Create: `backend/models/RegisterForm.php`
- Create: `backend/tests/auth_model_smoke.php`

**Interfaces:**
- Consumes: tables and seed from Task 2.
- Produces: `RegisterForm::register(): ?array`, `User::issueAccessToken(): string`, `User::findIdentityByAccessToken()`, and `User::toAuthArray(): array`.

- [ ] **Step 1: Write the failing model smoke test**

Create `backend/tests/auth_model_smoke.php`:

```php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(require dirname(__DIR__) . '/config/console.php');

use app\models\RegisterForm;
use app\models\User;

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$email = 'model-' . bin2hex(random_bytes(8)) . '@example.com';
$form = new RegisterForm();
$form->load([
    'email' => $email,
    'password' => 'correct-horse-123',
    'display_name' => 'Model Smoke',
    'faculty' => 'Test',
    'major' => 'Test',
    'year' => 3,
    'line_id' => 'model.smoke',
    'skill_ids' => [1, 2],
], '');

$userId = null;
try {
    $result = $form->register();
    check(is_array($result), 'Registration failed: ' . json_encode($form->errors));
    check(strlen($result['token']) >= 64, 'Raw token is too short');
    check(!isset($result['user']['password']), 'Password leaked');
    check(!isset($result['user']['password_hash']), 'Password hash leaked');
    check(!isset($result['user']['access_token_hash']), 'Token hash leaked');
    check(count($result['user']['skills']) === 2, 'Skills were not saved');
    $userId = $result['user']['id'];

    $identity = User::findIdentityByAccessToken($result['token']);
    check($identity && (int)$identity->id === $userId, 'Valid token did not resolve identity');

    $identity->access_token_expires_at = date('Y-m-d H:i:s', time() - 60);
    $identity->save(false, ['access_token_expires_at']);
    check(User::findIdentityByAccessToken($result['token']) === null, 'Expired token resolved identity');

    $duplicate = new RegisterForm();
    $duplicate->load([
        'email' => $email,
        'password' => 'correct-horse-123',
        'display_name' => 'Duplicate',
        'faculty' => 'Test',
        'major' => 'Test',
        'year' => 2,
        'line_id' => 'duplicate',
        'skill_ids' => [1],
    ], '');
    check($duplicate->register() === null && $duplicate->hasErrors('email'), 'Duplicate email was accepted');

    echo "Auth model smoke test passed\n";
} finally {
    if ($userId !== null) {
        $user = User::findOne($userId);
        if ($user !== null) {
            $user->delete();
        }
    }
}
```

Run from `backend`:

```powershell
php tests/auth_model_smoke.php
```

Expected before implementation: FAIL because `app\models\RegisterForm` does not exist.

- [ ] **Step 2: Implement skill and user models**

Create `backend/models/Skill.php`:

```php
<?php

namespace app\models;

use yii\db\ActiveRecord;

class Skill extends ActiveRecord
{
    public static function tableName()
    {
        return 'skill';
    }
}
```

Create `backend/models/User.php`:

```php
<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

class User extends ActiveRecord implements IdentityInterface
{
    public static function tableName()
    {
        return 'user';
    }

    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        return static::find()
            ->where(['access_token_hash' => hash('sha256', $token)])
            ->andWhere(['>', 'access_token_expires_at', date('Y-m-d H:i:s')])
            ->one();
    }

    public static function findByEmail($email)
    {
        return static::findOne(['email' => $email]);
    }

    public function getId()
    {
        return (int)$this->id;
    }

    public function getAuthKey()
    {
        return '';
    }

    public function validateAuthKey($authKey)
    {
        return false;
    }

    public function getSkills()
    {
        return $this->hasMany(Skill::class, ['id' => 'skill_id'])
            ->viaTable('user_skill', ['user_id' => 'id']);
    }

    public function issueAccessToken()
    {
        $token = Yii::$app->security->generateRandomString(64);
        $this->access_token_hash = hash('sha256', $token);
        $this->access_token_expires_at = date('Y-m-d H:i:s', time() + 604800);
        return $token;
    }

    public function toAuthArray()
    {
        return [
            'id' => (int)$this->id,
            'email' => $this->email,
            'display_name' => $this->display_name,
            'faculty' => $this->faculty,
            'major' => $this->major,
            'year' => (int)$this->year,
            'line_id' => $this->line_id,
            'bio' => $this->bio,
            'skills' => array_map(static function ($skill) {
                return ['id' => (int)$skill->id, 'name' => $skill->name];
            }, $this->getSkills()->orderBy(['skill.id' => SORT_ASC])->all()),
        ];
    }
}
```

- [ ] **Step 3: Implement transactional registration and exact validation**

Create `backend/models/RegisterForm.php`:

```php
<?php

namespace app\models;

use Yii;
use yii\base\Model;

class RegisterForm extends Model
{
    public $email;
    public $password;
    public $display_name;
    public $faculty;
    public $major;
    public $year;
    public $line_id;
    public $skill_ids = [];

    public function rules()
    {
        $trim = static fn($value) => is_string($value) ? trim($value) : $value;

        return [
            ['email', 'filter', 'filter' => static fn($value) => is_string($value) ? mb_strtolower(trim($value)) : $value],
            [['display_name', 'faculty', 'major', 'line_id'], 'filter', 'filter' => $trim],
            [[
                'email', 'password', 'display_name', 'faculty', 'major',
                'year', 'line_id', 'skill_ids',
            ], 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 190],
            ['email', 'unique', 'targetClass' => User::class, 'targetAttribute' => 'email'],
            ['password', 'string', 'min' => 8, 'max' => 72],
            ['display_name', 'string', 'max' => 100],
            [['faculty', 'major'], 'string', 'max' => 150],
            ['year', 'integer', 'min' => 1, 'max' => 8],
            ['line_id', 'string', 'max' => 50],
            ['skill_ids', 'validateSkills', 'skipOnEmpty' => false],
        ];
    }

    public function validateSkills($attribute)
    {
        if (!is_array($this->$attribute) || $this->$attribute === []) {
            $this->addError($attribute, 'Select at least one skill.');
            return;
        }

        $values = $this->$attribute;
        foreach ($values as $value) {
            if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1) {
                $this->addError($attribute, 'Skill IDs must be positive integers.');
                return;
            }
        }

        $ids = array_map('intval', $values);
        if (count(array_unique($ids)) !== count($ids)) {
            $this->addError($attribute, 'Skill IDs must not contain duplicates.');
            return;
        }

        if ((int)Skill::find()->where(['id' => $ids])->count() !== count($ids)) {
            $this->addError($attribute, 'One or more skills do not exist.');
            return;
        }

        $this->$attribute = array_values($ids);
    }

    public function register()
    {
        if (!$this->validate()) {
            return null;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user = new User();
            $user->email = $this->email;
            $user->display_name = $this->display_name;
            $user->faculty = $this->faculty;
            $user->major = $this->major;
            $user->year = (int)$this->year;
            $user->line_id = $this->line_id;
            $user->password_hash = Yii::$app->security->generatePasswordHash($this->password);
            $token = $user->issueAccessToken();

            if (!$user->save(false)) {
                throw new \RuntimeException('Could not create user.');
            }

            $rows = array_map(static fn($skillId) => [$user->id, $skillId], $this->skill_ids);
            Yii::$app->db->createCommand()
                ->batchInsert('user_skill', ['user_id', 'skill_id'], $rows)
                ->execute();

            $transaction->commit();
            return ['token' => $token, 'user' => $user->toAuthArray()];
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }
}
```

- [ ] **Step 4: Run the model test and PHP syntax checks**

```powershell
php tests/auth_model_smoke.php
php -l models/User.php
php -l models/RegisterForm.php
```

Expected: `Auth model smoke test passed` and no syntax errors.

### Task 4: HTTP Auth and Skills Endpoints

**Files:**
- Create: `backend/models/LoginForm.php`
- Create: `backend/controllers/AuthController.php`
- Create: `backend/controllers/SkillController.php`
- Create: `backend/tests/api_smoke.php`

**Interfaces:**
- Consumes: `RegisterForm::register()`, `User::issueAccessToken()`, `User::toAuthArray()` from Task 3 and routes from Task 1.
- Produces: the three Day 1 HTTP endpoints and a localhost-only API smoke command.

- [ ] **Step 1: Write the failing HTTP smoke test**

Create `backend/tests/api_smoke.php` with a JSON helper, assertions, and this exact flow:

```php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(require dirname(__DIR__) . '/config/console.php');

use app\models\User;

$baseUrl = getenv('API_URL') ?: 'http://localhost:8080/api';
$host = parse_url($baseUrl, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('API smoke tests may target localhost only.');
}

function request($method, $url, $body = null) {
    $headers = "Accept: application/json\r\n";
    $content = '';
    if ($body !== null) {
        $headers .= "Content-Type: application/json\r\n";
        $content = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headers,
        'content' => $content,
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    $raw = file_get_contents($url, false, $context);
    if ($raw === false) {
        throw new RuntimeException("Request failed: {$method} {$url}");
    }
    $statusLine = $http_response_header[0];
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);
    $status = (int)$matches[1];
    return [$status, json_decode($raw, true), $raw];
}

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$email = 'api-' . bin2hex(random_bytes(8)) . '@example.com';
$password = 'correct-horse-123';
$payload = [
    'email' => $email,
    'password' => $password,
    'display_name' => 'API Smoke',
    'faculty' => 'Test',
    'major' => 'Test',
    'year' => 3,
    'line_id' => 'api.smoke',
    'skill_ids' => [1, 2],
];
$userId = null;

try {
    [$status, $skills] = request('GET', $baseUrl . '/skills');
    check($status === 200, 'GET skills did not return 200');
    check(array_column($skills, 'name') === [
        'ตัดต่อ', 'เขียนเว็บ', 'ถ่ายภาพ', 'UI/UX', 'เขียนคอนเทนต์', 'วิเคราะห์ข้อมูล',
    ], 'Skills response differs');

    [$status, $registered] = request('POST', $baseUrl . '/auth/register', $payload);
    check($status === 201, 'Register did not return 201: ' . json_encode($registered));
    check(strlen($registered['token']) >= 64, 'Register token missing');
    check(!isset($registered['user']['password']), 'Password leaked');
    check(!isset($registered['user']['password_hash']), 'Password hash leaked');
    check(!isset($registered['user']['access_token_hash']), 'Token hash leaked');
    $firstToken = $registered['token'];
    $userId = $registered['user']['id'];

    [$status, $duplicate] = request('POST', $baseUrl . '/auth/register', $payload);
    check($status === 422, 'Duplicate email did not return 422');
    check(in_array('email', array_column($duplicate, 'field'), true), 'Email error missing');

    $invalidSkills = $payload;
    $invalidSkills['email'] = 'other-' . bin2hex(random_bytes(4)) . '@example.com';
    $invalidSkills['skill_ids'] = [999999];
    [$status] = request('POST', $baseUrl . '/auth/register', $invalidSkills);
    check($status === 422, 'Unknown skill did not return 422');

    $missingName = $payload;
    $missingName['email'] = 'missing-' . bin2hex(random_bytes(4)) . '@example.com';
    unset($missingName['display_name']);
    [$status, $missingErrors] = request('POST', $baseUrl . '/auth/register', $missingName);
    check($status === 422, 'Missing display name did not return 422');
    check(in_array('display_name', array_column($missingErrors, 'field'), true), 'Display name error missing');

    [$status, $wrongLogin] = request('POST', $baseUrl . '/auth/login', [
        'email' => $email,
        'password' => 'wrong-password',
    ]);
    check($status === 401, 'Wrong password did not return 401');
    check($wrongLogin['message'] === 'Invalid email or password.', 'Login error differs');

    [$status] = request('POST', $baseUrl . '/auth/register', '{broken-json');
    check($status === 400, 'Malformed JSON did not return 400');

    [$status, $loggedIn] = request('POST', $baseUrl . '/auth/login', [
        'email' => $email,
        'password' => $password,
    ]);
    check($status === 200, 'Valid login did not return 200');
    $secondToken = $loggedIn['token'];
    check($secondToken !== $firstToken, 'Login did not rotate token');
    check(User::findIdentityByAccessToken($firstToken) === null, 'Old token still resolves');
    $identity = User::findIdentityByAccessToken($secondToken);
    check($identity && (int)$identity->id === $userId, 'New token does not resolve');

    echo "API smoke test passed\n";
} finally {
    if ($userId !== null) {
        $user = User::findOne($userId);
        if ($user !== null) {
            $user->delete();
        }
    }
}
```

Start the API from `backend`:

```powershell
php yii serve --port=8080
```

In another terminal, run:

```powershell
php tests/api_smoke.php
```

Expected before controller implementation: FAIL because the API routes return 404.

- [ ] **Step 2: Implement login validation and authentication**

Create `backend/models/LoginForm.php`:

```php
<?php

namespace app\models;

use Yii;
use yii\base\Model;

class LoginForm extends Model
{
    public $email;
    public $password;

    public function rules()
    {
        return [
            ['email', 'filter', 'filter' => static fn($value) => is_string($value) ? mb_strtolower(trim($value)) : $value],
            [['email', 'password'], 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 190],
            ['password', 'string', 'max' => 72],
        ];
    }

    public function authenticate()
    {
        $user = User::findByEmail($this->email);
        if ($user === null || !Yii::$app->security->validatePassword($this->password, $user->password_hash)) {
            return null;
        }
        return $user;
    }
}
```

- [ ] **Step 3: Implement the three controllers**

Create `backend/controllers/AuthController.php`:

```php
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
```

Create `backend/controllers/SkillController.php`:

```php
<?php

namespace app\controllers;

use app\models\Skill;
use yii\filters\VerbFilter;
use yii\rest\Controller;

class SkillController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['index' => ['GET']],
            ],
        ]);
    }

    public function actionIndex()
    {
        return array_map(static function ($skill) {
            return ['id' => (int)$skill->id, 'name' => $skill->name];
        }, Skill::find()->orderBy(['id' => SORT_ASC])->all());
    }
}
```

- [ ] **Step 4: Run all backend checks**

```powershell
php tests/schema_smoke.php
php tests/auth_model_smoke.php
php tests/api_smoke.php
php -l controllers/AuthController.php
php -l controllers/SkillController.php
php -l models/LoginForm.php
```

Expected: all three smoke tests pass and syntax checks report no errors. Stop here for backend review before starting frontend.

### Task 5: Vite Application and Real API Client

**Files:**
- Create through Vite: `frontend/package.json` and standard React template files
- Modify: `frontend/vite.config.js`
- Create: `frontend/.env.example`
- Create: `frontend/src/api/client.js`
- Create: `frontend/src/api/auth.js`
- Create: `frontend/src/api/skills.js`

**Interfaces:**
- Consumes: the running Day 1 API at `http://localhost:8080/api`.
- Produces: `register(payload)`, `login(payload)`, `getSkills()`, `apiFetch()`, token helpers, and `ApiError`.

- [ ] **Step 1: Scaffold React and install the one routing dependency**

Run from project root:

```powershell
npm create vite@latest frontend -- --template react
Set-Location frontend
npm install
npm install react-router-dom
```

Do not install Axios, a CSS framework, a state manager, or a frontend test runner.

- [ ] **Step 2: Configure the same-origin proxy**

Replace `frontend/vite.config.js` with:

```js
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://localhost:8080',
    },
  },
})
```

Create `frontend/.env.example`:

```dotenv
VITE_API_URL=/api
```

No real `.env` is needed for Day 1 because `/api` is the default.

- [ ] **Step 3: Implement centralized Fetch, error mapping, and token handling**

Create `frontend/src/api/client.js`:

```js
const API_URL = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '')
const TOKEN_KEY = 'team_finder_token'

export class ApiError extends Error {
  constructor(status, data) {
    const message = Array.isArray(data)
      ? 'กรุณาตรวจสอบข้อมูลในฟอร์ม'
      : data?.message || (status === 429
        ? 'มีการเรียกใช้งานถี่เกินไป กรุณารอสักครู่'
        : 'ไม่สามารถทำรายการได้ กรุณาลองใหม่')
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.data = data
  }
}

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function saveToken(token) {
  localStorage.setItem(TOKEN_KEY, token)
}

export function clearToken() {
  localStorage.removeItem(TOKEN_KEY)
}

export function getFieldErrors(error) {
  if (!(error instanceof ApiError) || !Array.isArray(error.data)) return {}
  return Object.fromEntries(error.data.map(({ field, message }) => [field, message]))
}

export async function apiFetch(path, { method = 'GET', body, auth = false } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (auth) {
    const token = getToken()
    if (token) headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${API_URL}${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  const data = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401 && auth) {
      clearToken()
      window.location.replace('/login')
    }
    throw new ApiError(response.status, data)
  }
  return data
}
```

Create `frontend/src/api/auth.js`:

```js
import { apiFetch } from './client'

export function register(payload) {
  return apiFetch('/auth/register', { method: 'POST', body: payload })
}

export function login(payload) {
  return apiFetch('/auth/login', { method: 'POST', body: payload })
}
```

Create `frontend/src/api/skills.js`:

```js
import { apiFetch } from './client'

export function getSkills() {
  return apiFetch('/skills')
}
```

- [ ] **Step 4: Verify the unchanged template still builds with the new modules**

Run from `frontend`:

```powershell
npm run lint
npm run build
```

Expected: lint and build pass. API behavior is tested through the real forms in Task 6 rather than a mock.

### Task 6: Register, Login, Logout, and Responsive UI

**Files:**
- Modify: `frontend/src/main.jsx`
- Modify: `frontend/src/App.jsx`
- Modify: `frontend/src/index.css`
- Create: `frontend/src/components/AuthLinks.jsx`
- Create: `frontend/src/pages/RegisterPage.jsx`
- Create: `frontend/src/pages/LoginPage.jsx`

**Interfaces:**
- Consumes: `register()`, `login()`, `getSkills()`, token helpers, and `ApiError` from Task 5.
- Produces: `/register`, `/login`, `/`, redirect behavior, and local logout.

- [ ] **Step 1: Wire BrowserRouter and token-owned routes**

Replace `frontend/src/main.jsx`:

```jsx
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import App from './App'
import './index.css'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </StrictMode>,
)
```

Replace `frontend/src/App.jsx`:

```jsx
import { useState } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import { clearToken, getToken, saveToken } from './api/client'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'

export default function App() {
  const [token, setToken] = useState(getToken)
  const [user, setUser] = useState(null)

  function handleAuth(result) {
    saveToken(result.token)
    setToken(result.token)
    setUser(result.user)
  }

  function logout() {
    clearToken()
    setToken(null)
    setUser(null)
  }

  return (
    <Routes>
      <Route
        path="/"
        element={token ? (
          <main className="shell">
            <section className="card home-card">
              <p className="eyebrow">TEAM FINDER</p>
              <h1>{user ? `ยินดีต้อนรับ, ${user.display_name}` : 'คุณเข้าสู่ระบบแล้ว'}</h1>
              <p>ระบบสมัครสมาชิกและเข้าสู่ระบบพร้อมใช้งาน ฟีเจอร์กระดานหาทีมจะอยู่ในงานถัดไป</p>
              <button className="button secondary" type="button" onClick={logout}>ออกจากระบบ</button>
            </section>
          </main>
        ) : <Navigate to="/login" replace />}
      />
      <Route path="/login" element={token ? <Navigate to="/" replace /> : <LoginPage onAuth={handleAuth} />} />
      <Route path="/register" element={token ? <Navigate to="/" replace /> : <RegisterPage onAuth={handleAuth} />} />
      <Route path="*" element={<Navigate to={token ? '/' : '/login'} replace />} />
    </Routes>
  )
}
```

Create `frontend/src/components/AuthLinks.jsx`:

```jsx
import { Link } from 'react-router-dom'

export default function AuthLinks({ register }) {
  return (
    <p className="auth-link">
      {register ? 'มีบัญชีอยู่แล้ว?' : 'ยังไม่มีบัญชี?'}{' '}
      <Link to={register ? '/login' : '/register'}>{register ? 'เข้าสู่ระบบ' : 'สมัครสมาชิก'}</Link>
    </p>
  )
}
```

- [ ] **Step 2: Build the registration form with real skills**

Create `frontend/src/pages/RegisterPage.jsx`:

```jsx
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { register } from '../api/auth'
import { ApiError, getFieldErrors } from '../api/client'
import { getSkills } from '../api/skills'
import AuthLinks from '../components/AuthLinks'

const initialForm = {
  email: '', password: '', display_name: '', faculty: '', major: '', year: '1', line_id: '',
}

export default function RegisterPage({ onAuth }) {
  const [form, setForm] = useState(initialForm)
  const [skills, setSkills] = useState([])
  const [selectedSkills, setSelectedSkills] = useState([])
  const [fieldErrors, setFieldErrors] = useState({})
  const [message, setMessage] = useState('')
  const [loadingSkills, setLoadingSkills] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const navigate = useNavigate()

  useEffect(() => {
    let active = true
    getSkills()
      .then((items) => { if (active) setSkills(items) })
      .catch(() => { if (active) setMessage('โหลดรายการทักษะไม่สำเร็จ กรุณารีเฟรชหน้า') })
      .finally(() => { if (active) setLoadingSkills(false) })
    return () => { active = false }
  }, [])

  function update(event) {
    setForm((current) => ({ ...current, [event.target.name]: event.target.value }))
  }

  function toggleSkill(id) {
    setSelectedSkills((current) => current.includes(id)
      ? current.filter((item) => item !== id)
      : [...current, id])
  }

  async function submit(event) {
    event.preventDefault()
    setFieldErrors({})
    setMessage('')
    if (selectedSkills.length === 0) {
      setFieldErrors({ skill_ids: 'เลือกอย่างน้อย 1 ทักษะ' })
      return
    }

    setSubmitting(true)
    try {
      const result = await register({ ...form, year: Number(form.year), skill_ids: selectedSkills })
      onAuth(result)
      navigate('/')
    } catch (error) {
      if (error instanceof ApiError) {
        setFieldErrors(getFieldErrors(error))
        setMessage(error.message)
      } else {
        setMessage('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ กรุณาตรวจว่า backend กำลังรันอยู่')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const fields = [
    ['display_name', 'ชื่อที่แสดง', 'text', 'name'],
    ['email', 'อีเมล', 'email', 'email'],
    ['password', 'รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)', 'password', 'new-password'],
    ['faculty', 'คณะ', 'text', 'organization'],
    ['major', 'สาขา', 'text', 'organization-title'],
    ['line_id', 'LINE ID', 'text', 'off'],
  ]

  return (
    <main className="shell">
      <section className="card auth-card">
        <p className="eyebrow">TEAM FINDER</p>
        <h1>สมัครสมาชิก</h1>
        <p>สร้างบัญชีเพื่อเริ่มหาทีมและสมาชิกร่วมโปรเจกต์</p>
        {message && <div className="alert" role="alert">{message}</div>}
        <form onSubmit={submit}>
          {fields.map(([name, label, type, autoComplete]) => (
            <label className="field" key={name}>
              <span>{label}</span>
              <input
                id={name}
                name={name}
                type={type}
                value={form[name]}
                onChange={update}
                autoComplete={autoComplete}
                required
                minLength={name === 'password' ? 8 : undefined}
                maxLength={name === 'password' ? 72 : undefined}
                aria-invalid={Boolean(fieldErrors[name])}
                aria-describedby={fieldErrors[name] ? `${name}-error` : undefined}
              />
              {fieldErrors[name] && <small id={`${name}-error`} className="field-error">{fieldErrors[name]}</small>}
            </label>
          ))}
          <label className="field">
            <span>ชั้นปี</span>
            <select
              id="year"
              name="year"
              value={form.year}
              onChange={update}
              required
              aria-invalid={Boolean(fieldErrors.year)}
              aria-describedby={fieldErrors.year ? 'year-error' : undefined}
            >
              {[1, 2, 3, 4, 5, 6, 7, 8].map((year) => <option key={year} value={year}>ปี {year}</option>)}
            </select>
            {fieldErrors.year && <small id="year-error" className="field-error">{fieldErrors.year}</small>}
          </label>
          <p className="privacy-note">LINE ID จะแสดงให้บุคคลที่เกี่ยวข้องเมื่อใบสมัครได้รับการตอบรับเท่านั้น</p>
          <fieldset
            className="skills-fieldset"
            disabled={loadingSkills || submitting}
            aria-describedby={fieldErrors.skill_ids ? 'skill-ids-error' : undefined}
          >
            <legend>ทักษะของคุณ</legend>
            {loadingSkills ? <p>กำลังโหลดทักษะ…</p> : (
              <div className="skill-grid">
                {skills.map((skill) => (
                  <label className="skill-option" key={skill.id}>
                    <input
                      type="checkbox"
                      checked={selectedSkills.includes(skill.id)}
                      onChange={() => toggleSkill(skill.id)}
                    />
                    <span>{skill.name}</span>
                  </label>
                ))}
              </div>
            )}
            {fieldErrors.skill_ids && <small id="skill-ids-error" className="field-error" role="alert">{fieldErrors.skill_ids}</small>}
          </fieldset>
          <button className="button" type="submit" disabled={submitting || loadingSkills}>
            {submitting ? 'กำลังสมัคร…' : 'สมัครสมาชิก'}
          </button>
        </form>
        <AuthLinks register />
      </section>
    </main>
  )
}
```

- [ ] **Step 3: Build login without redirecting its own 401**

Create `frontend/src/pages/LoginPage.jsx`:

```jsx
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { login } from '../api/auth'
import { ApiError, getFieldErrors } from '../api/client'
import AuthLinks from '../components/AuthLinks'

export default function LoginPage({ onAuth }) {
  const [form, setForm] = useState({ email: '', password: '' })
  const [fieldErrors, setFieldErrors] = useState({})
  const [message, setMessage] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const navigate = useNavigate()

  function update(event) {
    setForm((current) => ({ ...current, [event.target.name]: event.target.value }))
  }

  async function submit(event) {
    event.preventDefault()
    setMessage('')
    setFieldErrors({})
    setSubmitting(true)
    try {
      const result = await login(form)
      onAuth(result)
      navigate('/')
    } catch (error) {
      if (error instanceof ApiError) {
        setFieldErrors(getFieldErrors(error))
        setMessage(error.status === 401 ? 'อีเมลหรือรหัสผ่านไม่ถูกต้อง' : error.message)
      } else {
        setMessage('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ กรุณาตรวจว่า backend กำลังรันอยู่')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="shell">
      <section className="card auth-card">
        <p className="eyebrow">TEAM FINDER</p>
        <h1>เข้าสู่ระบบ</h1>
        <p>กลับไปหาทีมที่ใช่สำหรับโปรเจกต์ของคุณ</p>
        {message && <div className="alert" role="alert">{message}</div>}
        <form onSubmit={submit}>
          <label className="field">
            <span>อีเมล</span>
            <input
              id="email"
              name="email"
              type="email"
              value={form.email}
              onChange={update}
              autoComplete="email"
              required
              aria-invalid={Boolean(fieldErrors.email)}
              aria-describedby={fieldErrors.email ? 'email-error' : undefined}
            />
            {fieldErrors.email && <small id="email-error" className="field-error">{fieldErrors.email}</small>}
          </label>
          <label className="field">
            <span>รหัสผ่าน</span>
            <input
              id="password"
              name="password"
              type="password"
              value={form.password}
              onChange={update}
              autoComplete="current-password"
              required
              aria-invalid={Boolean(fieldErrors.password)}
              aria-describedby={fieldErrors.password ? 'password-error' : undefined}
            />
            {fieldErrors.password && <small id="password-error" className="field-error">{fieldErrors.password}</small>}
          </label>
          <button className="button" type="submit" disabled={submitting}>
            {submitting ? 'กำลังเข้าสู่ระบบ…' : 'เข้าสู่ระบบ'}
          </button>
        </form>
        <AuthLinks />
      </section>
    </main>
  )
}
```

- [ ] **Step 4: Add the complete responsive styling**

Replace `frontend/src/index.css` and remove `frontend/src/App.css` from the application imports; delete `App.css` only after confirming no file imports it:

```css
:root {
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  color: #17221b;
  background: #f3f6ef;
  font-synthesis: none;
  text-rendering: optimizeLegibility;
}

* { box-sizing: border-box; }
body { margin: 0; min-width: 320px; min-height: 100vh; }
button, input, select { font: inherit; }

.shell {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 40px 18px;
  background: radial-gradient(circle at top right, #dcebd2, transparent 45%), #f3f6ef;
}

.card {
  width: min(100%, 620px);
  padding: clamp(24px, 5vw, 44px);
  border: 1px solid #d8e0d3;
  border-radius: 22px;
  background: #fff;
  box-shadow: 0 18px 55px rgba(33, 58, 39, 0.09);
}

.home-card { text-align: center; }
.eyebrow { margin: 0 0 10px; color: #39724b; font-size: 0.78rem; font-weight: 800; letter-spacing: 0.16em; }
h1 { margin: 0 0 10px; font-size: clamp(1.8rem, 5vw, 2.5rem); line-height: 1.15; }
p { line-height: 1.65; }
form { display: grid; gap: 17px; margin-top: 26px; }
.field { display: grid; gap: 7px; font-weight: 650; }

input, select {
  width: 100%;
  min-height: 46px;
  padding: 10px 12px;
  border: 1px solid #bcc9b8;
  border-radius: 10px;
  background: #fff;
  color: #17221b;
}

input:focus, select:focus, button:focus-visible, a:focus-visible {
  outline: 3px solid #9ac8a5;
  outline-offset: 2px;
}

.button {
  min-height: 48px;
  padding: 12px 20px;
  border: 0;
  border-radius: 10px;
  background: #285e3b;
  color: #fff;
  font-weight: 750;
  cursor: pointer;
}

.button:hover { background: #1e4b2f; }
.button:disabled { cursor: wait; opacity: 0.65; }
.button.secondary { margin-top: 14px; background: #e3eee0; color: #1e4b2f; }
.alert { margin-top: 18px; padding: 12px 14px; border-radius: 10px; background: #fff0ed; color: #8e2f21; }
.field-error { color: #a02c20; font-weight: 500; }
.privacy-note { margin: -4px 0 0; color: #526457; font-size: 0.88rem; }
.skills-fieldset { margin: 0; padding: 16px; border: 1px solid #d8e0d3; border-radius: 12px; }
.skills-fieldset legend { padding: 0 6px; font-weight: 750; }
.skill-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.skill-option { display: flex; align-items: center; gap: 9px; font-weight: 500; }
.skill-option input { width: 18px; min-height: 18px; margin: 0; }
.auth-link { margin: 22px 0 0; text-align: center; }
.auth-link a { color: #285e3b; font-weight: 750; }

@media (max-width: 520px) {
  .shell { padding: 16px 12px; align-items: start; }
  .card { border-radius: 16px; }
  .skill-grid { grid-template-columns: 1fr; }
}
```

- [ ] **Step 5: Run static checks and the real browser flow**

Run from `frontend`:

```powershell
npm run lint
npm run build
npm run dev -- --host 127.0.0.1
```

With backend running on port 8080 and Vite on its printed port, verify in the browser:

1. Opening `/` without a token redirects to `/login`.
2. `/register` loads six skills through `/api/skills`.
3. Submitting without a skill shows a client-side skill error and sends no register request.
4. Register a unique student; response is 201, token is in `localStorage`, and `/` shows the display name.
5. Logout removes `team_finder_token` and returns to `/login`.
6. Wrong password shows `อีเมลหรือรหัสผ่านไม่ถูกต้อง` and no redirect loop occurs.
7. Correct password returns to `/`; reload retains the logged-in state without claiming a display name.
8. Repeating the same registration after logout shows the backend email error.
9. At 375px viewport width, no horizontal scrolling occurs and every control remains reachable by keyboard.
10. Console contains no token, password, or debug logging; Network shows only real `/api` calls.

Expected: all ten checks pass without mock data.

### Task 7: Durable Rules, API Documentation, and Final Handoff

**Files:**
- Create: `AGENTS.md`
- Create: `README.md`
- Create: `docs/api.md`
- Create: `docs/handoff.md`

**Interfaces:**
- Consumes: verified behavior and actual command output from Tasks 1–6.
- Produces: enough project context for another developer to install, run, test, and continue with Day 2 without rediscovering decisions.

- [ ] **Step 1: Add non-negotiable project rules**

Create `AGENTS.md` with these sections and exact rules:

```markdown
# AGENTS.md

## Ownership and Scope

- Backend lives in `backend/`; frontend lives in `frontend/`.
- Only backend owners change migrations or database schema.
- `docs/api.md` is the API source of truth. Change it in the same change set as any API behavior change.
- Keep the main flow working before adding secondary features.

## Backend Authorization Is Mandatory

- Authenticate every protected endpoint at backend with the Bearer token.
- Return 401 for missing, invalid, or expired authentication and 403 for an authenticated user without permission.
- Never treat hidden React controls as authorization.
- Derive user IDs from the authenticated identity, not client-supplied owner/user IDs.
- Recheck post ownership, application state, and slot availability in the backend action that changes data.
- Use transactions for multi-row writes that must succeed or fail together.

## LINE ID Privacy

- Never expose another user's `line_id` unless the requester has the related application for the same post and its status is `accepted`.
- For an owner viewing applicants, include `applicant.line_id` only on accepted applications.
- For an applicant viewing a post owner, include `owner.line_id` only when their own application is accepted.
- Omit the `line_id` key for pending, rejected, or absent applications; do not substitute another user's value from cache.
- A user's own `line_id` is allowed only in contract-defined self responses such as register/login/me.
- Never put LINE IDs in feeds, logs, or cross-user caches.

## Secrets and Authentication

- Never commit `.env`, secret-bearing environment variants, or `backend/config/db-local.php`, including with force-add.
- Commit only `.env.example` and `db-local.example.php` files containing non-secret placeholders.
- Never commit or log real passwords, raw tokens, token hashes, DB credentials, or production secrets.
- If a secret enters Git history, rotate/revoke it immediately; deleting it in a later commit is insufficient.
- Hash passwords with Yii2 Security and never store plaintext passwords.
- Do not return password hashes, raw token hashes, or internal fields in API responses.

## Verification and Git

- Add a repeatable success and failure check for every new endpoint.
- For protected resources, test unauthenticated, non-owner, and owner cases.
- For LINE ID responses, test pending, rejected, and accepted cases when those endpoints are implemented.
- Run migrations against an empty local test database before merging schema changes.
- Do not weaken or delete a failing test to hide a backend bug.
- Run relevant backend smoke tests and frontend lint/build before handoff.
- Ask the user for fresh confirmation before every Git mutation, including init, add, commit, and push.
```

- [ ] **Step 2: Write installation and run instructions that match the real files**

Create `README.md` with:

- The Day 1 scope and explicit non-goals.
- Prerequisite versions: PHP 8.2+, Composer 2, Node 20+, npm 10+, MySQL/MariaDB through XAMPP.
- Backend commands, run from `backend`:

```powershell
composer install
Copy-Item config/db-local.example.php config/db-local.php
php -r '$pdo = new PDO("mysql:host=127.0.0.1;port=3306", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); $pdo->exec("CREATE DATABASE IF NOT EXISTS team_finder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");'
php yii migrate --interactive=0
php yii serve --port=8080
```

- Frontend commands, run from `frontend` in a second terminal:

```powershell
npm install
npm run dev
```

- Backend checks, with the API server running for the API test:

```powershell
php tests/schema_smoke.php
php tests/auth_model_smoke.php
php tests/api_smoke.php
```

- Frontend checks:

```powershell
npm run lint
npm run build
```

- Local credential edits belong only in ignored `backend/config/db-local.php`.
- No `.env` copy is needed unless overriding `VITE_API_URL`.
- Token lifetime is seven days; login rotates the token; logout currently clears only the browser token.
- Links to `docs/api.md`, `docs/handoff.md`, and the approved spec.

- [ ] **Step 3: Publish the verified API contract**

Create `docs/api.md` containing the exact request/response examples, required fields, length/range rules, 201/200/400/401/403/404/422 formats, token rules, skill order, date formats, and timezone from Sections 5–6 of `docs/superpowers/specs/2026-11-07-day-1-full-stack-design.md`.

After writing it, compare each statement with the actual models/controllers. If any behavior differs, either fix the implementation to meet the approved spec or record the deliberate contract change in both files before proceeding.

- [ ] **Step 4: Record the observed handoff, not a prediction**

Create `docs/handoff.md` after all checks with:

- Implemented endpoints and frontend routes.
- Exact six-table schema and seed list.
- Token/password/LINE-ID decisions.
- Commands actually run and their observed PASS/FAIL results.
- Browser flows actually exercised, including the mobile-width check.
- Files intentionally local-only and excluded from Git.
- Out-of-scope items: posts, feed, applications, `/api/me*`, rate limiting, and deployment.
- Next implementation slice: Day 2 post creation, feed, and detail endpoints with matching frontend screens.
- GitHub URL and commit hash only after the user approves and the push succeeds; before that, state `Git repository has not been initialized or pushed.`

- [ ] **Step 5: Run final verification and inspect the deliverable**

From `backend`:

```powershell
php tests/schema_smoke.php
php tests/auth_model_smoke.php
php tests/api_smoke.php
```

From `frontend`:

```powershell
npm run lint
npm run build
```

From project root:

```powershell
Get-ChildItem frontend -Force -File -Filter '.env*'
Get-ChildItem backend/config -Force -File -Filter '*local*'
Get-ChildItem backend/controllers,backend/models,backend/config,frontend/src -Recurse -File -Include '*.php','*.js','*.jsx' |
  Select-String -Pattern 'console\.log|dangerouslySetInnerHTML'
```

Expected:

- Every smoke test, lint, and build passes.
- Only `.env.example` exists under frontend unless the user deliberately created an ignored local override.
- `db-local.php` is local-only and its path appears in `.gitignore`; only the example is safe to publish.
- The source scan returns no `console.log` or `dangerouslySetInnerHTML` matches.
- `README.md`, `docs/api.md`, and `docs/handoff.md` agree with observed behavior.

- [ ] **Step 6: Stop before Git and request execution handoff confirmation**

Report changed files, test evidence, any local service limitation, and the remaining Day 2 scope. Ask separately before any Git initialization, staging, commit, repository creation, or push. Do not treat this plan or prior design approval as Git permission.
