-- ptnedinfo — schema สำหรับระบบรวบรวมข้อมูลจากแบบฟอร์ม Excel
-- รันครั้งเดียวตอนติดตั้งระบบใหม่ (phpMyAdmin ใน Plesk หรือ mysql CLI)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(255) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uploads (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_key          VARCHAR(60)  NOT NULL,
    sheet_name        VARCHAR(191) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename   VARCHAR(255) NOT NULL,
    uploaded_by        INT UNSIGNED NULL,
    uploaded_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    row_count          INT UNSIGNED NOT NULL DEFAULT 0,
    status             ENUM('parsed','error') NOT NULL DEFAULT 'parsed',
    error_message      TEXT NULL,
    KEY idx_form (form_key, sheet_name),
    CONSTRAINT fk_uploads_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- หนึ่งแถวข้อมูล (หนึ่งหน่วยงาน/หนึ่งโรงเรียน) ที่ import มาจากไฟล์ที่อัปโหลด
CREATE TABLE IF NOT EXISTS submissions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    upload_id    INT UNSIGNED NOT NULL,
    form_key     VARCHAR(60)  NOT NULL,
    sheet_name   VARCHAR(191) NOT NULL,
    row_seq      INT UNSIGNED NOT NULL,          -- แถวที่กี่ในชีทต้นฉบับ (สำหรับตรวจสอบย้อนกลับ)
    seq_no       VARCHAR(50)  NULL,               -- คอลัมน์ "ลำดับที่" ถ้ามี
    school_code  VARCHAR(20)  NULL,
    agency_name  VARCHAR(255) NULL,
    school_name  VARCHAR(255) NULL,
    amphoe       VARCHAR(120) NULL,
    tambon       VARCHAR(120) NULL,
    extra_identity JSON NULL,                     -- คอลัมน์ identity อื่น ๆ ที่ไม่เข้าฟิลด์มาตรฐานข้างบน
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- คีย์สำหรับ "อัปโหลดซ้ำ = อัปเดตของเดิม" ต่อฟอร์ม/ชีท โดยยึดรหัสสถานศึกษาก่อน ถ้าไม่มีให้ยึดชื่อหน่วยงาน
    KEY idx_form_sheet (form_key, sheet_name),
    KEY idx_school_code (form_key, sheet_name, school_code),
    KEY idx_agency (form_key, sheet_name, agency_name),
    CONSTRAINT fk_submissions_upload FOREIGN KEY (upload_id) REFERENCES uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ค่าข้อมูลรายคอลัมน์ (แบบ generic) เก็บ header หลายชั้นที่ถูกรวมเป็น path เดียว
-- เช่น "ปริญญาตรี / ปีที่ 1 / ชาย" -> ทำให้รองรับตาราง double/triple header ได้โดยไม่ต้อง map ทีละคอลัมน์
CREATE TABLE IF NOT EXISTS submission_values (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    col_index     INT UNSIGNED NOT NULL,   -- ลำดับคอลัมน์ในชีทต้นฉบับ (สำหรับเรียงคอลัมน์ตอน export)
    column_path   VARCHAR(500) NOT NULL,
    value         TEXT NULL,
    needs_review  TINYINT(1) NOT NULL DEFAULT 0,  -- ค่าที่ไม่ใช่ตัวเลข/-/ ที่ระบบอนุมานไม่ได้ ต้องให้ผู้ดูแลตรวจสอบ
    KEY idx_submission (submission_id),
    KEY idx_needs_review (needs_review),
    CONSTRAINT fk_values_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
