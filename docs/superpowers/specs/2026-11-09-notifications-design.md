# Team Finder — Notification System Design (LINE + Email + In-app)

**วันที่ออกแบบ:** 2026-11-09
**สถานะ:** ผู้ใช้อนุมัติดีไซน์แล้ว
**ขอบเขต:** Backend notification subsystem + in-app notification center
**อ้างอิง:** `AGENTS.md`, `docs/api.md`, `DESIGN.md`, `PRODUCT.md`

## 1. เป้าหมาย

แจ้งเตือน 3 เหตุการณ์ผ่าน 3 ช่องทาง (in-app, email, LINE) โดยไม่ทำให้ response ของ API เดิมเปลี่ยน และไม่ขยายขอบเขตการเปิดเผย LINE ID:

1. มีผู้สมัครใหม่ในประกาศ → เจ้าของโพสต์
2. ใบสมัครได้รับการตอบรับ → ผู้สมัคร
3. ใบสมัครไม่ผ่าน → ผู้สมัคร

## 2. ขอบเขต

### ทำในรอบนี้

- ตาราง `notification` และคอลัมน์เชื่อม LINE ใน `user`
- บริการสร้าง notification + ส่ง email + ส่ง LINE
- เชื่อม/ยกเลิก LINE ผ่าน LINE Login (OAuth code exchange)
- API list/mark-read ของ notification
- In-app notification center + badge ใน topbar
- Console command สำหรับ retry
- อัปเดต `docs/api.md`, `README.md`, `AGENTS.md`, `.gitignore`

### ไม่ทำในรอบนี้

- หน้าตั้งค่าเปิด/ปิดช่องทาง (ผู้ใช้เลือก "ไม่มี preference")
- Queue/worker process (ใช้ best-effort + retry command)
- Realtime (WebSocket/SSE) — badge รีเฟรชตอนเปลี่ยน route
- แจ้งเตือนเหตุการณ์อื่น (ปิดโพสต์, หมดเขต ฯลฯ)
- เปลี่ยนความหมายหรือรูปแบบของ `line_id` เดิม
- Production deploy

## 3. การตัดสินใจหลัก

| หัวข้อ | ข้อสรุป |
|---|---|
| ช่องทาง LINE | LINE Official Account + LINE Login (OAuth code exchange) — ไม่ใช้ LIFF SDK |
| Email | `yiisoft/yii2-symfonymailer` + SMTP DSN |
| รูปแบบ delivery | Best-effort หลัง commit + `notification/retry` command (แนวทาง A) |
| Preference | ไม่มี — email เปิดตลอด, LINE เฉพาะผู้ใช้ที่เชื่อมบัญชี |
| In-app | หน้า `/me/notifications` + badge |

## 4. สถาปัตยกรรม

```text
ApplicationController
  └─ NotificationService::dispatch($application, $type)
       ├─ INSERT notification (unique กันซ้ำ)
       ├─ EmailTransport  → SMTP (symfonymailer)
       └─ LineTransport   → Messaging API push (curl)
                              ↑
user.line_user_id ← LineController (OAuth code exchange)

Frontend
  ├─ NotificationBadge → GET /api/notifications (unread)
  ├─ NotificationsPage → GET /api/notifications, PATCH /api/notifications
  └─ ProfilePage → GET /api/me/line/authorize-url, POST/DELETE /api/me/line
```

### ไฟล์ที่เพิ่ม/แก้

