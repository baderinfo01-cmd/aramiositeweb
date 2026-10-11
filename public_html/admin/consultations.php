<?php
require_once 'admin_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $act = $_POST['act'] ?? ''; $bid = (int)($_POST['booking_id'] ?? 0);
    $st = $pdo->prepare("SELECT b.*, u.full_name AS trainee_name, a.trainer_id, a.id AS aid FROM consultation_bookings b JOIN users u ON u.id=b.trainee_id JOIN trainer_availabilities a ON a.id=b.availability_id WHERE b.id=?");
    $st->execute([$bid]); $b = $st->fetch();
    if (!$b) flash('err', d('الحجز غير موجود.', 'Booking not found.'));
    elseif ($b['status'] !== 'pending') flash('warn', d('تمت معالجة هذا الحجز مسبقًا.', 'This booking was already processed.'));
    elseif ($act === 'approve') {
        $type = $_POST['commission_type'] ?? 'percentage'; $val = (float)($_POST['commission_value'] ?? 0);
        $cur = $b['amount_usd'] > 0 ? 'usd' : 'dzd'; $paid = (float)($cur === 'usd' ? $b['amount_usd'] : $b['amount_dzd']);
        $comm = $type === 'percentage' ? $paid * min($val, 100) / 100 : min($val, $paid);
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare("UPDATE consultation_bookings SET status='approved' WHERE id=? AND status='pending'"); $up->execute([$bid]);
            if ($up->rowCount() === 1) $pdo->prepare("INSERT INTO trainer_earnings (trainer_id, booking_id, amount, currency) VALUES (?,?,?,?)")->execute([$b['trainer_id'], $bid, $comm, $cur]);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); error_log($e->getMessage()); flash('err', 'DB error — did you run database/upgrade_v2.sql?'); redirect('consultations.php'); }
        notify_user((int)$b['trainee_id'], d('تم تأكيد حجز الاستشارة', 'Consultation booking confirmed'), '', 'consultations.php');
        notify_user((int)$b['trainer_id'], d('حجز استشارة جديد مؤكد', 'New confirmed consultation'), $b['trainee_name'], 'schedule.php');
        audit('consult_approve', "booking $bid"); flash('ok', d('تم تأكيد الحجز وإيداع عمولة المدرب.', 'Booking confirmed and trainer commission credited.'));
    } elseif ($act === 'reject') {
        $pdo->prepare("UPDATE consultation_bookings SET status='rejected' WHERE id=?")->execute([$bid]);
        $pdo->prepare("UPDATE trainer_availabilities SET is_booked=0 WHERE id=?")->execute([$b['aid']]);
        notify_user((int)$b['trainee_id'], d('تعذّر تأكيد دفع الاستشارة', 'Consultation payment rejected'), '', 'consultations.php');
        audit('consult_reject', "booking $bid"); flash('ok', d('تم رفض الوصل وإعادة إتاحة الموعد.', 'Receipt rejected and the slot is available again.'));
    }
    redirect('consultations.php');
}
$bookings = $pdo->query("SELECT b.*, u.full_name AS trainee_name, a.trainer_id, t.full_name AS trainer_name, a.available_date, a.start_time
    FROM consultation_bookings b JOIN users u ON b.trainee_id=u.id JOIN trainer_availabilities a ON b.availability_id=a.id JOIN users t ON a.trainer_id=t.id ORDER BY b.created_at DESC LIMIT 300")->fetchAll();
$dashTitle = d('مدفوعات الاستشارات', 'Consultation payments');
require '../includes/partials/dash_header.php';
?>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(d('المتدرب / الموعد', 'Trainee / Slot')) ?></th><th><?= e(d('المبلغ', 'Amount')) ?></th><th><?= e(d('الدفع', 'Payment')) ?></th><th><?= e(d('المستشار', 'Consultant')) ?></th><th><?= e(t('status')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($bookings as $b): $cur = $b['amount_usd'] > 0 ? 'usd' : 'dzd'; $paid = $cur === 'usd' ? (float)$b['amount_usd'] : (float)$b['amount_dzd']; ?>
<tr class="<?= $b['status'] === 'pending' ? 'bg-warning bg-opacity-10' : '' ?>"><td><b><?= e($b['trainee_name']) ?></b><div class="small text-primary"><i class="fa-regular fa-calendar"></i> <?= e($b['available_date']) ?> <span dir="ltr"><?= e(substr($b['start_time'], 0, 5)) ?></span></div></td>
  <td class="fw-bold text-success"><?= $cur === 'usd' ? e(money_usd($paid)) : e(money_dzd($paid)) ?></td>
  <td><?php if ($b['status'] === 'reserved'): ?><span class="st st-warn"><?= e(d('لم يرفع الوصل', 'No receipt yet')) ?></span><?php elseif ($b['status'] === 'canceled'): ?><span class="st st-mute"><?= e(d('ملغى (تأخر الدفع)', 'Canceled (late)')) ?></span><?php else: ?>
    <span class="st st-mute"><?= e(['bank' => d('بنك', 'Bank'), 'paypal' => 'PayPal', 'agent' => d('وكيل', 'Agent')][$b['payment_method']] ?? '') ?></span><?php if ($b['receipt_image']): ?> <a target="_blank" href="../uploads/receipts/<?= e(basename($b['receipt_image'])) ?>" class="small"><i class="fa-solid fa-image"></i></a><?php endif; endif; ?></td>
  <td><?= e($b['trainer_name']) ?></td><td><?= status_badge($b['status']) ?></td>
  <td class="text-end text-nowrap"><?php if ($b['status'] === 'pending'): ?>
    <button class="btn-soft btn-soft-ok" data-bs-toggle="modal" data-bs-target="#cModal" data-id="<?= (int)$b['id'] ?>" data-trainee="<?= e($b['trainee_name']) ?>" data-amount="<?= e($cur === 'usd' ? money_usd($paid) : money_dzd($paid)) ?>"><i class="fa-solid fa-check"></i> <?= e(d('تأكيد', 'Approve')) ?></button>
    <?= action_btn('', ['act' => 'reject', 'booking_id' => $b['id']], 'btn-soft-bad', d('رفض الإيصال وإلغاء الحجز؟', 'Reject receipt & cancel booking?'), 'fa-xmark') ?><?php endif; ?></td></tr>
<?php endforeach; if (!$bookings): ?><tr><td colspan="6" class="empty"><i class="fa-solid fa-handshake"></i><?= e(d('لا توجد حجوزات', 'No bookings')) ?></td></tr><?php endif; ?></tbody></table></div></div>
<div class="modal fade" id="cModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="approve"><input type="hidden" name="booking_id" id="cId">
  <div class="modal-header"><h5 class="modal-title fw-bold text-success"><?= e(d('تأكيد حجز الاستشارة', 'Confirm consultation')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body text-center"><div class="fw-bold" id="cTr"></div><div class="display-6 fw-bold text-success mb-3" id="cAmt" dir="ltr"></div>
    <div class="ai-box"><div class="small fw-bold mb-2"><?= e(d('نصيب المستشار من الجلسة', "Consultant's share")) ?></div><div class="row g-2"><div class="col-6"><select name="commission_type" class="form-select"><option value="percentage">%</option><option value="fixed"><?= e(d('مبلغ ثابت', 'Fixed')) ?></option></select></div><div class="col-6"><input type="number" step="0.01" min="0" name="commission_value" class="form-control text-center" required></div></div></div></div>
  <div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(t('save')) ?></button></div></form></div></div>
<?php
$footExtra = '<script>cModal.addEventListener("show.bs.modal",function(e){var b=e.relatedTarget;cId.value=b.dataset.id;cTr.textContent=b.dataset.trainee;cAmt.textContent=b.dataset.amount});</script>';
require '../includes/partials/dash_footer.php';
