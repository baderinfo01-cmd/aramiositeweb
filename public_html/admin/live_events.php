<?php
require_once 'admin_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $id = (int)($_POST['lead_id'] ?? 0);
    if (($_POST['act'] ?? '') === 'status' && in_array($_POST['status'] ?? '', ['new', 'contacted', 'paid', 'canceled'], true)) { $pdo->prepare("UPDATE live_events_leads SET status=? WHERE id=?")->execute([$_POST['status'], $id]); flash('ok', d('تم تحديث الحالة.', 'Status updated.')); }
    elseif (($_POST['act'] ?? '') === 'delete') { admin_only(); $pdo->prepare("DELETE FROM live_events_leads WHERE id=?")->execute([$id]); flash('ok', d('تم الحذف.', 'Deleted.')); }
    redirect('live_events.php?' . http_build_query(array_intersect_key($_GET, ['marketer' => 1, 'status' => 1])));
}
$marketers = $pdo->query("SELECT marketer_code, COUNT(*) n FROM live_events_leads GROUP BY marketer_code ORDER BY n DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
$fm = $_GET['marketer'] ?? ''; $fs = $_GET['status'] ?? '';
$where = []; $par = [];
if ($fm !== '' && isset($marketers[$fm])) { $where[] = 'marketer_code=?'; $par[] = $fm; }
if (in_array($fs, ['new', 'contacted', 'paid', 'canceled'], true)) { $where[] = 'status=?'; $par[] = $fs; }
$st = $pdo->prepare("SELECT * FROM live_events_leads" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY created_at DESC"); $st->execute($par); $leads = $st->fetchAll();
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="event_leads.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, ['id', 'event', 'name', 'dob', 'phone', 'whatsapp', 'country', 'research_title', 'marketer', 'status', 'created_at']);
    foreach ($leads as $l) fputcsv($o, [$l['id'], $l['event_name'], $l['full_name_ar'], $l['dob'], $l['phone'], $l['whatsapp'], $l['country'], $l['research_title'], $l['marketer_code'], $l['status'], $l['created_at']]); exit;
}
$dashTitle = d('مسجلو الملتقيات الدولية', 'International event registrants');
require '../includes/partials/dash_header.php';
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <form class="d-flex gap-2 flex-wrap" method="get"><select name="marketer" class="form-select" style="width:auto" onchange="this.form.submit()"><option value=""><?= e(d('كل المسوقين', 'All marketers')) ?></option>
    <?php foreach ($marketers as $m => $n): ?><option value="<?= e($m) ?>" <?= $fm === $m ? 'selected' : '' ?>><?= e($m) ?> (<?= $n ?>)</option><?php endforeach; ?></select>
    <select name="status" class="form-select" style="width:auto" onchange="this.form.submit()"><option value=""><?= e(t('all')) ?></option><?php foreach (['new', 'contacted', 'paid', 'canceled'] as $s): ?><option value="<?= $s ?>" <?= $fs === $s ? 'selected' : '' ?>><?= e(strip_tags(status_badge($s))) ?></option><?php endforeach; ?></select></form>
  <a class="btn-soft btn-soft-ok py-2" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="fa-solid fa-file-csv"></i> CSV</a></div>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(t('name')) ?></th><th><?= e(t('country')) ?></th><th><?= e(d('التواصل', 'Contact')) ?></th><th><?= e(d('عنوان البحث', 'Research title')) ?></th><th><?= e(d('المسوّق', 'Marketer')) ?></th><th><?= e(t('status')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($leads as $l): ?><tr class="<?= $l['status'] === 'new' ? 'bg-warning bg-opacity-10' : '' ?>">
  <td><b><?= e($l['full_name_ar']) ?></b><div class="small text-muted"><?= e($l['dob'] ?: '') ?> · <?= e(substr($l['created_at'], 0, 10)) ?></div></td><td><?= e(country_flag((string)$l['country'])) ?> <?= e($l['country']) ?></td>
  <td class="small" dir="ltr"><?= e($l['phone']) ?><br><a target="_blank" href="<?= e(wa_link($l['whatsapp'], 'مرحباً ' . $l['full_name_ar'] . '، بخصوص تسجيلكم في ' . $l['event_name'])) ?>"><i class="fa-brands fa-whatsapp"></i> <?= e($l['whatsapp']) ?></a></td>
  <td class="small" style="max-width:260px"><?= e($l['research_title']) ?></td><td><span class="st st-info"><?= e($l['marketer_code']) ?></span></td>
  <td><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="status"><input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>"><select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
    <?php foreach (['new' => '🆕 ' . d('جديد', 'New'), 'contacted' => '📞 ' . d('تم الاتصال', 'Contacted'), 'paid' => '💸 ' . d('دفع مؤكد', 'Paid'), 'canceled' => '❌ ' . d('ملغي', 'Canceled')] as $k => $lb): ?><option value="<?= $k ?>" <?= $l['status'] === $k ? 'selected' : '' ?>><?= e($lb) ?></option><?php endforeach; ?></select></form></td>
  <td class="text-end"><?php if ($isSuperAdmin) echo action_btn('', ['act' => 'delete', 'lead_id' => $l['id']], 'btn-soft-bad', d('حذف؟', 'Delete?'), 'fa-trash'); ?></td></tr><?php endforeach; ?>
<?php if (!$leads): ?><tr><td colspan="7" class="empty"><i class="fa-solid fa-earth-asia"></i><?= e(d('لا يوجد مسجلون', 'No registrants')) ?></td></tr><?php endif; ?></tbody></table></div></div>
<?php require '../includes/partials/dash_footer.php';
