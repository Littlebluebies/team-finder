# Team Finder — Day 1 Handoff

วันที่ตรวจ: 2026-11-08  
ขอบเขต: เฉพาะสิ่งที่ implement และตรวจแล้วจริงใน Day 1

## สิ่งที่ใช้งานได้

### API (3 endpoints)

| Method | Path | Auth | Success |
| --- | --- | --- | --- |
| POST | `/api/auth/register` | ไม่ต้อง | `201` + token + user |
| POST | `/api/auth/login` | ไม่ต้อง | `200` + token ใหม่ + user |
| GET | `/api/skills` | ไม่ต้อง | `200` + array 6 ทักษะ |

Contract ฉบับเต็มอยู่ที่ [`docs/api.md`](api.md)

### Frontend routes

| Route | พฤติกรรม |
| --- | --- |
| `/register` | โหลดทักษะจาก API จริง, ฟอร์มสมัคร, แสดง error รายช่อง, สำเร็จแล้วไป `/` |
| `/login` | ฟอร์มเข้าสู่ระบบ, 401 แสดงข้อความไทยในฟอร์ม |
| `/` | ถ้าไม่มี token ไป `/login`; ถ้ามี token แสดงสถานะล็อกอินและปุ่มออกจากระบบ |
| อื่น ๆ | redirect ไป `/` หรือ `/login` ตามสถานะ token |

## สภาพแวดล้อมที่ตรวจ

- PHP 8.2.12 (CLI, `pdo_mysql` เปิด)
- Composer 2.10.2
- Yii 2.0.55
- MariaDB 10.4.32 ที่ `127.0.0.1:3306`
- Node 20.20.2, npm 10.8.2
- Backend ทำงานที่ `http://localhost:8080`
- Frontend dev server ใช้ Vite default `http://localhost:5173`

## Schema จริง — 6 ตาราง

ทุกตารางเป็น `InnoDB`, `utf8mb4`, `utf8mb4_unicode_ci` สร้างโดย `backend/migrations/m261107_000001_create_team_finder_schema.php`

### `user`

| Column | Type | Constraints |
| --- | --- | --- |
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `email` | VARCHAR(190) | NOT NULL, unique `uq_user_email` |
| `password_hash` | VARCHAR(255) | NOT NULL |
| `display_name` | VARCHAR(100) | NOT NULL |
| `faculty` | VARCHAR(150) | NOT NULL |
| `major` | VARCHAR(150) | NOT NULL |
| `year` | TINYINT UNSIGNED | NOT NULL, CHECK 1–8 (`ck_user_year`) |
| `line_id` | VARCHAR(50) | NOT NULL |
| `bio` | TEXT | NULL |
| `access_token_hash` | CHAR(64) | NULL, unique `uq_user_access_token_hash` |
| `access_token_expires_at` | DATETIME | NULL |
| `created_at` | DATETIME | NOT NULL, DEFAULT `CURRENT_TIMESTAMP` |

### `skill`

| Column | Type | Constraints |
| --- | --- | --- |
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `name` | VARCHAR(100) | NOT NULL, unique `uq_skill_name` |

Seed เรียงตาม `id`: 1 `ตัดต่อ`, 2 `เขียนเว็บ`, 3 `ถ่ายภาพ`, 4 `UI/UX`, 5 `เขียนคอนเทนต์`, 6 `วิเคราะห์ข้อมูล`

### `user_skill`

| Column | Type | Constraints |
| --- | --- | --- |
| `user_id` | INT UNSIGNED | NOT NULL, PK ร่วม |
| `skill_id` | INT UNSIGNED | NOT NULL, PK ร่วม |

- PK `(user_id, skill_id)`
- index `idx_user_skill_skill (skill_id)`
- FK `user_id` → `user.id` ON DELETE CASCADE ON UPDATE CASCADE
- FK `skill_id` → `skill.id` ON DELETE CASCADE ON UPDATE CASCADE

### `post`

| Column | Type | Constraints |
| --- | --- | --- |
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `owner_id` | INT UNSIGNED | NOT NULL, index `idx_post_owner` |
| `title` | VARCHAR(200) | NOT NULL |
| `description` | TEXT | NOT NULL |
| `category` | VARCHAR(20) | NOT NULL, CHECK `course` / `contest` / `personal` |
| `slots` | SMALLINT UNSIGNED | NOT NULL, CHECK > 0 |
| `deadline` | DATE | NOT NULL |
| `status` | VARCHAR(10) | NOT NULL, DEFAULT `open`, CHECK `open` / `closed` |
| `created_at` | DATETIME | NOT NULL, DEFAULT `CURRENT_TIMESTAMP` |

