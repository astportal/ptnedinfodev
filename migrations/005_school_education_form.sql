-- เพิ่มคอลัมน์ "รูปแบบการศึกษา" (เช่น "ในระบบ") ในทำเนียบโรงเรียน (schools_master) — พบคอลัมน์นี้ใน
-- ไฟล์ทำเนียบฉบับใหม่ (SchoolDetail_2569.xlsx ชีท "2569_School") ที่ไฟล์เดิม
-- (91_SchoolCode-Matching-V1.xlsx) ไม่มี — ใช้ทำกราฟ "จำนวนผู้เรียนแยกตามรูปแบบการศึกษา"
-- รันไฟล์นี้ผ่าน phpMyAdmin บนฐานข้อมูลที่รัน 003_school_code_check.sql ไปแล้ว (ครั้งเดียว)

SET NAMES utf8mb4;

ALTER TABLE schools_master
    ADD COLUMN education_form VARCHAR(120) NULL AFTER area_name;
