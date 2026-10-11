<?php
require_once 'includes/init.php';
$ok = false; $error = ''; $v = ['name' => '', 'email' => '', 'phone' => '', 'message' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
    if (!empty($_POST['website'])) { $ok = true; } // honeypot للروبوتات
    elseif (!rate_limit('contact', 5, 3600)) $error = t('err_too_many');
    elseif ($v['name'] === '' || $v['message'] === '') $error = t('err_required');
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $error = t('err_email');
    else {
        $pdo->prepare("INSERT INTO contact_messages (full_name, email, phone, message) VALUES (?,?,?,?)")
            ->execute([mb_substr($v['name'], 0, 100), mb_substr($v['email'], 0, 100), mb_substr($v['phone'], 0, 20), mb_substr($v['message'], 0, 4000)]);
        $ok = true; $v = array_map(fn() => '', $v);
    }
}
$wa = preg_replace('/\D/', '', getSetting($pdo, 'whatsapp_number') ?: $contact_phone);
$active = 'contact'; $pageTitle = t('contact'); $metaDesc = t('contact_sub');
require 'includes/partials/header.php';
?>
<section class="page-hero"><div class="container"><h1><?= e(t('contact_title')) ?></h1><p class="opacity-75 mb-0"><?= e(t('contact_sub')) ?></p></div></section>
<section class="section"><div class="container"><div class="row g-4">
  <div class="col-lg-5"><div class="card-x p-4 h-100">
    <h2 class="h5 fw-bold mb-4"><?= e(t('contact')) ?></h2>
    <ul class="list-unstyled d-grid gap-3">
      <li class="d-flex gap-3"><span class="stat-tile ico m-0" style="width:46px;height:46px;font-size:1.1rem"><i class="fa-solid fa-envelope"></i></span><div><small class="text-muted d-block"><?= e(t('email')) ?></small><a href="mailto:<?= e($contact_email) ?>"><?= e($contact_email) ?></a></div></li>
      <?php if ($contact_phone): ?><li class="d-flex gap-3"><span class="stat-tile ico m-0" style="width:46px;height:46px;font-size:1.1rem"><i class="fa-solid fa-phone"></i></span><div><small class="text-muted d-block"><?= e(t('phone')) ?></small><span dir="ltr"><?= e($contact_phone) ?></span></div></li><?php endif; ?>
    </ul>
    <?php if ($wa): ?><a class="btn w-100 rounded-pill text-white fw-bold" style="background:#25d366" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a><?php endif; ?>
  </div></div>
  <div class="col-lg-7"><div class="card-x p-4">
    <?php if ($ok): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e(t('contact_ok')) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-bold small"><?= e(t('name')) ?> *</label><input name="name" class="form-control" required value="<?= e($v['name']) ?>"></div>
        <div class="col-md-6"><label class="form-label fw-bold small"><?= e(t('phone')) ?></label><input name="phone" class="form-control" dir="ltr" value="<?= e($v['phone']) ?>"></div>
        <div class="col-12"><label class="form-label fw-bold small"><?= e(t('email')) ?> *</label><input type="email" name="email" class="form-control" dir="ltr" required value="<?= e($v['email']) ?>"></div>
        <div class="col-12"><label class="form-label fw-bold small"><?= e(t('message')) ?> *</label><textarea name="message" rows="5" class="form-control" required maxlength="4000"><?= e($v['message']) ?></textarea></div>
        <div class="col-12"><button class="btn-aramio px-5 py-2"><i class="fa-solid fa-paper-plane"></i> <?= e(t('send')) ?></button></div>
      </div></form>
  </div></div>
</div></div></section>
<?php require 'includes/partials/footer.php';