| ไฟล์ | การเปลี่ยนแปลง |
|---|---|
| `backend/migrations/m261109_000001_add_notifications.php` | ใหม่ |
| `backend/models/Notification.php` | ใหม่ |
| `backend/services/NotificationService.php` | ใหม่ |
| `backend/services/LineClient.php` | ใหม่ (authorize URL, token exchange, push) |
| `backend/controllers/NotificationController.php` | ใหม่ |
| `backend/controllers/LineController.php` | ใหม่ |
| `backend/commands/NotificationController.php` | ใหม่ (`notification/retry`) |
| `backend/views/mail/notification.php` | ใหม่ |
| `backend/config/notifications.php` + `notifications-local.example.php` | ใหม่ |
| `backend/config/web.php`, `console.php` | เพิ่ม `mailer` component และ params |
| `backend/controllers/ApplicationController.php` | เรียก dispatch 2 จุด |
| `backend/models/User.php` | `toAuthArray()` เพิ่ม `line_linked` |
| `backend/tests/notification_smoke.php` | ใหม่ |
| `frontend/src/App.jsx` | route + nav + badge |
| `frontend/src/pages/NotificationsPage.jsx` | ใหม่ |
| `frontend/src/pages/LineCallbackPage.jsx` | ใหม่ |
| `frontend/src/components/NotificationBadge.jsx` | ใหม่ |
| `frontend/src/pages/ProfilePage.jsx` | การ์ดเชื่อม LINE |
| `frontend/src/api/notifications.js` | ใหม่ — ฟังก์ชันเรียก endpoint notification/LINE |
| `frontend/src/index.css` | สไตล์ notification ตาม `DESIGN.md` |

## 5. ฐานข้อมูล

### 5.1 `user` เพิ่ม 4 คอลัมน์

- `line_user_id` VARCHAR(64) NULL — Messaging API `U…`, unique (NULL ซ้ำได้)
- `line_linked_at` DATETIME NULL
- `line_link_state_hash` CHAR(64) NULL — SHA-256 ของ state
- `line_link_state_expires_at` DATETIME NULL

`line_id` เดิมไม่ถูกแก้หรือย้าย

### 5.2 ตาราง `notification`

- `id` primary key unsigned
- `user_id` unsigned not null, FK → `user.id` CASCADE
- `type` VARCHAR(32) not null, check in (`application_received`, `application_accepted`, `application_rejected`)
- `application_id` unsigned not null, FK → `application.id` CASCADE
- `read_at` DATETIME NULL
- `email_sent_at` DATETIME NULL
- `line_sent_at` DATETIME NULL
- `email_error` VARCHAR(255) NULL
- `line_error` VARCHAR(255) NULL
- `attempts` TINYINT unsigned not null default 0
- `created_at` DATETIME not null default CURRENT_TIMESTAMP

Indexes:

- unique `uq_notification_event (user_id, type, application_id)` — กันส่งซ้ำ
- `idx_notification_user (user_id, read_at)`
- FK indexes ตาม Yii2 migration

ไม่เก็บ `post_id` — join ผ่าน `application` เมื่อต้องแสดงชื่อโพสต์

### 5.3 Migration

- `safeUp()` เพิ่มคอลัมน์/index/FK ตามลำดับ
- `safeDown()` drop ตาราง `notification` แล้ว drop คอลัมน์ทั้ง 4
- ต้องรัน `migrate` และ `migrate/down` บนฐานข้อมูลว่างผ่านก่อน merge

## 6. LINE Linking

### 6.1 ขั้นตอน

1. `GET /api/me/line/authorize-url` (Bearer)
   - สร้าง state = 32 random bytes (hex 64 ตัว)
   - เก็บ `line_link_state_hash = sha256(state)`, `line_link_state_expires_at = now + 10 นาที`
   - คืน URL:
     `https://access.line.me/oauth2/v2.1/authorize?response_type=code&client_id={channel_id}&redirect_uri={redirect_uri}&state={state}&scope=profile%20openid&bot_prompt=aggressive`
   - `bot_prompt=aggressive` เพื่อชวนผู้ใช้เพิ่ม OA เป็นเพื่อน (จำเป็นต่อการ push)
