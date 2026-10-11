<?php
require_once 'includes/init.php';
$msg = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    if (!rate_limit('forgot', 5, 3600)) $error = t('err_too_many');
    else {
        $st = $pdo->prepare("SELECT id FROM users WHERE email=? AND status=1 LIMIT 1"); $st->execute([$email]);
        if ($uid = $st->fetchColumn()) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")->execute([$uid, hash('sha256', $token)]);
            $link = base_url('reset_password.php?token=' . $token);
            send_mail($email, $siteName . ' — ' . t('forgot_title'), '<p><a href="' . e($link) . '">' . e($link) . '</a></p>');
            if (app_config('debug')) $_SESSION['debug_reset'] = $link;
        }
        $msg = t('forgot_sent'); // نفس الرد دائمًا لمنع كشف البريد المسجّل
    }
}
$pageTitle = t('forgot_title');
require 'includes/partials/auth_top.php';
?>
    <h1 class="h3 fw-bold text-center"><?= e(t('forgot_title')) ?></h1><p class="text-muted text-center"><?= e(t('forgot_desc')) ?></p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
    <?php if (app_config('debug') && !empty($_SESSION['debug_reset'])): ?><div class="alert alert-info small"><b>DEBUG:</b> <a href="<?= e($_SESSION['debug_reset']) ?>">reset link</a></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="email" name="email" class="form-control mb-3" required dir="ltr" placeholder="<?= e(t('email')) ?>"><button class="btn-aramio w-100 py-2"><?= e(t('send')) ?></button></form>
    <p class="text-center mt-3"><a href="login.php"><?= e(t('back')) ?></a></p>
<?php require 'includes/partials/auth_bottom.php';
