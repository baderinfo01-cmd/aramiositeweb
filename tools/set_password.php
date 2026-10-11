<?php
/**
 * أداة سطر أوامر لتغيير كلمة مرور أي مستخدم (مثلاً حساب الأدمن) بأمان.
 * الاستخدام:  php tools/set_password.php admin@aramio.com "كلمة_مرور_جديدة_قوية1"
 * لا تعمل من المتصفح إطلاقًا.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
[$self, $email, $pass] = array_pad($argv, 3, null);
if (!$email || !$pass) { fwrite(STDERR, "Usage: php tools/set_password.php <email> <new-password>\n"); exit(1); }
if (strlen($pass) < 8) { fwrite(STDERR, "Password must be at least 8 characters.\n"); exit(1); }
require __DIR__ . '/../public_html/includes/db.php';
$st = $pdo->prepare("UPDATE users SET password=? WHERE email=?");
$st->execute([password_hash($pass, PASSWORD_DEFAULT), $email]);
echo $st->rowCount() ? "Password updated for $email\n" : "No user found with that email.\n";
