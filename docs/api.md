# Team Finder API — Day 1

Source of truth สำหรับ API ที่ implement แล้วใน Day 1 มีเพียงสาม endpoint นี้เท่านั้น หากพฤติกรรมเปลี่ยน ต้องแก้ไฟล์นี้ในชุดการเปลี่ยนแปลงเดียวกัน

- Base URL (development): `http://localhost:8080/api`
- Frontend เรียกผ่าน `/api` แล้ว Vite proxy ไป `http://localhost:8080`
- Response เป็น JSON (`Content-Type: application/json; charset=UTF-8`) สำหรับ client ที่ส่ง `Accept: application/json` รวมถึง frontend; framework (`yii\rest\Controller` + `ContentNegotiator`) ยังตอบ XML เมื่อได้ `Accept: application/xml` หรือ Accept แบบ browser แต่ XML ไม่อยู่ใน contract
- Request ที่มี body ต้องส่ง `Content-Type: application/json`; GET ไม่ต้องส่ง
- JSON ที่ parse ไม่ได้คืน `400`
- Timezone ของแอป: `Asia/Bangkok`

## Routing aliases (non-contract)

`backend/config/web.php` ไม่ได้เปิด `enableStrictParsing` ทำให้ Yii default routing เปิด path ของ controller/action ตรง ๆ เพิ่มจาก URL rules:

- Contract มีเพียง `/api/auth/register`, `/api/auth/login`, `/api/skills`
- `GET /skill/index` ตอบ `200` พร้อมรายการทักษะ; `GET /auth/login` และ `GET /auth/register` ตอบ `405` เพราะ `VerbFilter` ของ `yii\rest\Controller`
- path เหล่านี้เป็นผลข้างเคียงของ default routing ห้ามใช้ใน client, test หรือเอกสาร; การปิดช่องทางนี้อยู่ในงาน Day 2 (`enableStrictParsing => true` หรือ catch-all 404 rule)

## Authentication และ token

Day 1 ไม่มี endpoint ที่ต้องล็อกอิน ทั้งสาม endpoint เป็น public

- Protected endpoint ในงานถัดไปต้องรับ header `Authorization: Bearer <token>`
- Token ดิบสร้างด้วย CSPRNG ของ Yii2 มีความยาว 64 ตัวอักษร และส่งกลับเฉพาะ response ของ register/login
- ฐานข้อมูลเก็บเฉพาะ SHA-256 hash (`user.access_token_hash`) พร้อม `user.access_token_expires_at`
- Token มีอายุ 7 วัน (604800 วินาที) นับจากเวลาออก
- Login สำเร็จทุกครั้งออก token ใหม่และแทนที่ token เดิม; token เก่าใช้ไม่ได้ทันที
- Token ที่ผิดหรือหมดอายุต้องไม่ผ่านการยืนยันตัวตน
- Day 1 ไม่ส่ง password, password hash, token hash หรือ `created_at` ใน response

## ค่าที่ใช้ร่วมกัน

- `email` ถูก trim และแปลงเป็นตัวพิมพ์เล็กก่อนตรวจและบันทึก; unique แบบไม่สนตัวพิมพ์เล็ก-ใหญ่
- `display_name`, `faculty`, `major`, `line_id` ถูก trim
- `user.skills` เรียงตาม `skill.id` จากน้อยไปมาก
- `GET /api/skills` เรียงตาม `id` จากน้อยไปมาก
- `created_at` ในฐานข้อมูลเป็น `YYYY-MM-DD HH:MM:SS` แต่ยังไม่ถูกส่งออกใน Day 1
- `deadline` ในฐานข้อมูลเป็น `YYYY-MM-DD` และยังไม่มี endpoint ที่ใช้ใน Day 1

## 1. `POST /api/auth/register`

ไม่ต้องล็อกอิน สร้าง user และ `user_skill` ใน transaction เดียว

### Request fields

| Field | Required | Rules |
| --- | --- | --- |
| `email` | yes | valid email, max 190, trim + lowercase, unique |
| `password` | yes | 8–72 characters |
| `display_name` | yes | 1–100 characters after trim |
| `faculty` | yes | 1–150 characters after trim |
| `major` | yes | 1–150 characters after trim |
| `year` | yes | integer 1–8 |
| `line_id` | yes | 1–50 characters after trim |
| `skill_ids` | yes | non-empty array of unique positive integers; every id must exist in `skill` |

### Request example

```json
{
  "email": "student@example.com",
  "password": "correct-horse-123",
  "display_name": "สมชาย ใจดี",
  "faculty": "คณะเทคโนโลยีสื่อสารมวลชน",
  "major": "สื่อสร้างสรรค์",
  "year": 3,
  "line_id": "somchai.j",
  "skill_ids": [1, 2]
}
```

### Success — `201 Created`

```json
{
  "token": "raw-token-at-least-64-characters",
  "user": {
    "id": 7,
    "email": "student@example.com",
    "display_name": "สมชาย ใจดี",
    "faculty": "คณะเทคโนโลยีสื่อสารมวลชน",
    "major": "สื่อสร้างสรรค์",
    "year": 3,
    "line_id": "somchai.j",
    "bio": null,
    "skills": [
      { "id": 1, "name": "ตัดต่อ" },
      { "id": 2, "name": "เขียนเว็บ" }
    ]
  }
}
```

`id` เป็น auto-increment จึงไม่จำเป็นต้องเป็น 7 `bio` เป็น `null` เสมอใน Day 1 เพราะยังไม่มีช่องทางแก้ไข

### Errors

