---
name: Team Finder
description: กระดานหาทีมสำหรับนักศึกษาไทย ในรูปเอกสารที่แก้ร่วมกัน
colors:
  highlighter-lime: "#d8f24a"
  lime-wash: "#f4f9d6"
  paper-white: "#ffffff"
  chip-paper: "#fbfbf7"
  desk-grey: "#f7f7f3"
  wash: "#f2f3ea"
  ink: "#16170f"
  ink-soft: "#4b4e43"
  graphite: "#6f7266"
  hairline: "#e3e4db"
  hairline-strong: "#c6c8bb"
  rust: "#a3341f"
typography:
  display:
    fontFamily: Anuphan
    fontSize: "clamp(30px, 4.2vw, 46px)"
    fontWeight: 600
    lineHeight: 1.08
    letterSpacing: -0.03em
  headline:
    fontFamily: Anuphan
    fontSize: 20px
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: -0.02em
  title:
    fontFamily: Anuphan
    fontSize: 21px
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: -0.02em
  body:
    fontFamily: Anuphan
    fontSize: 15px
    fontWeight: 400
    lineHeight: 1.65
  label:
    fontFamily: "Cascadia Mono"
    fontSize: 10px
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: 0.1em
  meta:
    fontFamily: "Cascadia Mono"
    fontSize: 11px
    fontWeight: 500
    lineHeight: 1.5
    letterSpacing: 0.01em
rounded:
  none: "0px"
  xs: "2px"
spacing:
  "2xs": "4px"
  xs: "6px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "20px"
  "2xl": "24px"
  "3xl": "32px"
  "4xl": "46px"
  "5xl": "64px"
components:
  button-primary:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.paper-white}"
    rounded: "{rounded.xs}"
    padding: "0 16px"
    height: "40px"
  button-primary-hover:
    backgroundColor: "{colors.highlighter-lime}"
    textColor: "{colors.ink}"
    rounded: "{rounded.xs}"
    padding: "0 16px"
    height: "40px"
  button-secondary:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    rounded: "{rounded.xs}"
    padding: "0 16px"
    height: "40px"
  input-underline:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    rounded: "{rounded.none}"
    padding: "8px 0"
    height: "38px"
  skill-chip:
    backgroundColor: "{colors.chip-paper}"
    textColor: "{colors.ink-soft}"
    rounded: "{rounded.xs}"
    padding: "3px 7px"
  post-row:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    rounded: "{rounded.none}"
    padding: "22px 12px 22px 46px"
---

# Design System: Team Finder

## Overview

**Creative North Star: "หน้ากระดาษที่แก้ร่วมกัน" (The Shared Page)**

Team Finder ไม่ได้อ่านเป็นเว็บหาทีมทั่วไปที่เต็มไปด้วยการ์ดลอยและเงานุ่ม แต่เป็นเอกสารแผ่นเดียวที่คนหลายคนเข้ามาเขียนร่วมกัน: กระดานคือสารบัญของโพสต์ หน้ารายละเอียดคือหน้าของเอกสาร และใบสมัครคือ comment ที่รอถูกตอบรับในคอลัมน์ขวา ภาษาภาพทั้งหมดจึงมาจากเครื่องมือทำงานจริง — กระดาษ หมึก เส้นบรรทัด ปากกาไฮไลต์ และตัวเลขกำกับในสารบัญ — ไม่ใช่จาก UI kit สำเร็จรูป

ความเรียบน้อยที่สุดไม่ได้หมายถึงความจืด: ตัวอักษรไทยขนาดใหญ่ น้ำหนัก 600 และช่องว่างที่ใจกว้างทำหน้าที่เป็นลำดับชั้นแทนสีและเงา มีสีเน้นเพียงสีเดียวคือไฮไลต์ lime ที่ทำตัวเหมือนปากกาเน้นข้อความ ใช้เมื่อข้อมูลนั้นกำลังถูกยืนยัน ไม่ใช่เพื่อตกแต่ง พื้นที่ว่างคือส่วนหนึ่งของงาน: เอกสารมีขอบชัด มีขอบกระดาษที่มองเห็น และมีที่ให้เขียน

