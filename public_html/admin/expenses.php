<?php
require_once 'admin_init.php';
admin_only();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $title = trim((string)$_POST['title']); $amt = (float)$_POST['amount']; $cur = in_array($_POST['currency'] ?? '', ['usd', 'dzd'], true) ? $_POST['currency'] : 'usd';
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['expense_date'] ?? '') ? $_POST['expense_date'] : date('Y-m-d');
        if ($title === '' || $amt <= 0) flash('err', t('err_required'));
        else { $pdo->prepare("INSERT INTO expenses (title, amount, currency, expense_date, notes) VALUES (?,?,?,?,?)")->execute([mb_substr($title, 0, 255), $amt, $cur, $date, mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 1000)]); audit('expense_add', "$title $amt $cur"); flash('ok', d('تم تسجيل المنصرف.', 'Expense recorded.')); }
    } elseif ($act === 'pay') {
        $tid = (int)$_POST['trainer_id']; $cur = in_array($_POST['curr'] ?? '', ['usd', 'dzd'], true) ? $_POST['curr'] : 'usd';
        $sum = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id=? AND currency=? AND is_paid=0"); $sum->execute([$tid, $cur]); $sum = (float)$sum->fetchColumn();
        $pdo->prepare("UPDATE trainer_earnings SET is_paid=1 WHERE trainer_id=? AND currency=? AND is_paid=0")->execute([$tid, $cur]);
        notify_user($tid, d('تم تحويل مستحقاتك', 'Your earnings were paid'), $cur === 'usd' ? money_usd($sum) : money_dzd($sum), 'earnings.php');
        $w = $pdo->prepare("SELECT full_name, whatsapp_number FROM users WHERE id=?"); $w->execute([$tid]); $w = $w->fetch();
        if ($w) { wa_queue(d('إبلاغ المدرب بالتحويل', 'Tell trainer'), wa_link($w['whatsapp_number'], "مرحباً كوتش *{$w['full_name']}* 💸\n\nتم تحويل مستحقاتك (" . ($cur === 'usd' ? money_usd($sum) : money_dzd($sum)) . ").\nشكراً لجهودك مع أراميو 🌟")); }
        audit('trainer_paid', "trainer $tid $cur $sum"); flash('ok', d('تمت تصفية حساب المدرب.', 'Trainer settled.'));
    } elseif ($act === 'delete') { $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([(int)$_POST['id']]); flash('ok', d('تم الحذف.', 'Deleted.')); }
    redirect('expenses.php');
}
$expenses = $pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC LIMIT 200")->fetchAll();
$debts = $pdo->query("SELECT u.id, u.full_name, SUM(CASE WHEN e.currency='usd' THEN e.amount ELSE 0 END) owed_usd, SUM(CASE WHEN e.currency='dzd' THEN e.amount ELSE 0 END) owed_dzd
    FROM trainer_earnings e JOIN users u ON e.trainer_id=u.id WHERE e.is_paid=0 GROUP BY u.id, u.full_name HAVING owed_usd>0 OR owed_dzd>0")->fetchAll();
$dashTitle = d('المصاريف وتسديد المدربين', 'Expenses & trainer payouts');
require '../includes/partials/dash_header.php';
echo wa_render();
?>
<div class="row g-3 mb-4">
  <div class="col-lg-7"><div class="dcard h-100"><div class="dcard-h"><?= e(d('مستحقات المدربين', 'Trainer payables')) ?></div><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(d('المدرب', 'Trainer')) ?></th><th>USD</th><th>DZD</th></tr></thead><tbody>
  <?php foreach ($debts as $x): ?><tr><td class="fw-bold"><?= e($x['full_name']) ?></td>
    <td><?php if ($x['owed_usd'] > 0) { echo '<b class="text-danger">' . e(money_usd($x['owed_usd'])) . '</b> ' . action_btn(d('سداد', 'Pay'), ['act' => 'pay', 'trainer_id' => $x['id'], 'curr' => 'usd'], 'btn-soft-bad', d('تأكيد التحويل؟', 'Confirm payout?')); } else echo '—'; ?></td>
    <td><?php if ($x['owed_dzd'] > 0) { echo '<b class="text-danger">' . e(money_dzd($x['owed_dzd'])) . '</b> ' . action_btn(d('سداد', 'Pay'), ['act' => 'pay', 'trainer_id' => $x['id'], 'curr' => 'dzd'], 'btn-soft-bad', d('تأكيد التحويل؟', 'Confirm payout?')); } else echo '—'; ?></td></tr><?php endforeach; ?>
  <?php if (!$debts): ?><tr><td colspan="3" class="empty text-success"><i class="fa-solid fa-check-double"></i><?= e(d('لا توجد مستحقات معلقة', 'Nothing owed')) ?></td></tr><?php endif; ?></tbody></table></div></div></div>
  <div class="col-lg-5"><div class="dcard h-100"><div class="dcard-h"><?= e(d('تسجيل منصرف', 'New expense')) ?></div><form method="post" class="dcard-b d-grid gap-3"><?= csrf_field() ?><input type="hidden" name="act" value="add">
    <input name="title" class="form-control" required placeholder="<?= e(d('بيان الصرف (إعلانات، سيرفر…)', 'Description (ads, server…)')) ?>">
    <div class="row g-2"><div class="col-7"><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required placeholder="<?= e(d('المبلغ', 'Amount')) ?>"></div><div class="col-5"><select name="currency" class="form-select"><option value="usd">USD</option><option value="dzd">DZD</option></select></div></div>
    <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required><button class="btn-aramio"><?= e(t('save')) ?></button></form></div></div>
</div>
<div class="dcard"><div class="dcard-h"><?= e(d('سجل المصاريف', 'Expense log')) ?></div><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(t('date')) ?></th><th><?= e(d('البيان', 'Description')) ?></th><th><?= e(d('المبلغ', 'Amount')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($expenses as $x): ?><tr><td><?= e($x['expense_date']) ?></td><td><?= e($x['title']) ?></td><td class="fw-bold text-danger"><?= $x['currency'] === 'usd' ? e(money_usd($x['amount'])) : e(money_dzd($x['amount'])) ?></td><td class="text-end"><?= action_btn('', ['act' => 'delete', 'id' => $x['id']], 'btn-soft-bad', d('حذف المصروف؟', 'Delete?'), 'fa-trash') ?></td></tr><?php endforeach; ?>
<?php if (!$expenses): ?><tr><td colspan="4" class="empty"><?= e(d('لا مصاريف', 'No expenses')) ?></td></tr><?php endif; ?></tbody></table></div></div>
<?php require '../includes/partials/dash_footer.php';
