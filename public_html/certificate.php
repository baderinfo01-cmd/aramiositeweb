<?php
require_once 'includes/init.php';
$cert = find_certificate((string)($_GET['code'] ?? ''));
if (!$cert) { http_response_code(404); redirect('verify_certificate.php?code=' . urlencode((string)($_GET['code'] ?? ''))); }
$verifyUrl = base_url('verify_certificate.php?code=' . urlencode($cert['code']));
$course = $lang === 'ar' ? $cert['course'] : ($cert['course_en'] ?: $cert['course']);
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e(t('cert_of_completion')) ?> — <?= e($cert['code']) ?></title>
<link rel="stylesheet" href="assets/vendor/fonts/fonts.css">
<style>
@page{size:A4 landscape;margin:0}
body{margin:0;background:#e9edf5;font-family:'Cairo',sans-serif;display:grid;place-items:center;min-height:100vh;padding:20px}
.cert{width:1123px;max-width:100%;aspect-ratio:1.414;background:#fff;position:relative;padding:34px;box-sizing:border-box;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.frame{position:absolute;inset:18px;border:6px double #0b1c3f;border-radius:6px}
.frame::after{content:"";position:absolute;inset:8px;border:2px solid #f5b942}
.inner{position:relative;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:30px 70px;color:#0b1c3f}
.inner img.logo{height:72px;margin-bottom:8px}
h1{font-family:'Playfair Display','Cairo',serif;font-size:46px;margin:6px 0;letter-spacing:1px}
.name{font-size:44px;font-weight:800;margin:14px 0;color:#12306b;border-bottom:3px solid #f5b942;padding:0 30px 6px}
.course{font-size:28px;font-weight:700;color:#13a671;margin:10px 0}
.meta{display:flex;justify-content:space-between;width:100%;margin-top:26px;align-items:flex-end;font-size:15px}
.meta div{min-width:200px} #qr{display:inline-block;background:#fff;padding:6px;border:1px solid #ddd}
.btnbar{position:fixed;top:12px;inset-inline-end:12px}.btnbar button{background:#13a671;color:#fff;border:0;border-radius:20px;padding:10px 22px;font-weight:700;cursor:pointer;font-family:inherit}
@media print{body{background:#fff;padding:0}.cert{box-shadow:none;width:100%}.btnbar{display:none}}
</style></head><body>
<div class="btnbar"><button onclick="print()">🖨 <?= e(t('cert_print')) ?></button></div>
<div class="cert"><div class="frame"></div><div class="inner">
  <img class="logo" src="assets/logo-desktop.png" alt="<?= e($siteName) ?>" onerror="this.style.display='none'">
  <div style="letter-spacing:4px;font-weight:700;color:#64708a"><?= e(strtoupper($siteName)) ?></div>
  <h1><?= e(t('cert_of_completion')) ?></h1>
  <div><?= e(t('cert_awarded_to')) ?></div>
  <div class="name"><?= e($cert['name']) ?></div>
  <div><?= e(t('cert_for_course')) ?></div>
  <div class="course"><?= e($course) ?></div>
  <?php if ($cert['grade']): ?><div><?= e(t('cert_grade')) ?>: <b><?= e($cert['grade']) ?></b></div><?php endif; ?>
  <div class="meta"><div><?= e(t('cert_date')) ?><br><b><?= e(date('Y-m-d', strtotime($cert['date']))) ?></b></div>
    <div style="text-align:center"><div id="qr"></div><div style="font-size:11px;margin-top:4px"><?= e(t('cert_scan')) ?></div></div>
    <div style="text-align:end"><?= e(t('cert_id')) ?><br><b dir="ltr"><?= e($cert['code']) ?></b></div></div>
</div></div>
<script src="assets/vendor/qrcode/qrcode.min.js"></script>
<script>new QRCode(document.getElementById("qr"),{text:<?= json_encode($verifyUrl) ?>,width:96,height:96});</script>
</body></html>
