# Team Finder API

Source of truth สำหรับ API ที่ implement แล้ว หากพฤติกรรมเปลี่ยน ต้องแก้ไฟล์นี้ในชุดการเปลี่ยนแปลงเดียวกัน

- Base URL (development): `http://localhost:8080/api`
- Frontend เรียกผ่าน `/api` แล้ว Vite proxy ไป `http://localhost:8080`
- Response เป็น JSON (`Content-Type: application/json; charset=UTF-8`) สำหรับ client ที่ส่ง `Accept: application/json` รวมถึง frontend; framework (`yii\rest\Controller` + `ContentNegotiator`) ยังตอบ XML เมื่อได้ `Accept: application/xml` หรือ Accept แบบ browser แต่ XML ไม่อยู่ใน contract
- Request ที่มี body ต้องส่ง `Content-Type: application/json`; GET ไม่ต้องส่ง
- JSON ที่ parse ไม่ได้คืน `400`
- Timezone ของแอป: `Asia/Bangkok`

## Routing

`enableStrictParsing` เปิดอยู่แล้ว มีเพียง path ที่ประกาศในไฟล์นี้เท่านั้นที่ตอบได้ path อื่นทั้งหมด (รวมถึง bare controller path เช่น `/post/index`) ตอบ `404`

| Method | Path | Auth |
| --- | --- | --- |
| POST | `/api/auth/register` | ไม่ต้อง |
| POST | `/api/auth/login` | ไม่ต้อง |
| GET | `/api/skills` | ไม่ต้อง |
| POST | `/api/posts` | ต้อง (Bearer token) |
| GET | `/api/posts` | ต้อง (Bearer token) |
| GET | `/api/posts/{id}` | ต้อง (Bearer token) |
| POST | `/api/posts/{id}/applications` | ต้อง (Bearer token) |
| GET | `/api/posts/{id}/applications` | ต้อง (Bearer token), เจ้าของโพสต์เท่านั้น |
| PATCH | `/api/applications/{id}` | ต้อง (Bearer token), เจ้าของโพสต์เท่านั้น |
| PATCH | `/api/posts/{id}` | ต้อง (Bearer token), เจ้าของโพสต์เท่านั้น |
| GET | `/api/me` | ต้อง (Bearer token) |
| PUT | `/api/me` | ต้อง (Bearer token) |
| GET | `/api/me/posts` | ต้อง (Bearer token) |
| GET | `/api/me/applications` | ต้อง (Bearer token) |

## Authentication และ token

Endpoint ที่ระบุว่า "ต้อง" ล็อกอินต้องส่ง header `Authorization: Bearer <token>` ทุกครั้ง หากขาด header, token ผิด หรือ token หมดอายุ จะได้ `401 Unauthorized` ก่อนพิจารณาอย่างอื่น

- Token ดิบสร้างด้วย CSPRNG ของ Yii2 มีความยาว 64 ตัวอักษร และส่งกลับเฉพาะ response ของ register/login
- ฐานข้อมูลเก็บเฉพาะ SHA-256 hash (`user.access_token_hash`) พร้อม `user.access_token_expires_at`
- Token มีอายุ 7 วัน (604800 วินาที) นับจากเวลาออก
- Login สำเร็จทุกครั้งออก token ใหม่และแทนที่ token เดิม; token เก่าใช้ไม่ได้ทันที
- Token ที่ผิดหรือหมดอายุต้องไม่ผ่านการยืนยันตัวตน
- ไม่ส่ง password, password hash, token hash หรือ `access_token_*` ใน response ใด ๆ
- `line_id` ของผู้ใช้อื่นเปิดเผยเฉพาะกรณีที่มีใบสมัครของโพสต์เดียวกันและสถานะเป็น `accepted`: owner เห็น `applicant.line_id` และผู้สมัครเห็น `owner.line_id`; กรณี pending/rejected/ไม่มีใบสมัครจะไม่มี key `line_id` เลย
- Feed ไม่มี `line_id` ของใครทั้งสิ้น; detail มีเฉพาะ `owner.line_id` ตามกฎข้างต้น

## ค่าที่ใช้ร่วมกัน

- `email` ถูก trim และแปลงเป็นตัวพิมพ์เล็กก่อนตรวจและบันทึก; unique แบบไม่สนตัวพิมพ์เล็ก-ใหญ่
- `display_name`, `faculty`, `major`, `line_id` ถูก trim
- `user.skills` เรียงตาม `skill.id` จากน้อยไปมาก
- `GET /api/skills` เรียงตาม `id` จากน้อยไปมาก
- `created_at` ในฐานข้อมูลเป็น `YYYY-MM-DD HH:MM:SS`
- `deadline` ในฐานข้อมูลเป็น `YYYY-MM-DD`

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
    "line_linked": false,
    "line_linked_at": null,
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
    "line_linked": false,
    "line_linked_at": null,
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

