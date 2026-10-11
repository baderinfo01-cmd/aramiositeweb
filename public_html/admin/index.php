<?php
require_once 'admin_init.php';
$rate = exchange_rate();
$q1 = fn($sql, $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();

$rev_usd = (float)$q1("SELECT COALESCE(SUM(amount_usd),0) FROM course_orders WHERE status='approved'") + (float)$q1("SELECT COALESCE(SUM(amount_usd),0) FROM consultation_bookings WHERE status='approved'");
$rev_dzd = (float)$q1("SELECT COALESCE(SUM(amount_dzd),0) FROM course_orders WHERE status='approved'") + (float)$q1("SELECT COALESCE(SUM(amount_dzd),0) FROM consultation_bookings WHERE status='approved'");
$tr_usd = (float)$q1("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE currency='usd'");
$tr_dzd = (float)$q1("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE currency='dzd'");
$ex_usd = (float)$q1("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE currency='usd'");
$ex_dzd = (float)$q1("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE currency='dzd'");
$income = $rev_usd * $rate + $rev_dzd;
$net = $income - ($tr_usd * $rate + $tr_dzd) - ($ex_usd * $rate + $ex_dzd);

$students = (int)$q1("SELECT COUNT(*) FROM users WHERE role='trainee'");
$trainers = (int)$q1("SELECT COUNT(*) FROM users WHERE role='trainer'");
$pendCourses = (int)$q1("SELECT COUNT(*) FROM courses WHERE status='pending'");
$pendOrders = (int)$q1("SELECT COUNT(*) FROM course_orders WHERE status='pending'");
$newMsgs = (int)$q1("SELECT COUNT(*) FROM contact_messages WHERE is_read=0");
$newUsers7 = (int)$q1("SELECT COUNT(*) FROM users WHERE created_at >= (NOW() - INTERVAL 7 DAY) AND role='trainee'");

// سلاسل الرسوم (آخر 6 أشهر)
$months = []; for ($i = 5; $i >= 0; $i--) $months[] = date('Y-m', strtotime("-$i month"));
$revRows = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COALESCE(SUM(amount_usd),0) usd, COALESCE(SUM(amount_dzd),0) dzd, COUNT(*) n FROM course_orders WHERE status='approved' AND created_at >= (NOW() - INTERVAL 6 MONTH) GROUP BY m")->fetchAll(PDO::FETCH_UNIQUE);
$revSeries = $ordSeries = [];
foreach ($months as $m) { $r = $revRows[$m] ?? ['usd' => 0, 'dzd' => 0, 'n' => 0]; $revSeries[] = round($r['usd'] + $r['dzd'] / $rate, 2); $ordSeries[] = (int)$r['n']; }
$usersRows = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) n FROM users WHERE role='trainee' AND created_at >= (NOW() - INTERVAL 6 MONTH) GROUP BY m")->fetchAll(PDO::FETCH_KEY_PAIR);
$userSeries = array_map(fn($m) => (int)($usersRows[$m] ?? 0), $months);
$statusRows = $pdo->query("SELECT status, COUNT(*) n FROM course_orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$countryRows = $pdo->query("SELECT COALESCE(NULLIF(country,''),'—') c, COUNT(*) n FROM users WHERE role='trainee' GROUP BY c ORDER BY n DESC LIMIT 6")->fetchAll();
$topCourses = $pdo->query("SELECT c.id, c.title_ar, c.title_en, COUNT(*) n FROM course_orders o JOIN courses c ON c.id=o.course_id WHERE o.status='approved' GROUP BY c.id ORDER BY n DESC LIMIT 5")->fetchAll();
$recent = $pdo->query("SELECT o.id, o.status, o.amount_usd, o.amount_dzd, o.created_at, u.full_name, c.title_ar, c.title_en FROM course_orders o JOIN users u ON u.id=o.user_id JOIN courses c ON c.id=o.course_id ORDER BY o.id DESC LIMIT 6")->fetchAll();
$aiCalls = (int)$q1("SELECT COUNT(*) FROM ai_logs WHERE created_at >= (NOW() - INTERVAL 30 DAY)");

$dashTitle = d('نظرة عامة على الأكاديمية', 'Academy overview'); $needChart = true;
require '../includes/partials/dash_header.php';
?>
<div class="row g-3 mb-4">
  <?php foreach ([
    ['bg-g1','fa-arrow-trend-up', d('إجمالي المداخيل','Total revenue'), money_usd($rev_usd), money_dzd($rev_dzd)],
    ['bg-g3','fa-users-rectangle', d('مستحقات المدربين','Trainer share'), money_usd($tr_usd), money_dzd($tr_dzd)],
    ['bg-g4','fa-money-bill-transfer', d('المصاريف','Expenses'), money_usd($ex_usd), money_dzd($ex_dzd)],
    ['bg-g5','fa-crown', d('صافي الأرباح','Net profit'), money_dzd($net), '≈ ' . money_usd($rate ? $net / $rate : 0)],
  ] as [$bg,$ic,$lb,$v,$sub]): ?>
  <div class="col-sm-6 col-xl-3"><div class="dcard kpi"><div class="ic <?= $bg ?>"><i class="fa-solid <?= $ic ?>"></i></div><div><div class="l"><?= e($lb) ?></div><div class="v"><?= e($v) ?></div><div class="sub"><?= e($sub) ?></div></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3 mb-4">
  <?php foreach ([
    ['bg-g2','fa-user-graduate', d('متدرب مسجل','Trainees'), $students, '+' . $newUsers7 . ' ' . d('هذا الأسبوع','this week'), 'users.php'],
    ['bg-g1','fa-chalkboard-user', d('مدرب معتمد','Trainers'), $trainers, '', 'users.php'],
    ['bg-g3','fa-photo-film', d('دورات بانتظار الموافقة','Courses to review'), $pendCourses, '', 'courses.php'],
    ['bg-g4','fa-file-invoice-dollar', d('طلبات بانتظار المراجعة','Orders to review'), $pendOrders, '', 'financial.php'],
  ] as [$bg,$ic,$lb,$v,$sub,$href]): ?>
  <div class="col-sm-6 col-xl-3"><a href="<?= $href ?>" class="text-reset"><div class="dcard kpi"><div class="ic <?= $bg ?>"><i class="fa-solid <?= $ic ?>"></i></div><div><div class="l"><?= e($lb) ?></div><div class="v"><?= number_format($v) ?></div><div class="sub"><?= e($sub) ?></div></div></div></a></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8"><div class="dcard h-100"><div class="dcard-h"><?= e(d('الإيرادات وعدد الطلبات (6 أشهر)', 'Revenue & orders (6 months)')) ?></div><div class="dcard-b"><canvas id="chRev" height="110"></canvas></div></div></div>
  <div class="col-lg-4"><div class="dcard h-100"><div class="dcard-h"><?= e(d('حالة الطلبات', 'Order status')) ?></div><div class="dcard-b"><canvas id="chStatus" height="220"></canvas></div></div></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-lg-4"><div class="dcard h-100"><div class="dcard-h"><?= e(d('نمو المتدربين', 'Trainee growth')) ?></div><div class="dcard-b"><canvas id="chUsers" height="200"></canvas></div></div></div>
  <div class="col-lg-4"><div class="dcard h-100"><div class="dcard-h"><?= e(d('المتدربون حسب البلد', 'Trainees by country')) ?></div><div class="dcard-b"><canvas id="chCountry" height="200"></canvas></div></div></div>
  <div class="col-lg-4"><div class="dcard h-100"><div class="dcard-h"><?= e(d('الدورات الأكثر طلبًا', 'Top courses')) ?></div><div class="dcard-b">
    <?php if (!$topCourses): ?><div class="empty"><i class="fa-solid fa-chart-column"></i><?= e(d('لا بيانات بعد', 'No data yet')) ?></div><?php endif; ?>
    <?php $max = max(1, (int)($topCourses[0]['n'] ?? 1)); foreach ($topCourses as $tc): ?>
      <div class="mb-3"><div class="d-flex justify-content-between small fw-bold"><span><?= e($lang === 'ar' ? $tc['title_ar'] : ($tc['title_en'] ?: $tc['title_ar'])) ?></span><span><?= (int)$tc['n'] ?></span></div><div class="progress"><div class="progress-bar" style="width:<?= round($tc['n'] / $max * 100) ?>%"></div></div></div>
    <?php endforeach; ?></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7"><div class="dcard h-100"><div class="dcard-h"><?= e(d('آخر الطلبات', 'Recent orders')) ?><a class="btn-soft btn-soft-info" href="financial.php"><?= e(t('view_all')) ?></a></div><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(d('المتدرب', 'Trainee')) ?></th><th><?= e(d('الدورة', 'Course')) ?></th><th><?= e(d('المبلغ', 'Amount')) ?></th><th><?= e(t('status')) ?></th></tr></thead><tbody>
    <?php foreach ($recent as $r): ?><tr><td class="fw-bold"><?= e($r['full_name']) ?></td><td><?= e($lang === 'ar' ? $r['title_ar'] : ($r['title_en'] ?: $r['title_ar'])) ?></td><td><?= $r['amount_usd'] > 0 ? e(money_usd($r['amount_usd'])) : e(money_dzd($r['amount_dzd'])) ?></td><td><?= status_badge($r['status']) ?></td></tr><?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="4" class="empty"><?= e(d('لا طلبات', 'No orders')) ?></td></tr><?php endif; ?></tbody></table></div></div></div>
  <div class="col-lg-5"><div class="dcard h-100"><div class="dcard-h"><span><i class="fa-solid fa-wand-magic-sparkles sparkle"></i> <?= e(d('تحليلات الذكاء الاصطناعي', 'AI insights')) ?></span>
      <button class="ai-btn" id="insBtn"><?= e(d('حلّل أداء الأكاديمية', 'Analyse performance')) ?></button></div>
    <div class="dcard-b"><div id="insOut" class="small" style="white-space:pre-wrap"><?= e(ai_available() ? d('اضغط للحصول على قراءة ذكية لبياناتك مع خطوات مقترحة.', 'Click to get a smart read of your data with suggested actions.') : d('فعّل مزوّد الذكاء الاصطناعي من الإعدادات > الذكاء الاصطناعي.', 'Enable an AI provider in Settings > AI.')) ?></div>
      <div class="mt-3 small text-muted"><i class="fa-solid fa-bolt"></i> <?= e(d('استدعاءات AI آخر 30 يوم:', 'AI calls last 30 days:')) ?> <b><?= $aiCalls ?></b> · <i class="fa-solid fa-envelope"></i> <?= $newMsgs ?> <?= e(d('رسائل جديدة', 'new messages')) ?></div></div></div></div>
</div>
<?php
$footExtra = '<script>(function(){var L=' . json_encode($months) . ',txt=getComputedStyle(document.documentElement).getPropertyValue("--muted")||"#64708a";
Chart.defaults.font.family="Cairo,Inter,sans-serif";Chart.defaults.color=txt.trim()||"#64708a";
new Chart(chRev,{data:{labels:L,datasets:[{type:"bar",label:' . json_encode(d('الإيراد $', 'Revenue $')) . ',data:' . json_encode($revSeries) . ',backgroundColor:"rgba(28,200,138,.75)",borderRadius:8,yAxisID:"y"},{type:"line",label:' . json_encode(d('الطلبات', 'Orders')) . ',data:' . json_encode($ordSeries) . ',borderColor:"#4e73df",backgroundColor:"#4e73df",tension:.35,yAxisID:"y1"}]},options:{responsive:true,scales:{y:{beginAtZero:true},y1:{beginAtZero:true,position:"right",grid:{drawOnChartArea:false}}}}});
new Chart(chStatus,{type:"doughnut",data:{labels:' . json_encode(array_map(fn($k) => trim(strip_tags(status_badge($k))), array_keys($statusRows)), JSON_UNESCAPED_UNICODE) . ',datasets:[{data:' . json_encode(array_map('intval', array_values($statusRows))) . ',backgroundColor:["#1cc88a","#f6c23e","#ef4444","#4e73df","#94a3b8"]}]},options:{plugins:{legend:{position:"bottom"}}}});
new Chart(chUsers,{type:"line",data:{labels:L,datasets:[{label:' . json_encode(d('متدربون جدد', 'New trainees')) . ',data:' . json_encode($userSeries) . ',borderColor:"#7c5cff",backgroundColor:"rgba(124,92,255,.15)",fill:true,tension:.4}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
new Chart(chCountry,{type:"bar",data:{labels:' . json_encode(array_column($countryRows, 'c'), JSON_UNESCAPED_UNICODE) . ',datasets:[{data:' . json_encode(array_map('intval', array_column($countryRows, 'n'))) . ',backgroundColor:"#13a1a6",borderRadius:8}]},options:{indexAxis:"y",plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0}}}}});
document.getElementById("insBtn").addEventListener("click",function(){var o=document.getElementById("insOut");o.textContent=' . json_encode(t('loading')) . ';AI_ASK("../api/ai_tools.php",{action:"insights"},function(r){o.textContent=r.ok?r.text:(r.message||"error")},this)});})();</script>';
require '../includes/partials/dash_footer.php';
