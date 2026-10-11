<?php
require_once 'includes/init.php';
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$row = null; $error = ''; $done = false;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $st = $pdo->prepare("SELECT id, user_id FROM password_resets WHERE token_hash=? AND used=0 AND expires_at > NOW() LIMIT 1");
    $st->execute([hash('sha256', $token)]); $row = $st->fetch();
}
if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $pw = (string)($_POST['password'] ?? '');
    if ($pw !== ($_POST['confirm_password'] ?? '')) $error = t('err_match');
    elseif (!strong_password($pw)) $error = t('err_weak_pw');
    else {
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($pw, PASSWORD_DEFAULT), $row['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used=1 WHERE user_id=?")->execute([$row['user_id']]);
        audit('password_reset', 'user ' . $row['user_id']);
        $done = true;
    }
}
$pageTitle = t('reset_title');
require 'includes/partials/auth_top.php';
?>
    <h1 class="h3 fw-bold text-center"><?= e(t('reset_title')) ?></h1>
    <?php if ($done): ?><div class="alert alert-success mt-3"><?= e(t('reset_ok')) ?></div><a class="btn-aramio w-100 text-center d-block" href="login.php"><?= e(t('login')) ?></a>
    <?php elseif (!$row): ?><div class="alert alert-danger mt-3"><?= e(t('reset_invalid')) ?></div><a href="forgot_password.php"><?= e(t('forgot_password')) ?></a>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="password" name="password" class="form-control mb-3" required minlength="8" placeholder="<?= e(t('password')) ?>" dir="ltr" autocomplete="new-password">
        <input type="password" name="confirm_password" class="form-control mb-3" required placeholder="<?= e(t('confirm_password')) ?>" dir="ltr" autocomplete="new-password">
        <button class="btn-aramio w-100 py-2"><?= e(t('save')) ?></button></form>
    <?php endif; ?>
<?php require 'includes/partials/auth_bottom.php';
