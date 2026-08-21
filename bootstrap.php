<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(500);
    die('ยังไม่ได้ตั้งค่าระบบ: กรุณาคัดลอก config.sample.php เป็น config.php แล้วใส่ค่าฐานข้อมูลให้ถูกต้อง');
}

require_once __DIR__ . '/src/Db.php';
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
