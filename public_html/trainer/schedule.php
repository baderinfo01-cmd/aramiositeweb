<?php
require_once 'trainer_init.php';
$types = ['personal' => d('فردية', 'Personal'), 'family' => d('أسرية', 'Family'), 'self_dev' => d('تطوير ذات', 'Self-development'), 'other' => d('أخرى', 'Other')];
$slotFields = function () {
    $mode = ($_POST['session_mode'] ?? '') === 'offline' ? 'offline' : 'online';
    return [
        'type' => array_key_exists($_POST['consult_type'] ?? '', ['personal' => 1, 'family' => 1, 'self_dev' => 1, 'other' => 1]) ? $_POST['consult_type'] : 'personal', 'mode' => $mode,
        'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['available_date'] ?? '') ? $_POST['available_date'] : null,
        'start' => preg_match('/^\d{2}:\d{2}/', $_POST['start_time'] ?? '') ? $_POST['start_time'] : null, 'end' => preg_match('/^\d{2}:\d{2}/', $_POST['end_time'] ?? '') ? $_POST['end_time'] : null,
        'usd' => (isset($_POST['price_usd']) && $_POST['price_usd'] !== '') ? (float)$_POST['price_usd'] : null, 'dzd' => (isset($_POST['price_dzd']) && $_POST['price_dzd'] !== '') ? (float)$_POST['price_dzd'] : null,
        'city' => $mode === 'offline' ? trim((string)($_POST['city'] ?? '')) : null, 'venue' => $mode === 'offline' ? trim((string)($_POST['venue_name'] ?? '')) : null, 'map' => $mode === 'offline' ? trim((string)($_POST['map_link'] ?? '')) : null,
    ];
};
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $act = $_POST['act'] ?? '';
    if ($act === 'add' || $act === 'accept') {
        $f = $slotFields(); $err = '';
        if (!$f['date'] || !$f['start'] || !$f['end'] || strtotime($f['end']) <= strtotime($f['start'])) $err = d('تحقق من التاريخ والوقت (النهاية بعد البداية).', 'Check date/time (end after start).');
        elseif ($f['usd'] === null && $f['dzd'] === null) $err = d('حدد السعر.', 'Set a price.');
        elseif ($f['mode'] === 'offline' && (!$f['city'] || !$f['venue'])) $err = d('اكتب المدينة والقاعة.', 'Enter city and venue.');
        elseif ($f['map'] && !url_ok($f['map'])) $err = d('رابط الخريطة غير صالح.', 'Invalid map link.');
        if ($err) flash('err', $err);
        elseif ($act === 'add') {
            $slot = 'AR-' . strtoupper(bin2hex(random_bytes(3)));
            $pdo->prepare("INSERT INTO trainer_availabilities (trainer_id, consult_type, session_mode, city, venue_name, map_link, available_date, start_time, end_time, price_usd, price_dzd, is_booked, slot_code) VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?)")
                ->execute([$trainer_id, $f['type'], $f['mode'], $f['city'], $f['venue'], $f['map'], $f['date'], $f['start'], $f['end'], $f['usd'], $f['dzd'], $slot]);
            flash('ok', d('تمت إضافة الموعد. كود الحجز المباشر: ', 'Slot added. Direct booking code: ') . $slot);
        } else {
            $rid = (int)$_POST['request_id'];
            $rq = $pdo->prepare("SELECT r.*, u.full_name, u.whatsapp_number FROM custom_consultation_requests r JOIN users u ON u.id=r.trainee_id WHERE r.id=? AND r.trainer_id=? AND r.status='pending_trainer'"); $rq->execute([$rid, $trainer_id]); $r = $rq->fetch();
            if ($r) {
                $pdo->prepare("INSERT INTO trainer_availabilities (trainer_id, consult_type, session_mode, city, venue_name, available_date, start_time, end_time, price_usd, price_dzd, is_booked, slot_code) VALUES (?,?,?,?,?,?,?,?,?,?,1,?)")
                    ->execute([$trainer_id, $f['type'], $f['mode'], $f['city'], $f['venue'], $f['date'], $f['start'], $f['end'], $f['usd'], $f['dzd'], 'AR-C' . random_int(1000, 9999)]);
                $aid = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO consultation_bookings (trainee_id, availability_id, amount_usd, amount_dzd, trainee_problem, status) VALUES (?,?,?,?,?,'reserved')")->execute([$r['trainee_id'], $aid, $f['usd'], $f['dzd'], $r['trainee_problem']]);
                $pdo->prepare("UPDATE custom_consultation_requests SET status='accepted' WHERE id=?")->execute([$rid]);
                notify_user((int)$r['trainee_id'], d('تمت الموافقة على طلب استشارتك', 'Your consultation request was accepted'), d('ادفع خلال 60 دقيقة لتأكيد الموعد', 'Pay within 60 minutes to confirm'), 'consultations.php');
                wa_push(d('أرسل الفاتورة للمتدرب', 'Send invoice to trainee'), wa_link($r['whatsapp_number'], "مرحباً *{$r['full_name']}* ✨\n\nتم تحديد موعد لاستشارتك يوم *{$f['date']}* الساعة *" . date('h:i A', strtotime($f['start'])) . "*.\n\n⚠️ يرجى الدخول للوحتك في أراميو لتأكيد الحجز والدفع خلال 60 دقيقة قبل إلغاء الموعد. 📅"));
                flash('ok', d('تمت الموافقة! أرسل الفاتورة للمتدرب.', 'Accepted! Send the invoice to the trainee.'));
            }
        }
    } elseif ($act === 'reject_req') { $pdo->prepare("UPDATE custom_consultation_requests SET status='rejected' WHERE id=? AND trainer_id=?")->execute([(int)$_POST['request_id'], $trainer_id]); flash('ok', d('تم إرسال الاعتذار.', 'Declined.')); }
    elseif ($act === 'link') {
        $bid = (int)$_POST['booking_id']; $link = trim((string)$_POST['meeting_link']);
        $b = $pdo->prepare("SELECT b.trainee_id, u.full_name, u.whatsapp_number, a.available_date, a.start_time, a.session_mode FROM consultation_bookings b JOIN trainer_availabilities a ON a.id=b.availability_id JOIN users u ON u.id=b.trainee_id WHERE b.id=? AND a.trainer_id=? AND b.status='approved'"); $b->execute([$bid, $trainer_id]); $b = $b->fetch();
        if ($b && $link !== '' && ($b['session_mode'] === 'offline' || url_ok($link))) {
            $pdo->prepare("UPDATE consultation_bookings SET meeting_link=? WHERE id=?")->execute([mb_substr($link, 0, 255), $bid]);
            notify_user((int)$b['trainee_id'], d('رابط/تفاصيل جلستك جاهزة', 'Your session details are ready'), '', 'consultations.php');
            $msg = "مرحباً *{$b['full_name']}* 🌟\n\nتذكير بموعد جلستنا يوم *{$b['available_date']}* الساعة *" . date('h:i A', strtotime($b['start_time'])) . "*.\n\n" . ($b['session_mode'] === 'online' ? "🔗 رابط الاجتماع:\n$link" : "📍 ملاحظة عن الجلسة الحضورية:\n$link") . "\n\n⏱️ يرجى الالتزام بالموعد.";
            wa_push(d('أرسل التفاصيل للمتدرب', 'Send details to trainee'), wa_link($b['whatsapp_number'], $msg)); flash('ok', d('تم الحفظ.', 'Saved.'));
        } else flash('err', d('رابط غير صالح (https://...).', 'Invalid link (https://...).'));
    } elseif ($act === 'delete') { $pdo->prepare("DELETE FROM trainer_availabilities WHERE id=? AND trainer_id=? AND is_booked=0")->execute([(int)$_POST['avail_id'], $trainer_id]); flash('ok', d('أُزيل الموعد.', 'Slot removed.')); }
    redirect('schedule.php');
}
$req = $pdo->prepare("SELECT r.*, u.full_name FROM custom_consultation_requests r JOIN users u ON u.id=r.trainee_id WHERE r.trainer_id=? AND r.status='pending_trainer' ORDER BY r.created_at"); $req->execute([$trainer_id]); $reqs = $req->fetchAll();
$av = $pdo->prepare("SELECT * FROM trainer_availabilities WHERE trainer_id=? AND is_booked=0 AND available_date >= CURDATE() ORDER BY available_date, start_time"); $av->execute([$trainer_id]); $avails = $av->fetchAll();
$bk = $pdo->prepare("SELECT b.id AS bid, b.meeting_link, b.trainee_problem, a.available_date, a.start_time, a.end_time, a.consult_type, a.session_mode, a.city, a.venue_name, u.full_name FROM consultation_bookings b JOIN trainer_availabilities a ON a.id=b.availability_id JOIN users u ON u.id=b.trainee_id WHERE a.trainer_id=? AND b.status='approved' AND a.available_date >= CURDATE() ORDER BY a.available_date, a.start_time"); $bk->execute([$trainer_id]); $booked = $bk->fetchAll();
$dashTitle = d('جدول الاستشارات', 'Consultation schedule');
require '../includes/partials/dash_header.php';
echo wa_buttons();
$slotForm = function (string $act, string $extra = '') use ($types) { ob_start(); ?>
  <?= csrf_field() ?><input type="hidden" name="act" value="<?= $act ?>"><?= $extra ?>
  <div class="row g-2"><div class="col-6"><label class="form-label"><?= e(d('النوع', 'Type')) ?></label><select name="consult_type" class="form-select"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="col-6"><label class="form-label"><?= e(d('الطريقة', 'Mode')) ?></label><select name="session_mode" class="form-select sm-mode"><option value="online"><?= e(d('أونلاين', 'Online')) ?></option><option value="offline"><?= e(d('حضوري', 'In person')) ?></option></select></div>
  <div class="col-md-4"><input type="date" name="available_date" class="form-control" required min="<?= date('Y-m-d') ?>"></div><div class="col-md-4"><input type="time" name="start_time" class="form-control" required></div><div class="col-md-4"><input type="time" name="end_time" class="form-control" required></div>
  <div class="col-6"><input type="number" step="0.01" min="0" name="price_usd" class="form-control" placeholder="USD"></div><div class="col-6"><input type="number" step="0.01" min="0" name="price_dzd" class="form-control" placeholder="DZD"></div>
  <div class="col-12 off-box d-none"><div class="row g-2"><div class="col-md-4"><input name="city" class="form-control" placeholder="<?= e(d('المدينة', 'City')) ?>"></div><div class="col-md-4"><input name="venue_name" class="form-control" placeholder="<?= e(d('القاعة', 'Venue')) ?>"></div><div class="col-md-4"><input name="map_link" class="form-control" dir="ltr" placeholder="Map URL"></div></div></div></div>
  <button class="btn-aramio w-100 mt-3"><?= e(t('save')) ?></button><?php return ob_get_clean(); };