FK `owner_id` → `user.id` ON DELETE RESTRICT ON UPDATE CASCADE

### `post_skill`

| Column | Type | Constraints |
| --- | --- | --- |
| `post_id` | INT UNSIGNED | NOT NULL, PK ร่วม |
| `skill_id` | INT UNSIGNED | NOT NULL, PK ร่วม |

- PK `(post_id, skill_id)`
- index `idx_post_skill_skill (skill_id)`
- FK `post_id` → `post.id` CASCADE/CASCADE
- FK `skill_id` → `skill.id` CASCADE/CASCADE

### `application`

| Column | Type | Constraints |
| --- | --- | --- |
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `post_id` | INT UNSIGNED | NOT NULL |
| `user_id` | INT UNSIGNED | NOT NULL |
| `message` | TEXT | NOT NULL |
| `status` | VARCHAR(10) | NOT NULL, DEFAULT `pending`, CHECK `pending` / `accepted` / `rejected` |
| `created_at` | DATETIME | NOT NULL, DEFAULT `CURRENT_TIMESTAMP` |

- unique `uq_application_post_user (post_id, user_id)`
- index `idx_application_user (user_id)`
- FK `post_id` → `post.id` RESTRICT/CASCADE
- FK `user_id` → `user.id` RESTRICT/CASCADE

## Token, password และ LINE ID

- Token ดิบ 64 ตัวอักษรจาก `Yii::$app->security->generateRandomString(64)`; เก็บเฉพาะ `hash('sha256', $token)` ใน `access_token_hash`
- อายุ 7 วัน = 604800 วินาที; คำนวณด้วย `date()` ของแอป timezone `Asia/Bangkok`
- การยืนยันตัวตนค้นทั้ง hash และเงื่อนไข `access_token_expires_at > now`
- Login สำเร็จทุกครั้งออก token ใหม่ทับ token เดิม; token เดิมใช้ไม่ได้ทันที
- Register ใช้ `generatePasswordHash()` และ login ใช้ `validatePassword()`; ไม่มี plaintext password
- Response register/login คืน `line_id` ของเจ้าของบัญชีเท่านั้น
- ยังไม่มี endpoint ที่เปิด LINE ID ของผู้อื่น; กฎ accepted-only อยู่ใน `AGENTS.md` สำหรับงานถัดไป
- Logout ฝั่งเว็บล้าง `localStorage` key `team_finder_token` เท่านั้น ไม่มี server-side revoke

## คำสั่งที่รันจริงและผล

### Backend (จาก `backend`)

| Command | ผล |
| --- | --- |
| `php tests/schema_smoke.php` | PASS 6 บรรทัด: tables, seed, email uniqueness, application uniqueness, foreign keys, constraints; `Schema smoke test passed`; exit 0 |
| `php tests/auth_model_smoke.php` | `Auth model smoke test passed`; exit 0 |
| `php tests/api_smoke.php` | `API smoke test passed`; exit 0 (backend ที่ 8080 เข้าถึงได้) |
| `php -l` กับไฟล์ PHP 16 ไฟล์ใน `controllers`, `models`, `config`, `migrations`, `tests`, `web` | `No syntax errors detected` ทุกไฟล์; 0 failures |

`api_smoke.php` ตรวจเพิ่มว่า: skills ครบ 6 ตามลำดับ, register 201 + token, ไม่มี password/hash/token hash ใน response, email ซ้ำ 422, skill id ที่ไม่มีอยู่จริง 422, ขาด `display_name` 422, login ผิด 401 พร้อมข้อความ `Invalid email or password.`, JSON เสีย 400, login ถูก 200 และ rotate token, token เดิมใช้ไม่ได้, token ใหม่ใช้ได้

### Frontend (จาก `frontend`)

| Command | ผล |
| --- | --- |
| `npm run lint` | `oxlint` ไม่มี error/warning; exit 0 |
| `npm run build` | Vite 8.3.6, 29 modules transformed, `built in 178ms`; `dist/index.html` 0.53 kB, CSS 2.15 kB, JS 266.97 kB; exit 0 |

### HTTP ที่ตรวจซ้ำในรอบ handoff

