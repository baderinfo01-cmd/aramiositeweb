<?php
require_once 'admin_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $act = $_POST['act'] ?? '';
    if ($act === 'home') { foreach (['stat_courses', 'stat_students', 'stat_trainers', 'stat_consultations'] as $k) setSetting($k, (string)max(0, (int)($_POST[$k] ?? 0))); flash('ok', d('تم تحديث أرقام الصفحة الرئيسية.', 'Homepage numbers updated.')); }
    elseif ($act === 'add') { $pdo->prepare("INSERT INTO statistics (title_ar, count_number, icon) VALUES (?,?,?)")->execute([mb_substr(trim((string)$_POST['title']), 0, 100), (int)$_POST['count'], preg_replace('/[^a-z0-9\- ]/i', '', (string)$_POST['icon']) ?: 'fa-star']); flash('ok', d('تمت الإضافة.', 'Added.')); }
    elseif ($act === 'delete') { $pdo->prepare("DELETE FROM statistics WHERE id=?")->execute([(int)$_POST['id']]); flash('ok', d('تم الحذف.', 'Deleted.')); }
    redirect('statistics.php');
}
$stats = $pdo->query("SELECT * FROM statistics ORDER BY id")->fetchAll();
$dashTitle = d('الإحصائيات المعروضة للزوار', 'Public statistics');
require '../includes/partials/dash_header.php';
?>
<div class="row g-3"><div class="col-lg-6"><div class="dcard"><div class="dcard-h"><?= e(d('أرقام الصفحة الرئيسية', 'Homepage counters')) ?></div><form method="post" class="dcard-b"><?= csrf_field() ?><input type="hidden" name="act" value="home"><div class="row g-3">
<?php foreach (['stat_courses' => t('stats_courses'), 'stat_students' => t('stats_students'), 'stat_trainers' => t('stats_trainers'), 'stat_consultations' => t('stats_cons')] as $k => $lb): ?>
  <div class="col-6"><label class="form-label"><?= e($lb) ?></label><input type="number" min="0" name="<?= $k ?>" value="<?= (int)getSetting($pdo, $k) ?>" class="form-control"></div><?php endforeach; ?>
  <div class="col-12"><button class="btn-aramio w-100"><?= e(t('save')) ?></button></div></div></form></div></div>
<div class="col-lg-6"><div class="dcard"><div class="dcard-h"><?= e(d('إحصائيات إضافية', 'Extra statistics')) ?></div><div class="dcard-b">
  <form method="post" class="row g-2 mb-3"><?= csrf_field() ?><input type="hidden" name="act" value="add"><div class="col-5"><input name="title" class="form-control" required placeholder="<?= e(d('العنوان', 'Title')) ?>"></div><div class="col-3"><input type="number" name="count" class="form-control" required></div><div class="col-4"><input name="icon" class="form-control" dir="ltr" placeholder="fa-users"></div><div class="col-12"><button class="btn-soft btn-soft-ok w-100 py-2"><?= e(t('save')) ?></button></div></form>
  <?php foreach ($stats as $s): ?><div class="d-flex justify-content-between align-items-center border-top py-2"><span><i class="fa-solid <?= e($s['icon']) ?>"></i> <?= e($s['title_ar']) ?> — <b><?= (int)$s['count_number'] ?></b></span><?= action_btn('', ['act' => 'delete', 'id' => $s['id']], 'btn-soft-bad', '?', 'fa-trash') ?></div><?php endforeach; ?></div></div></div></div>
<?php require '../includes/partials/dash_footer.php';
