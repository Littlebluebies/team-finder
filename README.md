# Team Finder

Team Finder คือเว็บหาทีมและสมาชิกโปรเจกต์สำหรับนักศึกษา ส่งมอบระบบสมัครสมาชิก เลือกทักษะ เข้าสู่ระบบ กระดานหาทีม และใบสมัครเข้าทีม โดยเชื่อม API จริงทั้งหมด ไม่มี mock

- API contract: [`docs/api.md`](docs/api.md)
- คู่มือ deploy production (Nginx + PHP-FPM + HTTPS): [`docs/deploy.md`](docs/deploy.md)
- สถานะงานที่ตรวจแล้วและข้อจำกัด: [`docs/handoff.md`](docs/handoff.md)
- Spec ที่อนุมัติ: [`docs/superpowers/specs/2026-11-07-day-1-full-stack-design.md`](docs/superpowers/specs/2026-11-07-day-1-full-stack-design.md)
- กฎสำหรับผู้พัฒนาต่อ: [`AGENTS.md`](AGENTS.md)

## ขอบเขตที่ส่งมอบแล้ว

สิ่งที่ส่งมอบ:

- MySQL schema 6 ตาราง (`user`, `skill`, `user_skill`, `post`, `post_skill`, `application`) พร้อม seed ทักษะ 6 รายการ
- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/skills`
- `POST /api/posts`, `GET /api/posts` (กรอง `skill/category/q`), `GET /api/posts/{id}`, `PATCH /api/posts/{id}` (ต้องส่ง Bearer token, 401 เมื่อ token ไม่ถูกต้อง)
- `POST /api/posts/{id}/applications`, `GET /api/posts/{id}/applications`, `PATCH /api/applications/{id}` (owner เท่านั้นสำหรับ list/update)
- `GET /api/me`, `PUT /api/me`, `GET /api/me/posts`, `GET /api/me/applications`
- หน้า `/register`, `/login`, กระดานหาทีม (`/`) พร้อมตัวกรอง, หน้าสร้างโพสต์ (`/posts/new`), หน้ารายละเอียดโพสต์ (`/posts/:id`) พร้อมฟอร์มสมัครและเครื่องมือ owner
- หน้าโปรไฟล์ (`/me`), โพสต์ของฉัน (`/me/posts`), ใบสมัครของฉัน (`/me/applications`)
- LINE ID ของผู้ใช้อื่นเปิดเผยเฉพาะใบสมัครที่ `accepted` ของโพสต์เดียวกัน
- Strict parsing: มีเพียง path ตาม `docs/api.md` เท่านั้นที่ตอบได้
- Smoke tests ฝั่ง backend, lint/build ฝั่ง frontend และเอกสาร API/ติดตั้ง/handoff

สิ่งที่ยังไม่ทำในรอบนี้:

- Pagination ของ feed, แก้ไข/ลบโพสต์
- Notification, chat, admin, รูปโปรไฟล์
- Rate limit, email verification, password reset
- Deploy จริงบน server/domain (มี config ตัวอย่างและคู่มือใน `docs/deploy.md` แล้ว แต่ยังไม่ได้รันบน server จริง)
- ทดสอบกับผู้ใช้จริง 3–5 คน

## สิ่งที่ต้องมี

- PHP 8.2+ และ Composer 2 (ตรวจแล้ว: PHP 8.2.12, Composer 2.10.2)
- Node 20.19+ หรือ 22.12+ และ npm 10+ (ตรวจแล้ว: Node 20.20.2, npm 10.8.2)
- MySQL หรือ MariaDB ผ่าน XAMPP ที่ `127.0.0.1:3306` (ตรวจแล้ว: MariaDB 10.4.32)
- PowerShell (คำสั่งด้านล่างเป็น PowerShell)

## โครงสร้างโปรเจกต์

```text
team-finder/
├── backend/          Yii2 JSON API
├── deploy/           Nginx production config ตัวอย่าง
├── frontend/         React + Vite SPA
├── docs/             api.md, deploy.md, handoff.md, spec/plan
├── AGENTS.md
├── README.md
└── .gitignore
```

## ติดตั้งครั้งแรก

### Backend

รันจาก `backend`:

```powershell
composer install
Copy-Item config/db-local.example.php config/db-local.php
php -r '$pdo = new PDO("mysql:host=127.0.0.1;port=3306", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); $pdo->exec("CREATE DATABASE IF NOT EXISTS team_finder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");'
php yii migrate --interactive=0
```

แก้ credential ของเครื่องตัวเองได้เฉพาะใน `backend/config/db-local.php` ซึ่งถูก ignore และห้าม commit หากไม่ได้สร้างไฟล์นี้ `config/db.php` จะใช้ค่า default `root` / รหัสผ่านว่าง / ฐานข้อมูล `team_finder`

### Frontend

รันจาก `frontend` ใน terminal ที่สอง:

```powershell
npm install
```

ไม่ต้องคัดลอก `.env` หากใช้ค่าเริ่มต้น เพราะ client ใช้ `/api` และ Vite proxy ไป `http://localhost:8080` ให้แล้ว ถ้าต้องชี้ API ที่อื่น ให้สร้าง `.env` จาก `.env.example` แล้วแก้ `VITE_API_URL`; URL ข้าม origin ต้องเปิด CORS ฝั่ง backend หรือใช้ proxy เพิ่ม เพราะ backend ไม่ได้เปิด CORS ไว้

