<?php
require_once 'trainer_init.php';
$q1 = function ($sql, $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); };
$earn = ['usd' => (float)$q1("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id=? AND currency='usd'", [$trainer_id]), 'dzd' => (float)$q1("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id=? AND currency='dzd'", [$trainer_id])];
$pend = ['usd' => (float)$q1("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id=? AND currency='usd' AND is_paid=0", [$trainer_id]), 'dzd' => (float)$q1("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id=? AND currency='dzd' AND is_paid=0", [$trainer_id])];
$courses = (int)$q1("SELECT COUNT(*) FROM courses WHERE trainer_id=?", [$trainer_id]);
$pubCourses = (int)$q1("SELECT COUNT(*) FROM courses WHERE trainer_id=? AND status='published'", [$trainer_id]);
$students = (int)$q1("SELECT COUNT(DISTINCT o.user_id) FROM course_orders o JOIN courses c ON c.id=o.course_id WHERE c.trainer_id=? AND o.status='approved'", [$trainer_id]);
$pendExams = (int)$q1("SELECT COUNT(*) FROM certificate_exams WHERE trainer_id=? AND status='pending'", [$trainer_id]);
$pendReq = (int)$q1("SELECT COUNT(*) FROM custom_consultation_requests WHERE trainer_id=? AND status='pending_trainer'", [$trainer_id]);
$rate = exchange_rate();
$months = []; for ($i = 5; $i >= 0; $i--) $months[] = date('Y-m', strtotime("-$i month"));
$rows = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') m, SUM(CASE WHEN currency='usd' THEN amount ELSE amount/? END) v FROM trainer_earnings WHERE trainer_id=? AND created_at >= (NOW() - INTERVAL 6 MONTH) GROUP BY m");
$rows->execute([$rate, $trainer_id]); $rows = $rows->fetchAll(PDO::FETCH_KEY_PAIR);
$series = array_map(fn($m) => round((float)($rows[$m] ?? 0), 2), $months);
$top = $pdo->prepare("SELECT c.id, c.title_ar, c.title_en, COUNT(o.id) n FROM courses c LEFT JOIN course_orders o ON o.course_id=c.id AND o.status='approved' WHERE c.trainer_id=? GROUP BY c.id ORDER BY n DESC LIMIT 5"); $top->execute([$trainer_id]); $top = $top->fetchAll();
$next = $pdo->prepare("SELECT a.available_date, a.start_time, a.session_mode, u.full_name FROM consultation_bookings b JOIN trainer_availabilities a ON a.id=b.availability_id JOIN users u ON u.id=b.trainee_id WHERE a.trainer_id=? AND b.status='approved' AND a.available_date >= CURDATE() ORDER BY a.available_date, a.start_time LIMIT 5");
$next->execute([$trainer_id]); $next = $next->fetchAll();
$dashTitle = d('مرحبًا ', 'Welcome ') . $me['full_name']; $needChart = true;
require '../includes/partials/dash_header.php';
?>
<div class="row g-3 mb-4">
<?php foreach ([
  ['bg-g1', 'fa-wallet', d('إجمالي أرباحي', 'Total earnings'), money_usd($earn['usd']), money_dzd($earn['dzd'])],
  ['bg-g3', 'fa-hourglass-half', d('أرباح معلّقة (لم تُحوَّل)', 'Pending payout'), money_usd($pend['usd']), money_dzd($pend['dzd'])],
  ['bg-g2', 'fa-user-graduate', d('طلابي', 'My students'), number_format($students), ''],
  ['bg-g5', 'fa-photo-film', d('دوراتي (المنشورة)', 'Courses (published)'), $courses . ' (' . $pubCourses . ')', ''],
] as [$bg, $ic, $lb, $v, $sub]): ?><div class="col-sm-6 col-xl-3"><div class="dcard kpi"><div class="ic <?= $bg ?>"><i class="fa-solid <?= $ic ?>"></i></div><div><div class="l"><?= e($lb) ?></div><div class="v"><?= e($v) ?></div><div class="sub"><?= e($sub) ?></div></div></div></div><?php endforeach; ?></div>
<?php if ($pendExams || $pendReq): ?><div class="row g-3 mb-4"><?php if ($pendExams): ?><div class="col-md-6"><a href="certificates.php" class="text-reset"><div class="dcard kpi" style="border-color:#f6c23e"><div class="ic bg-g3"><i class="fa-solid fa-award"></i></div><div><div class="v"><?= $pendExams ?></div><div class="l"><?= e(d('طلبات شهادات تنتظر اعتمادك', 'Certificate requests awaiting you')) ?></div></div></div></a></div><?php endif; ?>
<?php if ($pendReq): ?><div class="col-md-6"><a href="schedule.php" class="text-reset"><div class="dcard kpi" style="border-color:#4e73df"><div class="ic bg-g2"><i class="fa-solid fa-handshake"></i></div><div><div class="v"><?= $pendReq ?></div><div class="l"><?= e(d('طلبات استشارة مخصصة', 'Custom consultation requests')) ?></div></div></div></a></div><?php endif; ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-7"><div class="dcard h-100"><div class="dcard-h"><?= e(d('أرباحي خلال 6 أشهر ($)', 'Earnings, last 6 months ($)')) ?></div><div class="dcard-b"><canvas id="chE" height="120"></canvas></div></div></div>
  <div class="col-lg-5"><div class="dcard h-100"><div class="dcard-h"><?= e(d('أكثر دوراتي مبيعًا', 'Best-selling courses')) ?></div><div class="dcard-b">
    <?php $mx = max(1, (int)($top[0]['n'] ?? 1)); foreach ($top as $t): ?><div class="mb-3"><div class="d-flex justify-content-between small fw-bold"><span><?= e($lang === 'ar' ? $t['title_ar'] : ($t['title_en'] ?: $t['title_ar'])) ?></span><span><?= (int)$t['n'] ?></span></div><div class="progress"><div class="progress-bar" style="width:<?= round($t['n'] / $mx * 100) ?>%"></div></div></div><?php endforeach; ?>
    <?php if (!$top): ?><div class="empty"><i class="fa-solid fa-chart-column"></i><a href="my_courses.php"><?= e(d('أنشئ دورتك الأولى', 'Create your first course')) ?></a></div><?php endif; ?></div></div></div>
  <div class="col-12"><div class="dcard"><div class="dcard-h"><?= e(d('جلساتي القادمة', 'Upcoming sessions')) ?><a class="btn-soft btn-soft-info" href="schedule.php"><?= e(t('view_all')) ?></a></div><div class="table-wrap"><table class="dtable"><tbody>
    <?php foreach ($next as $n): ?><tr><td><b><?= e($n['full_name']) ?></b></td><td><i class="fa-regular fa-calendar"></i> <?= e($n['available_date']) ?> <span dir="ltr"><?= e(substr($n['start_time'], 0, 5)) ?></span></td><td><span class="st st-info"><?= e($n['session_mode'] === 'online' ? d('أونلاين', 'Online') : d('حضوري', 'In person')) ?></span></td></tr><?php endforeach; ?>
    <?php if (!$next): ?><tr><td class="empty"><?= e(d('لا جلسات قادمة', 'No upcoming sessions')) ?></td></tr><?php endif; ?></tbody></table></div></div></div>
</div>
<?php
$footExtra = '<script>Chart.defaults.font.family="Cairo,Inter,sans-serif";new Chart(chE,{type:"bar",data:{labels:' . json_encode($months) . ',datasets:[{data:' . json_encode($series) . ',backgroundColor:"rgba(28,200,138,.8)",borderRadius:8}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});</script>';
require '../includes/partials/dash_footer.php';