## 4. `POST /api/posts`

ต้องล็อกอิน สร้าง post และ `post_skill` ใน transaction เดียว `owner_id` มาจาก token เสมอ client ไม่ส่งเอง

### Request fields

| Field | Required | Rules |
| --- | --- | --- |
| `title` | yes | 1–200 characters after trim |
| `description` | yes | string ยาวอย่างน้อย 1 ตัวอักษร |
| `category` | yes | หนึ่งใน `course` / `contest` / `personal` |
| `slots` | yes | integer 1–99 |
| `deadline` | yes | วันที่รูปแบบ `YYYY-MM-DD` |
| `skill_ids` | yes | non-empty array of unique positive integers; every id must exist in `skill` |

### Request example

```json
{
  "title": "หาทีมแข่ง hackathon",
  "description": "ต้องการคนถ่ายภาพและเขียนคอนเทนต์",
  "category": "contest",
  "slots": 2,
  "deadline": "2026-12-31",
  "skill_ids": [3, 5]
}
```

### Success — `201 Created`

```json
{
  "id": 1,
  "title": "หาทีมแข่ง hackathon",
  "description": "ต้องการคนถ่ายภาพและเขียนคอนเทนต์",
  "category": "contest",
  "slots": 2,
  "accepted_count": 0,
  "deadline": "2026-12-31",
  "status": "open",
  "created_at": "2026-11-08 10:00:00",
  "is_owner": true,
  "viewer_application": null,
  "owner": { "id": 7, "display_name": "สมชาย ใจดี" },
  "skills": [
    { "id": 3, "name": "ถ่ายภาพ" },
    { "id": 5, "name": "เขียนคอนเทนต์" }
  ]
}
```

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ
- `422` — validation ไม่ผ่าน (array ของ `{field, message}` เหมือน register)
- `400 Bad Request` — JSON parse ไม่ได้

## 5. `GET /api/posts`

ต้องล็อกอิน คืนโพสต์ที่เปิดรับ เรียงตาม `id` จากมากไปน้อย (ใหม่สุดก่อน) ยังไม่มี pagination

### Query parameters

| Param | Rules | Behavior |
| --- | --- | --- |
| `skill` | positive integer, ส่งซ้ำหรือคั่นด้วย comma ได้ | คืนโพสต์ที่มีทักษะใดทักษะหนึ่งในรายการ |
| `category` | `course` / `contest` / `personal` | กรองตามประเภท; ค่าอื่นถูกเพิกเฉย |
| `q` | string | ค้นหาใน `title` และ `description` แบบ `LIKE` |
| `include_closed` | `1` | รวมโพสต์ที่ปิดแล้วและโพสต์ที่เลย `deadline` |

ค่าเริ่มต้นไม่ส่ง parameter ใด ๆ = เฉพาะโพสต์ `status=open` และ `deadline >= วันนี้`

### Success — `200 OK`

```json
[
  {
    "id": 2,
    "title": "…",
    "description": "…",
    "category": "course",
    "slots": 3,
    "accepted_count": 1,
    "deadline": "2026-12-15",
    "status": "open",
    "created_at": "2026-11-08 10:05:00",
    "is_owner": false,
    "viewer_application": null,
    "owner": { "id": 8, "display_name": "…" },
    "skills": [{ "id": 4, "name": "UI/UX" }]
  }
]
```

`accepted_count` คือจำนวนใบสมัครที่ `accepted` แล้ว ใช้แสดง "รับแล้ว 1/3"

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ

## 6. `GET /api/posts/{id}`

ต้องล็อกอิน คืนโพสต์หนึ่งรายการในรูปแบบเดียวกับ success ของ `POST /api/posts` พร้อม field สำหรับผู้ดู:

- `is_owner` — `true` เมื่อผู้เรียกเป็นเจ้าของโพสต์
- `viewer_application` — `null` เมื่อยังไม่สมัครหรือเป็นเจ้าของ; เมื่อสมัครแล้วเป็น `{ "id": …, "status": "pending|accepted|rejected" }`
- `owner.line_id` — มีเฉพาะเมื่อใบสมัครของผู้เรียกสถานะ `accepted` เท่านั้น

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ
- `404` — ไม่มีโพสต์ที่มี id นี้

## 7. `POST /api/posts/{id}/applications`