2. Frontend redirect ไป URL นั้น
3. LINE กลับมาที่ `{app_url}/line/callback?code=…&state=…`
4. `POST /api/me/line` body `{code, state}` (Bearer)
   - ตรวจ state hash ตรงกับผู้ใช้ปัจจุบันและยังไม่หมดอายุ; ใช้ครั้งเดียวแล้วล้างทั้งสองคอลัมน์
   - แลก code ที่ `https://api.line.me/oauth2/v2.1/token`
     (`grant_type=authorization_code`, `code`, `redirect_uri`, `client_id`, `client_secret`) ด้วย curl timeout 5 วิ
   - อ่าน `sub` จาก payload ของ `id_token`
   - บันทึก `line_user_id`, `line_linked_at`, คืน `{linked: true, linked_at}`
5. `DELETE /api/me/line` → set `line_user_id = NULL`, `line_linked_at = NULL` → `{linked: false}`

### 6.2 เหตุผลที่ไม่ verify `id_token` ซ้ำ

`id_token` ได้มาจาก token endpoint ที่ต้องส่ง `client_secret` และวิ่งบน TLS จึงเชื่อถือ payload ได้ในระดับเดียวกับการเรียก verify แยก ลด dependency และ request ที่ไม่จำเป็น

### 6.3 ข้อจำกัด

- `redirect_uri` ต้องตรงกับที่ลงทะเบียนใน LINE Developers Console ทุกตัวอักษร
- ต้องมี LINE Login channel และ Messaging API channel ของ OA เดียวกัน (หรือผูกกัน)
- LINE push ใช้โควตาต่อ OA (free tier จำกัดต่อเดือน)

## 7. Notification Flow

### 7.1 จุดเสียบ

| เหตุการณ์ | ตำแหน่ง | ผู้รับ | type |
|---|---|---|---|
| มีคนสมัคร | `ApplicationController::actionCreate` หลัง `$application->save(false)` | `post.owner` | `application_received` |
| รับใบสมัคร | `actionUpdate` หลัง `$transaction->commit()` | `application.user` | `application_accepted` |
| ปฏิเสธ | `actionUpdate` หลัง commit | `application.user` | `application_rejected` |

ห้าม dispatch ก่อน commit — กันแจ้งเตือนของรายการที่ rollback

### 7.2 `NotificationService::dispatch(Application $application, string $type)`

1. หา recipient จาก type
2. `INSERT` notification row; ถ้า unique ชน ให้ใช้ row เดิม
3. `deliver($notification)` — ครอบ `try/catch` ทั้งก้อน ไม่โยน exception ออกไป
4. `deliver`:
   - email: render view ตาม type → `Yii::$app->mailer->compose(...)->setTo(...)->send()` → set `email_sent_at` หรือ `email_error`
   - LINE: ถ้า `user.line_user_id` ว่าง → ข้าม; ถ้ามี → push → set `line_sent_at` หรือ `line_error`
   - ทุกกรณีบันทึกด้วย `save(false, [...])` เฉพาะคอลัมน์ delivery
5. ถ้า `dry_run = true` → set `email_sent_at`/`line_sent_at` = now โดยไม่ยิงเน็ต (ใช้ใน test/dev)

### 7.3 เนื้อหา

| type | Email subject | LINE text |
|---|---|---|
| `application_received` | `มีผู้สมัครใหม่ในประกาศ “{title}”` | `📬 มีผู้สมัครใหม่\nประกาศ: {title}\nผู้สมัคร: {name}\n{link}` |
| `application_accepted` | `ใบสมัครของคุณได้รับการตอบรับ — “{title}”` | `🎉 ใบสมัครได้รับการตอบรับ\nประกาศ: {title}\n{link}` |
| `application_rejected` | `ผลการพิจารณาใบสมัคร — “{title}”` | `📄 ผลการพิจารณาใบสมัคร\nประกาศ: {title}\n{link}` |

- `link = {app_url}/posts/{post_id}`
- อีเมลเป็น HTML เรียบ ๆ inline CSS ตามสีแบรนด์ใน `DESIGN.md` + CTA "เปิดประกาศ"
- ข้อความของผู้สมัครแสดงเฉพาะในอีเมล `application_received` แบบ escape และตัด 200 ตัวอักษร
- ไม่มี `line_id` ในทุกช่องทาง

