<?php
require_once 'trainee_init.php';
$rows = $pdo->prepare("(SELECT o.id, o.amount_usd, o.amount_dzd, o.payment_method, o.status, o.created_at, c.title_ar AS item, 'course' AS kind FROM course_orders o JOIN courses c ON c.id=o.course_id WHERE o.user_id=?)
    UNION ALL (SELECT b.id, b.amount_usd, b.amount_dzd, b.payment_method, b.status, b.created_at, CONCAT(?, u.full_name), 'consultation' FROM consultation_bookings b JOIN trainer_availabilities a ON a.id=b.availability_id JOIN users u ON u.id=a.trainer_id WHERE b.trainee_id=?) ORDER BY created_at DESC");
$rows->execute([$user_id, d('استشارة: ', 'Consultation: '), $user_id]); $rows = $rows->fetchAll();
$usd = $dzd = 0; foreach ($rows as $r) if ($r['status'] === 'approved') { $usd += (float)$r['amount_usd']; $dzd += (float)$r['amount_dzd']; }
$dashTitle = d('مدفوعاتي', 'My payments');
require '../includes/partials/dash_header.php';
?>
<div class="row g-3 mb-4"><div class="col-md-6"><div class="dcard kpi"><div class="ic bg-g1"><i class="fa-solid fa-dollar-sign"></i></div><div><div class="l"><?= e(d('إجمالي المدفوع (USD)', 'Total paid (USD)')) ?></div><div class="v"><?= e(money_usd($usd)) ?></div></div></div></div><div class="col-md-6"><div class="dcard kpi"><div class="ic bg-g2"><i class="fa-solid fa-money-bill"></i></div><div><div class="l"><?= e(d('إجمالي المدفوع (DZD)', 'Total paid (DZD)')) ?></div><div class="v"><?= e(money_dzd($dzd)) ?></div></div></div></div></div>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(t('date')) ?></th><th><?= e(d('البند', 'Item')) ?></th><th><?= e(d('المبلغ', 'Amount')) ?></th><th><?= e(d('الطريقة', 'Method')) ?></th><th><?= e(t('status')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="small"><?= e(substr($r['created_at'], 0, 10)) ?></td><td><?= e($r['item']) ?> <span class="st st-mute"><?= e($r['kind'] === 'course' ? d('دورة', 'Course') : d('استشارة', 'Consultation')) ?></span></td>
  <td class="fw-bold"><?= (float)$r['amount_usd'] > 0 ? e(money_usd($r['amount_usd'])) : ((float)$r['amount_dzd'] > 0 ? e(money_dzd($r['amount_dzd'])) : '—') ?></td><td><?= e(['bank' => d('بنك', 'Bank'), 'paypal' => 'PayPal', 'agent' => d('وكيل', 'Agent')][$r['payment_method']] ?? '—') ?></td><td><?= status_badge($r['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty"><i class="fa-solid fa-receipt"></i><?= e(d('لا مدفوعات', 'No payments')) ?></td></tr><?php endif; ?></tbody></table></div></div>
<?php require '../includes/partials/dash_footer.php';
