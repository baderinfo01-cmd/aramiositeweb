<?php
require_once 'admin_init.php';
admin_only();
$roles = ['admin' => d('مدير', 'Admin'), 'staff' => d('موظف', 'Staff'), 'trainer' => d('مدرب', 'Trainer'), 'trainee' => d('متدرب', 'Trainee')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $act = $_POST['act'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($act === 'add') {
        $name = trim((string)$_POST['full_name']); $email = trim((string)$_POST['email']); $pw = (string)$_POST['password']; $role = $_POST['role'] ?? 'trainee';
        $dup = $pdo->prepare("SELECT id FROM users WHERE email=?"); $dup->execute([$email]);
        if (!isset($roles[$role]) || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('err', t('err_required'));
        elseif ($dup->fetch()) flash('err', t('err_exists'));
        elseif (!strong_password($pw)) flash('err', t('err_weak_pw'));
        else { $pdo->prepare("INSERT INTO users (full_name, email, password, role, is_verified) VALUES (?,?,?,?,1)")->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), $role]); audit('user_add', "$email ($role)"); flash('ok', d('تمت إضافة المستخدم.', 'User added.')); }
    } elseif ($act === 'edit') {
        $role = $_POST['role'] ?? 'trainee';
        if (!isset($roles[$role])) { flash('err', 'role'); }
        elseif ($id === (int)$me['id'] && ($role !== 'admin' || (int)$_POST['status'] === 0)) flash('err', d('لا يمكنك تغيير صلاحية حسابك أو إيقافه.', 'You cannot demote or suspend your own account.'));
        else { $pdo->prepare("UPDATE users SET full_name=?, role=?, status=? WHERE id=?")->execute([trim((string)$_POST['full_name']), $role, (int)(!empty($_POST['status'])), $id]); audit('user_edit', "id $id -> $role"); flash('ok', d('تم تحديث المستخدم.', 'User updated.')); }
    } elseif ($act === 'delete') {
        if ($id === (int)$me['id']) flash('err', d('لا يمكنك حذف حسابك!', 'You cannot delete your own account!'));
        else { $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]); audit('user_delete', "id $id"); flash('ok', d('تم حذف المستخدم.', 'User deleted.')); }
    } elseif ($act === 'reset') {
        $tmp = substr(bin2hex(random_bytes(6)), 0, 10) . 'A1';
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($tmp, PASSWORD_DEFAULT), $id]);
        audit('user_pw_reset', "id $id");
        flash('warn', d('كلمة المرور المؤقتة (تظهر مرة واحدة): ', 'Temporary password (shown once): ') . $tmp);
    }
    redirect('users.php?' . http_build_query(array_intersect_key($_GET, ['role' => 1, 'q' => 1])));
}

