<?php
require_once 'includes/init.php';
if (empty($_SESSION['temp_email'])) redirect('login.php');
$email = $_SESSION['temp_email'];
$error = ''; $info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    if (isset($_POST['resend'])) {
        if (rate_limit('resend_code', 3, 900)) {
            $code = (string)random_int(100000, 999999);
            $pdo->prepare("UPDATE users SET verification_code=? WHERE email=? AND is_verified=0")->execute([$code, $email]);
            send_mail($email, $siteName . ' — ' . t('verify_email_title'), '<p style="font-size:32px;letter-spacing:6px;font-weight:bold">' . e($code) . '</p>');
            if (app_config('debug')) $_SESSION['debug_code'] = $code;
            $info = t('verify_resent');
        } else $error = t('err_too_many');
    } elseif (!rate_limit('verify_code', 8, 900)) {
        $error = t('err_too_many');
    } else {
        $st = $pdo->prepare("SELECT id, full_name, verification_code FROM users WHERE email=? AND is_verified=0 LIMIT 1");
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && $u['verification_code'] && hash_equals((string)$u['verification_code'], trim((string)($_POST['code'] ?? '')))) {
            $pdo->prepare("UPDATE users SET is_verified=1, verification_code=NULL WHERE id=?")->execute([$u['id']]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$u['id']; $_SESSION['user_role'] = 'trainee'; $_SESSION['user_name'] = $u['full_name'];
            unset($_SESSION['temp_email'], $_SESSION['debug_code']);
            notify_user((int)$u['id'], t('welcome_back'), $siteName);
            redirect('trainee/index.php');
        }
        $error = t('verify_code_wrong');
    }
}
$pageTitle = t('verify_email_title');
require 'includes/partials/auth_top.php';
?>
    <div class="text-center"><i class="fa-solid fa-envelope-circle-check fa-4x mb-3" style="color:var(--emerald)"></i><h1 class="h3 fw-bold"><?= e(t('verify_email_title')) ?></h1>
    <p class="text-muted"><?= e(t('verify_code_sent')) ?><br><b dir="ltr"><?= e($email) ?></b></p></div>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="alert alert-success"><?= e($info) ?></div><?php endif; ?>
    <?php if (app_config('debug') && !empty($_SESSION['debug_code'])): ?><div class="alert alert-info small"><b>DEBUG:</b> <code><?= e($_SESSION['debug_code']) ?></code></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <input name="code" class="form-control form-control-lg text-center fw-bold mb-3" maxlength="6" inputmode="numeric" pattern="\d{6}" dir="ltr" placeholder="000000" autocomplete="one-time-code" required style="letter-spacing:8px;font-size:1.8rem">
      <button class="btn-aramio w-100 py-2" type="submit"><?= e(t('verify_btn_confirm')) ?></button></form>
    <form method="post" class="text-center mt-3"><?= csrf_field() ?><button class="btn btn-link" name="resend" value="1"><?= e(t('verify_resend')) ?></button></form>
<?php require 'includes/partials/auth_bottom.php';
