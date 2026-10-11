<?php /** غلاف صفحات الدخول والتسجيل. المتغيرات: $pageTitle, $sideTitle, $sideDesc */
$noindex = true; $ai_on = false;
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="theme-color" content="#0b1c3f">
<title><?= e($pageTitle) ?> | <?= e($siteName) ?></title>
<link rel="icon" href="assets/logo-mobile.png">
<link rel="stylesheet" href="assets/vendor/fonts/fonts.css">
<link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap<?= $dir === 'rtl' ? '.rtl' : '' ?>.min.css">
<link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/app.css?v=2">
<script>(function(){try{var t=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t)}catch(e){}})();</script>
</head><body>
<div class="auth-shell"><div class="auth-card">
  <div class="auth-form">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <a href="index.php" class="text-muted" aria-label="home"><i class="fa-solid fa-house"></i></a>
      <div class="d-flex gap-2 align-items-center">
        <button type="button" class="btn btn-sm btn-light rounded-circle" data-theme-toggle aria-label="theme"><i data-theme-icon class="fa-solid fa-moon"></i></button>
        <div class="dropdown"><button class="btn btn-sm btn-light rounded-pill" data-bs-toggle="dropdown"><i class="fa-solid fa-globe"></i> <?= e(SUPPORTED_LANGS[$lang]['name']) ?></button>
          <ul class="dropdown-menu lang-menu p-2"><?php foreach (SUPPORTED_LANGS as $code => $li): ?><li><a class="dropdown-item <?= $code === $lang ? 'active' : '' ?>" href="<?= e(lang_switcher_url($code)) ?>"><?= $li['flag'] ?> <?= e($li['name']) ?></a></li><?php endforeach; ?></ul></div>
      </div>
    </div>
