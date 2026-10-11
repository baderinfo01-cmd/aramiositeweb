<?php
require_once 'admin_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $act = $_POST['act'] ?? '';
    if ($act === 'add') { $ar = trim((string)$_POST['msg_ar']); $en = trim((string)$_POST['msg_en']); if ($ar !== '') { $pdo->prepare("INSERT INTO popup_notifications (message_ar, message_en) VALUES (?,?)")->execute([mb_substr($ar, 0, 255), mb_substr($en ?: $ar, 0, 255)]); flash('ok', d('تمت الإضافة.', 'Added.')); } }
    elseif ($act === 'timer') { setSetting('popup_timer', (string)max(4, (int)$_POST['popup_timer'])); flash('ok', d('تم تحديث المدة.', 'Timer updated.')); }
    elseif ($act === 'toggle') { $pdo->prepare("UPDATE popup_notifications SET status = 1 - status WHERE id=?")->execute([(int)$_POST['id']]); }
    elseif ($act === 'delete') { $pdo->prepare("DELETE FROM popup_notifications WHERE id=?")->execute([(int)$_POST['id']]); flash('ok', d('تم الحذف.', 'Deleted.')); }
    elseif ($act === 'bulk' && !empty($_POST['items'])) { $n = 0; foreach ((array)$_POST['items'] as $it) { $ar = trim((string)($it['ar'] ?? '')); if ($ar !== '') { $pdo->prepare("INSERT INTO popup_notifications (message_ar, message_en) VALUES (?,?)")->execute([mb_substr($ar, 0, 255), mb_substr(trim((string)($it['en'] ?? '')) ?: $ar, 0, 255)]); $n++; } } flash('ok', "+$n"); }
    redirect('notifications.php');
}
$list = $pdo->query("SELECT * FROM popup_notifications ORDER BY id DESC")->fetchAll(); $timer = (int)(getSetting($pdo, 'popup_timer') ?: 10);
$dashTitle = d('الإشعارات المنبثقة للزوار', 'Visitor popup notices');
require '../includes/partials/dash_header.php';
?>
<div class="row g-3"><div class="col-lg-5"><div class="dcard"><div class="dcard-h"><?= e(d('إضافة إشعار', 'Add notice')) ?></div><form method="post" class="dcard-b d-grid gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="add">
  <input name="msg_ar" class="form-control" required maxlength="255" placeholder="النص بالعربية"><input name="msg_en" class="form-control" maxlength="255" dir="ltr" placeholder="English text"><button class="btn-aramio"><?= e(t('save')) ?></button></form></div>
  <div class="dcard mt-3"><div class="dcard-h"><?= e(d('مدة الظهور (ثوانٍ)', 'Interval (seconds)')) ?></div><form method="post" class="dcard-b d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="timer"><input type="number" name="popup_timer" min="4" value="<?= $timer ?>" class="form-control"><button class="btn-soft btn-soft-ok"><?= e(t('save')) ?></button></form></div>
  <div class="dcard mt-3"><div class="dcard-h"><span><i class="fa-solid fa-wand-magic-sparkles sparkle"></i> <?= e(d('توليد بالذكاء الاصطناعي', 'Generate with AI')) ?></span><button class="ai-btn" id="genBtn"><?= e(d('اقترح 5', 'Suggest 5')) ?></button></div>
    <form method="post" class="dcard-b" id="genForm"><?= csrf_field() ?><input type="hidden" name="act" value="bulk"><div id="genOut" class="small text-muted"><?= e(d('يولّد رسائل إثبات اجتماعي قصيرة اعتمادًا على دوراتك.', 'Generates short social-proof messages from your courses.')) ?></div></form></div></div>
<div class="col-lg-7"><div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th>AR / EN</th><th></th></tr></thead><tbody>
<?php foreach ($list as $n): ?><tr class="<?= $n['status'] ? '' : 'opacity-50' ?>"><td><?= e($n['message_ar']) ?><div class="small text-muted" dir="ltr"><?= e($n['message_en']) ?></div></td><td class="text-end text-nowrap"><?= action_btn('', ['act' => 'toggle', 'id' => $n['id']], 'btn-soft-warn', '', $n['status'] ? 'fa-eye' : 'fa-eye-slash') ?><?= action_btn('', ['act' => 'delete', 'id' => $n['id']], 'btn-soft-bad', d('حذف؟', 'Delete?'), 'fa-trash') ?></td></tr><?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="2" class="empty"><i class="fa-solid fa-bell"></i></td></tr><?php endif; ?></tbody></table></div></div></div></div>
<?php
$footExtra = '<script>genBtn.addEventListener("click",function(){var s=this;AI_ASK("../api/ai_tools.php",{action:"popups"},function(r){if(!r.ok){alert(r.message||"error");return}var o=document.getElementById("genOut");o.innerHTML="";(r.data.items||[]).forEach(function(it,i){var d=document.createElement("div");d.className="mb-2";d.innerHTML=\'<input class="form-control form-control-sm mb-1" name="items[\'+i+\'][ar]"><input dir="ltr" class="form-control form-control-sm" name="items[\'+i+\'][en]">\';d.children[0].value=it.ar||"";d.children[1].value=it.en||"";o.appendChild(d)});var b=document.createElement("button");b.className="btn-aramio w-100 mt-2";b.textContent=' . json_encode(t('save')) . ';o.appendChild(b)},s)});</script>';
require '../includes/partials/dash_footer.php';
