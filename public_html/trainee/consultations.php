<?php
require_once 'trainee_init.php';
$types = ['personal' => d('فردية', 'Personal'), 'family' => d('أسرية', 'Family'), 'self_dev' => d('تطوير ذات', 'Self-development'), 'other' => d('أخرى', 'Other')];
$found = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $act = $_POST['act'] ?? '';
    if ($act === 'search') {
        $s = $pdo->prepare("SELECT a.*, u.full_name AS trainer_name FROM trainer_availabilities a JOIN users u ON u.id=a.trainer_id WHERE a.slot_code=? AND a.is_booked=0 AND a.available_date >= CURDATE()"); $s->execute([trim((string)$_POST['slot_code'])]); $found = $s->fetch();
        if (!$found) flash('err', d('الكود غير صحيح أو الموعد محجوز.', 'Invalid code or slot already booked.'));
    } elseif ($act === 'reserve') {
        $aid = (int)$_POST['availability_id'];
        $pdo->beginTransaction();
        try {
            $s = $pdo->prepare("SELECT price_usd, price_dzd FROM trainer_availabilities WHERE id=? AND is_booked=0 AND available_date >= CURDATE() FOR UPDATE"); $s->execute([$aid]); $a = $s->fetch();
            if ($a) { $pdo->prepare("UPDATE trainer_availabilities SET is_booked=1 WHERE id=?")->execute([$aid]); $pdo->prepare("INSERT INTO consultation_bookings (trainee_id, availability_id, amount_usd, amount_dzd, trainee_problem, status) VALUES (?,?,?,?,?, 'reserved')")->execute([$user_id, $aid, $a['price_usd'], $a['price_dzd'], mb_substr(trim((string)$_POST['trainee_problem']), 0, 2000)]); }
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); $a = null; error_log($e->getMessage()); }
        flash($a ? 'ok' : 'err', $a ? d('تم الحجز مبدئيًا! لديك 60 دقيقة لرفع الإيصال.', 'Reserved! You have 60 minutes to upload the receipt.') : d('عذرًا، الموعد غير متاح.', 'Sorry, this slot is no longer available.'));
    } elseif ($act === 'custom') {
        $tid = (int)$_POST['trainer_id']; $p = trim((string)$_POST['problem']);
        $ok = $pdo->prepare("SELECT 1 FROM users WHERE id=? AND role='trainer' AND status=1"); $ok->execute([$tid]);
        if ($ok->fetchColumn() && $p !== '') { $pdo->prepare("INSERT INTO custom_consultation_requests (trainee_id, trainer_id, trainee_problem) VALUES (?,?,?)")->execute([$user_id, $tid, mb_substr($p, 0, 3000)]); notify_user($tid, d('طلب استشارة مخصصة', 'Custom consultation request'), $me['full_name'], 'schedule.php'); flash('ok', d('أُرسل طلبك للمدرب.', 'Request sent to the trainer.')); }
        else flash('err', t('err_required'));
    } elseif ($act === 'pay') {
        $bid = (int)$_POST['booking_id']; $cur = $_POST['currency'] ?? '';
        $b = $pdo->prepare("SELECT id, amount_usd, amount_dzd FROM consultation_bookings WHERE id=? AND trainee_id=? AND status='reserved'"); $b->execute([$bid, $user_id]); $b = $b->fetch();
        if (!$b || !in_array($cur, ['usd', 'dzd'], true) || (float)$b["amount_$cur"] <= 0 || !in_array($_POST['payment_method'] ?? '', ['bank', 'paypal', 'agent'], true)) flash('err', d('الحجز غير متاح.', 'Booking unavailable.'));
        elseif (!($f = pay_receipt_save())) flash('err', d('ارفع وصلًا واضحًا (JPG/PNG/PDF).', 'Upload a clear receipt (JPG/PNG/PDF).'));
        else {
            $pdo->prepare("UPDATE consultation_bookings SET receipt_image=?, payment_method=?, amount_usd=?, amount_dzd=?, status='pending' WHERE id=? AND trainee_id=?")->execute([$f, $_POST['payment_method'], $cur === 'usd' ? $b['amount_usd'] : null, $cur === 'dzd' ? $b['amount_dzd'] : null, $bid, $user_id]);
            foreach ($pdo->query("SELECT id FROM users WHERE role IN ('admin','staff')")->fetchAll(PDO::FETCH_COLUMN) as $aid) notify_user((int)$aid, d('إيصال استشارة جديد', 'New consultation receipt'), $me['full_name'], 'consultations.php');
            flash('ok', d('تم رفع الإيصال وبانتظار تأكيد الإدارة.', 'Receipt uploaded; awaiting confirmation.'));
        }
    }
    if ($act !== 'search') redirect('consultations.php');
}
$open = $pdo->query("SELECT a.*, u.full_name AS trainer_name, u.avatar FROM trainer_availabilities a JOIN users u ON u.id=a.trainer_id WHERE a.is_booked=0 AND a.available_date >= CURDATE() ORDER BY a.available_date, a.start_time LIMIT 60")->fetchAll();
$trainers = $pdo->query("SELECT id, full_name, country FROM users WHERE role='trainer' AND status=1 ORDER BY full_name")->fetchAll();
$my = $pdo->prepare("SELECT b.*, a.available_date, a.start_time, a.end_time, a.consult_type, a.session_mode, a.city, a.venue_name, a.map_link, u.full_name AS trainer_name, TIMESTAMPDIFF(SECOND, b.created_at, NOW()) AS secs FROM consultation_bookings b JOIN trainer_availabilities a ON a.id=b.availability_id JOIN users u ON u.id=a.trainer_id WHERE b.trainee_id=? ORDER BY b.created_at DESC"); $my->execute([$user_id]); $mine = $my->fetchAll();
$dashTitle = d('الاستشارات', 'Consultations');
require '../includes/partials/dash_header.php';
$slotCard = function (array $a, bool $showTrainer = true) use ($types) { ?>
  <div class="dcard h-100"><div class="dcard-b"><div class="d-flex justify-content-between"><b><?= e($a['trainer_name']) ?></b><span class="st st-info"><?= e($types[$a['consult_type']] ?? '') ?></span></div>
    <div class="small my-2"><i class="fa-regular fa-calendar"></i> <?= e($a['available_date']) ?> <span dir="ltr"><?= e(substr($a['start_time'], 0, 5)) ?>–<?= e(substr($a['end_time'], 0, 5)) ?></span><br><i class="fa-solid <?= $a['session_mode'] === 'online' ? 'fa-video' : 'fa-location-dot' ?>"></i> <?= e($a['session_mode'] === 'online' ? d('أونلاين', 'Online') : $a['city'] . ' — ' . $a['venue_name']) ?></div>
    <div class="fw-bold text-success mb-2"><?= $a['price_usd'] > 0 ? e(money_usd($a['price_usd'])) : '' ?> <?= $a['price_dzd'] > 0 ? e(money_dzd($a['price_dzd'])) : '' ?></div>
    <button class="btn-aramio py-1 w-100" data-bs-toggle="modal" data-bs-target="#resModal" data-id="<?= (int)$a['id'] ?>" data-t="<?= e($a['trainer_name']) ?>"><?= e(d('احجز هذا الموعد', 'Book this slot')) ?></button></div></div>
<?php };
?>
<?php if ($mine): ?><div class="dcard mb-4"><div class="dcard-h"><?= e(d('حجوزاتي', 'My bookings')) ?></div><div class="dcard-b"><?php foreach ($mine as $b): ?><div class="border-bottom pb-3 mb-3"><div class="row g-3"><div class="col-lg-5"><div class="d-flex justify-content-between"><b><?= e($b['trainer_name']) ?></b><?= status_badge($b['status']) ?></div>
  <div class="small text-muted"><i class="fa-regular fa-calendar"></i> <?= e($b['available_date']) ?> <span dir="ltr"><?= e(substr($b['start_time'], 0, 5)) ?></span> · <?= e($b['session_mode'] === 'online' ? d('أونلاين', 'Online') : $b['city']) ?></div>
  <?php if ($b['status'] === 'reserved'): $left = max(0, 3600 - (int)$b['secs']); ?><div class="alert alert-warning py-2 small mt-2 mb-0"><i class="fa-solid fa-clock"></i> <?= e(d('الوقت المتبقي للدفع:', 'Time left to pay:')) ?> <b class="cd" data-left="<?= $left ?>"></b></div><?php endif; ?>
  <?php if (in_array($b['status'], ['approved', 'completed'], true)): ?><div class="mt-2"><?php if ($b['meeting_link']): ?><?php if (url_ok_pub($b['meeting_link'])): ?><a class="btn-aramio py-1" target="_blank" rel="noopener noreferrer" href="<?= e($b['meeting_link']) ?>"><i class="fa-solid fa-video"></i> <?= e(d('انضم للجلسة', 'Join session')) ?></a><?php else: ?><div class="ai-box small"><?= e($b['meeting_link']) ?></div><?php endif; ?><?php else: ?><span class="small text-muted"><?= e(d('سيضيف المدرب رابط/تفاصيل الجلسة قريبًا.', 'The trainer will add the session details soon.')) ?></span><?php endif; ?></div><?php endif; ?></div>
  <?php if ($b['status'] === 'reserved'): ?><div class="col-lg-7"><?php $f = $b; pay_form('booking', (int)$b['id'], (float)$b['amount_usd'], (float)$b['amount_dzd'], 'b' . $b['id']); ?></div><?php endif; ?></div></div><?php endforeach; ?></div></div><?php endif; ?>