| Request | ผล |
| --- | --- |
| `GET /api/skills` | 200, JSON 6 ทักษะเรียงถูก |
| `POST /api/auth/register` ด้วยข้อมูลไม่ครบ | 422, array `{field,message}` |
| `POST /api/auth/register` ด้วย `skill_ids: [1,1]` | 422, `Skill IDs must not contain duplicates.` |
| `POST /api/auth/login` ด้วยอีเมลที่ไม่มี | 401, `Invalid email or password.` |
| `POST /api/auth/register` ด้วย JSON เสีย | 400, Yii2 Bad Request |
| `GET /api/does-not-exist` | 404, Yii2 Not Found |
| `GET /api/auth/login` (method ผิด) | 404, Yii2 Not Found (ไม่ใช่ 405) |

หลัง smoke test ไม่มี user ค้าง: `SELECT COUNT(*) FROM user` = 0

### Secret และ source scan

- `frontend` มีเฉพาะ `.env.example`; ไม่มี `.env` จริง
- `backend/config/db-local.php` มีอยู่จริงแต่ SHA-256 เท่ากับ `db-local.example.php` (placeholder ไม่มี secret)
- ไม่พบ `.npmrc`, `auth.json`, private key, registry token หรือ pattern `sk-*`, `AIza*`, `ghp_*`, `AKIA*`
- Source scan `backend/controllers`, `backend/models`, `backend/config`, `backend/migrations`, `backend/tests`, `backend/web`, `frontend/src` สำหรับ `console.log`, `console.debug`, `dangerouslySetInnerHTML`: ไม่พบ match
- ยังไม่มี Git repository จึงรัน `git check-ignore` ในโปรเจกต์ไม่ได้ (และห้าม `git init`); ตรวจ pattern ด้วย temp repo ที่คัดลอก `.gitignore` พบว่า `db-local.php`, `backend/vendor/`, `frontend/node_modules/`, `frontend/dist/`, `.env`, `.env.local`, `backend/runtime/*` ถูก ignore และ `.env.example` / `db-local.example.php` ไม่ถูก ignore

## Browser flows ที่เคยทดสอบจริง

Task 6 reviewer รัน Chromium จริงกับ backend + Vite dev server และบันทึกไว้ใน SDD ledger ว่าครอบคลุม:

- routing ระหว่าง `/`, `/login`, `/register`
- เปิด `/` โดยไม่มี token → ไป `/login`
- Logout → ล้าง token → ไป `/login`
- Reload ขณะมี token → หน้า `/` แสดงสถานะล็อกอินแบบไม่ระบุชื่อ (เพราะยังไม่มี `/api/me`)
- Login รหัสผิด → 401 → ข้อความไทย `อีเมลหรือรหัสผ่านไม่ถูกต้อง` ไม่เกิด redirect loop
- สมัครด้วย email ซ้ำ → 422 → error แสดงใต้ช่อง email
- เดินด้วย keyboard ผ่าน controls ทั้งหมดได้ และมี focus ring
- ความกว้าง 375px → ไม่มี horizontal overflow

ส่วน flow ที่ยืนยันด้วย HTTP จริง (ไม่ใช่ browser) ใน Task 6 และ Task 7: `GET /api/skills` 200 ครบ 6, register 201 + token, login 200 + token ใหม่, duplicate email 422, wrong password 401, malformed JSON 400 ส่วนการตรวจ Network panel ว่า token ไม่โผล่ใน console/UI ไม่มีบันทึกแยกในหลักฐาน review; ตรวจจากโค้ดแล้วว่าไม่มี `console.*` และไม่มีการแสดง token ใน UI

หลัง review มี hotfix: `index.html` เปลี่ยนเป็น `lang="th"` และ title `Team Finder — หาทีมและสมาชิกโปรเจกต์`; `index.css` เพิ่ม contrast ของ border เป็น `#6f8069` และ focus ring `#3f6b4c`; fieldset ทักษะมี `aria-invalid`; lint/build ผ่านอีกครั้ง

รอบ Task 7 นี้ไม่ได้เปิด browser; ตรวจซ้ำด้วย HTTP จริงเท่านั้น

## ไฟล์ที่เป็น local-only และไม่ควร commit

| Path | เหตุผล / ignore rule |
| --- | --- |
| `backend/config/db-local.php` | credential เครื่องตัวเอง; `.gitignore` บรรทัด `backend/config/db-local.php` |
| `backend/vendor/` | Composer dependencies |
| `backend/runtime/*` | runtime cache/log (ยกเว้น `.gitignore` ในโฟลเดอร์) |
| `backend/web/assets/*` | generated assets (ยกเว้น `.gitignore`) |
| `frontend/node_modules/` | npm dependencies |
| `frontend/dist/` | production build output |
| `.env`, `.env.*` | secrets; อนุญาตเฉพาะ `.env.example` |
| `*.log`, `.DS_Store`, `Thumbs.db` | local/system files |

