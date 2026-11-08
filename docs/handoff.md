# Team Finder — Handoff (Day 1–2)

วันที่ตรวจ: 2026-11-08  
ขอบเขต: เฉพาะสิ่งที่ implement และตรวจแล้วจริงถึง Day 2

## สิ่งที่ใช้งานได้

### API (6 endpoints)

| Method | Path | Auth | Success |
| --- | --- | --- | --- |
| POST | `/api/auth/register` | ไม่ต้อง | `201` + token + user |
| POST | `/api/auth/login` | ไม่ต้อง | `200` + token ใหม่ + user |
| GET | `/api/skills` | ไม่ต้อง | `200` + array 6 ทักษะ |
| POST | `/api/posts` | Bearer | `201` + post |
| GET | `/api/posts` | Bearer | `200` + feed เรียงใหม่สุดก่อน |
| GET | `/api/posts/{id}` | Bearer | `200` + post, `404` ถ้าไม่พบ |

Contract ฉบับเต็มอยู่ที่ [`docs/api.md`](api.md)

### Frontend routes

| Route | พฤติกรรม |
| --- | --- |
| `/` | feed โพสต์ทั้งหมด (ต้องมี token) |
| `/posts/new` | ฟอร์มสร้างโพสต์ (ต้องมี token) |
| `/posts/:id` | รายละเอียดโพสต์ (ต้องมี token) |
| `/login` | ฟอร์มเข้าสู่ระบบ, 401 แสดงข้อความไทยในฟอร์ม |
| `/register` | ฟอร์มสมัคร + เลือกทักษะจาก API จริง |
| อื่น ๆ | redirect ไป `/` หรือ `/login` ตามสถานะ token |

เมื่อมี token จะแสดง topbar พร้อมชื่อผู้ใช้ (ถ้ามีข้อมูล) และปุ่มออกจากระบบ

## สภาพแวดล้อมที่ตรวจ

- PHP 8.2.12 (CLI, `pdo_mysql` เปิด)
- Composer 2.10.2
- Yii 2.0.55
- MariaDB 10.4.32 ที่ `127.0.0.1:3306`
- Node 20.20.2, npm 10.8.2
- Backend ทำงานที่ `http://localhost:8080`
- Frontend dev server ใช้ Vite default `http://localhost:5173`

## Schema จริง — 6 ตาราง

ทุกตารางเป็น `InnoDB`, `utf8mb4`, `utf8mb4_unicode_ci` สร้างโดย `backend/migrations/m261107_000001_create_team_finder_schema.php` ไม่มีการเปลี่ยน schema ใน Day 2

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

## Auth, token, password และ LINE ID

- Token ดิบ 64 ตัวอักษรจาก `Yii::$app->security->generateRandomString(64)`; เก็บเฉพาะ `hash('sha256', $token)` ใน `access_token_hash`
- อายุ 7 วัน = 604800 วินาที; คำนวณด้วย `date()` ของแอป timezone `Asia/Bangkok`
- Protected endpoint ใช้ `yii\filters\auth\HttpBearerAuth`; token หาย/ผิด/หมดอายุได้ `401`
- Register ใช้ `generatePasswordHash()` และ login ใช้ `validatePassword()`; ไม่มี plaintext password
- Login สำเร็จทุกครั้งออก token ใหม่ทับ token เดิม; token เดิมใช้ไม่ได้ทันที
- `POST /api/posts` derives `owner_id` จาก token ไม่รับ `owner_id` จาก client
- Feed/detail คืน `owner` เฉพาะ `id` และ `display_name`; ยังไม่คืน `line_id` ของผู้อื่น
- Response register/login คืน `line_id` ของเจ้าของบัญชีเท่านั้น
- ยังไม่มี endpoint สำหรับใบสมัครหรือการเปิด LINE ID ของผู้อื่น; กฎ accepted-only อยู่ใน `AGENTS.md` สำหรับงานถัดไป
- Logout ฝั่งเว็บล้าง `localStorage` key `team_finder_token` เท่านั้น ไม่มี server-side revoke
- API เป็น stateless: `enableSession = false`, `enableCsrfCookie = false`

## คำสั่งที่รันจริงและผล

### Backend (จาก `backend`)