<div class="row g-3 mb-4"><div class="col-lg-6"><div class="dcard h-100"><div class="dcard-h"><?= e(d('هل لديك كود موعد؟', 'Have a slot code?')) ?></div><div class="dcard-b"><form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="search"><input name="slot_code" class="form-control" dir="ltr" placeholder="AR-XXXXXX" required><button class="btn-aramio"><i class="fa-solid fa-magnifying-glass"></i></button></form>
  <?php if ($found): $found['trainer_name'] = $found['trainer_name']; ?><div class="mt-3"><?php $slotCard($found); ?></div><?php endif; ?></div></div></div>
  <div class="col-lg-6"><div class="dcard h-100"><div class="dcard-h"><?= e(d('اطلب استشارة مخصصة', 'Request a custom consultation')) ?></div><form method="post" class="dcard-b d-grid gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="custom"><select name="trainer_id" class="form-select" required><option value=""><?= e(d('اختر المستشار', 'Choose a consultant')) ?></option><?php foreach ($trainers as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['full_name']) ?></option><?php endforeach; ?></select>
    <textarea name="problem" rows="3" class="form-control" required placeholder="<?= e(d('اشرح مشكلتك أو هدفك…', 'Describe your issue or goal…')) ?>"></textarea><button class="btn-aramio"><?= e(t('send')) ?></button></form></div></div></div>