ไฟล์ที่ commit ได้: `backend/config/db-local.example.php`, `frontend/.env.example` (placeholder เท่านั้น)

## ข้อจำกัดที่รู้อยู่

- ยังไม่มี `/api/me`; หลัง reload หน้า `/` รู้แค่ว่ามี token ไม่รู้ชื่อผู้ใช้ และไม่ตรวจว่า token ยังไม่หมดอายุ
- Token เก็บใน `localStorage`; ถ้า token หมดอายุระหว่างใช้งาน จะรู้เมื่อมี request ที่ใช้ token ซึ่ง Day 1 ยังไม่มี
- Logout ไม่ revoke token ฝั่ง server; token เดิมยังใช้ได้จนหมดอายุ 7 วัน
- ไม่มี rate limit, lockout, email verification, password reset
- Validation message จาก backend เป็นภาษาอังกฤษและแสดงใต้ช่องตามที่ส่งมา; ข้อความระดับฟอร์มเป็นภาษาไทย
- ไม่มี CORS; development พึ่ง Vite proxy และ production ยังไม่มี config
- `YII_DEBUG` เปิดใน `web/index.php` (development) ทำให้ error บางตัวมี `previous`; ต้องปิดเมื่อทำ production
- Method ผิดบน path `/api/*` ที่รู้จักคืน 404; แต่ bare controller path จาก Yii default routing (`GET /auth/login`, `GET /auth/register`) คืน 405 เพราะ `VerbFilter` และ `GET /skill/index` ยังคืน 200 — path เหล่านี้ไม่อยู่ใน contract
- `backend/config/web.php` ยังไม่เปิด `enableStrictParsing` จึงมี route alias จาก default routing; ต้องปิดในงาน Day 2
- Response เป็น JSON ตาม contract เมื่อ client ส่ง `Accept: application/json`; Yii ยัง negotiate เป็น XML สำหรับ `Accept: application/xml` หรือ Accept แบบ browser ซึ่ง XML ไม่อยู่ใน contract
- MariaDB session timezone เป็น `SYSTEM`; แอปใช้ `Asia/Bangkok` และเวลาที่ตรวจตรงกัน แต่ `created_at` มาจาก DB `CURRENT_TIMESTAMP` จึงควรระวังถ้าย้ายเครื่อง/ตั้ง DB timezone ต่าง
- `frontend/README.md` ยังเป็น template ของ Vite; ใช้ `README.md` ที่ root เป็นหลัก
- ไฟล์ template ที่ยังไม่ใช้ (`src/assets/hero.png`, `react.svg`, `vite.svg`, `public/icons.svg`) ยังอยู่

## สิ่งที่อยู่นอกรอบ Day 1

- Post creation, feed, post detail, filter
- Application สมัครเข้าทีม, accept/reject, close post
- `/api/me*`, profile
- การเปิดเผย LINE ID ของผู้อื่น
- Rate limiting/429, notification, chat, admin, profile image
- Nginx, HTTPS, production deployment

## งานถัดไป — Day 2

Post creation, feed และ detail endpoints พร้อมหน้าจอ frontend ที่เรียก API จริง:

1. เพิ่ม protected endpoints ที่อ่าน identity จาก Bearer token และคืน 401/403 ตามกฎใน `AGENTS.md`
2. เพิ่ม post create/feed/detail โดยใช้ตาราง `post` และ `post_skill` ที่มีอยู่ พร้อม validation ตาม schema
3. เพิ่มหน้าสร้างโพสต์, feed และรายละเอียดโพสต์ใน React โดยใช้ `src/api/` และ `apiFetch` เดิม
4. เพิ่ม smoke test success/failure ของทุก endpoint ใหม่ และอัปเดต `docs/api.md` ในชุดเดียวกัน
5. เปิด `enableStrictParsing => true` หรือเพิ่ม catch-all 404 rule เพื่อปิด bare controller path (`/auth/*`, `/skill/index`) ให้เหลือเฉพาะ `/api/*` ตาม contract

## สถานะ Git

- Repository: `https://github.com/Littlebluebies/team-finder.git`
- Branch หลัก: `main`
- ดู commit hash ล่าสุดได้จาก `git log --oneline -1` หรือหน้า repository
- การกระทำกับ Git ทุกชนิดหลังจากนี้ต้องได้รับการยืนยันใหม่จากผู้ใช้ก่อน
