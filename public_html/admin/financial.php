<?php
require_once 'admin_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $act = $_POST['act'] ?? ''; $oid = (int)($_POST['order_id'] ?? 0);
    $st = $pdo->prepare("SELECT o.*, u.full_name AS trainee_name, u.whatsapp_number AS trainee_wa, t.id AS trainer_id, t.full_name AS trainer_name, t.whatsapp_number AS trainer_wa, c.title_ar AS course_title
        FROM course_orders o JOIN users u ON o.user_id=u.id JOIN courses c ON o.course_id=c.id JOIN users t ON c.trainer_id=t.id WHERE o.id=?");
    $st->execute([$oid]); $o = $st->fetch();
    if (!$o) flash('err', d('الطلب غير موجود.', 'Order not found.'));
    elseif (!$o['status'] === 'pending') flash('warn', d('تمت معالجة هذا الطلب مسبقًا.', 'This order was already processed.'));
    elseif ($act === 'approve') {
        $type = $_POST['commission_type'] ?? 'percentage'; $val = (float)($_POST['commission_value'] ?? 0);
        if (!in_array($type, ['percentage', 'fixed'], true) || $val < 0) { flash('err', 'invalid'); redirect('financial.php'); }
        $cur = $o['amount_usd'] > 0 ? 'usd' : 'dzd'; $paid = (float)($cur === 'usd' ? $o['amount_usd'] : $o['amount_dzd']);
        $comm = $type === 'percentage' ? $paid * min($val, 100) / 100 : min($val, $paid);
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare("UPDATE course_orders SET status='approved' WHERE id=? AND status = 'pending'"); $up->execute([$oid]);
            if ($up->rowCount() === 1) $pdo->prepare("INSERT INTO trainer_earnings (trainer_id, order_id, amount, currency) VALUES (?,?,?,?)")->execute([$o['trainer_id'], $oid, $comm, $cur]);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); error_log($e->getMessage()); flash('err', 'DB error'); redirect('financial.php'); }
        $fmt = fn($v) => $cur === 'usd' ? '$' . (float)$v : (float)$v . ' د.ج';
        notify_user((int)$o['user_id'], d('تم تفعيل دورتك 🎉', 'Your course is active 🎉'), $o['course_title'], 'my_courses.php');
        notify_user((int)$o['trainer_id'], d('مبيعة جديدة', 'New sale'), $o['course_title'] . ' — ' . $fmt($comm), 'earnings.php');
        wa_queue(d('تأكيد التفعيل للمتدرب', 'Notify trainee'), wa_link($o['trainee_wa'], "مرحباً *{$o['trainee_name']}* 🎉\n\nتم تأكيد استلام الدفع (" . $fmt($paid) . ").\nدورتك 📖 *{$o['course_title']}* مفعلة الآن في لوحتك.\n\nنتمنى لك رحلة تعلم ممتعة مع أراميو! 🚀"));
        wa_queue(d('بشّر المدرب', 'Notify trainer'), wa_link($o['trainer_wa'], "مرحباً كوتش *{$o['trainer_name']}* 💸\n\nمبيعة جديدة لدورتك 📖 *{$o['course_title']}*.\nتم إيداع عمولتك *[" . $fmt($comm) . "]* في رصيدك المعلق.\n\nإلى مزيد من النجاح! 🌟"), 'btn-warning text-dark');
        audit('order_approve', "order $oid"); flash('ok', d('تم تفعيل الدورة وإيداع عمولة المدرب.', 'Course activated and trainer commission credited.'));
    } elseif ($act === 'reject') {
        $pdo->prepare("UPDATE course_orders SET status='rejected' WHERE id=?")->execute([$oid]);
        notify_user((int)$o['user_id'], d('تعذّر تأكيد الدفع', 'Payment could not be confirmed'), $o['course_title'], 'my_courses.php');
        wa_queue(d('تنبيه المتدرب بالرفض', 'Notify trainee'), wa_link($o['trainee_wa'], "عذراً *{$o['trainee_name']}* ⚠️\n\nواجهتنا مشكلة في تأكيد إيصال الدفع لطلبك رقم [#" . str_pad((string)$oid, 5, '0', STR_PAD_LEFT) . "].\nيرجى التأكد من وضوح الصورة وإعادة رفعها، أو تزويدنا بتفاصيل الحوالة. 🤝"), 'btn-danger');
        audit('order_reject', "order $oid"); flash('ok', d('تم رفض الوصل.', 'Receipt rejected.'));
    }
    redirect('financial.php?' . http_build_query(array_intersect_key($_GET, ['s' => 1])));
}
$f = $_GET['s'] ?? '';
$sql = "SELECT o.*, u.full_name AS trainee_name, c.title_ar AS course_title, c.id AS cid, c.trainer_id, t.full_name AS trainer_name,
        c.commission_type_usd, c.commission_value_usd, c.commission_type_dzd, c.commission_value_dzd
        FROM course_orders o JOIN users u ON o.user_id=u.id JOIN courses c ON o.course_id=c.id JOIN users t ON c.trainer_id=t.id";