<h5 class="fw-bold mb-3"><?= e(d('المواعيد المتاحة', 'Open slots')) ?></h5>
<div class="row g-3"><?php foreach ($open as $a): ?><div class="col-md-6 col-xl-4"><?php $slotCard($a); ?></div><?php endforeach; ?><?php if (!$open): ?><div class="col-12"><div class="empty"><i class="fa-solid fa-calendar"></i><?= e(d('لا مواعيد متاحة حاليًا.', 'No open slots right now.')) ?></div></div><?php endif; ?></div>
<div class="modal fade" id="resModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="reserve"><input type="hidden" name="availability_id" id="rId"><div class="modal-header"><h5 class="modal-title fw-bold" id="rT"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><label class="form-label small fw-bold"><?= e(d('ما الذي تريد مناقشته؟', 'What would you like to discuss?')) ?></label><textarea name="trainee_problem" rows="4" class="form-control" required></textarea></div><div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(d('تأكيد الحجز المبدئي', 'Confirm reservation')) ?></button></div></form></div></div>
<?php
$footExtra = pay_js() . '<script>resModal.addEventListener("show.bs.modal",function(e){rId.value=e.relatedTarget.dataset.id;rT.textContent=e.relatedTarget.dataset.t});document.querySelectorAll(".cd").forEach(function(el){var s=parseInt(el.dataset.left,10);(function t(){if(s<=0){location.reload();return}el.textContent=String(Math.floor(s/60)).padStart(2,"0")+":"+String(s%60).padStart(2,"0");s--;setTimeout(t,1000)})()});</script>';
require '../includes/partials/dash_footer.php';