| Command | ผล |
| --- | --- |
| `php tests/schema_smoke.php` | PASS 6 บรรทัด: tables, seed, email uniqueness, application uniqueness, foreign keys, constraints; `Schema smoke test passed`; exit 0 |
| `php tests/auth_model_smoke.php` | `Auth model smoke test passed`; exit 0 |
| `php tests/api_smoke.php` | `API smoke test passed`; exit 0 |
| `php tests/post_smoke.php` | `Post smoke test passed`; exit 0 |
| `php -l` กับไฟล์ PHP 22 ไฟล์ใน `controllers`, `models`, `config`, `migrations`, `tests`, `web` | `No syntax errors detected` ทุกไฟล์; 0 failures |

`post_smoke.php` ตรวจ: 401 เมื่อไม่มี/ใช้ token ผิด, create 201 และ owner มาจาก token, `status=open`, title/deadline/skills ตรง, ไม่มี `owner.line_id` หลุด, 422 สำหรับ title หาย/category ผิด/slots 0/skill ซ้ำ, 400 สำหรับ JSON เสีย, feed 200 เรียงใหม่สุดก่อน, detail 200, 404 สำหรับ id ที่ไม่มี, 404 สำหรับ bare path `/post/index`, และ 404 สำหรับ `DELETE /api/posts`

### Frontend (จาก `frontend`)

| Command | ผล |
| --- | --- |
| `npm run lint` | `oxlint` ไม่มี error/warning; exit 0 |
| `npm run build` | Vite 8.3.6, 34 modules transformed, `built in 1.09s`; `dist/index.html` 0.53 kB, CSS 4.01 kB, JS 275.80 kB; exit 0 |

### HTTP ที่ตรวจซ้ำในรอบ handoff

| Request | ผล |
| --- | --- |
| `GET /api/skills` | 200, JSON 6 ทักษะเรียงถูก |
| `GET /api/posts` ไม่มี token | 401 |
| `GET /skill/index` | 404 เพราะ `enableStrictParsing` เปิดแล้ว |
| `GET /api/posts/999999` ไม่มี token | 401 (auth ทำงานก่อนตรวจ resource) |
| `GET /api/posts/999999` พร้อม token | 404 (ตรวจใน `post_smoke.php`) |

หลังรัน smoke tests: `user` = 1 (demo account ที่สร้างไว้ลองใช้งาน), `post` = 0, `application` = 0

### Secret และ source scan

- `frontend` มีเฉพาะ `.env.example`; ไม่มี `.env` จริง
- `backend/config/db-local.php` มีอยู่จริงแต่ SHA-256 เท่ากับ `db-local.example.php` (placeholder ไม่มี secret)
- ไม่พบ pattern `sk-*`, `AIza*`, `ghp_*`, `AKIA*`, private key หรือ registry token
- Source scan `backend/controllers`, `backend/models`, `backend/config`, `backend/migrations`, `backend/tests`, `backend/web`, `frontend/src` สำหรับ `console.log`, `console.debug`, `dangerouslySetInnerHTML`: ไม่พบ match
- `.gitignore` ยัง ignore `backend/config/db-local.php`, `.env`, `backend/vendor/`, `backend/runtime/*`, `frontend/node_modules/`, `frontend/dist/`

## Browser flows ที่เคยทดสอบจริง

Day 1 (Task 6 reviewer รัน Chromium จริงกับ backend + Vite dev server):

- routing ระหว่าง `/`, `/login`, `/register`
- เปิด `/` โดยไม่มี token → ไป `/login`
- Logout → ล้าง token → ไป `/login`
- Reload ขณะมี token → หน้า `/` แสดงสถานะล็อกอินแบบไม่ระบุชื่อ (เพราะยังไม่มี `/api/me`)
- Login รหัสผิด → 401 → ข้อความไทย `อีเมลหรือรหัสผ่านไม่ถูกต้อง` ไม่เกิด redirect loop
- สมัครด้วย email ซ้ำ → 422 → error แสดงใต้ช่อง email
- เดินด้วย keyboard ผ่าน controls ทั้งหมดได้ และมี focus ring
- ความกว้าง 375px → ไม่มี horizontal overflow

