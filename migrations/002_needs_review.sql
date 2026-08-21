-- เพิ่มระบบตรวจสอบค่าที่กรอกมาไม่ชัดเจนว่าเป็นตัวเลขหรือไม่
-- รันไฟล์นี้ผ่าน phpMyAdmin บนฐานข้อมูลที่ติดตั้ง schema.sql ไปแล้ว (ครั้งเดียว)

ALTER TABLE submission_values
    ADD COLUMN needs_review TINYINT(1) NOT NULL DEFAULT 0 AFTER value;

ALTER TABLE submission_values
    ADD KEY idx_needs_review (needs_review);
