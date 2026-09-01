-- เพิ่มตาราง population_by_age — เก็บจำนวนประชากรแยกตามช่วงอายุ x อำเภอ (ข้อมูลจาก กรมการปกครอง
-- stat.bora.dopa.go.th ไม่ใช่ข้อมูลที่หน่วยงานกรอกส่งเหมือนฟอร์มสำรวจ 1-16) ใช้เทียบกับจำนวนผู้เรียน
-- จริง (ฟอร์ม 4) คำนวณ "อัตราการเข้าเรียน" ต่อช่วงอายุ x อำเภอ ในหน้าสาธารณะ — อัปโหลดผ่าน
-- population_upload.php (เก็บแยกตามปีเหมือน schools_master อัปโหลดใหม่ = แทนที่เฉพาะปีนั้น)
-- รันไฟล์นี้ผ่าน phpMyAdmin บนฐานข้อมูลที่รัน 003_school_code_check.sql ไปแล้ว (ต้องมี schools_master
-- ใช้เป็นข้อมูลอ้างอิง ตำบล→อำเภอ ตอนนำเข้าไฟล์ประชากร)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS population_by_age (
    academic_year    SMALLINT UNSIGNED NOT NULL,
    amphoe           VARCHAR(120) NOT NULL,
    age_3_5_male     INT UNSIGNED NOT NULL DEFAULT 0,
    age_3_5_female   INT UNSIGNED NOT NULL DEFAULT 0,
    age_6_11_male    INT UNSIGNED NOT NULL DEFAULT 0,
    age_6_11_female  INT UNSIGNED NOT NULL DEFAULT 0,
    age_12_14_male   INT UNSIGNED NOT NULL DEFAULT 0,
    age_12_14_female INT UNSIGNED NOT NULL DEFAULT 0,
    age_15_17_male   INT UNSIGNED NOT NULL DEFAULT 0,
    age_15_17_female INT UNSIGNED NOT NULL DEFAULT 0,
    age_18_19_male   INT UNSIGNED NOT NULL DEFAULT 0,
    age_18_19_female INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (academic_year, amphoe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
