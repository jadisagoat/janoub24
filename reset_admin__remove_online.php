<?php
/**
 * ملف لمرة واحدة: يعيد تعيين كلمة مرور المدير ثم يحذف نفسه.
 * الاستخدام: https://janoub24.com/reset_admin.php?key=d28f272063d98af7
 */
declare(strict_types=1);
if (($_GET['key'] ?? '') !== 'd28f272063d98af7') { http_response_code(404); exit; }
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
$config = require __DIR__ . '/config.php';
$d = $config['db'];
$pdo = new PDO('mysql:host=' . $d['host'] . ';dbname=' . $d['name'] . ';charset=utf8mb4', $d['user'], $d['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$email = 'admin@janoub24.com';
$pass  = 'OK3KdtrjdeRf';
$hash  = password_hash($pass, PASSWORD_DEFAULT);

$id = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
if ($id) {
    $pdo->prepare('UPDATE users SET email = ?, password_hash = ?, is_active = 1 WHERE id = ?')->execute([$email, $hash, $id]);
} else {
    $pdo->prepare("INSERT INTO users (name, slug, email, password_hash, role) VALUES ('إدارة التحرير', 'editorial', ?, ?, 'admin')")->execute([$email, $hash]);
}
@file_put_contents(__DIR__ . '/storage/login_attempts.json', '[]');

echo "قاعدة البيانات: {$d['name']}\n";
echo "المستخدمون الإداريون:\n";
foreach ($pdo->query("SELECT id, email, role, is_active FROM users WHERE role IN ('admin','editor')") as $r) {
    echo "  #{$r['id']}  {$r['email']}  ({$r['role']}, active={$r['is_active']})\n";
}
echo "\nتم. ادخل الآن:\n  البريد: $email\n  كلمة المرور: $pass\n";
echo @unlink(__FILE__) ? "\nتم حذف هذا الملف تلقائياً.\n" : "\n!! احذف reset_admin.php من الخادم يدوياً الآن.\n";