## 8. API Contract

เพิ่มใน `docs/api.md` พร้อม implement

### 8.1 `GET /api/notifications`

Bearer required. คืนเฉพาะ `user_id = identity`, เรียง `created_at DESC`, สูงสุด 50:

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

`actor` = คู่กรณี: `application_received` → ผู้สมัคร; `accepted`/`rejected` → เจ้าของโพสต์

### 8.2 `PATCH /api/notifications`

Body `{ "ids": [1, 2] }` หรือ `{}`

- ไม่ส่ง `ids` = mark read ทั้งหมดของผู้ใช้
- `ids` ต้องเป็น array ของ integer ที่ไม่ว่าง; id ที่ไม่ใช่ของผู้ใช้ถูก ignore
- `ids: []` หรือชนิดข้อมูลผิด → 422
- คืน `{ "unread": 0 }`

### 8.3 `GET /api/me/line/authorize-url`

Bearer required. คืน `{ "url": "https://access.line.me/oauth2/v2.1/authorize?..." }`
ถ้า config LINE ไม่ครบ (channel id/secret/redirect uri) → 503 พร้อมข้อความกลาง
ข้อนี้ต่างจากกรณี delivery ที่ config ไม่ครบแล้วข้ามเงียบ ๆ เพราะ endpoint นี้ผู้ใช้สั่งงานโดยตรงและต้องรู้ว่าทำไม่ได้

### 8.4 `POST /api/me/line`

Body `{ "code": "...", "state": "..." }`

- state ผิด/หมดอายุ/ไม่ใช่ของผู้ใช้ → 422
- LINE ตอบ error → 502 ข้อความกลาง (ไม่ส่งต่อ response ดิบ)
- สำเร็จ → `{ "linked": true, "linked_at": "2026-11-09 15:00:00" }`

### 8.5 `DELETE /api/me/line`

คืน `{ "linked": false }` เสมอ (idempotent)

### 8.6 `toAuthArray()` เพิ่ม `line_linked`

- `line_linked = (line_user_id !== null)`
- ไม่คืน `line_user_id` หรือ state ออก API ทุกกรณี

### 8.7 ไม่เปลี่ยน endpoint เดิม

Response ของ application/post/me คงฟิลด์เดิมทั้งหมด การเพิ่ม `line_linked` เป็นการเพิ่มฟิลด์แบบ additive

## 9. ความปลอดภัยและความเป็นส่วนตัว

- ทุก endpoint ใหม่ใช้ `HttpBearerAuth`
- notification query บังคับ `user_id = identity`; ไม่รับ `user_id` จาก client
- `line_user_id` ไม่เคยออก API, log, หรือข้อความแจ้งเตือน
- ไม่มี `line_id` ใน notification payload, อีเมล, LINE text
- อีเมล `application_accepted` ไม่แนบ LINE ID ของเจ้าของโพสต์; ให้กดลิงก์ไปหน้าโพสต์ซึ่งใช้กฎเดิม
- state เก็บเป็น hash, ใช้ครั้งเดียว, อายุ 10 นาที
- ไม่ log `code`, `access_token`, `client_secret`, หรือ state ดิบ
- error ที่บันทึกลง `email_error`/`line_error` ตัดความยาว 255 และไม่รวม secret
- `notifications-local.php` ถูก gitignore; commit เฉพาะ `.example` ที่เป็น placeholder

## 10. Error Handling และ Retry

