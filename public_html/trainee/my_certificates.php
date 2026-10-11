<?php
require_once 'trainee_init.php';
$st = $pdo->prepare("SELECT e.certificate_code, e.issue_date, e.grade, c.title_ar, c.title_en, t.full_name AS trainer FROM certificate_exams e JOIN courses c ON c.id=e.course_id JOIN users t ON t.id=e.trainer_id WHERE e.trainee_id=? AND e.status='passed' ORDER BY e.issue_date DESC"); $st->execute([$user_id]); $certs = $st->fetchAll();
$dashTitle = d('شهاداتي', 'My certificates');
require '../includes/partials/dash_header.php';
?>
<div class="row g-3"><?php foreach ($certs as $c): ?><div class="col-md-6 col-xl-4"><div class="dcard h-100" style="border-top:5px solid var(--gold)"><div class="dcard-b text-center">
  <i class="fa-solid fa-award fa-3x mb-2" style="color:var(--gold)"></i><h5 class="fw-bold"><?= e($lang === 'ar' ? $c['title_ar'] : ($c['title_en'] ?: $c['title_ar'])) ?></h5>
  <div class="small text-muted mb-2"><?= e(d('المدرب:', 'Trainer:')) ?> <?= e($c['trainer']) ?> · <?= e($c['issue_date']) ?></div><span class="st st-ok mb-2"><?= e($c['grade']) ?></span><div><code><?= e($c['certificate_code']) ?></code></div>
  <div class="d-flex gap-2 justify-content-center mt-3"><a class="btn-aramio py-1" target="_blank" href="../certificate.php?code=<?= urlencode($c['certificate_code']) ?>"><i class="fa-solid fa-print"></i> <?= e(d('عرض / طباعة', 'View / print')) ?></a><a class="btn-soft btn-soft-info py-2" target="_blank" href="../verify_certificate.php?code=<?= urlencode($c['certificate_code']) ?>"><i class="fa-solid fa-shield-halved"></i></a></div>
  <div class="mt-3"><?php $wa = 'https://wa.me/?text=' . rawurlencode(d('حصلت على شهادة من أراميو! تحقق منها: ', 'I earned a certificate from Aramio! Verify: ') . base_url('verify_certificate.php?code=' . $c['certificate_code'])); ?><a class="small" target="_blank" href="<?= e($wa) ?>"><i class="fa-brands fa-whatsapp"></i> <?= e(d('شارك إنجازك', 'Share')) ?></a></div></div></div></div><?php endforeach; ?>
<?php if (!$certs): ?><div class="col-12"><div class="dcard"><div class="empty"><i class="fa-solid fa-award"></i><?= e(d('لم تحصل على شهادات بعد. أكمل دوراتك واطلب شهادتك!', 'No certificates yet. Finish your courses and request one!')) ?></div></div></div><?php endif; ?></div>
<?php require '../includes/partials/dash_footer.php';
