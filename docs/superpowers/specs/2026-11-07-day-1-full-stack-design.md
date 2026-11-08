# Team Finder — Day 1 Full-Stack Design

**วันที่ออกแบบ:** 2026-11-07  
**สถานะ:** ผู้ใช้อนุมัติดีไซน์แล้ว  
**ขอบเขต:** Backend วันที่ 1 ก่อน แล้วจึง Frontend วันที่ 1  
**ผู้พัฒนา:** ผู้ใช้ทำทั้งฝั่ง A และ B ในระยะแรก  
**ต้นฉบับความต้องการ:** `handoff-A-to-B.md`

## 1. เป้าหมาย

สร้างโปรเจกต์ใหม่ที่ `C:\Users\mooab\Projects\team-finder` ให้ผู้ใช้สมัครสมาชิก เลือกทักษะ เข้าสู่ระบบ และออกจากระบบผ่าน API จริงได้ พร้อมโครงสร้างฐานข้อมูลสำหรับ flow หลักทั้งหมดและเอกสารที่คนถัดไปใช้ทำงานต่อได้

งานต้องเสร็จและทดสอบเป็นส่วน ๆ โดยไม่พยายามทำฟีเจอร์ทั้งแผน 5 วันพร้อมกัน เนื่องจากวันส่งคือ 2026-11-09

## 2. แนวทางและขอบเขต

ใช้ Yii2 Basic สำหรับ REST API, MySQL สำหรับข้อมูล และ React + Vite สำหรับหน้าเว็บ เริ่ม backend และ migration ก่อน จากนั้นเชื่อม frontend เข้ากับ API จริงโดยไม่สร้าง mock API

### ส่งมอบในรอบนี้

