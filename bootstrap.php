<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(500);
    die('ยังไม่ได้ตั้งค่าระบบ: กรุณาคัดลอก config.sample.php เป็น config.php แล้วใส่ค่าฐานข้อมูลให้ถูกต้อง');
}

require_once __DIR__ . '/src/Db.php';
require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/XlsxReader.php';
require_once __DIR__ . '/src/Importer.php';
require_once __DIR__ . '/src/Reporting.php';

function form_registry(): array
{
    return require __DIR__ . '/forms/registry.php';
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Human label for a non-standard identity field name used in some sheets'
 * identity_fields (see forms/registry.php) — falls back to the raw field name.
 */
function extra_identity_label(string $field): string
{
    static $labels = [
        'age_group'  => 'ช่วงอายุ',
        'admin_name' => 'ผู้บริหาร/ผู้ประสานงาน',
        'phone' => 'เบอร์โทรศัพท์',
        'address' => 'ที่อยู่',
        'postal_code' => 'รหัสไปรษณีย์',
        'license_holder_name' => 'ผู้รับใบอนุญาต',
        'manager_name' => 'ผู้จัดการ',
        'headteacher_name' => 'ครูใหญ่',
        'director_name' => 'ผู้อำนวยการ',
        'tokkhru_name' => 'โต๊ะครู',
        'pondok_size' => 'ขนาดสถาบันศึกษาปอเนาะ',
        'school_type' => 'ประเภทโรงเรียน',
        'established_date' => 'จัดตั้งเมื่อ',
    ];
    return $labels[$field] ?? $field;
}
