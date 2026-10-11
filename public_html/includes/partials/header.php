<?php
/** رأس الصفحات العامة. المتغيرات: $pageTitle, $metaDesc, $active, $jsonLd, $ogImage, $noindex */
$pageTitle = $pageTitle ?? $siteName;
$metaDesc  = $metaDesc  ?? (getSetting($pdo, 'seo_description', $lang) ?: $siteName);
$active    = $active    ?? '';
$ogImage   = $ogImage   ?? base_url('assets/logo-desktop.png');
$canonical = base_url(ltrim(strtok($_SERVER['REQUEST_URI'], '?'), '/'));
$nav = ['index.php' => ['home', 'home'], 'courses.php' => ['courses', 'courses'], 'about.php' => ['about', 'about'], 'verify_certificate.php' => ['verify', 'verify'], 'contact.php' => ['contact', 'contact']];
$ai_on = ai_available();
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="#0b1c3f">
<title><?= e($pageTitle) ?> | <?= e($siteName) ?></title>
<meta name="description" content="<?= e(mb_substr(strip_tags($metaDesc), 0, 300)) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<meta property="og:type" content="website"><meta property="og:title" content="<?= e($pageTitle) ?>"><meta property="og:description" content="<?= e(mb_substr(strip_tags($metaDesc), 0, 200)) ?>"><meta property="og:image" content="<?= e($ogImage) ?>"><meta property="og:locale" content="<?= e(SUPPORTED_LANGS[$lang]['locale']) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php foreach (SUPPORTED_LANGS as $code => $_): ?><link rel="alternate" hreflang="<?= $code ?>" href="<?= e(base_url(ltrim(strtok($_SERVER['REQUEST_URI'], '?'), '/')) . '?lang=' . $code) ?>"><?php endforeach; ?>
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href="assets/logo-mobile.png">
<link rel="stylesheet" href="assets/vendor/fonts/fonts.css">
<link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap<?= $dir === 'rtl' ? '.rtl' : '' ?>.min.css">
<link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/app.css?v=2">
<script>(function(){try{var t=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t)}catch(e){}})();window.APP_BASE=document.baseURI.replace(/[^\/]*$/,'');</script>
<?php if (!empty($jsonLd)): ?><script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script><?php endif; ?>
<?= $headExtra ?? '' ?>
</head>
<body>
<a class="skip-link" href="#main"><?= e(t('skip_to_content')) ?></a>
<header class="site-nav">
  <nav class="navbar navbar-expand-lg container py-2" aria-label="Main">
    <a class="navbar-brand brand" href="index.php"><img src="assets/logo-desktop.png" alt="<?= e($siteName) ?>"></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-1">
        <?php foreach ($nav as $file => [$key, $act]): ?>
          <li class="nav-item"><a class="nav-link <?= $active === $act ? 'active' : '' ?>" href="<?= $file ?>"><?= e(t($key)) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="dropdown">
          <button class="btn-icon" data-bs-toggle="dropdown" aria-label="<?= e(t('language')) ?>"><i class="fa-solid fa-globe"></i></button>
          <ul class="dropdown-menu lang-menu p-2">
            <?php foreach (SUPPORTED_LANGS as $code => $info): ?>
              <li><a class="dropdown-item <?= $code === $lang ? 'active' : '' ?>" href="<?= e(lang_switcher_url($code)) ?>"><?= $info['flag'] ?> <?= e($info['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <a class="btn-icon" href="?<?= e(http_build_query(array_merge($_GET, ['currency' => ($_SESSION['currency'] === 'USD' ? 'DZD' : 'USD')]))) ?>" title="USD / DZD" style="font-weight:800;font-size:.8rem"><?= $_SESSION['currency'] ?></a>
        <button class="btn-icon" data-theme-toggle aria-label="<?= e(t('theme')) ?>"><i data-theme-icon class="fa-solid fa-moon"></i></button>
        <?php if ($me): ?>
          <a class="btn-aramio" href="<?= $me['role'] === 'admin' || $me['role'] === 'staff' ? 'admin/' : ($me['role'] === 'trainer' ? 'trainer/' : 'trainee/') ?>"><i class="fa-solid fa-gauge-high"></i> <?= e(t('dashboard')) ?></a>
        <?php else: ?>
          <a class="btn-ghost" href="login.php"><?= e(t('login')) ?></a>
          <a class="btn-aramio" href="register.php"><?= e(t('register')) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
</header>
<main id="main">