- delivery ทั้งหมดอยู่ใน `try/catch`; API ที่เรียกต้นทางต้องสำเร็จแม้ส่งไม่สำเร็จ
- SMTP timeout 5 วิ, LINE curl timeout 5 วิ
- config ไม่ครบ → `*_error = "not configured"` ไม่ throw
- LINE ตอบ 403 (ผู้ใช้บล็อก OA) → บันทึก error ไม่ retry ซ้ำเกิน 5 ครั้ง
- `php yii notification/retry --limit=100`
  - เลือก row ที่ `attempts < 5`, `created_at > now - 7 days`
  - email ที่ยัง `email_sent_at IS NULL`
  - LINE ที่ยัง `line_sent_at IS NULL` และผู้รับมี `line_user_id`
  - เพิ่ม `attempts` ต่อรอบ, log สรุปจำนวนสำเร็จ/ล้มเหลว
- ถ้า `dry_run = true` command ไม่ทำอะไรและแจ้งเตือนว่าอยู่ในโหมด dry-run

## 11. Frontend

### 11.1 Routes

- `/me/notifications` — notification center (ต้องมี token)
- `/line/callback` — รับ `code`/`state` หรือ `error` จาก LINE แล้ว POST กลับ

### 11.2 Badge

- `NotificationBadge` เรียก `GET /api/notifications` ตอน mount และเมื่อ `useLocation().pathname` เปลี่ยน
- แสดงตัวเลข `unread`; เกิน 9 แสดง `9+`; `unread = 0` ไม่แสดง
- ไม่มี polling interval
- `aria-label` เป็นภาษาไทย เช่น "การแจ้งเตือน 3 รายการที่ยังไม่อ่าน"

### 11.3 NotificationsPage

- หัวข้อ "การแจ้งเตือน" + ปุ่ม "อ่านทั้งหมด" (disabled เมื่อ `unread = 0`)
- การ์ดเรียงใหม่→เก่า; unread มีจุด accent และพื้นต่างจาก read
- แต่ละการ์ด: ชื่อเหตุการณ์ภาษาไทย, ชื่อโพสต์, ชื่อคู่กรณี, เวลาแบบ relative ภาษาไทย
- คลิกการ์ด → `PATCH /api/notifications {ids:[id]}` แล้วไป `/posts/{post_id}`
- empty state: "ยังไม่มีการแจ้งเตือน" + ลิงก์ไปกระดาน
- error state: ข้อความกลาง + ปุ่มลองใหม่
- ใช้ token/สไตล์จาก `DESIGN.md`; ตรวจที่ 390px

### 11.4 ProfilePage — การ์ด LINE

- แสดงสถานะจาก `GET /api/me` (`line_linked`)
- ยังไม่เชื่อม: ปุ่ม "เชื่อมต่อ LINE" → เรียก authorize-url แล้ว `window.location.assign(url)`
- เชื่อมแล้ว: แสดง "เชื่อมต่อแล้ว" + เวลา + ปุ่ม "ยกเลิกการเชื่อมต่อ"
- คำอธิบาย: "อีเมลแจ้งเตือนเปิดตลอด; LINE ต้องเชื่อมบัญชีก่อน"
- ไม่แสดง `line_user_id`

### 11.5 LineCallbackPage

- อ่าน `code`, `state`, `error` จาก query string
- ถ้ามี `error` → แสดง "ยกเลิกหรือไม่สำเร็จ" + ลิงก์กลับโปรไฟล์
- ถ้าขาด `code`/`state` → แสดง error
- POST `/api/me/line` → สำเร็จแสดงข้อความแล้ว redirect `/me` หลัง 1.5 วิ
- ระหว่างรอแสดงสถานะ loading และกัน submit ซ้ำ

## 12. การทดสอบและเกณฑ์รับมอบ

### 12.1 Backend

`backend/tests/notification_smoke.php` (รันด้วย config `dry_run = true`):

