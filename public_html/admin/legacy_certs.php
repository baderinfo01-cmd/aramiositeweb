<?php
require_once 'admin_init.php';
function cert_fields(): array { return array_map(fn($k) => trim((string)($_POST[$k] ?? '')), ['certificate_code', 'student_name', 'nationality', 'specialty', 'grade', 'issue_date']); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'add' || $act === 'edit') {
        [$code, $name, $nat, $spec, $grade, $date] = cert_fields(); $id = (int)($_POST['id'] ?? 0);
        $dup = $pdo->prepare("SELECT id FROM legacy_certificates WHERE certificate_code=? AND id<>?"); $dup->execute([$code, $id]);
        if ($code === '' || $name === '' || $spec === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) flash('err', t('err_required'));
        elseif ($dup->fetch()) flash('err', d('رقم الشهادة موجود مسبقًا!', 'Certificate ID already exists!'));
        elseif ($act === 'add') { $pdo->prepare("INSERT INTO legacy_certificates (certificate_code, student_name, nationality, specialty, grade, issue_date) VALUES (?,?,?,?,?,?)")->execute([$code, $name, $nat, $spec, $grade ?: '-', $date]); flash('ok', d('تمت إضافة الشهادة.', 'Certificate added.')); }
        else { $pdo->prepare("UPDATE legacy_certificates SET certificate_code=?, student_name=?, nationality=?, specialty=?, grade=?, issue_date=? WHERE id=?")->execute([$code, $name, $nat, $spec, $grade ?: '-', $date, $id]); flash('ok', d('تم تحديث الشهادة.', 'Certificate updated.')); }
    } elseif ($act === 'delete') { admin_only(); $pdo->prepare("DELETE FROM legacy_certificates WHERE id=?")->execute([(int)$_POST['id']]); flash('ok', d('تم الحذف.', 'Deleted.')); }
    elseif ($act === 'import' && !empty($_FILES['csv']['tmp_name'])) {
        // CSV: code,name,nationality,specialty,grade,date(YYYY-MM-DD)
        $h = fopen($_FILES['csv']['tmp_name'], 'r'); $ok = $skip = 0; $ins = $pdo->prepare("INSERT IGNORE INTO legacy_certificates (certificate_code, student_name, nationality, specialty, grade, issue_date) VALUES (?,?,?,?,?,?)");
        while (($r = fgetcsv($h)) !== false) { if (count($r) < 6 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($r[5]))) { $skip++; continue; } $ins->execute(array_map('trim', array_slice($r, 0, 6))); $ins->rowCount() ? $ok++ : $skip++; }
        fclose($h); flash('ok', d("تم استيراد $ok شهادة (تخطي $skip).", "Imported $ok (skipped $skip)."));
    }
    redirect('legacy_certs.php');
}
$q = trim((string)($_GET['q'] ?? ''));
$st = $pdo->prepare("SELECT * FROM legacy_certificates" . ($q !== '' ? " WHERE certificate_code LIKE ? OR student_name LIKE ?" : '') . " ORDER BY issue_date DESC, id DESC LIMIT 500"); $st->execute($q !== '' ? ["%$q%", "%$q%"] : []); $certs = $st->fetchAll();
$dashTitle = d('أرشيف الشهادات', 'Certificates archive');
require '../includes/partials/dash_header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3"><form class="d-flex gap-2" method="get"><input name="q" value="<?= e($q) ?>" class="form-control" placeholder="<?= e(t('search')) ?>..."><button class="btn-soft btn-soft-info"><i class="fa-solid fa-magnifying-glass"></i></button></form>
  <div class="d-flex gap-2"><button class="btn-soft btn-soft-info py-2" data-bs-toggle="modal" data-bs-target="#impModal"><i class="fa-solid fa-file-import"></i> CSV</button><button class="btn-aramio" data-bs-toggle="modal" data-bs-target="#cModal" data-mode="add"><i class="fa-solid fa-plus"></i> <?= e(d('إضافة شهادة', 'Add certificate')) ?></button></div></div>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(t('cert_id')) ?></th><th><?= e(t('name')) ?></th><th><?= e(t('cert_nat')) ?></th><th><?= e(t('cert_spec')) ?></th><th><?= e(t('cert_grade')) ?></th><th><?= e(t('date')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($certs as $c): ?><tr><td><code><?= e($c['certificate_code']) ?></code></td><td class="fw-bold"><?= e($c['student_name']) ?></td><td><?= e($c['nationality']) ?></td><td><?= e($c['specialty']) ?></td><td><?= e($c['grade']) ?></td><td><?= e($c['issue_date']) ?></td>
  <td class="text-end text-nowrap"><a class="btn-soft btn-soft-ok" target="_blank" href="../certificate.php?code=<?= urlencode($c['certificate_code']) ?>" title="<?= e(t('cert_print')) ?>"><i class="fa-solid fa-print"></i></a>
  <button class="btn-soft btn-soft-info" data-bs-toggle="modal" data-bs-target="#cModal" data-mode="edit" data-j="<?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>"><i class="fa-solid fa-pen"></i></button>
  <?php if ($isSuperAdmin) echo action_btn('', ['act' => 'delete', 'id' => $c['id']], 'btn-soft-bad', d('حذف؟', 'Delete?'), 'fa-trash'); ?></td></tr><?php endforeach; ?>
<?php if (!$certs): ?><tr><td colspan="7" class="empty"><i class="fa-solid fa-certificate"></i><?= e(d('لا شهادات', 'No certificates')) ?></td></tr><?php endif; ?></tbody></table></div></div>
<div class="modal fade" id="cModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" id="cAct" value="add"><input type="hidden" name="id" id="cId">
  <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(t('cert_id')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body d-grid gap-2"><input name="certificate_code" id="f_code" class="form-control" dir="ltr" required placeholder="<?= e(t('cert_id')) ?>"><input name="student_name" id="f_name" class="form-control" required placeholder="<?= e(t('name')) ?>"><input name="nationality" id="f_nat" class="form-control" placeholder="<?= e(t('cert_nat')) ?>">
    <input name="specialty" id="f_spec" class="form-control" required placeholder="<?= e(t('cert_spec')) ?>"><div class="row g-2"><div class="col-6"><input name="grade" id="f_grade" class="form-control" placeholder="<?= e(t('cert_grade')) ?>"></div><div class="col-6"><input type="date" name="issue_date" id="f_date" class="form-control" required></div></div></div>
  <div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(t('save')) ?></button></div></form></div></div>
<div class="modal fade" id="impModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" enctype="multipart/form-data" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="import">
  <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(d('استيراد من CSV', 'Import CSV')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p class="small text-muted"><?= e(d('الأعمدة بالترتيب: رقم الشهادة، الاسم، الجنسية، التخصص، الدرجة، التاريخ (YYYY-MM-DD). بدون صف عناوين.', 'Columns in order: code, name, nationality, specialty, grade, date (YYYY-MM-DD). No header row.')) ?></p><input type="file" name="csv" accept=".csv,text/csv" class="form-control" required></div>
  <div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(d('استيراد', 'Import')) ?></button></div></form></div></div>
<?php
$footExtra = '<script>cModal.addEventListener("show.bs.modal",function(e){var b=e.relatedTarget,ed=b.dataset.mode==="edit",j=ed?JSON.parse(b.dataset.j):{};cAct.value=ed?"edit":"add";cId.value=j.id||"";f_code.value=j.certificate_code||"";f_name.value=j.student_name||"";f_nat.value=j.nationality||"";f_spec.value=j.specialty||"";f_grade.value=j.grade||"";f_date.value=j.issue_date||""});</script>';
require '../includes/partials/dash_footer.php';
