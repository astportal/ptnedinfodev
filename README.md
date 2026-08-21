# ptnedinfo — ระบบรวบรวมข้อมูลจากแบบฟอร์ม Excel

เว็บแอปสำหรับอัปโหลดไฟล์ Excel ที่หน่วยงานต่าง ๆ กรอกและส่งกลับมา แล้วรวบรวมเป็นฐานข้อมูลเดียว
ดูข้อมูลรวมและส่งออกเป็น CSV ได้ทันที รองรับตารางแบบ double/triple header โดยอัตโนมัติ

ภาษา: PHP + MySQL (ไม่ใช้ Composer/framework — รันได้บน shared hosting ทั่วไปที่มี PHP + ext-zip + ext-pdo_mysql)

## สถาปัตยกรรม

- `src/XlsxReader.php` — อ่านไฟล์ .xlsx ด้วย ZipArchive + DOM (ไม่พึ่ง library ภายนอก) คืนค่าเป็น grid ของแต่ละชีท
  พร้อม merge-fill เซลล์ที่ถูก merge เพื่อให้ header หลายแถวอ่านค่าได้ถูกต้อง
- `forms/registry.php` — จุดเดียวที่ต้องแก้เพื่อเพิ่มฟอร์มใหม่ (ดูรายละเอียดด้านล่าง)
- `src/Importer.php` — ตัวนำเข้าข้อมูลแบบ generic: อ่านตาม config ใน registry แล้วรวม header ทุกแถว
  ของแต่ละคอลัมน์เป็น "column_path" เดียว (เช่น `ปริญญาตรี / ปีที่ 1 / ชาย`) ทำให้รองรับตาราง
  double/triple header ได้โดยไม่ต้อง mapping ทีละคอลัมน์ด้วยมือ
- `src/Reporting.php` — ประกอบข้อมูลกลับเป็นตาราง pivot (เหมือนไฟล์ต้นฉบับ) สำหรับหน้าดูข้อมูล/ส่งออก
- ฐานข้อมูล: `submissions` (1 แถว = 1 หน่วยงาน/โรงเรียนต่อฟอร์ม) + `submission_values` (ค่าต่อคอลัมน์แบบ
  key-value) — อัปโหลดไฟล์ซ้ำของหน่วยงาน/โรงเรียนเดิม จะแทนที่ข้อมูลเดิมอัตโนมัติ (อิงรหัสสถานศึกษา
  หรือชื่อหน่วยงานถ้าไม่มีรหัส)

## การติดตั้งครั้งแรก (บน Plesk / HostAtom)

1. เชื่อม Git ตามที่ตั้งค่าไว้ใน `ai_note.md` (Plesk Git Extension ดึงจาก branch `develop`/`main`)
2. สร้างฐานข้อมูล MySQL ใน Plesk แล้ว import `migrations/schema.sql`
3. คัดลอก `config.sample.php` เป็น `config.php` แล้วใส่ค่าฐานข้อมูลจริง (ไฟล์นี้ไม่ถูก commit ขึ้น git)
4. สร้างบัญชีผู้ดูแลระบบผ่าน SSH:
   ```bash
   php scripts/create_admin.php admin "รหัสผ่านของคุณ" "ชื่อที่แสดง"
   ```
5. ตั้งค่า Plesk ให้ document root ชี้ที่ root ของ repo (ไฟล์ `index.php` อยู่ที่ root)
6. เข้าเว็บ → login.php → เข้าสู่ระบบด้วยบัญชีที่สร้างไว้

## การเพิ่มฟอร์มใหม่

แบบฟอร์มต้นฉบับทั้ง 16 ไฟล์ (55 ชีทรวมกัน) อยู่ในโฟลเดอร์ `exelform/` (ไม่ได้ commit ขึ้น git เพราะเป็นไฟล์ต้นฉบับ)
ระบบตอนนี้รองรับฟอร์มตัวอย่างไว้ 3 ฟอร์มแรก (`1_agency`, `2_school_basic`, `3_classrooms`) เพื่อพิสูจน์ว่า
ตัวนำเข้าข้อมูลแบบ generic ใช้ได้กับโครงสร้าง header หลายแบบ ในการเพิ่มฟอร์มที่เหลือ:

1. เปิดไฟล์ .xlsx ต้นฉบับ ดูว่าชีทนั้นมี**กี่แถว header** (รวมแถวชื่อตาราง) และ**กี่คอลัมน์แรกที่เป็นข้อมูลระบุตัวตน**
   (ลำดับที่ / รหัสสถานศึกษา / สังกัด / ชื่อ / อำเภอ / ตำบล ฯลฯ) ก่อนที่จะเริ่มเป็นคอลัมน์ตัวเลขรายงาน
2. เพิ่ม entry ใหม่ใน `forms/registry.php` ตามรูปแบบเดิม — **ไม่ต้อง map ทีละคอลัมน์ตัวเลข** เพราะ
   `Importer` จะรวม header หลายแถวของแต่ละคอลัมน์เป็น path อัตโนมัติ
3. เสร็จแล้ว — หน้า dashboard/upload/view/export จะรองรับฟอร์มใหม่ทันทีโดยไม่ต้องแก้โค้ดอื่น

ถ้าไฟล์ที่ได้รับจริงมีแถวตัวอย่าง/placeholder หลงเหลืออยู่ (เช่น "รหัสสถานศึกษา 10 หลัก" หรือมี "...")
ระบบจะข้ามแถวเหล่านั้นให้อัตโนมัติ (ดู `Importer::isRealDataRow`)

## โครงสร้างไฟล์

```
bootstrap.php        โหลด config/DB/Auth/Importer ให้ทุกหน้า
config.sample.php     ตัวอย่าง config (คัดลอกเป็น config.php)
index.php             แดชบอร์ดรายการฟอร์ม
login.php / logout.php
upload.php            หน้าอัปโหลดไฟล์ + นำเข้าข้อมูล
view.php               หน้าดูข้อมูลรวม (ตาราง pivot)
export.php             ดาวน์โหลด CSV
src/                   คลาสหลักของระบบ
forms/registry.php     ค่า config ของแต่ละฟอร์ม/ชีท
migrations/schema.sql  โครงสร้างฐานข้อมูล
scripts/create_admin.php
uploads/                ไฟล์ที่อัปโหลด (ไม่ commit ขึ้น git)
```

## Git workflow

ดู `ai_note.md` — พัฒนาบน branch `develop` (deploy อัตโนมัติไป dev-ptnedinfo.timevela.com)
แล้ว merge เข้า `main` เมื่อทดสอบผ่านแล้ว (deploy อัตโนมัติไป ptnedinfo.timevela.com)