$fRole = $_GET['role'] ?? ''; $fq = trim((string)($_GET['q'] ?? ''));
$where = []; $par = [];
if (isset($roles[$fRole])) { $where[] = 'role = ?'; $par[] = $fRole; }
if ($fq !== '') { $where[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)'; array_push($par, "%$fq%", "%$fq%", "%$fq%"); }
$st = $pdo->prepare("SELECT * FROM users" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY created_at DESC LIMIT 500"); $st->execute($par);
$users = $st->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="users.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, ['id', 'name', 'last_name', 'email', 'phone', 'whatsapp', 'country', 'role', 'status', 'created_at']);
    foreach ($users as $u) fputcsv($o, [$u['id'], $u['full_name'], $u['last_name'], $u['email'], $u['phone'], $u['whatsapp_number'], $u['country'], $u['role'], $u['status'], $u['created_at']]);
    exit;
}
$dashTitle = d('المستخدمون والصلاحيات', 'Users & roles');
require '../includes/partials/dash_header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <form class="d-flex gap-2 flex-wrap" method="get"><input name="q" value="<?= e($fq) ?>" class="form-control" placeholder="<?= e(t('search')) ?>..." style="min-width:220px"><select name="role" class="form-select" style="width:auto" onchange="this.form.submit()"><option value=""><?= e(t('all')) ?></option>
    <?php foreach ($roles as $k => $v): ?><option value="<?= $k ?>" <?= $fRole === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select><button class="btn-soft btn-soft-info"><i class="fa-solid fa-magnifying-glass"></i></button></form>
  <div class="d-flex gap-2"><a class="btn-soft btn-soft-ok py-2" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="fa-solid fa-file-csv"></i> CSV</a>
    <button class="btn-aramio" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fa-solid fa-user-plus"></i> <?= e(d('مستخدم جديد', 'New user')) ?></button></div></div>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(t('name')) ?></th><th><?= e(t('email')) ?></th><th><?= e(t('country')) ?></th><th><?= e(d('الصلاحية', 'Role')) ?></th><th><?= e(t('status')) ?></th><th><?= e(d('انضم', 'Joined')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($users as $u): ?><tr>
  <td><div class="d-flex align-items-center gap-2"><img src="<?= e(avatar_url($u['avatar'], $u['full_name'], '../')) ?>" width="38" height="38" class="rounded-circle" style="object-fit:cover" alt=""><b><?= e(trim($u['full_name'] . ' ' . $u['last_name'])) ?></b></div></td>
  <td dir="ltr" class="text-start small"><?= e($u['email']) ?><?= $u['whatsapp_number'] ? '<br><a target="_blank" href="' . e(wa_link($u['whatsapp_number'])) . '"><i class="fa-brands fa-whatsapp"></i> ' . e($u['whatsapp_number']) . '</a>' : '' ?></td>
  <td><?= e(country_flag((string)$u['country'])) ?> <?= e($u['country']) ?></td><td><span class="st st-info"><?= e($roles[$u['role']] ?? $u['role']) ?></span></td>
  <td><?= $u['status'] ? '<span class="st st-ok">' . e(d('نشط', 'Active')) . '</span>' : '<span class="st st-bad">' . e(d('موقوف', 'Suspended')) . '</span>' ?><?= !$u['is_verified'] ? ' <span class="st st-warn">' . e(d('غير موثق', 'Unverified')) . '</span>' : '' ?></td>
  <td class="small text-muted"><?= e(substr($u['created_at'], 0, 10)) ?></td>
  <td class="text-end text-nowrap"><button class="btn-soft btn-soft-info" data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?= (int)$u['id'] ?>" data-name="<?= e($u['full_name']) ?>" data-role="<?= e($u['role']) ?>" data-st="<?= (int)$u['status'] ?>"><i class="fa-solid fa-pen"></i></button>
    <?= action_btn('', ['act' => 'reset', 'id' => $u['id']], 'btn-soft-warn', d('توليد كلمة مرور مؤقتة لهذا المستخدم؟', 'Generate a temporary password?'), 'fa-key') ?>
    <?php if ((int)$u['id'] !== (int)$me['id']) echo action_btn('', ['act' => 'delete', 'id' => $u['id']], 'btn-soft-bad', d('حذف نهائي؟ لا يمكن التراجع.', 'Delete permanently?'), 'fa-trash'); ?></td></tr><?php endforeach; ?>
<?php if (!$users): ?><tr><td colspan="7" class="empty"><i class="fa-solid fa-users"></i><?= e(d('لا نتائج', 'No results')) ?></td></tr><?php endif; ?>
</tbody></table></div></div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="add">
  <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(d('إضافة مستخدم', 'Add user')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body d-grid gap-3"><input name="full_name" class="form-control" required placeholder="<?= e(t('name')) ?>"><input type="email" name="email" class="form-control" dir="ltr" required placeholder="<?= e(t('email')) ?>">
    <input type="text" name="password" class="form-control" dir="ltr" required minlength="8" placeholder="<?= e(t('password')) ?>"><select name="role" class="form-select"><?php foreach ($roles as $k => $v): ?><option value="<?= $k ?>" <?= $k === 'trainer' ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(t('save')) ?></button></div></form></div></div>
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="edit"><input type="hidden" name="id" id="eId">
  <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(t('edit')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body d-grid gap-3"><input name="full_name" id="eName" class="form-control" required><select name="role" id="eRole" class="form-select"><?php foreach ($roles as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
    <select name="status" id="eSt" class="form-select"><option value="1"><?= e(d('نشط', 'Active')) ?></option><option value="0"><?= e(d('موقوف', 'Suspended')) ?></option></select></div>
  <div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(t('save')) ?></button></div></form></div></div>
<?php
$footExtra = '<script>editModal.addEventListener("show.bs.modal",function(e){var b=e.relatedTarget;eId.value=b.dataset.id;eName.value=b.dataset.name;eRole.value=b.dataset.role;eSt.value=b.dataset.st});</script>';
require '../includes/partials/dash_footer.php';
