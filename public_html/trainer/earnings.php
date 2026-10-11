<?php
require_once 'trainer_init.php';
$sum = $pdo->prepare("SELECT currency, SUM(amount) total, SUM(CASE WHEN is_paid=0 THEN amount ELSE 0 END) pending FROM trainer_earnings WHERE trainer_id=? GROUP BY currency"); $sum->execute([$trainer_id]); $S = $sum->fetchAll(PDO::FETCH_UNIQUE);
$rows = $pdo->prepare("SELECT e.*, COALESCE(c.title_ar, ?) AS item, u.full_name AS trainee FROM trainer_earnings e
    LEFT JOIN course_orders o ON o.id=e.order_id LEFT JOIN courses c ON c.id=o.course_id
    LEFT JOIN consultation_bookings b ON b.id=e.booking_id LEFT JOIN users u ON u.id=COALESCE(o.user_id, b.trainee_id)
    WHERE e.trainer_id=? ORDER BY e.id DESC LIMIT 300");
$rows->execute([d('استشارة', 'Consultation'), $trainer_id]); $rows = $rows->fetchAll();
$dashTitle = d('أرباحي', 'My earnings');
require '../includes/partials/dash_header.php';
?>
<div class="row g-3 mb-4"><?php foreach (['usd' => ['bg-g1', 'money_usd'], 'dzd' => ['bg-g2', 'money_dzd']] as $cur => [$bg, $fn]): ?>
  <div class="col-md-6"><div class="dcard kpi"><div class="ic <?= $bg ?>"><i class="fa-solid fa-wallet"></i></div><div><div class="l"><?= strtoupper($cur) ?> — <?= e(d('الإجمالي', 'Total')) ?></div><div class="v"><?= e($fn($S[$cur]['total'] ?? 0)) ?></div><div class="sub"><?= e(d('معلّق:', 'Pending:')) ?> <b class="text-warning"><?= e($fn($S[$cur]['pending'] ?? 0)) ?></b></div></div></div></div><?php endforeach; ?></div>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(t('date')) ?></th><th><?= e(d('البند', 'Item')) ?></th><th><?= e(d('المتدرب', 'Trainee')) ?></th><th><?= e(d('عمولتي', 'My share')) ?></th><th><?= e(t('status')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="small"><?= e(substr($r['created_at'], 0, 10)) ?></td><td><?= e($r['item']) ?></td><td><?= e($r['trainee'] ?: '—') ?></td><td class="fw-bold text-success"><?= $r['currency'] === 'usd' ? e(money_usd($r['amount'])) : e(money_dzd($r['amount'])) ?></td><td><?= $r['is_paid'] ? '<span class="st st-ok">' . e(d('تم التحويل', 'Paid')) . '</span>' : '<span class="st st-warn">' . e(d('معلّق', 'Pending')) . '</span>' ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty"><i class="fa-solid fa-wallet"></i><?= e(d('لا أرباح بعد', 'No earnings yet')) ?></td></tr><?php endif; ?></tbody></table></div></div>
<?php require '../includes/partials/dash_footer.php';