1. 401 ทุก endpoint ใหม่เมื่อไม่มี token
2. สมัครงาน → เจ้าของได้ 1 row `application_received`, ผู้สมัครไม่ได้
3. accept → ผู้สมัครได้ `application_accepted`; reject → `application_rejected`
4. `GET /api/notifications` คืนเฉพาะของตัวเอง และ `unread` ถูกต้อง
5. `PATCH` mark read ทีละ id และทั้งหมด
6. `PATCH` ด้วย `ids` ผิดชนิด → 422
7. `POST /api/me/line` ด้วย state ปลอม → 422
8. `DELETE /api/me/line` → `linked: false`
9. `php yii notification/retry` ใน dry-run จบด้วย exit code 0 และไม่ยิงเน็ต
10. response ของ application/post ไม่มีฟิลด์ใหม่ที่ไม่ตั้งใจ และยังไม่มี `line_id` ในกรณี pending/rejected

รัน `backend/tests/application_smoke.php` เดิมทั้งไฟล์เพื่อยืนยันไม่ถดถอย

### 12.2 Migration

- `php yii migrate` บน DB ว่าง
- `php yii migrate/down 1` แล้ว `up` อีกครั้ง
- ตรวจ unique `(user_id, type, application_id)` และ FK cascade

### 12.3 Frontend

- `npm run lint` ผ่าน
- `npm run build` ผ่าน
- ตรวจ 390px: badge, หน้า notification, การ์ด LINE, callback page ไม่มี horizontal scroll
- ตรวจ keyboard: ปุ่มและการ์ดเข้าถึงได้, focus มองเห็น

### 12.4 เอกสาร

- `docs/api.md` ตรงกับ endpoint จริง
- `README.md` อธิบายการตั้งค่า SMTP และ LINE OA/Login
- `AGENTS.md` เพิ่มข้อห้าม LINE ID ใน notification/email/LINE message
- ไม่มี secret ในไฟล์ที่ commit

### 12.5 สิ่งที่ตรวจไม่ได้จนกว่าจะมี credential

- ส่งอีเมลจริงผ่าน SMTP
- push LINE จริงและ flow เชื่อมบัญชีครบวงจร
- ต้องมี SMTP credential, LINE channel id/secret และ redirect URI ที่ลงทะเบียนแล้ว

## 13. ลำดับการทำงาน

1. เพิ่ม dependency `yiisoft/yii2-symfonymailer` + config/example + gitignore
2. Migration + `Notification` model + `User::toAuthArray()` เพิ่ม `line_linked`
3. `LineClient` + `LineController` (authorize-url, link, unlink)
4. `NotificationService` + mail view + LINE push
5. เสียบ trigger ใน `ApplicationController` 2 จุด
6. `NotificationController` (list, mark read)
7. `notification/retry` command
8. `notification_smoke.php` + รัน `application_smoke.php`
9. อัปเดต `docs/api.md`, `README.md`, `AGENTS.md`
10. Frontend: badge, page, profile card, callback page, CSS
11. lint/build + ตรวจ 390px + ทดสอบ flow ใน browser
12. สรุปผลและรอยืนยันก่อน Git mutation ทุกครั้ง

## 14. ความเสี่ยง

| ความเสี่ยง | ผลกระทบ | การรับมือ |
|---|---|---|
| ไม่มี LINE OA/Login credential | ทดสอบเชื่อมบัญชีและ push จริงไม่ได้ | ทำโค้ดครบ, dry-run, บันทึกเป็น blocked |
| ไม่มี SMTP credential | ส่งอีเมลจริงไม่ได้ | เหมือนข้างบน |
| redirect URI ไม่ตรง | LINE ปฏิเสธก่อนแลก token | ระบุค่าใน example และ README ให้คัดลอกตรง |
| SMTP ช้า | request ยืด | timeout 5 วิ + best-effort |
| ผู้ใช้บล็อก OA | push ล้ม | บันทึก error, ไม่ retry เกิน 5 ครั้ง |
| โควตา LINE หมด | push ล้มชั่วคราว | บันทึก error, retry command |
| `line_id` เดิมเป็นข้อความอิสระ | ผู้ใช้อาจเข้าใจผิดว่าใช้ได้เลย | ข้อความใน UI อธิบายว่าต้องเชื่อมบัญชีใหม่ |
