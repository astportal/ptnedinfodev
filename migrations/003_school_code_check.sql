-- เพิ่มระบบตรวจสอบรหัสสถานศึกษาที่กรอกมาเทียบกับทำเนียบโรงเรียน (schools_master)
-- รันไฟล์นี้ผ่าน phpMyAdmin บนฐานข้อมูลที่ติดตั้ง schema.sql (และ 002_needs_review.sql) ไปแล้ว (ครั้งเดียว)

SET NAMES utf8mb4;

-- ทำเนียบโรงเรียนอ้างอิง — อัปโหลด/แทนที่ทั้งตารางได้ผ่านหน้า schools_master.php (เปลี่ยนทุกปีการศึกษา)
CREATE TABLE IF NOT EXISTS schools_master (
    school_code VARCHAR(20)  NOT NULL PRIMARY KEY,
    school_name VARCHAR(255) NOT NULL,
    tambon      VARCHAR(120) NULL,
    amphoe      VARCHAR(120) NULL,
    province    VARCHAR(120) NULL,
    department  VARCHAR(120) NULL,
    area_name   VARCHAR(120) NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ผลตรวจสอบรหัสสถานศึกษาของแต่ละแถวข้อมูล (เฉพาะฟอร์มที่มีคอลัมน์นี้) — NULL = ไม่มีปัญหา
ALTER TABLE submissions
    ADD COLUMN school_code_issue ENUM('missing', 'not_found') NULL AFTER school_code;

ALTER TABLE submissions
    ADD KEY idx_school_code_issue (school_code_issue);
