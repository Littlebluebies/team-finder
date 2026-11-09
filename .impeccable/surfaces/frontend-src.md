---
version: 1
slug: "frontend-src"
primary_target: "frontend/src"
related_targets: []
---

# Surface brief — Team Finder web app

## Scope and mode

- Target: `frontend/src` (all routes of the existing React SPA)
- Platform: web
- Visitor mode: Operate
- Scope: replacement visual world for the whole app; no feature, copy, API, or authorization changes

## Audience and job

นักศึกษาไทยที่ต้องการหาสมาชิกทีมสำหรับโปรเจกต์รายวิชา/การแข่งขัน
งาน: สแกนโพสต์ที่เปิดรับ → อ่านรายละเอียด → สมัคร → ติดตามสถานะ → แลก LINE เมื่อถูกตอบรับ
ความสำเร็จ: รู้ว่าตัวเองอยู่ขั้นไหนของ flow และปิดดีลได้โดยไม่ต้องเดา

## Action, content, constraints

- Action หลัก: สมัครโพสต์ (ผู้สมัคร) และตอบรับ/ปฏิเสธ/ปิดรับ (เจ้าของ)
- เนื้อหาจริง: ข้อความไทยเดิมทุกหน้า, ข้อมูลจาก API จริง, ไม่มี mock
- ข้อจำกัด: คงฟังก์ชันและ API contract เดิม, LINE ID เห็นเฉพาะ accepted, ไม่เพิ่ม dependency, ใช้ CSS ธรรมดา
- สิ่งที่ถือว่าผิด: corporate/แข็ง และสีฉูดฉาด/นีออน

## Chosen direction

THE ROLL — หน้ากระดาษที่แก้ร่วมกัน (Google Docs collaborative page)
Memorable moment: ใบสมัครถูกอ่านเป็น comment ในคอลัมน์ขวา และการตอบรับคือการ accept suggestion ที่ไฮไลต์ด้วย lime

## Direction contract

THESIS: กระดานหาทีมคือสารบัญของเอกสารกลาง ไม่ใช่กำแพงการ์ด; หน้ารายละเอียดคือเอกสาร และใบสมัครคือ comment ในคอลัมน์ขวา — ปฏิเสธโครง top-nav + filter bar + card grid แบบเว็บหาทีมทั่วไป

OWN-WORLD: กระดาษขาว `#ffffff` บนพื้น `#f7f7f3`, หมึก `#16170f`, เส้น hairline `#e2e3da`, ไฮไลต์เดียว highlighter lime `#d8f24a`, ตัวอักษร Anuphan + mono สำหรับ metadata, มุม 2px, ไม่มีเงาและ gradient

STORY: ผู้ใช้อ่านหัวข้อโพสต์เหมือนสารบัญ → เปิดเอกสาร → สมัครในช่อง comment → เจ้าของตอบรับแบบ accept suggestion → LINE ปรากฏใน comment ที่ resolved

FIRST VIEWPORT: topbar บาง 56px; เอกสารกว้างสุด 980px กลางจอ; หัวเรื่องใหญ่ชิดซ้ายพร้อม eyebrow mono; toolbar ตัวกรองเป็นเส้นใต้; รายการโพสต์เป็นแถวมีเลขกำกับ 01/02/03 คั่นด้วย hairline; ปุ่มสร้างโพสต์มุมขวาบนของเอกสาร; ไม่มีการ์ดเงา

FORM: grounded candidate 3/7 — Google Docs collaborative page; seed key 12b052ee

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Unresolved

- ไม่มี; ข้อความไทยและพฤติกรรมเดิมถูกตรึงไว้
