<?php
/**
 * สร้างบัญชีผู้ดูแลระบบ (รันครั้งเดียวตอนติดตั้ง)
 * วิธีใช้ (SSH บนโฮสต์ หรือเครื่อง dev ที่มี PHP):
 *   php scripts/create_admin.php <username> <password> "<ชื่อที่แสดง>"
 */
require_once __DIR__ . '/../bootstrap.php';

if ($argc < 3) {
    fwrite(STDERR, "วิธีใช้: php scripts/create_admin.php <username> <password> \"<ชื่อที่แสดง>\"\n");
    exit(1);
}

[$script, $username, $password] = $argv;
$displayName = $argv[3] ?? $username;

$db = Db::conn();
$stmt = $db->prepare(
    'INSERT INTO users (username, password_hash, display_name) VALUES (:u, :p, :d)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), display_name = VALUES(display_name)'
);
$stmt->execute([
    'u' => $username,
    'p' => password_hash($password, PASSWORD_DEFAULT),
    'd' => $displayName,
]);

echo "สร้าง/อัปเดตผู้ใช้ '$username' เรียบร้อยแล้ว\n";
