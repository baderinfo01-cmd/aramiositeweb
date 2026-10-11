<?php
require_once __DIR__ . '/../includes/init.php';
$me = require_role(['trainee'], '../login.php');
$user_id = (int)$me['id'];
$dashRole = 'trainee';
// إلغاء الحجوزات غير المدفوعة بعد 60 دقيقة (دورات واستشارات)
$pdo->exec("UPDATE course_orders SET status='canceled' WHERE status='reserved' AND created_at <= DATE_SUB(NOW(), INTERVAL 60 MINUTE)");
foreach ($pdo->query("SELECT id, availability_id FROM consultation_bookings WHERE status='reserved' AND created_at <= DATE_SUB(NOW(), INTERVAL 60 MINUTE)")->fetchAll() as $x) {
    $pdo->prepare("UPDATE trainer_availabilities SET is_booked=0 WHERE id=?")->execute([$x['availability_id']]);
    $pdo->prepare("DELETE FROM consultation_bookings WHERE id=?")->execute([$x['id']]);
}
function owns_course(int $uid, int $cid): bool { global $pdo; $s = $pdo->prepare("SELECT 1 FROM course_orders WHERE user_id=? AND course_id=? AND status='approved'"); $s->execute([$uid, $cid]); return (bool)$s->fetchColumn(); }
function course_progress(int $uid, int $cid): array {
    global $pdo;
    $t = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id=?"); $t->execute([$cid]); $total = (int)$t->fetchColumn();
    $d = $pdo->prepare("SELECT COUNT(*) FROM lesson_completions lc JOIN lessons l ON l.id=lc.lesson_id WHERE lc.trainee_id=? AND l.course_id=?"); $d->execute([$uid, $cid]); $done = (int)$d->fetchColumn();
    return [$total, $done, $total ? (int)round($done / $total * 100) : 0];
}
