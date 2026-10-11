<?php
require_once 'includes/init.php';

function safe_next(?string $n): string {
    // نسمح فقط بمسارات نسبية داخل الموقع
    if ($n && preg_match('#^[a-zA-Z0-9_\-/]+\.php(\?[a-zA-Z0-9_=&%\-]*)?$#', $n)) return $n;
    return '';
}
function role_home(string $role): string {
    return $role === 'admin' || $role === 'staff' ? 'admin/index.php' : ($role === 'trainer' ? 'trainer/index.php' : 'trainee/index.php');
}
if ($me) redirect(role_home($me['role']));

$error = '';
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!rate_limit('login_ip', 10, 600) || !rate_limit('login_' . strtolower($email), 6, 600)) {
        $error = t('err_too_many');
    } else {
        $st = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && password_verify($password, $u['password'])) {
            if ((int)$u['status'] === 0) {
                $error = t('err_suspended');
            } elseif ((int)$u['is_verified'] === 0 && $u['role'] === 'trainee') {
                $_SESSION['temp_email'] = $u['email'];
                redirect('verify.php');
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$u['id'];
                $_SESSION['user_role'] = $u['role'];
                $_SESSION['user_name'] = $u['full_name'];
                if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
                    $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
                }
                audit('login', $u['email']);
                redirect($next && $u['role'] === 'trainee' ? $next : role_home($u['role']));
            }
        } else {
            $error = t('err_invalid');
            audit('login_failed', $email);
        }
    }
}
$pageTitle = t('login'); $sideTitle = 'Aramio Smart Global';
require 'includes/partials/auth_top.php';
?>
    <div class="text-center mb-4"><div class="stat-tile ico mx-auto" style="width:64px;height:64px;font-size:1.8rem"><i class="fa-solid fa-graduation-cap"></i></div>
      <h1 class="h3 fw-bold mt-3"><?= e(t('welcome_back')) ?></h1><p class="text-muted"><?= e(t('login_sub')) ?></p></div>
    <?php if ($error): ?><div class="alert alert-danger text-center"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate><?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="mb-3"><label class="form-label fw-bold small" for="em"><?= e(t('email')) ?></label><input id="em" type="email" name="email" class="form-control" required dir="ltr" autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label fw-bold small" for="pw"><?= e(t('password')) ?></label><input id="pw" type="password" name="password" class="form-control" required dir="ltr" autocomplete="current-password"></div>
      <div class="text-end mb-3"><a class="small" href="forgot_password.php"><?= e(t('forgot_password')) ?></a></div>
      <button class="btn-aramio w-100 py-2 fs-5" type="submit"><?= e(t('btn_login')) ?></button>
    </form>
    <p class="text-center mt-4 mb-0 text-muted"><?= e(t('no_account')) ?> <a class="fw-bold" href="register.php"><?= e(t('register_free')) ?></a></p>
<?php require 'includes/partials/auth_bottom.php';