ทุกหน้าอยู่ในโหมด Operate — ผู้ใช้ต้องสแกน หา สมัคร และติดตามสถานะได้เร็ว ภาษาเอกสารจึงต้องไม่ขัดขวางงาน: เส้น hairline แยกทุกส่วน ป้ายสถานะเป็นสี่เหลี่ยมเล็ก ๆ 6px ที่อ่านได้จากระยะไกล และ metadata ทั้งหมดเป็น mono เพื่อแยก "ข้อมูล" ออกจาก "ข้อความ"

**Key Characteristics:**
- กระดาษขาวบนโต๊ะสีอุ่น ไม่มีการ์ดและไม่มีเงา
- ไฮไลต์ lime เดียว ทำหน้าที่เป็นปากกาเน้น ไม่ใช่พื้นหลังใหญ่
- Anuphan ไทยตัวใหญ่คม ประกบ mono บาง ๆ สำหรับ metadata
- เส้น hairline 1px และเลขกำกับ 01/02/03 แทนขอบเขตการ์ด
- มุม 2px เท่านั้น ไม่มี pill ไม่มีวงกลม

## Colors

ชุดสีเป็นกระดาษและหมึกเกือบทั้งหมด มีสีเน้นเดียวที่จงใจให้ดูเหมือนปากกาไฮไลต์

