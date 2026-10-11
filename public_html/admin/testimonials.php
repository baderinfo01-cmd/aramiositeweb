<?php
require_once 'admin_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $act = $_POST['act'] ?? '';
    if ($act === 'add') { $r = max(1, min(5, (int)$_POST['rating'])); $pdo->prepare("INSERT INTO testimonials (student_name, course_name, feedback_text, rating) VALUES (?,?,?,?)")->execute([mb_substr(trim((string)$_POST['name']), 0, 100), mb_substr(trim((string)$_POST['course']), 0, 100), trim((string)$_POST['feedback']), $r]); flash('ok', d('تمت الإضافة.', 'Added.')); }
    elseif ($act === 'toggle') $pdo->prepare("UPDATE testimonials SET status = 1 - status WHERE id=?")->execute([(int)$_POST['id']]);
    elseif ($act === 'delete') { $pdo->prepare("DELETE FROM testimonials WHERE id=?")->execute([(int)$_POST['id']]); flash('ok', d('تم الحذف.', 'Deleted.')); }
    redirect('testimonials.php');
}
$tests = $pdo->query("SELECT * FROM testimonials ORDER BY id DESC")->fetchAll();
$dashTitle = d('آراء المتدربين', 'Testimonials');
require '../includes/partials/dash_header.php';
?>
<div class="row g-3"><div class="col-lg-4"><div class="dcard"><div class="dcard-h"><?= e(d('إضافة رأي', 'Add testimonial')) ?></div><form method="post" class="dcard-b d-grid gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="add">
  <input name="name" class="form-control" required placeholder="<?= e(t('name')) ?>"><input name="course" class="form-control" required placeholder="<?= e(d('الدورة', 'Course')) ?>"><textarea name="feedback" rows="4" class="form-control" required placeholder="<?= e(d('نص الرأي', 'Feedback')) ?>"></textarea>
  <select name="rating" class="form-select"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= str_repeat('★', $i) ?></option><?php endfor; ?></select><button class="btn-aramio"><?= e(t('save')) ?></button></form></div></div>
<div class="col-lg-8"><div class="row g-3"><?php foreach ($tests as $x): ?><div class="col-md-6"><div class="dcard h-100 <?= $x['status'] ? '' : 'opacity-50' ?>"><div class="dcard-b"><div class="text-warning"><?= stars((float)$x['rating']) ?></div><p class="my-2 small">“<?= e($x['feedback_text']) ?>”</p><b><?= e($x['student_name']) ?></b> <span class="text-muted small">— <?= e($x['course_name']) ?></span>
  <div class="mt-2"><?= action_btn('', ['act' => 'toggle', 'id' => $x['id']], 'btn-soft-warn', '', $x['status'] ? 'fa-eye' : 'fa-eye-slash') ?> <?= action_btn('', ['act' => 'delete', 'id' => $x['id']], 'btn-soft-bad', '?', 'fa-trash') ?></div></div></div></div><?php endforeach; ?></div></div></div>
<?php require '../includes/partials/dash_footer.php';
