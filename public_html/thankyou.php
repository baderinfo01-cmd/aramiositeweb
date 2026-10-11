<?php
require_once 'includes/init.php';
$wa = preg_replace('/\D/', '', getSetting($pdo, 'whatsapp_number') ?: $contact_phone);
$pageTitle = t('thanks_title'); $noindex = true;
require 'includes/partials/header.php';
?>
<section class="section"><div class="container text-center" style="max-width:640px"><div class="card-x p-5" style="border-top:6px solid var(--emerald)">
  <i class="fa-solid fa-circle-check" style="font-size:5.5rem;color:var(--emerald)"></i>
  <h1 class="fw-bold mt-3"><?= e(t('thanks_title')) ?></h1><p class="text-muted fs-5"><?= e(t('thanks_msg')) ?></p>
  <?php if ($wa): ?><a class="btn btn-lg rounded-pill text-white fw-bold px-5 mb-3" style="background:#25d366" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a><br><?php endif; ?>
  <a href="index.php"><?= e(t('go_home')) ?></a></div></div></section>
<?php require 'includes/partials/footer.php';