- `422` — validation ไม่ผ่าน, email ซ้ำ, `skill_ids` ว่าง/ซ้ำ/ไม่ใช่จำนวนเต็มบวก/ไม่มีในฐานข้อมูล (reason phrase ที่ Yii2 ส่งจริงคือ `Data Validation Failed.`)
- `400 Bad Request` — JSON parse ไม่ได้

ตัวอย่าง `422` จริงจากการส่ง email ไม่ถูกต้องและ `skill_ids` ที่ไม่มีอยู่ในฐานข้อมูล (array ของ `{field, message}`):

```json
[
  { "field": "email", "message": "Email is not a valid email address." },
  { "field": "skill_ids", "message": "One or more skills do not exist." }
]
```

ข้อความ error เป็นภาษาอังกฤษตาม Yii2 และลำดับของรายการไม่ใช่ส่วนหนึ่งของ contract

## 2. `POST /api/auth/login`

ไม่ต้องล็อกอิน

### Request fields

| Field | Required | Rules |
| --- | --- | --- |
| `email` | yes | valid email, max 190, trim + lowercase |
| `password` | yes | max 72 |

### Request example

```json
{
  "email": "student@example.com",
  "password": "correct-horse-123"
}
```

### Success — `200 OK`

รูปแบบเดียวกับ register:

```json
{
  "token": "new-raw-token-at-least-64-characters",
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

Token ที่ได้จะต่างจาก token เดิมเสมอ และ token เดิมใช้ยืนยันตัวตนไม่ได้อีก

### Errors

- `422` — ขาด `email`/`password` หรือชนิดข้อมูลผิด (reason phrase ที่ Yii2 ส่งจริงคือ `Data Validation Failed.`)
- `401 Unauthorized` — email หรือ password ไม่ถูกต้อง ใช้ข้อความกลางเพื่อไม่เปิดเผยว่าอีเมลมีอยู่หรือไม่:

```json
{
  "name": "Unauthorized",
  "message": "Invalid email or password.",
  "code": 0,
  "status": 401,
  "type": "yii\\web\\UnauthorizedHttpException"
}
```

- `400 Bad Request` — JSON parse ไม่ได้

## 3. `GET /api/skills`

ไม่ต้องล็อกอิน

### Success — `200 OK`

```json
[
  { "id": 1, "name": "ตัดต่อ" },
  { "id": 2, "name": "เขียนเว็บ" },
  { "id": 3, "name": "ถ่ายภาพ" },
  { "id": 4, "name": "UI/UX" },
  { "id": 5, "name": "เขียนคอนเทนต์" },
  { "id": 6, "name": "วิเคราะห์ข้อมูล" }
]
```

Seed มีหกรายการนี้เสมอและเรียงตาม `id`

## Error formats และ status codes

| Status | ใช้เมื่อ |
| --- | --- |
| `200` | login สำเร็จ, อ่าน skills สำเร็จ |
| `201` | register สำเร็จ |
| `400` | JSON body parse ไม่ได้ |
| `401` | login ไม่สำเร็จ |
| `403` | ยังไม่ถูกใช้ใน Day 1 (สงวนไว้สำหรับ authenticated user ที่ไม่มีสิทธิ์) |
| `404` | route ไม่มีอยู่ หรือเรียก path `/api/*` ที่มีอยู่ด้วย HTTP method ที่ไม่รองรับ |
| `405` | เกิดเฉพาะ bare controller path จาก Yii default routing (เช่น `GET /auth/login`) ไม่เกิดบน `/api/*` |
| `422` | validation ไม่ผ่าน |
| `429` | อยู่นอกรอบ Day 1 (rate limit) |

- `401`, `403`, `404` ใช้ object ของ Yii2 ที่มี `name`, `message`, `code`, `status` และอาจมี `type`
- `422` ใช้ array ของ `{field, message}`
- error body ทั้งหมดข้างต้นเป็น JSON ตาม contract ของ client ที่ส่ง `Accept: application/json`; ถ้า client ส่ง `Accept: application/xml` หรือ Accept แบบ browser framework จะตอบ XML ซึ่งไม่อยู่ใน contract
- ตัวอย่าง `400`:

```json
{
  "name": "Bad Request",
  "message": "Invalid JSON data in request body: Syntax error",
  "code": 0,
  "status": 400,
  "type": "yii\\web\\BadRequestHttpException"
}
```

- ตัวอย่าง `404`:

```json
{
  "name": "Not Found",
  "message": "Page not found.",
  "code": 0,
  "status": 404,
  "type": "yii\\web\\NotFoundHttpException"
}
```

### หมายเหตุที่ตรวจพบจริง

- URL rules ผูก method ไว้ (`POST api/auth/register`, `POST api/auth/login`, `GET api/skills`) การเรียก path `/api/*` ที่มีอยู่ด้วย method ผิดจะได้ `404 Not Found`; ส่วน bare controller path จาก default routing (เช่น `GET /auth/login`) จะได้ `405 Method Not Allowed` จาก `VerbFilter`
- `web/index.php` เปิด `YII_DEBUG` เป็น `true` ใน development จึงอาจเห็น field `previous` เพิ่มใน error object โดยเฉพาะ `404`; field ที่ควรพึ่งพาคือ `name`, `message`, `code`, `status`
- ไม่มี CORS header เพราะ development ใช้ Vite proxy; production ต้องตั้ง Nginx แยก `/` และ `/api/*` ซึ่งอยู่นอกรอบ Day 1
- ไม่มี endpoint สำหรับ `/api/me`, post, feed, application, logout หรือ profile ใน Day 1
