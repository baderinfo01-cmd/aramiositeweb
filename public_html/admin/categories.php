<?php
require_once 'admin_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $act = $_POST['act'] ?? '';
    $ar = trim((string)($_POST['name_ar'] ?? '')); $en = trim((string)($_POST['name_en'] ?? '')); $st = (int)(($_POST['status'] ?? 1) ? 1 : 0);
    if ($act === 'add' || $act === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $dup = $pdo->prepare("SELECT id FROM categories WHERE (name_ar = ? OR name_en = ?) AND id <> ?"); $dup->execute([$ar, $en, $id]);
        if ($ar === '' || $en === '') flash('err', t('err_required'));
        elseif ($dup->fetch()) flash('err', d('هذا القسم موجود مسبقًا!', 'This category already exists!'));
        elseif ($act === 'add') { $pdo->prepare("INSERT INTO categories (name_ar, name_en, status) VALUES (?,?,?)")->execute([$ar, $en, $st]); flash('ok', d('تمت إضافة القسم.', 'Category added.')); }
        else { $pdo->prepare("UPDATE categories SET name_ar=?, name_en=?, status=? WHERE id=?")->execute([$ar, $en, $st, $id]); flash('ok', d('تم تحديث القسم.', 'Category updated.')); }
    } elseif ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $n = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id=?"); $n->execute([$id]); $n = (int)$n->fetchColumn();
        if ($n > 0) flash('err', d("لا يمكن الحذف: يوجد ($n) دورة في هذا القسم.", "Cannot delete: $n course(s) use this category."));
        else { $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]); flash('ok', d('تم حذف القسم.', 'Category deleted.')); }
    }
    redirect('categories.php');
}
$cats = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM courses WHERE category_id=c.id) AS n FROM categories c ORDER BY c.id DESC")->fetchAll();
$dashTitle = d('تصنيفات الدورات', 'Course categories');
require '../includes/partials/dash_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><span class="text-muted"><?= count($cats) ?> <?= e(d('تصنيف', 'categories')) ?></span>
  <button class="btn-aramio" data-bs-toggle="modal" data-bs-target="#catModal" data-mode="add"><i class="fa-solid fa-plus"></i> <?= e(d('إضافة تصنيف', 'Add category')) ?></button></div>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th>#</th><th>العربية</th><th>English</th><th><?= e(d('الدورات', 'Courses')) ?></th><th><?= e(t('status')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($cats as $c): ?><tr><td><?= (int)$c['id'] ?></td><td class="fw-bold"><?= e($c['name_ar']) ?></td><td><?= e($c['name_en']) ?></td><td><?= (int)$c['n'] ?></td>
  <td><?= $c['status'] ? '<span class="st st-ok">' . e(d('نشط', 'Active')) . '</span>' : '<span class="st st-mute">' . e(d('مخفي', 'Hidden')) . '</span>' ?></td>
  <td class="text-end text-nowrap"><button class="btn-soft btn-soft-info" data-bs-toggle="modal" data-bs-target="#catModal" data-mode="edit" data-id="<?= (int)$c['id'] ?>" data-ar="<?= e($c['name_ar']) ?>" data-en="<?= e($c['name_en']) ?>" data-st="<?= (int)$c['status'] ?>"><i class="fa-solid fa-pen"></i></button>
  <?= action_btn('', ['act' => 'delete', 'id' => $c['id']], 'btn-soft-bad', d('حذف هذا القسم؟', 'Delete this category?'), 'fa-trash') ?></td></tr><?php endforeach; ?>
<?php if (!$cats): ?><tr><td colspan="6" class="empty"><i class="fa-solid fa-tags"></i><?= e(d('لا توجد تصنيفات', 'No categories')) ?></td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="modal fade" id="catModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" id="cAct" value="add"><input type="hidden" name="id" id="cId">
  <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(d('تصنيف', 'Category')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body d-grid gap-3"><div><label class="form-label">الاسم بالعربية</label><input name="name_ar" id="cAr" class="form-control" required></div><div><label class="form-label">Name (English)</label><input name="name_en" id="cEn" class="form-control" required dir="ltr"></div>
    <div><label class="form-label"><?= e(t('status')) ?></label><select name="status" id="cSt" class="form-select"><option value="1"><?= e(d('نشط', 'Active')) ?></option><option value="0"><?= e(d('مخفي', 'Hidden')) ?></option></select></div></div>
  <div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(t('save')) ?></button></div></form></div></div>
<?php
$footExtra = '<script>document.getElementById("catModal").addEventListener("show.bs.modal",function(e){var b=e.relatedTarget,ed=b.dataset.mode==="edit";cAct.value=ed?"edit":"add";cId.value=ed?b.dataset.id:"";cAr.value=ed?b.dataset.ar:"";cEn.value=ed?b.dataset.en:"";cSt.value=ed?b.dataset.st:"1"});</script>';
require '../includes/partials/dash_footer.php';
