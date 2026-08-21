<?php
/**
 * คัดลอกไฟล์นี้เป็น config.php แล้วใส่ค่าจริง (config.php ไม่ถูก commit ขึ้น git)
 */

return [
    'db' => [
        'host'    => '127.0.0.1',
        'name'    => 'ptnedinfo',
        'user'    => 'db_user',
        'pass'    => 'db_password',
        'charset' => 'utf8mb4',
    ],
    // ใช้เข้ารหัสรหัสผ่านผู้ดูแลระบบ / คุกกี้ session — สุ่มค่าใหม่แล้วเก็บเป็นความลับ
    'app_key' => 'change-this-to-a-random-string',
];