?>
<?php if ($reqs): ?><div class="dcard mb-4" style="border-color:#4e73df"><div class="dcard-h"><?= e(d('طلبات استشارة مخصصة', 'Custom consultation requests')) ?> <span class="st st-info"><?= count($reqs) ?></span></div><div class="dcard-b">
  <?php foreach ($reqs as $r): ?><div class="border-bottom pb-3 mb-3"><b><?= e($r['full_name']) ?></b><p class="mb-2 small" style="white-space:pre-wrap"><?= e($r['trainee_problem']) ?></p>
    <button class="btn-soft btn-soft-ok" data-bs-toggle="modal" data-bs-target="#accModal" data-id="<?= (int)$r['id'] ?>"><i class="fa-solid fa-check"></i> <?= e(d('قبول وتحديد موعد', 'Accept & schedule')) ?></button> <?= action_btn(d('اعتذار', 'Decline'), ['act' => 'reject_req', 'request_id' => $r['id']], 'btn-soft-bad', d('تأكيد الاعتذار؟', 'Decline?')) ?></div><?php endforeach; ?></div></div><?php endif; ?>
<div class="row g-3"><div class="col-lg-6"><div class="dcard h-100"><div class="dcard-h"><?= e(d('جلسات مؤكدة قادمة', 'Confirmed upcoming sessions')) ?></div><div class="dcard-b">
  <?php foreach ($booked as $b): ?><div class="border-bottom pb-3 mb-3"><div class="d-flex justify-content-between"><b><?= e($b['full_name']) ?></b><span class="st st-info"><?= e($types[$b['consult_type']] ?? '') ?></span></div>
    <div class="small text-primary"><i class="fa-regular fa-calendar"></i> <?= e($b['available_date']) ?> <span dir="ltr"><?= e(substr($b['start_time'], 0, 5)) ?>–<?= e(substr($b['end_time'], 0, 5)) ?></span> · <?= e($b['session_mode'] === 'online' ? d('أونلاين', 'Online') : $b['city'] . ' / ' . $b['venue_name']) ?></div>
    <?php if ($b['trainee_problem']): ?><p class="small text-muted mb-2" style="white-space:pre-wrap"><?= e($b['trainee_problem']) ?></p><?php endif; ?>
    <?php if ($b['meeting_link']): ?><div class="small"><i class="fa-solid fa-link"></i> <?= e($b['meeting_link']) ?></div><?php else: ?><button class="btn-soft btn-soft-ok" data-bs-toggle="modal" data-bs-target="#lnkModal" data-id="<?= (int)$b['bid'] ?>" data-mode="<?= e($b['session_mode']) ?>"><i class="fa-solid fa-link"></i> <?= e($b['session_mode'] === 'online' ? d('أضف رابط الاجتماع', 'Add meeting link') : d('أضف ملاحظة', 'Add note')) ?></button><?php endif; ?></div><?php endforeach; ?>
  <?php if (!$booked): ?><div class="empty"><i class="fa-solid fa-calendar"></i></div><?php endif; ?></div></div></div>
 <div class="col-lg-6"><div class="dcard h-100"><div class="dcard-h"><?= e(d('مواعيدي المتاحة', 'My open slots')) ?><button class="btn-aramio py-1" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fa-solid fa-plus"></i></button></div><div class="dcard-b">
  <?php foreach ($avails as $a): ?><div class="d-flex justify-content-between align-items-center border-bottom py-2"><div><b><?= e($a['available_date']) ?></b> <span dir="ltr" class="small"><?= e(substr($a['start_time'], 0, 5)) ?>–<?= e(substr($a['end_time'], 0, 5)) ?></span><div class="small text-muted"><code><?= e($a['slot_code']) ?></code> · <?= $a['price_usd'] !== null ? e(money_usd($a['price_usd'])) : '' ?> <?= $a['price_dzd'] !== null ? e(money_dzd($a['price_dzd'])) : '' ?></div></div><?= action_btn('', ['act' => 'delete', 'avail_id' => $a['id']], 'btn-soft-bad', d('إزالة الموعد؟', 'Remove?'), 'fa-trash') ?></div><?php endforeach; ?>
  <?php if (!$avails): ?><div class="empty"><i class="fa-solid fa-calendar-plus"></i></div><?php endif; ?></div></div></div></div>
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title fw-bold"><?= e(d('موعد متاح جديد', 'New open slot')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><?= $slotForm('add') ?></div></form></div></div>
<div class="modal fade" id="accModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title fw-bold"><?= e(d('تحديد موعد للمتدرب', 'Schedule for the trainee')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><?= $slotForm('accept', '<input type="hidden" name="request_id" id="accId">') ?></div></form></div></div>
<div class="modal fade" id="lnkModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="link"><input type="hidden" name="booking_id" id="lnkId"><div class="modal-header"><h5 class="modal-title fw-bold"><?= e(d('تفاصيل الجلسة', 'Session details')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input name="meeting_link" class="form-control" required placeholder="https://zoom.us/..."></div><div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(t('save')) ?></button></div></form></div></div>
<?php
$footExtra = '<script>document.querySelectorAll(".sm-mode").forEach(function(s){s.addEventListener("change",function(){s.closest("form").querySelector(".off-box").classList.toggle("d-none",s.value!=="offline")})});
accModal.addEventListener("show.bs.modal",function(e){accId.value=e.relatedTarget.dataset.id});lnkModal.addEventListener("show.bs.modal",function(e){lnkId.value=e.relatedTarget.dataset.id});</script>';
require '../includes/partials/dash_footer.php';
