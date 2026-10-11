<?php
/**
 * غلاف لوحات التحكم. المتغيرات المطلوبة قبل الاستدعاء:
 *  $dashRole (admin|trainer|trainee), $dashTitle, $dashActive (اسم الملف)
 * اختياري: $needChart (true) لتحميل Chart.js
 */

$B = '../';
$dashActive = $dashActive ?? basename($_SERVER['PHP_SELF']);
$roleLabel = ['admin' => d('الإدارة المركزية', 'Admin console'), 'trainer' => d('لوحة المدرب', 'Trainer panel'), 'trainee' => d('لوحة المتدرب', 'Trainee panel')][$dashRole];
$pills = [];
if ($dashRole === 'admin') {
    $pills['messages.php'] = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
    $pills['financial.php'] = (int)$pdo->query("SELECT COUNT(*) FROM course_orders WHERE status='pending'")->fetchColumn();
    $pills['courses.php'] = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status='pending'")->fetchColumn();
    $pills['consultations.php'] = (int)$pdo->query("SELECT COUNT(*) FROM consultation_bookings WHERE status='pending'")->fetchColumn();
}
$unread = unread_count();
$notes = [];
try { $ns = $pdo->prepare("SELECT * FROM user_notifications WHERE user_id=? ORDER BY id DESC LIMIT 6"); $ns->execute([$me['id']]); $notes = $ns->fetchAll(); } catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>" data-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="theme-color" content="#0b1c3f">
<title><?= e($dashTitle ?? '') ?> | <?= e($siteName) ?></title>
<link rel="icon" href="<?= $B ?>assets/logo-mobile.png">
<link rel="stylesheet" href="<?= $B ?>assets/vendor/fonts/fonts.css">
<link rel="stylesheet" href="<?= $B ?>assets/vendor/bootstrap/bootstrap<?= $dir === 'rtl' ? '.rtl' : '' ?>.min.css">
<link rel="stylesheet" href="<?= $B ?>assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="<?= $B ?>assets/css/app.css?v=2">
<link rel="stylesheet" href="<?= $B ?>assets/css/dash.css?v=2">
<script>(function(){try{var t=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t)}catch(e){}})();</script>
<?= $headExtra ?? '' ?>
</head>
<body class="dash">
<div class="dash-overlay" id="dOverlay"></div>
<aside class="dash-side" id="dSide" aria-label="Sidebar">
  <div class="dash-brand"><img src="<?= $B ?>assets/logo-desktop.png" alt=""><div><b class="text-white">Aramio</b><small><?= e($roleLabel) ?></small></div></div>
  <nav class="dash-nav">
    <?php foreach (dash_menu($dashRole) as $it): if ($it[0] === '#'): ?><div class="grp"><?= e($it[2]) ?></div><?php continue; endif; ?>
      <a href="<?= e($it[0]) ?>" class="<?= $dashActive === $it[0] ? 'active' : '' ?> <?= ($it[3] ?? '') === 'ai' ? 'ai-link' : '' ?>"><i class="fa-solid <?= $it[1] ?>"></i><span><?= e($it[2]) ?></span>
        <?php if (!empty($pills[$it[0]])): ?><span class="pill"><?= (int)$pills[$it[0]] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    <div class="grp"><?= e(d('الموقع', 'Website')) ?></div>
    <a href="<?= $B ?>index.php" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i><span><?= e(d('عرض الموقع', 'View site')) ?></span></a>
    <a href="<?= $B ?>logout.php" style="color:#fca5a5"><i class="fa-solid fa-power-off" style="color:#fca5a5"></i><span><?= e(t('logout')) ?></span></a>
  </nav>
</aside>
<div class="dash-main">
  <header class="dash-top">
    <button class="tb d-lg-none" id="dOpen" aria-label="menu"><i class="fa-solid fa-bars"></i></button>
    <h1><?= e($dashTitle ?? '') ?></h1>
    <div class="ms-auto d-flex align-items-center gap-2">
      <div class="dropdown"><button class="tb" data-bs-toggle="dropdown" aria-label="notifications"><i class="fa-regular fa-bell"></i><?php if ($unread): ?><span class="dot"><?= $unread ?></span><?php endif; ?></button>
        <div class="dropdown-menu dropdown-menu-end p-0 lang-menu" style="width:320px;max-width:92vw">
          <div class="p-3 fw-bold border-bottom d-flex justify-content-between"><?= e(d('الإشعارات', 'Notifications')) ?>
            <?php if ($unread): ?><a href="#" id="markRead" class="small"><?= e(d('تعليم الكل كمقروء', 'Mark all read')) ?></a><?php endif; ?></div>
          <?php if (!$notes): ?><div class="empty py-4"><i class="fa-regular fa-bell-slash"></i><?= e(d('لا توجد إشعارات', 'No notifications')) ?></div><?php endif; ?>
          <?php foreach ($notes as $n): ?><a class="dropdown-item py-2 <?= $n['is_read'] ? 'text-muted' : 'fw-bold' ?>" href="<?= e($n['link'] ?: '#') ?>" style="white-space:normal"><?= e($n['title']) ?><small class="d-block text-muted fw-normal"><?= e($n['body']) ?></small></a><?php endforeach; ?>
        </div></div>
      <div class="dropdown"><button class="tb" data-bs-toggle="dropdown" aria-label="language"><i class="fa-solid fa-globe"></i></button>
        <ul class="dropdown-menu dropdown-menu-end lang-menu p-2"><?php foreach (SUPPORTED_LANGS as $code => $info): ?><li><a class="dropdown-item <?= $code === $lang ? 'active' : '' ?>" href="<?= e(lang_switcher_url($code)) ?>"><?= $info['flag'] ?> <?= e($info['name']) ?></a></li><?php endforeach; ?></ul></div>
      <button class="tb" data-theme-toggle aria-label="theme"><i data-theme-icon class="fa-solid fa-moon"></i></button>
      <div class="d-flex align-items-center gap-2 ps-2"><img src="<?= e(avatar_url($me['avatar'] ?? null, $me['full_name'], $B)) ?>" width="42" height="42" class="rounded-circle" style="object-fit:cover" alt="">
        <div class="d-none d-md-block lh-1"><b><?= e($me['full_name']) ?></b><br><small class="text-muted"><?= e($roleLabel) ?></small></div></div>
    </div>
  </header>
  <div class="dash-body">
<?= flash_render() ?>
