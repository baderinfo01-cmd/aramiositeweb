<?php
require_once 'includes/init.php';
if ($me) redirect('login.php');

function send_verification_code(string $email, string $code): void {
    global $siteName;
    send_mail($email, $siteName . ' — ' . t('verify_email_title'),
        '<div style="font-family:Arial,sans-serif;direction:' . SUPPORTED_LANGS[$GLOBALS['lang']]['dir'] . '"><h2>' . e($siteName) . '</h2><p>' . e(t('verify_code_sent')) . ' ' . e($email) . '</p><p style="font-size:32px;letter-spacing:6px;font-weight:bold">' . e($code) . '</p></div>');
}

$error = '';
$f = ['first_name' => '', 'last_name' => '', 'phone' => '', 'whatsapp_number' => '', 'country' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['confirm_password'] ?? '');
    if (!rate_limit('register', 8, 3600)) $error = t('err_too_many');
    elseif ($f['first_name'] === '' || $f['email'] === '' || $f['country'] === '') $error = t('err_required');
    elseif (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $error = t('err_email');
    elseif ($pw !== $pw2) $error = t('err_match');
    elseif (!strong_password($pw)) $error = t('err_weak_pw');
    else {
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ?"); $st->execute([$f['email']]);
        if ($st->fetch()) $error = t('err_exists');
        else {
            $code = (string)random_int(100000, 999999);
            $pdo->prepare("INSERT INTO users (full_name, last_name, phone, whatsapp_number, country, email, password, role, verification_code, is_verified) VALUES (?,?,?,?,?,?,?,'trainee',?,0)")
                ->execute([mb_substr($f['first_name'], 0, 100), mb_substr($f['last_name'], 0, 100), mb_substr($f['phone'], 0, 20), mb_substr($f['whatsapp_number'], 0, 20), mb_substr($f['country'], 0, 100), $f['email'], password_hash($pw, PASSWORD_DEFAULT), $code]);
            send_verification_code($f['email'], $code);
            $_SESSION['temp_email'] = $f['email'];
            if (app_config('debug')) $_SESSION['debug_code'] = $code; // يظهر فقط عند debug=true محليًا
            audit('register', $f['email']);
            redirect('verify.php');
        }
    }
}
$pageTitle = t('register'); $sideTitle = t('reg_title');
require 'includes/partials/auth_top.php';
?>
    <h1 class="h3 fw-bold text-center"><?= e(t('reg_title')) ?></h1><p class="text-muted text-center"><?= e(t('reg_sub')) ?></p>
    <?php if ($error): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate><?= csrf_field() ?><div class="row g-3">
      <div class="col-6"><label class="form-label small fw-bold"><?= e(t('first_name')) ?> *</label><input name="first_name" class="form-control" required value="<?= e($f['first_name']) ?>"></div>
      <div class="col-6"><label class="form-label small fw-bold"><?= e(t('last_name')) ?></label><input name="last_name" class="form-control" value="<?= e($f['last_name']) ?>"></div>
      <div class="col-6"><label class="form-label small fw-bold"><?= e(t('phone')) ?></label><input name="phone" class="form-control" dir="ltr" value="<?= e($f['phone']) ?>"></div>
      <div class="col-6"><label class="form-label small fw-bold"><?= e(t('whatsapp')) ?></label><input name="whatsapp_number" class="form-control" dir="ltr" value="<?= e($f['whatsapp_number']) ?>"></div>
      <div class="col-12"><label class="form-label small fw-bold"><?= e(t('country')) ?> *</label><select name="country" class="form-select" required><option value=""><?= e(t('select_country')) ?></option>
        <?php foreach (countries_list() as $ar => $en): ?><option value="<?= e($ar) ?>" <?= $f['country'] === $ar ? 'selected' : '' ?>><?= e(country_label($ar)) ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><label class="form-label small fw-bold"><?= e(t('email')) ?> *</label><input type="email" name="email" class="form-control" required dir="ltr" value="<?= e($f['email']) ?>"></div>
      <div class="col-6"><label class="form-label small fw-bold"><?= e(t('password')) ?> *</label><input type="password" name="password" class="form-control" required minlength="8" dir="ltr" autocomplete="new-password"></div>
      <div class="col-6"><label class="form-label small fw-bold"><?= e(t('confirm_password')) ?> *</label><input type="password" name="confirm_password" class="form-control" required dir="ltr" autocomplete="new-password"></div>
      <div class="col-12"><button class="btn-aramio w-100 py-2 fs-5" type="submit"><?= e(t('btn_register')) ?></button></div></div></form>
    <p class="text-center mt-3 mb-0 text-muted"><?= e(t('have_account')) ?> <a class="fw-bold" href="login.php"><?= e(t('login')) ?></a></p>
<?php require 'includes/partials/auth_bottom.php';
