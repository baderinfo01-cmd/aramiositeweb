<?php
require_once 'trainer_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $act = $_POST['act'] ?? ''; $eid = (int)($_POST['exam_id'] ?? 0);
    $st = $pdo->prepare("SELECT e.*, u.full_name AS trainee_name, u.whatsapp_number, c.title_ar FROM certificate_exams e JOIN users u ON u.id=e.trainee_id JOIN courses c ON c.id=e.course_id WHERE e.id=? AND e.trainer_id=? AND e.status='pending'");
    $st->execute([$eid, $trainer_id]); $x = $st->fetch();
    if (!$x) flash('warn', d('الطلب غير متاح.', 'Request not available.'));
    elseif ($act === 'issue') {
        $grade = mb_substr(trim((string)$_POST['grade']), 0, 50) ?: '—';
        do { $code = 'AR-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('y'); $ex = $pdo->prepare("SELECT 1 FROM certificate_exams WHERE certificate_code=? UNION SELECT 1 FROM legacy_certificates WHERE certificate_code=?"); $ex->execute([$code, $code]); } while ($ex->fetch());
        $pdo->prepare("UPDATE certificate_exams SET status='passed', certificate_code=?, issue_date=CURDATE(), grade=? WHERE id=?")->execute([$code, $grade, $eid]);
        notify_user((int)$x['trainee_id'], d('🎓 شهادتك جاهزة', '🎓 Your certificate is ready'), $x['title_ar'] . ' — ' . $code, 'my_certificates.php');
        wa_push(d('أرسل التهنئة للمتدرب', 'Congratulate trainee'), wa_link($x['whatsapp_number'], "ألف مبروك *{$x['trainee_name']}*! 🎓🎉\n\nلقد اجتزت دورة *{$x['title_ar']}* بتقدير (*$grade*).\nشهادتك جاهزة للتحميل والطباعة من لوحتك في منصة أراميو.\n🔑 رقم الشهادة: *$code*\n\nفخورون بك! 🏆"));
        audit('cert_issue', $code); flash('ok', d("تم اعتماد المتدرب. رقم الشهادة: $code", "Certificate issued: $code"));
    } elseif ($act === 'reject') { $pdo->prepare("UPDATE certificate_exams SET status='rejected' WHERE id=?")->execute([$eid]); notify_user((int)$x['trainee_id'], d('لم يُعتمد طلب الشهادة', 'Certificate request not approved'), $x['title_ar'], 'my_certificates.php'); flash('ok', d('تم الرفض. يمكن للمتدرب إعادة المحاولة.', 'Rejected. The trainee may try again.')); }
    redirect('certificates.php');
}
$ex = $pdo->prepare("SELECT e.*, u.full_name AS trainee_name, c.title_ar FROM certificate_exams e JOIN users u ON u.id=e.trainee_id JOIN courses c ON c.id=e.course_id WHERE e.trainer_id=? ORDER BY FIELD(e.status,'pending') DESC, e.created_at DESC"); $ex->execute([$trainer_id]); $exams = $ex->fetchAll();
$dashTitle = d('الشهادات والامتحانات', 'Certificates & exams');
require '../includes/partials/dash_header.php';
echo wa_buttons();
?>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(d('المتدرب', 'Trainee')) ?></th><th><?= e(d('الدورة', 'Course')) ?></th><th><?= e(d('موعد الامتحان', 'Exam date')) ?></th><th><?= e(d('ملاحظات', 'Notes')) ?></th><th><?= e(t('status')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($exams as $x): ?><tr class="<?= $x['status'] === 'pending' ? 'bg-warning bg-opacity-10' : '' ?>"><td class="fw-bold"><?= e($x['trainee_name']) ?></td><td><?= e($x['title_ar']) ?></td><td><?= e($x['exam_date']) ?></td><td class="small"><?= e($x['notes']) ?></td>
  <td><?= status_badge($x['status']) ?><?= $x['certificate_code'] ? '<div class="small"><code>' . e($x['certificate_code']) . '</code> · ' . e($x['grade']) . '</div>' : '' ?></td>
  <td class="text-end text-nowrap"><?php if ($x['status'] === 'pending'): ?><form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="act" value="issue"><input type="hidden" name="exam_id" value="<?= (int)$x['id'] ?>">
    <select name="grade" class="form-select form-select-sm" style="width:130px"><?php foreach ([d('ممتاز', 'Excellent'), d('جيد جدًا', 'Very good'), d('جيد', 'Good'), d('مقبول', 'Pass')] as $g): ?><option><?= e($g) ?></option><?php endforeach; ?></select><button class="btn-soft btn-soft-ok"><i class="fa-solid fa-award"></i> <?= e(d('اعتماد', 'Issue')) ?></button></form>
    <?= action_btn('', ['act' => 'reject', 'exam_id' => $x['id']], 'btn-soft-bad', d('رفض الطلب؟', 'Reject?'), 'fa-xmark') ?>
  <?php elseif ($x['status'] === 'passed'): ?><a class="btn-soft btn-soft-info" target="_blank" href="../certificate.php?code=<?= urlencode($x['certificate_code']) ?>"><i class="fa-solid fa-print"></i></a><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$exams): ?><tr><td colspan="6" class="empty"><i class="fa-solid fa-award"></i><?= e(d('لا طلبات', 'No requests')) ?></td></tr><?php endif; ?></tbody></table></div></div>
<?php require '../includes/partials/dash_footer.php';
