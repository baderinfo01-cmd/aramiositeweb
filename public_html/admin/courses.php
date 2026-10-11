<?php
require_once 'admin_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $act = $_POST['act'] ?? ''; $cid = (int)($_POST['course_id'] ?? 0);
    $st = $pdo->prepare("SELECT c.*, u.full_name AS trainer_name, u.whatsapp_number AS trainer_wa, u.id AS tid FROM courses c JOIN users u ON u.id=c.trainer_id WHERE c.id=?"); $st->execute([$cid]); $c = $st->fetch();
    if ($c) {
        if ($act === 'publish') {
            $ct = fn($k) => in_array($_POST[$k] ?? '', ['percentage', 'fixed'], true) ? $_POST[$k] : null;
            $cv = fn($k) => (isset($_POST[$k]) && $_POST[$k] !== '' && is_numeric($_POST[$k])) ? (float)$_POST[$k] : null;
            $pdo->prepare("UPDATE courses SET commission_type_usd=?, commission_value_usd=?, commission_type_dzd=?, commission_value_dzd=?, status='published' WHERE id=?")
                ->execute([$ct('comm_type_usd'), $cv('comm_value_usd'), $ct('comm_type_dzd'), $cv('comm_value_dzd'), $cid]);
            notify_user((int)$c['tid'], d('تمت الموافقة على دورتك', 'Your course was approved'), $c['title_ar'], 'my_courses.php');
            $tpl = getSetting($pdo, 'msg_course_approved', 'ar');
            wa_queue(d('تهنئة المدرب', 'Congratulate trainer'), wa_link($c['trainer_wa'], "مرحباً كوتش *{$c['trainer_name']}* 🌟\n\n" . ($tpl ? $tpl . "\n\n" : '') . "📖 *{$c['title_ar']}*\n\nالدورة الآن منشورة في منصة أراميو."));
            audit('course_publish', (string)$cid); flash('ok', d('تمت الموافقة ونشر الدورة.', 'Course approved and published.'));
        } elseif ($act === 'reject') { $pdo->prepare("UPDATE courses SET status='rejected' WHERE id=?")->execute([$cid]); notify_user((int)$c['tid'], d('تم رفض دورتك', 'Your course was rejected'), $c['title_ar'], 'my_courses.php'); audit('course_reject', (string)$cid); flash('ok', d('تم رفض الدورة.', 'Course rejected.')); }
        elseif ($act === 'unpublish') { $pdo->prepare("UPDATE courses SET status='draft' WHERE id=?")->execute([$cid]); flash('ok', d('أُعيدت الدورة إلى مسودة.', 'Course moved to draft.')); }
        elseif ($act === 'delete') { admin_only(); $pdo->prepare("DELETE FROM courses WHERE id=?")->execute([$cid]); audit('course_delete', (string)$cid); flash('ok', d('تم حذف الدورة نهائيًا.', 'Course deleted.')); }
    }
    redirect('courses.php?' . http_build_query(array_intersect_key($_GET, ['s' => 1])));
}
$f = $_GET['s'] ?? '';
$counts = $pdo->query("SELECT status, COUNT(*) n FROM courses GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$sql = "SELECT c.*, u.full_name AS trainer_name, cat.name_ar AS cat_name FROM courses c JOIN users u ON c.trainer_id=u.id JOIN categories cat ON c.category_id=cat.id";
$par = []; if (in_array($f, ['draft', 'pending', 'published', 'rejected'], true)) { $sql .= " WHERE c.status=?"; $par[] = $f; }
$st = $pdo->prepare($sql . " ORDER BY FIELD(c.status,'pending','published','draft','rejected'), c.created_at DESC"); $st->execute($par); $courses = $st->fetchAll();
$dashTitle = d('إدارة الدورات', 'Course management');
require '../includes/partials/dash_header.php';
echo wa_render();
?>
<ul class="nav nav-pills mb-3 gap-1">
  <?php foreach (['' => t('all'), 'pending' => d('بانتظار الموافقة', 'Pending'), 'published' => d('منشورة', 'Published'), 'draft' => d('مسودات', 'Drafts'), 'rejected' => d('مرفوضة', 'Rejected')] as $k => $lb): ?>
    <li class="nav-item"><a class="nav-link <?= $f === $k ? 'active' : '' ?>" style="<?= $f === $k ? 'background:var(--emerald)' : 'color:var(--text)' ?>" href="?s=<?= $k ?>"><?= e($lb) ?> <span class="badge bg-secondary bg-opacity-25 text-reset"><?= $k === '' ? array_sum($counts) : (int)($counts[$k] ?? 0) ?></span></a></li>
  <?php endforeach; ?></ul>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(d('الدورة', 'Course')) ?></th><th><?= e(d('المدرب', 'Trainer')) ?></th><th><?= e(t('price')) ?></th><th><?= e(d('العمولة', 'Commission')) ?></th><th><?= e(t('status')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($courses as $c): ?><tr>
  <td><a href="../course.php?id=<?= (int)$c['id'] ?>" target="_blank" class="fw-bold text-reset"><?= e($c['title_ar']) ?></a><div class="small text-muted"><?= e($c['title_en']) ?></div><span class="st st-mute"><?= e($c['cat_name']) ?></span> <span class="st st-info"><?= e(type_label($c['training_type'])) ?></span></td>
  <td><?= e($c['trainer_name']) ?></td>
  <td class="fw-bold"><?= $c['price_usd'] > 0 ? e(money_usd($c['price_usd'])) . '<br>' : '' ?><?= $c['price_dzd'] > 0 ? e(money_dzd($c['price_dzd'])) : '' ?></td>
  <td class="small"><?php foreach (['usd' => '$', 'dzd' => 'DZD'] as $cur => $sym) if ($c["commission_type_$cur"]) echo e($c["commission_value_$cur"] . ($c["commission_type_$cur"] === 'percentage' ? '%' : " $sym")) . " ($sym)<br>"; ?></td>
  <td><?= status_badge($c['status']) ?></td>
  <td class="text-end text-nowrap">
    <?php if ($c['status'] === 'pending'): ?><button class="btn-soft btn-soft-ok" data-bs-toggle="modal" data-bs-target="#pubModal" data-id="<?= (int)$c['id'] ?>" data-title="<?= e($c['title_ar']) ?>" data-usd="<?= e($c['price_usd']) ?>" data-dzd="<?= e($c['price_dzd']) ?>"><i class="fa-solid fa-check"></i> <?= e(d('مراجعة ونشر', 'Review & publish')) ?></button>
      <?= action_btn('', ['act' => 'reject', 'course_id' => $c['id']], 'btn-soft-warn', d('رفض هذه الدورة؟', 'Reject this course?'), 'fa-xmark') ?><?php endif; ?>
    <?php if ($c['status'] === 'published'): ?><button class="btn-soft btn-soft-info ai-tr" data-id="<?= (int)$c['id'] ?>" title="<?= e(d('ترجمة تلقائية للغات الأخرى', 'AI-translate to other languages')) ?>"><i class="fa-solid fa-language"></i> AI</button>
      <?= action_btn('', ['act' => 'unpublish', 'course_id' => $c['id']], 'btn-soft-warn', d('إلغاء النشر؟', 'Unpublish?'), 'fa-eye-slash') ?><?php endif; ?>
    <?php if ($isSuperAdmin) echo action_btn('', ['act' => 'delete', 'course_id' => $c['id']], 'btn-soft-bad', d('حذف نهائي؟ سيتم حذف الدروس والبيانات المرتبطة.', 'Delete permanently?'), 'fa-trash'); ?></td></tr><?php endforeach; ?>
<?php if (!$courses): ?><tr><td colspan="6" class="empty"><i class="fa-solid fa-photo-film"></i><?= e(t('no_courses')) ?></td></tr><?php endif; ?>
</tbody></table></div></div>

<div class="modal fade" id="pubModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="publish"><input type="hidden" name="course_id" id="pId">
  <div class="modal-header"><h5 class="modal-title fw-bold text-success"><i class="fa-solid fa-check-double"></i> <?= e(d('الموافقة وتحديد عمولة المدرب', 'Approve & set trainer commission')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><h6 class="text-center fw-bold mb-3" id="pTitle"></h6>
    <?php foreach (['usd' => ['$', 'bg-g1'], 'dzd' => [d('د.ج', 'DZD'), 'bg-g2']] as $cur => [$sym, $_]): ?>
    <div class="ai-box mb-3" id="sec_<?= $cur ?>" style="display:none"><div class="fw-bold mb-2"><?= e(d('عمولة', 'Commission')) ?> (<?= $sym ?>) — <?= e(t('price')) ?>: <span id="lp_<?= $cur ?>"></span></div>
      <div class="row g-2"><div class="col-6"><select name="comm_type_<?= $cur ?>" class="form-select"><option value="percentage"><?= e(d('نسبة مئوية %', 'Percentage %')) ?></option><option value="fixed"><?= e(d('مبلغ ثابت', 'Fixed amount')) ?></option></select></div>
      <div class="col-6"><input type="number" step="0.01" min="0" name="comm_value_<?= $cur ?>" class="form-control" placeholder="<?= e(d('القيمة', 'Value')) ?>"></div></div></div><?php endforeach; ?></div>
  <div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(d('تأكيد ونشر الدورة', 'Confirm & publish')) ?></button></div></form></div></div>
<?php
$footExtra = '<script>pubModal.addEventListener("show.bs.modal",function(e){var b=e.relatedTarget;pId.value=b.dataset.id;pTitle.textContent=b.dataset.title;
["usd","dzd"].forEach(function(c){var v=parseFloat(b.dataset[c]||0);document.getElementById("sec_"+c).style.display=v>0?"block":"none";document.getElementById("lp_"+c).textContent=v})});
document.querySelectorAll(".ai-tr").forEach(function(b){b.addEventListener("click",function(){var self=this;AI_ASK("../api/ai_tools.php",{action:"translate_course",course_id:self.dataset.id},function(r){alert(r.ok?' . json_encode(d('تمت الترجمة وحفظها: ', 'Translated & saved: ')) . '+r.saved:(r.message||"error"))},self)})});</script>';
require '../includes/partials/dash_footer.php';