### Primary
- **Highlighter Lime** (#d8f24a): ปากกาเน้นข้อความของระบบ ใช้กับจุดสถานะ "เปิดรับ", พื้นหลังตอนเลือกข้อความ, ไฮไลต์ LINE ID ที่ถูกตอบรับ, ปุ่มหลักตอน hover และแถวทักษะที่ถูกเลือก ห้ามใช้เป็นพื้นหลังขนาดใหญ่ หรือเป็นสีตัวอักษรบนกระดาษขาว
- **Lime Wash** (#f4f9d6): เงาของไฮไลต์ ใช้เป็นพื้นหลัง alert สำเร็จเท่านั้น

### Neutral
- **Paper White** (#ffffff): กระดาษของเอกสาร พื้นหลัง topbar, sheet, การ์ด auth และช่องกรอก
- **Chip Paper** (#fbfbf7): พื้นของ skill chip เท่านั้น
- **Desk Grey** (#f7f7f3): โต๊ะที่วางกระดาษ พื้นหลัง body ทั้งแอป
- **Wash** (#f2f3ea): สี hover ของแถวและลิงก์ nav, พื้น alert, พื้นกล่องสถานะใบสมัคร
- **Ink** (#16170f): ตัวอักษรหลัก ปุ่มหลัก ขอบโฟกัส และเส้นที่ต้องหนักที่สุด
- **Ink Soft** (#4b4e43): ข้อความรอง ข้อความใน chip และ nav ปกติ
- **Graphite** (#6f7266): metadata, เลขลำดับโพสต์, label ของฟิลด์, ข้อความช่วย
- **Hairline** (#e3e4db): เส้นแบ่งทุกเส้นในเอกสาร
- **Hairline Strong** (#c6c8bb): เส้นใต้ช่องกรอก และขอบปุ่มรอง
- **Rust** (#a3341f): ข้อความ error และเส้นบนสุดของ alert ผิดพลาด

### Named Rules
**The One Highlighter Rule.** สี lime ปรากฏได้ไม่เกินหนึ่งจุดต่อหนึ่งหน้าจอ และต้องหมายถึง "สิ่งนี้กำลังถูกยืนยัน" เท่านั้น ถ้าเอาออกแล้วความหมายไม่เปลี่ยน แปลว่าไม่ควรมีตั้งแต่แรก

**The Paper First Rule.** พื้นหลังของเนื้อหาคือขาวเสมอ สีอุ่น (#f7f7f3) สงวนไว้ให้โต๊ะรอบเอกสาร ห้ามกลับด้าน (ห้ามเอา ink เป็นพื้นหลังทั้งหน้า)

## Typography

**Display Font:** Anuphan (self-hosted variable, Thai + Latin, fallback Leelawadee UI / Noto Sans Thai)
**Body Font:** Anuphan
**Label/Mono Font:** Cascadia Mono (fallback SFMono-Regular / Consolas)

**Character:** Anuphan ให้ความรู้สึกเป็นตัวพิมพ์ไทยร่วมสมัยที่คมและอ่านง่ายบนจอ ตัวใหญ่และแน่นพอให้หัวข้อหนึ่งบรรทัดอ่านจบในสายตาเดียว ส่วน mono ทำหน้าที่เป็นชั้นข้อมูล (เลข วันที่ ชื่อฟิลด์) เพื่อให้ metadata ดูเหมือนพิมพ์ด้วยเครื่อง ไม่ใช่ประโยค

### Hierarchy
- **Display** (600, clamp 30–46px, 1.08, -0.03em): หัวเรื่องของแต่ละหน้า ขึ้นบรรทัดใหม่ได้แต่ไม่เกินสองบรรทัด
- **Title** (600, 21px, 1.3, -0.02em): ชื่อโพสต์ในสารบัญ
- **Headline** (600, 20px, 1.3): หัวข้อย่อยในเอกสาร และหัวข้อ empty state
- **Body** (400, 15–16px, 1.65, max 68ch): คำอธิบายโพสต์ ข้อความใบสมัคร และเนื้อหาเอกสาร
- **Label** (600 mono, 10px, 0.1em, uppercase): ชื่อฟิลด์, legend, wordmark และป้ายสถานะ
- **Meta** (500 mono, 11px, tabular-nums): จำนวนคน วันที่ ชื่อเจ้าของ และเลขลำดับ 01/02/03

### Named Rules
**The Two-Voice Rule.** มีสองเสียงเท่านั้น: Anuphan พูด และ mono กำกับ ห้ามใช้ mono เป็นประโยคยาว และห้ามใช้ Anuphan กับ metadata ตัวเลข

**The No-Kicker Rule.** หัวข้อไม่ต้องมีป้ายเล็ก ๆ เหนือมัน ชื่อหน้าและเส้น hairline ทำหน้าที่นั้นอยู่แล้ว

## Layout

เอกสารวางกลางโต๊ะกว้างสุด 1040px ขอบ 1px และ padding ภายใน `clamp(28px, 5vw, 64px)` แนวตั้ง ระยะขอบบน–ล่าง `clamp(16px, 3vw, 32px)` / `clamp(48px, 8vw, 96px)` ทำให้ที่ 1440px มีโต๊ะอุ่นเหลือรอบกระดาษชัดเจน

Topbar สูง 56px ติดบน (sticky) พื้นขาว เส้น hairline ล่าง ภายในเรียง wordmark → nav → ผู้ใช้ → ออกจากระบบ ชิดขวาด้วย `margin-left: auto`

กระดานโพสต์เป็นสารบัญ: แต่ละแถวมีเลข mono อยู่ในรางซ้ายกว้าง 46px คั่นด้วย hairline ล่าง ไม่มีการ์ดและไม่มีระยะห่างระหว่างแถว เนื้อหาภายในเรียง ป้ายสถานะ → ชื่อโพสต์ → คำอธิบาย 2 บรรทัด → metadata → ทักษะ

หน้ารายละเอียดที่กว้าง ≥820px แบ่งเป็น 2 คอลัมน์ `minmax(0, 1fr) 300px` ช่องว่าง 56px คอลัมน์ขวาคือ owner rail ที่ติดตามการเลื่อน (sticky top 88px) มีเส้น hairline ซ้ายกั้นและไม่มีพื้นหลัง

ที่ ≤860px กระดาษกลายเป็นเต็มความกว้าง ไม่มีขอบและมุมโค้ง topbar ตัดเป็นสองแถว (nav เลื่อนแนวนอนได้) และ filter bar ยุบเป็น 2 คอลัมน์; ที่ ≤560px filter เป็นคอลัมน์เดียว skill grid เป็นคอลัมน์เดียว และเลขลำดับแถวขยับเข้ามาเป็นราง 34px

## Elevation & Depth

ระบบนี้แบนสนิท: **ไม่มี `box-shadow` แม้แต่จุดเดียว** และไม่มี gradient ความลึกมาจากสามอย่างเท่านั้น — กระดาษขาวที่ลอยบนพื้นอุ่น, เส้น hairline ที่บอกขอบเขต และคอลัมน์ขวาที่ตรึงอยู่ขณะเนื้อหาซ้ายเลื่อน

### Named Rules
**The Flat Page Rule.** ถ้าต้องเพิ่มเงาเพื่อให้อ่านออก แปลว่าลำดับชั้นยังผิด ให้แก้ด้วยขนาดตัวอักษร น้ำหนัก หรือเส้น ก่อนเสมอ

## Shapes

มุมโค้งสูงสุด 2px ใช้กับปุ่ม ช่องข้อความหลายบรรทัด chip และ checkbox เท่านั้น ช่องกรอกแบบบรรทัดเดียวไม่มีกรอบบน/ข้าง/ล่างเป็นมุม 0 เพราะใช้เส้นใต้แทนกล่อง

รูปทรงที่วนซ้ำคือ **สี่เหลี่ยมจัตุรัสเล็ก**: จุดสถานะ 6px, ป้าย wordmark 9px, checkbox 16px, marker ของใบสมัคร 16px ทั้งหมดเป็นเหลี่ยม ไม่มีวงกลม ไม่มี pill และไม่มี avatar กลมในระบบ

### Named Rules
**The Square Marker Rule.** สถานะทุกชนิดแสดงด้วยสี่เหลี่ยมจัตุรัส ไม่ใช่จุดกลม สีของสี่เหลี่ยมคือความหมาย (lime = เปิด/รอ, ink = ยอมรับ, เส้นว่าง = ปิด/ปฏิเสธ)

## Components

### Buttons
- **Shape:** สี่เหลี่ยมมุม 2px สูง 40px (เล็ก 30–36px ใน toolbar และแถวใบสมัคร)
- **Primary:** พื้น ink ตัวอักษรขาว padding `0 16px` น้ำหนัก 600 ขนาด 13px
- **Hover / Focus:** primary เปลี่ยนเป็นพื้น lime ตัวอักษร ink ใน 90ms แบบ steps(2) ให้ความรู้สึกเป็นดิจิทัลไม่เนียน; focus เป็น outline 2px ink offset 2px
- **Secondary:** พื้นโปร่งใส ขอบ 1px hairline-strong ตัวอักษร ink; hover พื้น wash ขอบ ink
- **Disabled:** opacity 0.45 และ cursor wait (ระหว่างส่งฟอร์ม) โดยไม่เปลี่ยนสีพื้น

### Chips
- **Style:** พื้น #fbfbf7 ขอบ 1px hairline มุม 2px ตัวอักษร 12px สี ink-soft ระยะ `3px 7px`
- **State:** ใช้แสดงทักษะเท่านั้น ไม่ทำหน้าที่เป็นตัวกรองที่กดได้

### Cards / Containers
- **Corner Style:** 0 (การ์ดไม่มีอยู่จริงในระบบ)
- **Background:** กระดาษขาวเฉพาะ `.page-shell` และ `.auth-card`; เนื้อหาข้างในโปร่งใส
- **Shadow Strategy:** ไม่มี — ดู Elevation & Depth
- **Border:** 1px hairline รอบ sheet และ auth card; ภายในใช้ hairline ล่าง/บนเท่านั้น
- **Internal Padding:** `clamp(28px, 5vw, 64px)` แนวนอน/บน, ล่าง `clamp(48px, 7vw, 80px)`

### Inputs / Fields
- **Style:** ช่องบรรทัดเดียวไม่มีกล่อง พื้นโปร่งใส เส้นใต้ 1px hairline-strong สูง 38px ตัวอักษร 15px; textarea มีกรอบเต็ม 1px มุม 2px
- **Focus:** เส้นใต้เปลี่ยนเป็น ink (ไม่หนาขึ้น) พร้อม outline โฟกัสมาตรฐาน
- **Error / Disabled:** เส้นใต้เป็น rust เมื่อ `aria-invalid="true"`; ข้อความ error 12px rust ใต้ฟิลด์
- **Checkbox:** 16px ขอบ 1.5px ink มุม 2px เครื่องหมายถูกเป็น ink บนพื้นขาว; แถวที่เลือกพื้นเป็น lime

### Navigation
- **Style:** ลิงก์ข้อความ 13px น้ำหนัก 500 สี ink-soft padding `6px 10px` มุม 2px; hover พื้น wash ตัวอักษร ink; ไม่มีสถานะ active ที่ใช้สี
- **Mobile:** nav ย้ายไปแถวที่สองของ topbar เลื่อนแนวนอนได้ ซ่อน scrollbar

### Signature Component — สารบัญโพสต์ (Post Row)
แถวเดียวจบ: เลข mono 01/02/03 ในรางซ้าย, ป้ายสถานะแบบสี่เหลี่ยม, ชื่อโพสต์ 21px, คำอธิบาย 2 บรรทัด, metadata mono และ chip ทักษะ hover ทั้งแถวเป็น wash และขีดเส้นใต้ชื่อโพสต์ ไม่มีการยกการ์ด ไม่มีเงา

### Signature Component — Owner Rail (comment column)
คอลัมน์ขวา 300px ของหน้ารายละเอียด ใช้แทน "comment" ของเอกสาร: เส้น hairline ซ้าย, หัวข้อ "ผู้สมัคร (n)" ขนาด 14px, แต่ละใบสมัครมี marker สี่เหลี่ยม 16px พื้น lime, ข้อความ 13px และปุ่มตอบรับ/ปฏิเสธขนาดเล็ก เมื่อตอบรับแล้ว LINE ID ของอีกฝ่ายถูกเน้นด้วย lime ในการ์ดสถานะด้านซ้าย

## Do's and Don'ts

### Do:
- **Do** ใช้ `#ffffff` เป็นพื้นเอกสารและ `#f7f7f3` เป็นพื้นโต๊ะเท่านั้น
- **Do** แยกทุกส่วนด้วยเส้น 1px `#e3e4db` ก่อนคิดจะเพิ่มระยะห่างหรือเงา
- **Do** ใช้ lime `#d8f24a` เป็นปากกาเน้น: จุดสถานะ, selection, ไฮไลต์ LINE, hover ของปุ่มหลัก
- **Do** ตั้งชื่อหน้าเป็น Display 600 ขนาด clamp 30–46px และปล่อยให้ขึ้นสองบรรทัดได้
- **Do** ใส่ metadata ทุกตัว (วันที่ จำนวนคน ลำดับ) เป็น mono 11px tabular-nums
- **Do** คงมุม 2px และสี่เหลี่ยมจัตุรัสเป็นภาษารูปทรงเดียวของระบบ
- **Do** ให้คอลัมน์ owner rail ตรึงที่ top 88px บนจอ ≥820px

### Don't:
- **Don't** ใส่ `box-shadow` หรือ gradient ใด ๆ ทั้งสิ้น
- **Don't** ใช้ lime เป็นพื้นหลังขนาดใหญ่ หรือเป็นสีตัวอักษรบนกระดาษขาว
- **Don't** สร้างการ์ดลอย มีเงา หรือแยกโพสต์ด้วยระยะห่างระหว่างการ์ด — ใช้แถวและ hairline
- **Don't** ใส่ eyebrow/kicker เหนือหัวข้อ
- **Don't** ใช้ pill, วงกลม, avatar กลม หรือมุมโค้งเกิน 2px
- **Don't** ใช้ mono เป็นประโยค หรือใช้ Anuphan กับตัวเลข metadata
- **Don't** เพิ่มสีใหม่เพื่อแสดงสถานะ ใช้สีของสี่เหลี่ยม marker ตามที่กำหนด
- **Don't** เปลี่ยนข้อความไทยหรือพฤติกรรมเดิมของแอประหว่างงานภาพ