$par = []; if (in_array($f, ['pending', 'approved', 'rejected', 'reserved', 'canceled'], true)) { $sql .= " WHERE o.status=?"; $par[] = $f; }
$st = $pdo->prepare($sql . " ORDER BY FIELD(o.status,'pending') DESC, o.created_at DESC LIMIT 300"); $st->execute($par); $orders = $st->fetchAll();
$dashTitle = d('طلبات الدورات والمدفوعات', 'Course orders & payments');
require '../includes/partials/dash_header.php';
echo wa_render();
?>
<ul class="nav nav-pills mb-3 gap-1"><?php foreach (['' => t('all'), 'pending' => d('بانتظار المراجعة', 'Pending'), 'approved' => d('مقبولة', 'Approved'), 'rejected' => d('مرفوضة', 'Rejected'), 'reserved' => d('محجوزة', 'Reserved'), 'canceled' => d('ملغاة', 'Canceled')] as $k => $lb): ?>
  <li class="nav-item"><a class="nav-link <?= $f === $k ? 'active' : '' ?>" style="<?= $f === $k ? 'background:var(--emerald)' : 'color:var(--text)' ?>" href="?s=<?= $k ?>"><?= e($lb) ?></a></li><?php endforeach; ?></ul>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th>#</th><th><?= e(d('المتدرب / الدورة', 'Trainee / Course')) ?></th><th><?= e(d('المبلغ', 'Amount')) ?></th><th><?= e(d('الدفع', 'Payment')) ?></th><th><?= e(d('المدرب', 'Trainer')) ?></th><th><?= e(t('status')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($orders as $o): $cur = $o['amount_usd'] > 0 ? 'usd' : 'dzd'; $paid = $cur === 'usd' ? (float)$o['amount_usd'] : (float)$o['amount_dzd']; ?>
<tr class="<?= $o['status'] === 'pending' ? 'bg-warning bg-opacity-10' : '' ?>"><td class="text-muted fw-bold">#<?= str_pad((string)$o['id'], 5, '0', STR_PAD_LEFT) ?></td>
  <td><b><?= e($o['trainee_name']) ?></b><div class="small text-muted"><?= e($o['course_title']) ?></div><div class="small text-muted"><?= e(substr($o['created_at'], 0, 16)) ?></div></td>
  <td class="fw-bold text-success fs-6"><?= $cur === 'usd' ? e(money_usd($paid)) : e(money_dzd($paid)) ?></td>
  <td><span class="st st-mute"><i class="fa-solid <?= ['bank' => 'fa-building-columns', 'paypal' => 'fa-paypal', 'agent' => 'fa-handshake'][$o['payment_method']] ?? 'fa-credit-card' ?>"></i> <?= e(['bank' => d('بنك', 'Bank'), 'paypal' => 'PayPal', 'agent' => d('وكيل', 'Agent')][$o['payment_method']] ?? $o['payment_method']) ?></span>
    <?php if ($o['receipt_image']): ?><br><a class="small" target="_blank" href="../uploads/receipts/<?= e(basename($o['receipt_image'])) ?>"><i class="fa-solid fa-image"></i> <?= e(d('الوصل', 'Receipt')) ?></a><?php endif; ?></td>
  <td><?= e($o['trainer_name']) ?></td><td><?= status_badge($o['status']) ?></td>
  <td class="text-end text-nowrap"><?php if ($o['status'] === 'pending'): ?>
    <button class="btn-soft btn-soft-ok" data-bs-toggle="modal" data-bs-target="#apModal" data-id="<?= (int)$o['id'] ?>" data-trainee="<?= e($o['trainee_name']) ?>" data-amount="<?= e($cur === 'usd' ? money_usd($paid) : money_dzd($paid)) ?>" data-ctype="<?= e($o["commission_type_$cur"] ?: 'percentage') ?>" data-cval="<?= e($o["commission_value_$cur"]) ?>"><i class="fa-solid fa-check"></i> <?= e(d('تأكيد', 'Approve')) ?></button>
    <?= action_btn('', ['act' => 'reject', 'order_id' => $o['id']], 'btn-soft-bad', d('رفض هذا الإيصال؟', 'Reject this receipt?'), 'fa-xmark') ?><?php endif; ?></td></tr>
<?php endforeach; if (!$orders): ?><tr><td colspan="7" class="empty"><i class="fa-solid fa-file-invoice"></i><?= e(d('لا توجد طلبات', 'No orders')) ?></td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="modal fade" id="apModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="approve"><input type="hidden" name="order_id" id="aId">
  <div class="modal-header"><h5 class="modal-title fw-bold text-success"><i class="fa-solid fa-check-double"></i> <?= e(d('تأكيد وتفعيل الدورة', 'Approve & activate')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body text-center"><div class="fw-bold mb-1" id="aTr"></div><div class="display-6 fw-bold text-success mb-3" id="aAmt" dir="ltr"></div>
    <div class="ai-box"><div class="fw-bold mb-2 small"><i class="fa-solid fa-hand-holding-dollar text-warning"></i> <?= e(d('نصيب المدرب من هذه المبيعة', "Trainer's share of this sale")) ?></div>
      <div class="row g-2"><div class="col-6"><select name="commission_type" id="aType" class="form-select"><option value="percentage"><?= e(d('نسبة %', 'Percentage %')) ?></option><option value="fixed"><?= e(d('مبلغ ثابت', 'Fixed')) ?></option></select></div>
      <div class="col-6"><input type="number" step="0.01" min="0" name="commission_value" id="aVal" class="form-control text-center" required></div></div></div></div>
  <div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(d('حفظ وتفعيل', 'Save & activate')) ?></button></div></form></div></div>
<?php
$footExtra = '<script>apModal.addEventListener("show.bs.modal",function(e){var b=e.relatedTarget;aId.value=b.dataset.id;aTr.textContent=b.dataset.trainee;aAmt.textContent=b.dataset.amount;aType.value=b.dataset.ctype;aVal.value=b.dataset.cval||""});</script>';
require '../includes/partials/dash_footer.php';