ต้องล็อกอิน สมัครเข้าทีมของโพสต์ `id` หนึ่งครั้งต่อคน (unique `post_id` + `user_id`) `user_id` มาจาก token เสมอ

### Request fields

| Field | Required | Rules |
| --- | --- | --- |
| `message` | yes | string ยาวอย่างน้อย 1 ตัวอักษร |

### Request example

```json
{ "message": "สนใจร่วมทีมครับ มีประสบการณ์เขียนเว็บ" }
```

### Success — `201 Created`

```json
{
  "id": 3,
  "post_id": 1,
  "status": "pending",
  "message": "สนใจร่วมทีมครับ มีประสบการณ์เขียนเว็บ",
  "created_at": "2026-11-08 11:00:00",
  "applicant": {
    "id": 8,
    "display_name": "สมหญิง ตั้งใจ",
    "skills": [{ "id": 2, "name": "เขียนเว็บ" }]
  }
}
```

`applicant.line_id` ไม่มีใน response จนกว่าใบสมัครจะ `accepted`

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ
- `404` — ไม่มีโพสต์ที่มี id นี้
- `409 Conflict` — ผู้ใช้เคยสมัครโพสต์นี้แล้ว
- `422` — `message` หาย/ว่าง, สมัครโพสต์ของตัวเอง, หรือโพสต์ปิดรับแล้ว
- `400 Bad Request` — JSON parse ไม่ได้

## 8. `GET /api/posts/{id}/applications`

ต้องล็อกอินและเป็นเจ้าของโพสต์ คืนใบสมัครทั้งหมดของโพสต์ เรียงตาม `application.id` จากน้อยไปมาก

### Success — `200 OK`

```json
[
  {
    "id": 3,
    "post_id": 1,
    "status": "accepted",
    "message": "สนใจร่วมทีมครับ",
    "created_at": "2026-11-08 11:00:00",
    "applicant": {
      "id": 8,
      "display_name": "สมหญิง ตั้งใจ",
      "skills": [{ "id": 2, "name": "เขียนเว็บ" }],
      "line_id": "somying.t"
    }
  }
]
```

`applicant.line_id` มีเฉพาะใบสมัครที่ `accepted`; pending/rejected ไม่มี key นี้

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ
- `403` — ผู้เรียกไม่ใช่เจ้าของโพสต์
- `404` — ไม่มีโพสต์ที่มี id นี้

## 9. `PATCH /api/applications/{id}`

ต้องล็อกอินและเป็นเจ้าของโพสต์ของใบสมัครนั้น ใช้ตอบรับหรือปฏิเสธใบสมัครที่ยัง `pending`

### Request fields

| Field | Required | Rules |
| --- | --- | --- |
| `status` | yes | `accepted` หรือ `rejected` |

### Success — `200 OK`

response เป็น application object รูปแบบเดียวกับข้อ 7; เมื่อ `accepted` จะมี `applicant.line_id`

เมื่อจำนวนใบสมัคร `accepted` ถึง `post.slots` ระบบจะเปลี่ยน `post.status` เป็น `closed` ใน transaction เดียวกัน

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ
- `403` — ผู้เรียกไม่ใช่เจ้าของโพสต์
- `404` — ไม่มีใบสมัครที่มี id นี้
- `409 Conflict` — ใบสมัครถูกประมวลผลไปแล้ว (ไม่ใช่ `pending`)
- `422` — `status` ไม่ใช่ `accepted`/`rejected`
- `400 Bad Request` — JSON parse ไม่ได้

## 10. `PATCH /api/posts/{id}`

ต้องล็อกอินและเป็นเจ้าของโพสต์ ใช้ปิดรับสมัคร

### Request fields

| Field | Required | Rules |
| --- | --- | --- |
| `status` | yes | ต้องเป็น `closed` เท่านั้น |

### Success — `200 OK`

response เป็น post object รูปแบบเดียวกับข้อ 6 การปิดโพสต์ที่ปิดอยู่แล้วสำเร็จได้ (idempotent)

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ
- `403` — ผู้เรียกไม่ใช่เจ้าของโพสต์
- `404` — ไม่มีโพสต์ที่มี id นี้
- `422` — `status` ไม่ใช่ `closed`
- `400 Bad Request` — JSON parse ไม่ได้

## 11. `GET /api/me`

ต้องล็อกอิน คืนข้อมูลโปรไฟล์ของผู้เรียกในรูปแบบเดียวกับ `user` ของ register/login รวม `line_id` ของตัวเอง, `line_linked`, `line_linked_at` และ `skills`

### Success — `200 OK`

