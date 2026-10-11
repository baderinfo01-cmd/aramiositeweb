<?php
/** صفحة الإعدادات الشخصية المشتركة (مدرب/متدرب) */
function profile_handle(): void {
    global $pdo, $me;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    require_post_csrf();
    $uid = (int)$me['id'];
    $cur = $pdo->prepare("SELECT * FROM users WHERE id=?"); $cur->execute([$uid]); $u = $cur->fetch();
    if (($_POST['act'] ?? '') === 'profile') {
        $avatar = $u['avatar'];
        if (!empty($_FILES['avatar']['name'])) {
            $n = save_upload('avatar', 'avatars', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 3145728);
            if ($n) { if ($avatar && $avatar !== 'default_avatar.png') { $old = dirname(__DIR__) . '/uploads/avatars/' . basename($avatar); if (is_file($old)) @unlink($old); } $avatar = $n; }
            else flash('warn', d('تعذّر رفع الصورة (JPG/PNG/WEBP حتى 3MB).', 'Avatar upload failed (JPG/PNG/WEBP ≤3MB).'));
        }
        $name = trim((string)$_POST['full_name']);
        if ($name === '') flash('err', t('err_required'));
        else {
            $pdo->prepare("UPDATE users SET full_name=?, last_name=?, phone=?, whatsapp_number=?, country=?, bio=?, avatar=? WHERE id=?")
                ->execute([mb_substr($name, 0, 100), mb_substr(trim((string)($_POST['last_name'] ?? '')), 0, 100), mb_substr(trim((string)$_POST['phone']), 0, 20), mb_substr(trim((string)$_POST['whatsapp_number']), 0, 20), mb_substr(trim((string)$_POST['country']), 0, 100), $u['role'] === 'trainer' ? trim((string)($_POST['bio'] ?? '')) : $u['bio'], $avatar, $uid]);
            $_SESSION['user_name'] = $name; flash('ok', d('تم تحديث ملفك الشخصي.', 'Profile updated.'));
        }
    } elseif (($_POST['act'] ?? '') === 'password') {
        $new = (string)$_POST['new_password'];
        if (!password_verify((string)$_POST['current_password'], $u['password'])) flash('err', d('كلمة المرور الحالية غير صحيحة.', 'Current password is incorrect.'));
        elseif ($new !== ($_POST['confirm_password'] ?? '')) flash('err', t('err_match'));
        elseif (!strong_password($new)) flash('err', t('err_weak_pw'));
        else { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $uid]); session_regenerate_id(true); audit('password_change'); flash('ok', d('تم تغيير كلمة المرور.', 'Password changed.')); }
    }
    redirect(basename($_SERVER['PHP_SELF']));
}
function profile_render(bool $withBio): void {
    global $pdo, $me;
    $s = $pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$me['id']]); $u = $s->fetch();
    ?>
<div class="row g-3"><div class="col-lg-7"><form method="post" enctype="multipart/form-data" class="dcard"><div class="dcard-h"><?= e(d('الملف الشخصي', 'Profile')) ?></div><div class="dcard-b"><?= csrf_field() ?><input type="hidden" name="act" value="profile"><div class="row g-3">
  <div class="col-12 d-flex align-items-center gap-3"><img src="<?= e(avatar_url($u['avatar'], $u['full_name'], '../')) ?>" width="72" height="72" class="rounded-circle" style="object-fit:cover" alt=""><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="form-control"></div>
  <div class="col-md-6"><label class="form-label"><?= e(t('first_name')) ?></label><input name="full_name" class="form-control" required value="<?= e($u['full_name']) ?>"></div><div class="col-md-6"><label class="form-label"><?= e(t('last_name')) ?></label><input name="last_name" class="form-control" value="<?= e($u['last_name']) ?>"></div>
  <div class="col-md-6"><label class="form-label"><?= e(t('phone')) ?></label><input name="phone" class="form-control" dir="ltr" value="<?= e($u['phone']) ?>"></div><div class="col-md-6"><label class="form-label">WhatsApp</label><input name="whatsapp_number" class="form-control" dir="ltr" value="<?= e($u['whatsapp_number']) ?>"></div>
  <div class="col-md-6"><label class="form-label"><?= e(t('country')) ?></label><select name="country" class="form-select"><?php foreach (countries_list() as $ar => $en): ?><option value="<?= e($ar) ?>" <?= $u['country'] === $ar ? 'selected' : '' ?>><?= e(country_label($ar)) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-6"><label class="form-label"><?= e(t('email')) ?></label><input class="form-control" dir="ltr" value="<?= e($u['email']) ?>" disabled></div>
  <?php if ($withBio): ?><div class="col-12"><label class="form-label d-flex justify-content-between"><span><?= e(d('النبذة المهنية (تظهر للزوار)', 'Professional bio (public)')) ?></span><button type="button" class="ai-btn" id="bioAi"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= e(d('حسّن بالذكاء الاصطناعي', 'Improve with AI')) ?></button></label><textarea name="bio" id="bio" rows="6" class="form-control"><?= e($u['bio']) ?></textarea></div><?php endif; ?>
  <div class="col-12"><button class="btn-aramio px-5"><?= e(t('save')) ?></button></div></div></div></form></div>
<div class="col-lg-5"><form method="post" class="dcard"><div class="dcard-h"><?= e(d('تغيير كلمة المرور', 'Change password')) ?></div><div class="dcard-b d-grid gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="password">
  <input type="password" name="current_password" class="form-control" required placeholder="<?= e(d('الحالية', 'Current')) ?>" autocomplete="current-password"><input type="password" name="new_password" class="form-control" required minlength="8" placeholder="<?= e(d('الجديدة', 'New')) ?>" autocomplete="new-password"><input type="password" name="confirm_password" class="form-control" required placeholder="<?= e(t('confirm_password')) ?>" autocomplete="new-password"><button class="btn-aramio"><?= e(t('save')) ?></button></div></form></div></div>
<?php }
