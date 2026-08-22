-- เพิ่มการรองรับหลายปีการศึกษา (เลือกดู/อัปโหลดแยกตามปีได้)
-- รันไฟล์นี้ผ่าน phpMyAdmin บนฐานข้อมูลที่ติดตั้ง schema.sql + 002 + 003 ไปแล้ว (ครั้งเดียว)

SET NAMES utf8mb4;

-- ค่าตั้งค่าระบบแบบ key-value ทั่วไป — ใช้เก็บ "ปีการศึกษาปัจจุบัน" ที่จะแปะให้ทุกไฟล์ที่อัปโหลด
-- ใหม่โดยอัตโนมัติ (ไม่ต้องเลือกปีเองทุกครั้งที่อัปโหลด เพราะไฟล์ฟอร์มสำรวจไม่มีคอลัมน์ปีอยู่ในไฟล์)
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key   VARCHAR(64)  NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ค่าเริ่มต้น 2569 (ปี พ.ศ. ตอนที่เขียน migration นี้) — เปลี่ยนได้ทันทีที่หน้า settings.php
INSERT INTO app_settings (setting_key, setting_value)
    VALUES ('current_academic_year', '2569')
    ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- แปะปีการศึกษาให้ทุกไฟล์ที่เคยอัปโหลดมาก่อนหน้านี้เป็น 2569 ไปก่อน (ค่า default) แก้เองทีหลังได้
-- ถ้าจำเป็น ผ่าน SQL โดยตรง ระบบเว็บไม่มีหน้าจอแก้ปีของข้อมูลเก่าย้อนหลัง
ALTER TABLE uploads
    ADD COLUMN academic_year SMALLINT UNSIGNED NOT NULL DEFAULT 2569 AFTER sheet_name;
ALTER TABLE uploads
    ADD KEY idx_academic_year (academic_year);

ALTER TABLE submissions
    ADD COLUMN academic_year SMALLINT UNSIGNED NOT NULL DEFAULT 2569 AFTER sheet_name;
ALTER TABLE submissions
    ADD KEY idx_form_sheet_year (form_key, sheet_name, academic_year);

-- ทำเนียบโรงเรียน (schools_master) เก็บแยกตามปีการศึกษาด้วย — โรงเรียนเดียวกันอาจมีข้อมูลต่าง
-- ปีต่างกันได้ (เช่น รหัสสถานศึกษาเดิมแต่เปลี่ยนสังกัด) จึงเปลี่ยนคีย์หลักจาก school_code เดี่ยว ๆ
-- เป็นคู่ (academic_year, school_code) — ปีของไฟล์ทำเนียบอ่านมาจากคอลัมน์ YearEdu ในไฟล์เองอัตโนมัติ
-- ไม่ต้องให้แอดมินกรอกปีเอง (ต่างจากฟอร์มสำรวจ 1-15 ที่ไม่มีคอลัมน์ปีในไฟล์)
ALTER TABLE schools_master
    ADD COLUMN academic_year SMALLINT UNSIGNED NOT NULL DEFAULT 2569 AFTER school_code;
ALTER TABLE schools_master
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (academic_year, school_code);