```json
{
  "id": 7,
  "email": "student@example.com",
  "display_name": "สมชาย ใจดี",
  "faculty": "คณะเทคโนโลยีสื่อสารมวลชน",
  "major": "สื่อสร้างสรรค์",
  "year": 3,
  "line_id": "somchai.j",
  "line_linked": false,
  "line_linked_at": null,
  "bio": null,
  "skills": [{ "id": 1, "name": "ตัดต่อ" }]
}
```

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ

## 12. `PUT /api/me`

ต้องล็อกอิน แก้โปรไฟล์ของตัวเองและแทนที่ `user_skill` ทั้งชุดใน transaction เดียว ไม่สามารถแก้ `email` หรือรหัสผ่านผ่าน endpoint นี้

### Request fields

| Field | Required | Rules |
| --- | --- | --- |
| `display_name` | yes | 1–100 characters after trim |
| `faculty` | yes | 1–150 characters after trim |
| `major` | yes | 1–150 characters after trim |
| `year` | yes | integer 1–8 |
| `line_id` | yes | 1–50 characters after trim |
| `bio` | no | string max 2000; ค่าว่างกลายเป็น `null` |
| `skill_ids` | yes | non-empty array of unique positive integers; every id must exist in `skill` |

### Success — `200 OK`

response เป็น user object รูปแบบเดียวกับข้อ 11

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ
- `422` — validation ไม่ผ่าน (array ของ `{field, message}`)
- `400 Bad Request` — JSON parse ไม่ได้

## 13. `GET /api/me/posts`

ต้องล็อกอิน คืนโพสต์ทั้งหมดที่ผู้เรียกเป็นเจ้าของ รวมโพสต์ที่ปิดแล้ว เรียงตาม `id` จากมากไปน้อย แต่ละรายการเป็น post object รูปแบบเดียวกับข้อ 6 และเพิ่ม:

- `pending_count` — จำนวนใบสมัครที่ยัง `pending` ของโพสต์นั้น

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ

## 14. `GET /api/me/applications`

ต้องล็อกอิน คืนใบสมัครทั้งหมดของผู้เรียก เรียงตาม `id` จากมากไปน้อย

### Success — `200 OK`

```json
[
  {
    "id": 31,
    "status": "accepted",
    "message": "สนใจร่วมทีมครับ",
    "created_at": "2026-11-08 11:00:00",
    "post": {
      "id": 12,
      "title": "หาคนตัดต่อคลิปส่งประกวด",
      "status": "open",
      "owner": {
        "id": 5,
        "display_name": "สมชาย ใจดี",
        "line_id": "somchai.j"
      }
    }
  }
]
```

`post.owner.line_id` มีเฉพาะใบสมัครที่ `accepted`; pending/rejected ไม่มี key นี้

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ

## Error formats และ status codes

| Status | ใช้เมื่อ |
| --- | --- |
| `200` | login สำเร็จ, อ่าน skills/feed/detail สำเร็จ |
| `201` | register สำเร็จ, สร้าง post สำเร็จ, สมัครเข้าทีมสำเร็จ |
| `400` | JSON body parse ไม่ได้ |
| `401` | login ไม่สำเร็จ หรือ protected endpoint ไม่มี/ไม่ยอมรับ token |
| `403` | authenticated user ไม่มีสิทธิ์ เช่น ไม่ใช่เจ้าของโพสต์แต่เรียกดู/แก้ใบสมัครหรือปิดโพสต์ |
| `404` | route ไม่มีอยู่ (strict parsing), โพสต์/ใบสมัครไม่มีอยู่, หรือเรียก path `/api/*` ที่มีอยู่ด้วย HTTP method ที่ไม่รองรับ |
| `409` | สมัครโพสต์เดิมซ้ำ หรือใบสมัครถูกประมวลผลไปแล้ว |
| `422` | validation ไม่ผ่าน |
| `429` | อยู่นอกรอบ (rate limit) |

- `401`, `403`, `404`, `409` ใช้ object ของ Yii2 ที่มี `name`, `message`, `code`, `status` และอาจมี `type`
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

- `enableStrictParsing` เปิดอยู่ จึงไม่มี bare controller path (`/auth/login`, `/skill/index` ฯลฯ) อีกต่อไป ทุก path ที่ไม่ตรงกับ contract ตอบ `404` ทั้งหมด; การเรียก path `/api/*` ที่มีอยู่ด้วย method ผิดก็ได้ `404` เพราะ rule ผูก method ไว้
- `web/index.php` เปิด `YII_DEBUG` เป็น `true` ใน development จึงอาจเห็น field `previous` เพิ่มใน error object โดยเฉพาะ `404`; field ที่ควรพึ่งพาคือ `name`, `message`, `code`, `status`
- ไม่มี CORS header เพราะ development ใช้ Vite proxy; production ต้องตั้ง Nginx แยก `/` และ `/api/*` ซึ่งอยู่นอกรอบปัจจุบัน
- ยังไม่มี endpoint สำหรับ profile แยกจาก `/api/me`, pagination หรือ chat

