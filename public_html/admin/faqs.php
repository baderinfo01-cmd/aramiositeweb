<?php
require_once 'admin_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $act = $_POST['act'] ?? '';
    if ($act === 'add') { $pdo->prepare("INSERT INTO faqs (question_ar, question_en, answer_ar, answer_en, sort_order) VALUES (?,?,?,?,?)")->execute([trim((string)$_POST['q_ar']), trim((string)$_POST['q_en']) ?: trim((string)$_POST['q_ar']), trim((string)$_POST['a_ar']), trim((string)$_POST['a_en']) ?: trim((string)$_POST['a_ar']), (int)$_POST['sort']]); flash('ok', d('تمت الإضافة.', 'Added.')); }
    elseif ($act === 'delete') { $pdo->prepare("DELETE FROM faqs WHERE id=?")->execute([(int)$_POST['id']]); flash('ok', d('تم الحذف.', 'Deleted.')); }
    redirect('faqs.php');
}
$faqs = $pdo->query("SELECT * FROM faqs ORDER BY sort_order, id")->fetchAll();
$dashTitle = d('الأسئلة الشائعة', 'FAQ'); require '../includes/partials/dash_header.php';
?>
<p class="text-muted small"><i class="fa-solid fa-circle-info"></i> <?= e(d('يستخدم المساعد الذكي هذه الأسئلة كمرجع للإجابة.', 'The AI assistant uses these answers as its knowledge base.')) ?></p>
<div class="row g-3"><div class="col-lg-5"><div class="dcard"><div class="dcard-h"><?= e(d('إضافة سؤال', 'Add question')) ?></div><form method="post" class="dcard-b d-grid gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="add">
  <input name="q_ar" class="form-control" required placeholder="السؤال (عربي)"><textarea name="a_ar" rows="3" class="form-control" required placeholder="الإجابة (عربي)"></textarea><input name="q_en" class="form-control" dir="ltr" placeholder="Question (English)"><textarea name="a_en" rows="3" class="form-control" dir="ltr" placeholder="Answer (English)"></textarea><input type="number" name="sort" value="10" class="form-control"><button class="btn-aramio"><?= e(t('save')) ?></button></form></div></div>
<div class="col-lg-7"><div class="dcard"><div class="dcard-b"><?php foreach ($faqs as $f): ?><div class="border-bottom pb-3 mb-3"><b><?= e($f['question_ar']) ?></b><div class="small text-muted"><?= e($f['answer_ar']) ?></div><div class="small mt-1" dir="ltr"><b><?= e($f['question_en']) ?></b> — <?= e($f['answer_en']) ?></div><?= action_btn('', ['act' => 'delete', 'id' => $f['id']], 'btn-soft-bad mt-2', '?', 'fa-trash') ?></div><?php endforeach; ?></div></div></div></div>
<?php require '../includes/partials/dash_footer.php';
