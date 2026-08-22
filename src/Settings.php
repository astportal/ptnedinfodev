<?php

/**
 * Simple key-value app settings backed by app_settings (migrations/004_academic_year.sql).
 * Every call degrades gracefully to the given default if that migration hasn't run yet, so the
 * rest of the app never has to special-case "table doesn't exist".
 */
class Settings
{
    public static function get(PDO $db, string $key, ?string $default = null): ?string
    {
        try {
            $stmt = $db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :k');
            $stmt->execute(['k' => $key]);
            $value = $stmt->fetchColumn();
            return $value !== false ? $value : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    public static function set(PDO $db, string $key, string $value): void
    {
        $stmt = $db->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2'
        );
        $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
    }

    /** ปีการศึกษาปัจจุบัน (พ.ศ.) — ใช้แปะให้ทุกไฟล์ที่อัปโหลดใหม่โดยอัตโนมัติ */
    public static function currentAcademicYear(PDO $db): int
    {
        return (int)self::get($db, 'current_academic_year', (string)(date('Y') + 543));
    }
}