## 16. Rate limit

ทุก request ถูกนับต่อ `route + IP` ด้วยหน้าต่างคงที่ 60 วินาที:

| กลุ่ม route | จำกัด |
| --- | --- |
| `POST /api/auth/login`, `POST /api/auth/register`, `GET /api/me/line/authorize-url`, `POST /api/me/line` | 5 ครั้ง/นาที |
| route อื่นทั้งหมด | 120 ครั้ง/นาที |

เกินจำกัดคืน `429 Too Many Requests` พร้อม header `Retry-After: 60`:

```json
{
  "name": "Too Many Requests",
  "message": "Rate limit exceeded.",
  "code": 0,
  "status": 429,
  "type": "yii\\web\\TooManyRequestsHttpException"
}
```

## 17. `POST /api/auth/logout`

ต้องล็อกอิน ยกเลิก token ปัจจุบันทันทีฝั่ง server — token เดิมใช้ยืนยันตัวตนไม่ได้อีก

### Success — `200 OK`

```json
{ "logged_out": true }
```

### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ

## 15. Notifications

ทุก endpoint ในส่วนนี้ต้องใช้ `Authorization: Bearer <token>`

ระบบสร้าง notification ให้อัตโนมัติ 3 เหตุการณ์:

| เหตุการณ์ | ผู้รับ | `type` |
| --- | --- | --- |
| มีผู้สมัครใหม่ | เจ้าของโพสต์ | `application_received` |
| ใบสมัครได้รับการตอบรับ | ผู้สมัคร | `application_accepted` |
| ใบสมัครไม่ผ่าน | ผู้สมัคร | `application_rejected` |

การสร้าง notification ไม่เปลี่ยน response หรือ status code ของ endpoint เดิม แม้การส่ง email/LINE จะล้มเหลว

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

- `type` เป็นหนึ่งใน `application_received`, `application_accepted`, `application_rejected`
- `actor` คือผู้สมัครสำหรับ `application_received` และเป็นเจ้าของโพสต์สำหรับอีกสองประเภท
- `unread` นับเฉพาะรายการที่ `read_at` เป็น `null` ของผู้ใช้ปัจจุบัน
- response ต้องไม่มี `line_id` หรือ `line_user_id`

#### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ

### `PATCH /api/notifications`

Body: `{ "ids": [1, 2] }` หรือ `{}`

- ไม่ส่ง `ids` = mark read ทั้งหมดของผู้ใช้ปัจจุบัน
- `ids` ต้องเป็น array ของ integer ที่ไม่ว่าง; id ของผู้ใช้อื่นถูก ignore
- `ids: []` หรือชนิดข้อมูลผิดคืน 422

คืน:

```json
{ "unread": 0 }
```

#### Errors

- `401` — ขาด header, token ผิด หรือ token หมดอายุ
- `422` — `ids` ไม่ใช่ array ของ integer ที่ไม่ว่าง

### `GET /api/me/line/authorize-url`

คืน URL สำหรับ redirect ผู้ใช้ไปยืนยันตัวตนกับ LINE:

```json
{ "url": "https://access.line.me/oauth2/v2.1/authorize?response_type=code&client_id=..." }
```

- state ถูกสร้างใหม่ทุกครั้ง, เก็บเป็น hash, มีอายุ 10 นาที และใช้ได้ครั้งเดียว
- ถ้ายังไม่ตั้งค่า LINE Login คืน `503` พร้อม `{ "message": "LINE login is not configured." }`

### `POST /api/me/line`

Body: `{ "code": "...", "state": "..." }` จาก query string ที่ LINE ส่งกลับมา

- `state` ผิด/หมดอายุ/ไม่ใช่ของผู้ใช้ คืน `422`
- LINE ปฏิเสธหรือแลก token ไม่สำเร็จ คืน `502`
- สำเร็จคืน:

```json
{ "linked": true, "linked_at": "2026-11-09 15:00:00" }
```

### `DELETE /api/me/line`

ยกเลิกการเชื่อมบัญชี LINE คืน `{ "linked": false }` เสมอ (idempotent)

### `line_linked` และ `line_linked_at`

response ของ register/login/me มี `line_linked` (boolean) และ `line_linked_at` (string|null) เพิ่มขึ้น; ไม่มี `line_user_id` ใน response ใด ๆ
