<?php
require_once 'includes/init.php';
$code = trim((string)($_GET['code'] ?? ''));
$cert = null; $searched = false;
if ($code !== '') {
    if (!rate_limit('verify_cert', 30, 600)) { $searched = true; }
    else { $searched = true; $cert = find_certificate($code); }
}
$active = 'verify'; $pageTitle = t('verify_title'); $metaDesc = t('verify_desc');
if ($code !== '') $noindex = true;
require 'includes/partials/header.php';
?>
<section class="page-hero"><div class="container"><h1><i class="fa-solid fa-certificate text-warning"></i> <?= e(t('verify_title')) ?></h1><p class="opacity-75 mb-0"><?= e(t('verify_desc')) ?></p></div></section>
<section class="section"><div class="container" style="max-width:760px">
  <form method="get" class="card-x p-3 d-flex gap-2 flex-wrap">
    <input name="code" value="<?= e($code) ?>" class="form-control form-control-lg rounded-pill text-center fw-bold" dir="ltr" required maxlength="50" placeholder="<?= e(t('verify_placeholder')) ?>" style="flex:1;min-width:200px" id="codeInput">
    <button class="btn-aramio px-4"><?= e(t('verify_btn')) ?></button>
    <button type="button" class="btn btn-outline-secondary rounded-pill" id="scanBtn" aria-label="QR"><i class="fa-solid fa-qrcode"></i></button>
  </form>
  <div id="scanBox" class="mt-3 d-none mx-auto" style="max-width:320px"><div id="reader"></div></div>

  <?php if ($searched && $cert): ?>
  <div class="card-x mt-4" style="border-color:var(--emerald)">
    <div class="p-3 text-white text-center fw-bold" style="background:var(--grad-accent)"><i class="fa-solid fa-circle-check"></i> <?= e(t('cert_valid')) ?></div>
    <div class="p-4"><div class="row g-3">
      <div class="col-sm-6"><small class="text-muted d-block"><?= e(t('cert_fname')) ?></small><b class="fs-5"><?= e($cert['name']) ?></b></div>
      <div class="col-sm-6"><small class="text-muted d-block"><?= e(t('cert_nat')) ?></small><b><?= e($cert['nationality'] ?: '—') ?></b></div>
      <div class="col-sm-6"><small class="text-muted d-block"><?= e(t('cert_spec')) ?></small><b><?= e($lang === 'ar' ? $cert['course'] : ($cert['course_en'] ?: $cert['course'])) ?></b></div>
      <div class="col-sm-3"><small class="text-muted d-block"><?= e(t('cert_grade')) ?></small><b><?= e($cert['grade'] ?: '—') ?></b></div>
      <div class="col-sm-3"><small class="text-muted d-block"><?= e(t('cert_result')) ?></small><span class="badge bg-success"><?= e(t('cert_passed')) ?></span></div>
      <div class="col-sm-6"><small class="text-muted d-block"><?= e(t('cert_id')) ?></small><code class="fs-6"><?= e($cert['code']) ?></code></div>
      <div class="col-sm-6"><small class="text-muted d-block"><?= e(t('cert_date')) ?></small><b><?= e(date('Y-m-d', strtotime($cert['date']))) ?></b></div>
    </div>
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mt-4 pt-3 border-top">
      <small class="text-muted"><?= e(t('cert_help')) ?> <a href="mailto:<?= e($contact_email) ?>"><?= e($contact_email) ?></a></small>
      <a class="btn-aramio py-1" target="_blank" href="certificate.php?code=<?= urlencode($cert['code']) ?>"><i class="fa-solid fa-print"></i> <?= e(t('cert_print')) ?></a></div></div>
  </div>
  <?php elseif ($searched): ?><div class="alert alert-danger mt-4 text-center fw-bold"><i class="fa-solid fa-triangle-exclamation"></i> <?= e(t('cert_not_found')) ?></div><?php endif; ?>
</div></section>
<?php
$footExtra = '<script src="assets/vendor/html5-qrcode/html5-qrcode.min.js"></script><script>
var sc=null;document.getElementById("scanBtn").addEventListener("click",function(){var b=document.getElementById("scanBox");
if(sc){sc.stop().then(function(){sc.clear();sc=null;b.classList.add("d-none")});return}
b.classList.remove("d-none");sc=new Html5Qrcode("reader");sc.start({facingMode:"environment"},{fps:10,qrbox:220},function(txt){var m=txt.match(/code=([^&]+)/);var c=m?decodeURIComponent(m[1]):txt;sc.stop();location.href="verify_certificate.php?code="+encodeURIComponent(c)},function(){}).catch(function(){b.classList.add("d-none");sc=null})});</script>';
require 'includes/partials/footer.php';