- Migration 6 ตารางและข้อมูลทักษะเริ่มต้น
- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/skills`
- หน้า Register, Login และหน้าหลักสำหรับยืนยันสถานะล็อกอิน
- การออกจากระบบและการล้าง token เมื่อได้รับ 401 จาก request ที่ใช้ token
- เอกสาร API, วิธีติดตั้ง/รัน/ทดสอบ และกฎ `AGENTS.md`
- การทดสอบ backend ผ่าน HTTP และ frontend build/lint พร้อมทดสอบ flow ใน browser

### ไม่ทำในรอบนี้

- Feed, การสร้าง/ดูโพสต์ และตัวกรอง
- การสมัครเข้าทีม การรับ/ปฏิเสธ และการปิดโพสต์
- `/api/me*` และหน้าโปรไฟล์
- การเปิดเผย LINE ID ของผู้ใช้อื่น
- Rate limit, notification, chat, admin และ profile image
- Nginx, HTTPS และ production deployment
- Git commit/push จนกว่าผู้ใช้จะยืนยันในขั้นตอนนั้นโดยตรง

## 3. สถาปัยกรรม

```text
Browser
  └─ React + Vite
       └─ /api/* (Vite proxy ใน development)
            └─ PHP 8.2 + Yii2 REST
                 └─ MySQL
```

Development ใช้ Vite proxy ไปยัง `http://localhost:8080` จึงไม่มี CORS ระหว่าง frontend และ backend Production จะใช้ Nginx แยก `/` และ `/api/*` ตาม handoff ในงานวันที่ 5 ซึ่งอยู่นอกรอบนี้

โครงสร้างระดับบนสุด:

```text
team-finder/
├── backend/
├── frontend/
├── docs/
│   ├── api.md
│   └── superpowers/
├── AGENTS.md
├── README.md
└── .gitignore
```

Backend ใช้ ActiveRecord สำหรับฐานข้อมูล, controller แยกตามทรัพยากร และ response เป็น JSON Frontend แยกการเรียก API ไว้ใน `src/api/` โดยใช้ `fetch` ผ่านฟังก์ชันกลางแทนเพิ่ม Axios ใช้ React Router สำหรับ route และ CSS ธรรมดาโดยไม่เพิ่ม UI framework

## 4. ฐานข้อมูล

ใช้ `utf8mb4` และ foreign keys ทุกความสัมพันธ์ ชื่อตารางและคอลัมน์เป็นดังนี้

### `user`

- `id`: primary key
- `email`: สูงสุด 190 ตัวอักษร, not null, unique
- `password_hash`: ไม่เก็บ plaintext password
- `display_name`: สูงสุด 100 ตัวอักษร, not null
- `faculty`: สูงสุด 150 ตัวอักษร, not null
- `major`: สูงสุด 150 ตัวอักษร, not null
- `year`: integer 1–8, not null
- `line_id`: สูงสุด 50 ตัวอักษร, not null
- `bio`: text, nullable
- `access_token_hash`: SHA-256 hash ของ token ปัจจุบัน, nullable, unique
- `access_token_expires_at`: datetime, nullable
- `created_at`: datetime, not null

### `skill`

- `id`: primary key
- `name`: สูงสุด 100 ตัวอักษร, not null, unique

ข้อมูลเริ่มต้นเรียงตาม `id` และสร้างใน migration เดียวกับตาราง:

1. ตัดต่อ
2. เขียนเว็บ
3. ถ่ายภาพ
4. UI/UX
5. เขียนคอนเทนต์
6. วิเคราะห์ข้อมูล

### `user_skill`

- `user_id`, `skill_id` เป็น composite primary key
- อ้างอิง `user.id` และ `skill.id`
- ลบ mapping เมื่อ parent ถูกลบด้วย foreign-key cascade

### `post`

- `id`: primary key
- `owner_id`: อ้างอิง `user.id`, not null
- `title`: สูงสุด 200 ตัวอักษร, not null
- `description`: text, not null
- `category`: `course`, `contest` หรือ `personal`
- `slots`: integer มากกว่า 0
- `deadline`: date, not null
- `status`: `open` หรือ `closed`, ค่าเริ่มต้น `open`
- `created_at`: datetime, not null

ไม่เก็บ `accepted_count` เพราะคำนวณจาก applications ในงานวันที่ 2–3

### `post_skill`

- `post_id`, `skill_id` เป็น composite primary key
- อ้างอิง `post.id` และ `skill.id`
- ลบ mapping เมื่อ parent ถูกลบด้วย foreign-key cascade

### `application`

- `id`: primary key
- `post_id`: อ้างอิง `post.id`, not null
- `user_id`: อ้างอิง `user.id`, not null
- `message`: text, not null
- `status`: `pending`, `accepted` หรือ `rejected`, ค่าเริ่มต้น `pending`
- `created_at`: datetime, not null
- unique `(post_id, user_id)` เพื่อกันสมัครซ้ำที่ฐานข้อมูล

เพิ่ม index สำหรับ foreign key ที่ไม่ได้ถูกครอบคลุมด้วย primary/unique key เดิม การลบ user/post ที่มีข้อมูลธุรกิจใช้ `RESTRICT` ส่วน join rows ใช้ `CASCADE` เนื่องจากรอบนี้ไม่มี delete endpoint

## 5. Authentication และความปลอดภัย

- รหัสผ่านใช้ `Yii::$app->security->generatePasswordHash()` และ `validatePassword()`
- Token ดิบสร้างด้วย CSPRNG ของ Yii2 มีความยาวอย่างน้อย 64 ตัวอักษร
- เก็บเฉพาะ SHA-256 hash ของ token ใน `access_token_hash`; token ดิบส่งให้ client เฉพาะ response ของ register/login
- Token มีอายุ 7 วันนับจากเวลาออก และ login สำเร็จออก token ใหม่แทน token เดิมเสมอ
- การค้นหา identity ต้องตรวจทั้ง hash และ `access_token_expires_at`; token ผิดหรือหมดอายุไม่ผ่าน
- Header สำหรับ protected endpoint คือ `Authorization: Bearer <token>`
- ทุกการคุมสิทธิ์ต้องอยู่ที่ backend การซ่อนปุ่มใน React เป็นเพียง UX
- ไม่ log หรือแสดง password, password hash, token ดิบ หรือ token hash ใน API response
- ไม่ใช้ `dangerouslySetInnerHTML` กับข้อมูลผู้ใช้
- `.env`, ไฟล์ environment ที่มีค่าลับ และ `backend/config/db-local.php` ต้องถูก ignore และห้าม commit แม้ใช้ force-add
- ไฟล์ `.example` มีได้เฉพาะ placeholder ที่ไม่ใช่ค่าลับจริง

รอบนี้มีเพียง `line_id` ของผู้ใช้ตนเองใน response register/login การเปิดเผย LINE ID ของผู้อื่นจะอนุญาตเฉพาะ application ที่ `accepted` เมื่อ implement endpoint นั้นในงานถัดไป

## 6. API Contract

`docs/api.md` เป็น source of truth หลัง implement หากการทำงานจริงเปลี่ยนจากเอกสารนี้ ต้องแก้ `docs/api.md` ในการเปลี่ยนแปลงเดียวกัน

Response ทุก API เป็น JSON เฉพาะ request ที่มี body ต้องส่ง `Content-Type: application/json`; GET ไม่จำเป็นต้องส่ง header นี้ JSON ที่ parse ไม่ได้คืน 400 ด้วยรูปแบบ error ของ Yii2

### 6.1 `POST /api/auth/register`

ไม่ต้องล็อกอิน รับฟิลด์:

- `email`: บังคับ, trim และแปลงเป็นตัวพิมพ์เล็ก, รูปแบบอีเมลถูกต้อง, ไม่ซ้ำ
- `password`: บังคับ, 8–72 ตัวอักษร
- `display_name`: บังคับหลัง trim, 1–100 ตัวอักษร
- `faculty`: บังคับหลัง trim, 1–150 ตัวอักษร
- `major`: บังคับหลัง trim, 1–150 ตัวอักษร
- `year`: บังคับ, integer 1–8
- `line_id`: บังคับหลัง trim, 1–50 ตัวอักษร
- `skill_ids`: บังคับ, array ของ integer ที่ไม่ซ้ำกันอย่างน้อย 1 รายการ และทุก id ต้องมีอยู่ใน `skill`

บันทึก user และ `user_skill` ใน transaction เดียว หากขั้นตอนใดล้มเหลวต้อง rollback ทั้งหมด

สำเร็จคืน 201:

```json
{
  "token": "raw-token",
  "user": {
    "id": 7,
    "email": "student@example.com",
    "display_name": "สมชาย ใจดี",
    "faculty": "คณะเทคโนโลยีสื่อสารมวลชน",
    "major": "สื่อสร้างสรรค์",
    "year": 3,
    "line_id": "somchai.j",
    "bio": null,
    "skills": [{ "id": 1, "name": "ตัดต่อ" }]
  }
}
```

ข้อมูลไม่ผ่าน validation หรือ email ซ้ำคืน 422 ในรูปแบบ array ของ `{field,message}` ตาม Yii2

### 6.2 `POST /api/auth/login`

ไม่ต้องล็อกอิน รับ `email` และ `password` โดยทั้งสองบังคับ ข้อมูลขาดหรือผิดชนิดคืน 422 สำเร็จคืน 200 ด้วยรูปแบบเดียวกับ register และออก token ใหม่

Email หรือ password ไม่ถูกต้องคืน 401 รูปแบบ Yii2 พร้อมข้อความกลาง `Invalid email or password.` เพื่อไม่เปิดเผยว่าอีเมลมีอยู่หรือไม่

### 6.3 `GET /api/skills`

ไม่ต้องล็อกอิน คืน 200 เป็น array เรียงตาม `id`:

```json
[
  { "id": 1, "name": "ตัดต่อ" },
  { "id": 2, "name": "เขียนเว็บ" }
]
```

### 6.4 รูปแบบ error และวันเวลา

- 400: JSON request ไม่ถูกต้อง
- 401: ไม่ได้ยืนยันตัวตน, token ผิด/หมดอายุ หรือ login ไม่สำเร็จ
- 403: ยืนยันตัวตนแล้วแต่ไม่มีสิทธิ์
- 404: ไม่พบทรัพยากร
- 422: ข้อมูลไม่ผ่าน validation
- 401/403/404 ใช้ออบเจ็กต์ Yii2 ที่มี `name`, `message`, `code`, `status`
- 422 ใช้ array ของ `field`, `message` ตาม Yii2
- ใช้ timezone `Asia/Bangkok`
- `created_at` ใช้ `YYYY-MM-DD HH:MM:SS`
- `deadline` ใช้ `YYYY-MM-DD`; ในงานถัดไปโพสต์ถือว่าเลยกำหนดเมื่อวันที่ปัจจุบันมากกว่าวัน `deadline`

Rate limit และ 429 อยู่นอกรอบนี้ แต่ frontend ต้องมีข้อความรองรับเมื่อเพิ่มภายหลัง

## 7. Frontend

### Routes

- `/register`: ฟอร์มสมัครสมาชิกและเลือกทักษะจาก API
- `/login`: ฟอร์มเข้าสู่ระบบ
- `/`: หน้ายืนยันว่าล็อกอินแล้ว พร้อมปุ่มออกจากระบบ; feed จะมาแทนในงานถัดไป

หากยังไม่มี token เมื่อเปิด `/` ให้ไป `/login` หากล็อกอินอยู่และเปิด `/login` หรือ `/register` ให้ไป `/`

### API layer

- Base URL ค่าเริ่มต้นคือ `/api` ผ่าน `VITE_API_URL`
- ใช้ฟังก์ชัน `fetch` กลางจัดการ JSON, Authorization และ error
- เก็บ token ที่ `localStorage` key `team_finder_token`
- 401 จาก request ที่แนบ token ต้องล้าง token และกลับไป `/login`
- 401 จาก login ต้องแสดงข้อความที่ฟอร์ม ไม่วน redirect
- เก็บ `user` ไว้ใน React state หลัง register/login; ไม่สร้าง `/api/me` เพื่อรองรับ reload ในรอบนี้
- หน้าหลักหลัง reload แสดงสถานะล็อกอินโดยไม่อ้างชื่อผู้ใช้ จนกว่าจะมี `/api/me` ในงานถัดไป

### พฤติกรรมฟอร์ม

- ทุกช่องมี label และแสดง error ใต้ช่องที่เกี่ยวข้อง
- Register ตรวจช่องบังคับ รหัสผ่านขั้นต่ำ และการเลือกทักษะอย่างน้อยหนึ่งรายการก่อนส่ง แต่ backend ยังเป็นผู้ตัดสินสุดท้าย
- ระหว่าง submit ปิดปุ่มและป้องกัน request ซ้ำ
- แสดงข้อความใกล้ช่อง LINE ID ว่าจะเปิดเผยให้บุคคลที่เกี่ยวข้องเมื่อใบสมัครได้รับการตอบรับเท่านั้น
- Register หรือ login สำเร็จเก็บ token แล้วไป `/`
- Logout ล้าง token และ user state แล้วไป `/login`; ไม่เพิ่ม logout endpoint ในรอบนี้
- รองรับมือถือ, keyboard, focus state และข้อความ error ที่ assistive technology อ่านได้
- ไม่มี mock switch เพราะ frontend เชื่อม API จริงตั้งแต่เริ่ม

## 8. การทดสอบและเกณฑ์รับมอบ

### Backend

เพิ่ม smoke test ที่รันซ้ำได้โดยไม่เพิ่ม test framework ใหม่ ใช้ random email ในแต่ละรอบและอนุญาตเฉพาะ localhost เพื่อไม่ชี้ไป production โดยไม่ตั้งใจ ครอบคลุมอย่างน้อย:

1. Skills คืน 200 และข้อมูล seed ครบตามลำดับ
2. Register ที่ถูกต้องคืน 201, token และ user ที่มี skills
3. Response ไม่มี password, password hash หรือ token hash
4. Email ซ้ำคืน 422
5. Register ขาดข้อมูลหรือ `skill_ids` ไม่ถูกต้องคืน 422
6. Login ผิดคืน 401 และไม่เปิดเผยว่าอีเมลมีอยู่
7. Login ถูกคืน 200 และ token ใหม่
8. Token เดิมไม่สามารถยืนยันตัวตนหลัง login ใหม่ และ token ใหม่ใช้ได้ โดยทดสอบที่ identity method โดยไม่เพิ่ม endpoint ชั่วคราว

ตรวจ migration บนฐานข้อมูลว่าง, รันซ้ำแล้วไม่สร้าง seed ซ้ำ และตรวจ unique/foreign-key constraints บนฐานข้อมูล local

### Frontend

- Lint และ production build ผ่าน
- ทดสอบใน browser: โหลด skills, register สำเร็จ, logout, login ผิด, login สำเร็จ และ redirect เมื่อไม่มี token
- ตรวจ Network ว่า request ไป `/api` และไม่มี token ใน console หรือ UI
- ตรวจหน้าจอขนาดมือถือและการใช้งาน keyboard

### เอกสารและ Git

- README ทำให้คนใหม่ install, ตั้งค่า DB, migrate, รัน backend/frontend และทดสอบได้
- `docs/api.md` ตรงกับ response และ validation จริง
- `AGENTS.md` มีกฎ backend authorization, LINE ID เฉพาะ accepted สำหรับผู้อื่น และห้าม commit secrets
- ตรวจไฟล์ทั้งหมดว่าไม่มี `.env`, `db-local.php`, password หรือ token จริง
- แสดงสรุปไฟล์และผลทดสอบให้ผู้ใช้ก่อนขอยืนยัน commit/push

## 9. ลำดับการทำงาน

1. สร้าง root guardrails, backend template และตรวจการเชื่อมต่อ MySQL
2. เขียน migration และตรวจ schema/seed
3. Implement authentication และ skills พร้อม smoke test
4. ปรับ `docs/api.md` และคำสั่งรัน backend ให้ตรงกับของจริง
5. สร้าง frontend และเชื่อม API จริง
6. รัน lint/build และทดสอบ full flow ใน browser
7. ทำ README, `AGENTS.md` และ handoff summary
8. รายงานผลและรอยืนยันก่อน Git commit/push