Day 2 frontend (feed/create/detail) ยังไม่ได้รัน browser flow ในรอบนี้; ตรวจด้วย `npm run lint`, `npm run build`, `post_smoke.php` และ HTTP จริงเท่านั้น ก่อนส่งงานควรรัน browser flow ของ feed/create/detail อีกครั้ง

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
- Token เก็บใน `localStorage`; ถ้า token หมดอายุระหว่างใช้งาน จะรู้เมื่อมี request ที่ใช้ token
- Logout ไม่ revoke token ฝั่ง server; token เดิมยังใช้ได้จนหมดอายุ 7 วัน
- ยังไม่มีระบบใบสมัคร: สมัครเข้าทีม, ดูผู้สมัคร, accept/reject, ปิดโพสต์
- ยังไม่มีการเปิด LINE ID ของผู้ใช้อื่น แม้ใบสมัครจะ accepted
- Feed ยังไม่มี pagination, filter, search; คืนโพสต์ทั้งหมดเรียงใหม่สุดก่อน
- ยังไม่มีแก้ไข/ลบโพสต์ และไม่มีการจอง slot
- อ่าน feed/detail ต้องมี token; ยังไม่มี public read
- ไม่มี rate limit, lockout, email verification, password reset
- Validation message จาก backend เป็นภาษาอังกฤษและแสดงใต้ช่องตามที่ส่งมา; ข้อความระดับฟอร์มเป็นภาษาไทย
- ไม่มี CORS; development พึ่ง Vite proxy และ production ยังไม่มี config
- `YII_DEBUG` เปิดใน `web/index.php` (development) ทำให้ error บางตัวมี `previous`; ต้องปิดเมื่อทำ production
- `enableStrictParsing` เปิดแล้ว: เฉพาะ path ใน `docs/api.md` เท่านั้น; bare controller path คืน 404
- Response เป็น JSON ตาม contract เมื่อ client ส่ง `Accept: application/json`; Yii ยัง negotiate เป็น XML สำหรับ `Accept: application/xml` หรือ Accept แบบ browser ซึ่ง XML ไม่อยู่ใน contract
- MariaDB session timezone เป็น `SYSTEM`; แอปใช้ `Asia/Bangkok` และเวลาที่ตรวจตรงกัน แต่ `created_at` มาจาก DB `CURRENT_TIMESTAMP` จึงควรระวังถ้าย้ายเครื่อง/ตั้ง DB timezone ต่าง
- `frontend/README.md` ยังเป็น template ของ Vite; ใช้ `README.md` ที่ root เป็นหลัก
- ไฟล์ template ที่ยังไม่ใช้ (`src/assets/hero.png`, `react.svg`, `vite.svg`, `public/icons.svg`) ยังอยู่
- มี demo user `demo@teamfinder.local` ใน local DB สำหรับลองใช้งาน (ไม่ใช่ seed ใน migration และไม่ควรใช้ใน production)

## สิ่งที่อยู่นอกรอบ Day 2

- Application สมัครเข้าทีม, ดูผู้สมัคร, accept/reject, close post
- `/api/me*`, profile
- การเปิดเผย LINE ID ของผู้อื่นตามกฎ accepted-only
- Feed filter/pagination/search
- Notification, chat, admin, profile image
- Nginx, HTTPS, production deployment

## งานถัดไป — Day 3

1. เพิ่ม application endpoints: สมัคร, owner ดูผู้สมัคร, accept/reject และปิดโพสต์ พร้อม transaction/constraint ที่ถูกต้อง
2. ทำ LINE ID privacy: owner เห็น `applicant.line_id` เฉพาะ accepted; applicant เห็น `owner.line_id` เฉพาะเมื่อใบสมัครของตัวเอง accepted
3. เพิ่ม frontend: ฟอร์มสมัครในหน้า detail, owner เห็นรายชื่อผู้สมัคร + accept/reject + ปุ่มปิดโพสต์
4. เพิ่ม `application_smoke.php` ครอบ 401/403/404/409/422/200/201 และ LINE ID privacy
5. อัปเดต `docs/api.md` และ handoff ในชุดเดียวกัน

## สถานะ Git

- Repository: `https://github.com/Littlebluebies/team-finder.git`
- Branch หลัก: `main`
- Day 2 commit ล่าสุดก่อนอัปเดต handoff: `b44dca7` (`feat: Day 2 post create, feed and detail with bearer auth`)
- ดู commit hash ล่าสุดได้จาก `git log --oneline -1` หรือหน้า repository
- การกระทำกับ Git ทุกชนิดหลังจากนี้ต้องได้รับการยืนยันใหม่จากผู้ใช้ก่อน