## รันระบบ

Terminal 1 จาก `backend`:

```powershell
php yii serve --port=8080
```

Terminal 2 จาก `frontend`:

```powershell
npm run dev
```

- API: `http://localhost:8080/api`
- เว็บ: `http://localhost:5173` (Vite default)
- ทุก request ของเว็บไปที่ `/api` ผ่าน proxy จึงไม่มี CORS ใน development

## ตรวจสอบ

Backend รันจาก `backend` (ต้องเปิด API server ที่ port 8080 ก่อนรัน `api_smoke.php`):

```powershell
php tests/schema_smoke.php
php tests/auth_model_smoke.php
php tests/api_smoke.php
php tests/post_smoke.php
php tests/application_smoke.php
php tests/me_smoke.php
```

`api_smoke.php` รับ `API_URL` ได้ แต่ยอมให้ยิงเฉพาะ host `localhost`, `127.0.0.1`, `::1` เท่านั้น

Frontend รันจาก `frontend`:

```powershell
npm run lint
npm run build
```

## พฤติกรรม token และรหัสผ่าน

- Token ดิบมีความยาว 64 ตัวอักษร ส่งกลับเฉพาะ response ของ register/login
- ฐานข้อมูลเก็บเฉพาะ SHA-256 hash ใน `user.access_token_hash` และเวลาหมดอายุใน `user.access_token_expires_at`
- Token มีอายุ 7 วัน (604800 วินาที) นับจากเวลาออก
- Login สำเร็จทุกครั้งจะออก token ใหม่และแทนที่ token เดิม
- Logout ใน Day 1 ล้าง token จาก browser (`localStorage` key `team_finder_token`) เท่านั้น ยังไม่มี logout endpoint ฝั่ง server
- รหัสผ่านเก็บด้วย `Yii::$app->security->generatePasswordHash()` ไม่เก็บ plaintext

## เอกสารเพิ่มเติม

- [`docs/api.md`](docs/api.md) — source of truth ของ request/response/error ทั้ง 14 endpoint
- [`docs/deploy.md`](docs/deploy.md) — ขั้นตอน deploy production พร้อม Nginx/PHP-FPM/HTTPS
- [`docs/handoff.md`](docs/handoff.md) — สิ่งที่ implement แล้ว, ผลตรวจจริง, ข้อจำกัด, งานถัดไป

## Notifications (email + LINE)

คัดลอก `backend/config/notifications-local.example.php` เป็น
`backend/config/notifications-local.php` แล้วใส่ค่าจริง ไฟล์นี้ถูก gitignore

- `mail.dsn`: SMTP DSN เช่น `smtp://user:pass@smtp.example.com:587`
- `line.channel_id` / `line.channel_secret`: จาก LINE Login channel
- `line.access_token`: channel access token ของ Messaging API (คนละตัวกับ channel secret)
- `line.redirect_uri`: ต้องตรงกับที่ลงทะเบียนใน LINE Developers Console ทุกตัวอักษร
- `dry_run`: `true` = ไม่ยิงเน็ต (ใช้ใน dev/test), `false` = ส่งจริง

เมื่อยังไม่มี credential ให้คง `dry_run => true` ระบบยังสร้าง in-app notification ได้ปกติ

Retry ของที่ส่งไม่สำเร็จ:

```powershell
cd backend
php yii notification/retry --limit=100
```

ทดสอบ flow (ต้องเปิด backend และตั้ง `dry_run => true`):

```powershell
cd backend
php tests/notification_smoke.php
```
