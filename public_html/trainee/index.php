<?php
require_once 'trainee_init.php';
$q1 = function ($sql, $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); };
$ap = $pdo->prepare("SELECT c.id, c.title_ar, c.title_en, c.training_type, c.thumbnail FROM course_orders o JOIN courses c ON c.id=o.course_id WHERE o.user_id=? AND o.status='approved' ORDER BY o.created_at DESC"); $ap->execute([$user_id]); $mine = $ap->fetchAll();
$prog = []; $sum = 0; $onlineN = 0;
foreach ($mine as $c) { [$tl, $dn, $pc] = course_progress($user_id, (int)$c['id']); $prog[$c['id']] = [$tl, $dn, $pc]; if ($c['training_type'] === 'online' && $tl) { $sum += $pc; $onlineN++; } }
$avg = $onlineN ? (int)round($sum / $onlineN) : 0;
$certs = (int)$q1("SELECT COUNT(*) FROM certificate_exams WHERE trainee_id=? AND status='passed'", [$user_id]);
$quizAvg = $q1("SELECT ROUND(AVG(score)) FROM quiz_attempts WHERE trainee_id=?", [$user_id]);
$waiting = $pdo->prepare("SELECT o.id, c.title_ar, c.title_en, TIMESTAMPDIFF(SECOND, o.created_at, NOW()) secs FROM course_orders o JOIN courses c ON c.id=o.course_id WHERE o.user_id=? AND o.status='reserved'"); $waiting->execute([$user_id]); $waiting = $waiting->fetchAll();
$next = $pdo->prepare("SELECT a.available_date, a.start_time, a.session_mode, u.full_name FROM consultation_bookings b JOIN trainer_availabilities a ON a.id=b.availability_id JOIN users u ON u.id=a.trainer_id WHERE b.trainee_id=? AND b.status='approved' AND a.available_date >= CURDATE() ORDER BY a.available_date LIMIT 3"); $next->execute([$user_id]); $next = $next->fetchAll();
// اقتراحات: دورات من نفس تصنيفات اشتراكاتي ولم أشترك بها، ثم الأحدث
$owned = array_column($mine, 'id') ?: [0]; $in = implode(',', array_map('intval', $owned));
$rec = $pdo->query("SELECT c.*, u.full_name AS trainer_name, cat.name_ar AS cat_ar, cat.name_en AS cat_en, (SELECT COALESCE(AVG(rating),0) FROM course_reviews WHERE course_id=c.id) avg_rating, (SELECT COUNT(*) FROM course_reviews WHERE course_id=c.id) reviews_count
    FROM courses c JOIN users u ON u.id=c.trainer_id JOIN categories cat ON cat.id=c.category_id WHERE c.status='published' AND c.id NOT IN ($in)
    ORDER BY (c.category_id IN (SELECT category_id FROM courses WHERE id IN ($in))) DESC, c.created_at DESC LIMIT 3")->fetchAll();
$dashTitle = d('أهلاً ', 'Welcome ') . $me['full_name'];
require '../includes/partials/dash_header.php';
?>
<?php foreach ($waiting as $w): $left = max(0, 3600 - (int)$w['secs']); ?><div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2"><span><i class="fa-solid fa-clock"></i> <?= e(d('أكمل دفع دورة', 'Complete payment for')) ?> <b><?= e($w['title_ar']) ?></b> — <b class="cd" data-left="<?= $left ?>"></b></span><a class="btn-aramio py-1" href="my_courses.php"><?= e(d('ادفع الآن', 'Pay now')) ?></a></div><?php endforeach; ?>
<div class="row g-3 mb-4"><?php foreach ([
  ['bg-g1', 'fa-book-open-reader', d('دوراتي', 'My courses'), count($mine)], ['bg-g2', 'fa-gauge-high', d('متوسط التقدّم', 'Avg progress'), $avg . '%'],
  ['bg-g3', 'fa-award', d('شهاداتي', 'Certificates'), $certs], ['bg-g5', 'fa-list-check', d('متوسط الاختبارات', 'Quiz average'), $quizAvg !== null && $quizAvg !== false ? $quizAvg . '%' : '—'],
] as [$bg, $ic, $lb, $v]): ?><div class="col-sm-6 col-xl-3"><div class="dcard kpi"><div class="ic <?= $bg ?>"><i class="fa-solid <?= $ic ?>"></i></div><div><div class="l"><?= e($lb) ?></div><div class="v"><?= e((string)$v) ?></div></div></div></div><?php endforeach; ?></div>

<div class="row g-3 mb-4"><div class="col-lg-8"><div class="dcard h-100"><div class="dcard-h"><?= e(d('تابع التعلّم', 'Continue learning')) ?><a class="btn-soft btn-soft-info" href="my_courses.php"><?= e(t('view_all')) ?></a></div><div class="dcard-b">
  <?php foreach (array_slice($mine, 0, 4) as $c): [$tl, $dn, $pc] = $prog[$c['id']]; ?><div class="d-flex align-items-center gap-3 mb-3"><div class="flex-grow-1"><div class="d-flex justify-content-between"><b><?= e($lang === 'ar' ? $c['title_ar'] : ($c['title_en'] ?: $c['title_ar'])) ?></b><span class="small text-muted"><?= $c['training_type'] === 'online' ? "$dn/$tl" : e(type_label($c['training_type'])) ?></span></div><div class="progress mt-1"><div class="progress-bar" style="width:<?= $pc ?>%"></div></div></div><a class="btn-soft btn-soft-ok" href="my_courses.php?course_id=<?= (int)$c['id'] ?>"><i class="fa-solid fa-play"></i></a></div><?php endforeach; ?>
  <?php if (!$mine): ?><div class="empty"><i class="fa-solid fa-book-open-reader"></i><a class="btn-aramio" href="store.php"><?= e(d('ابدأ أول دورة', 'Start your first course')) ?></a></div><?php endif; ?></div></div></div>
  <div class="col-lg-4"><div class="dcard h-100"><div class="dcard-h"><?= e(d('جلساتي القادمة', 'Upcoming sessions')) ?></div><div class="dcard-b"><?php foreach ($next as $n): ?><div class="border-bottom pb-2 mb-2"><b><?= e($n['full_name']) ?></b><div class="small text-primary"><i class="fa-regular fa-calendar"></i> <?= e($n['available_date']) ?> <span dir="ltr"><?= e(substr($n['start_time'], 0, 5)) ?></span></div></div><?php endforeach; ?>
    <?php if (!$next): ?><div class="empty py-3"><i class="fa-solid fa-calendar"></i><a href="consultations.php"><?= e(d('احجز استشارة', 'Book a consultation')) ?></a></div><?php endif; ?></div></div></div></div>

<div class="dcard"><div class="dcard-h"><span><i class="fa-solid fa-wand-magic-sparkles sparkle"></i> <?= e(d('مقترحة لك', 'Recommended for you')) ?></span><a class="ai-btn text-decoration-none" href="ai_tutor.php"><?= e(d('اسأل المعلّم الذكي', 'Ask the AI tutor')) ?></a></div><div class="dcard-b"><div class="row g-3"><?php foreach ($rec as $c): ?><div class="col-md-4"><a class="text-reset" href="../course.php?id=<?= (int)$c['id'] ?>"><div class="ai-box h-100"><span class="badge-cat"><?= e($lang === 'ar' ? $c['cat_ar'] : ($c['cat_en'] ?: $c['cat_ar'])) ?></span><div class="fw-bold my-2"><?= e(tr_field($c, 'title', 'course')) ?></div><div class="small text-muted"><?= e($c['trainer_name']) ?> · <?= (float)$c['price_usd'] > 0 ? e(formatPrice($c['price_usd'], $c['price_dzd'])) : e(t('free')) ?></div></div></a></div><?php endforeach; ?></div></div></div>
<?php
$footExtra = '<script>document.querySelectorAll(".cd").forEach(function(el){var s=parseInt(el.dataset.left,10);(function t(){if(s<=0){location.reload();return}el.textContent=String(Math.floor(s/60)).padStart(2,"0")+":"+String(s%60).padStart(2,"0");s--;setTimeout(t,1000)})()});</script>';
require '../includes/partials/dash_footer.php';
