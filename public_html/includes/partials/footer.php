</main>
<?php
$fb = getSetting($pdo, 'facebook_link'); $tw = getSetting($pdo, 'twitter_link'); $ig = getSetting($pdo, 'instagram_link'); $yt = getSetting($pdo, 'youtube_link');
$wa = preg_replace('/\D/', '', getSetting($pdo, 'whatsapp_number') ?: $contact_phone);
$ai_on = $ai_on ?? ai_available();
?>
<footer class="site-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4">
        <img src="assets/logo-desktop.png" alt="<?= e($siteName) ?>" height="46" class="mb-3">
        <p><?= e(mb_substr(strip_tags(getSetting($pdo, 'seo_description', $lang)), 0, 200)) ?></p>
        <div class="social">
          <?php foreach ([[$fb,'facebook-f'],[$tw,'x-twitter'],[$ig,'instagram'],[$yt,'youtube']] as [$u,$ic]) if ($u && $u !== '#'): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener" aria-label="<?= $ic ?>"><i class="fa-brands fa-<?= $ic ?>"></i></a><?php endif; ?>
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <h5><?= e(t('quick_links')) ?></h5>
        <ul class="list-unstyled d-grid gap-2">
          <li><a href="courses.php"><?= e(t('courses')) ?></a></li><li><a href="about.php"><?= e(t('about')) ?></a></li>
          <li><a href="verify_certificate.php"><?= e(t('verify')) ?></a></li><li><a href="contact.php"><?= e(t('contact')) ?></a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-3">
        <h5><?= e(t('contact')) ?></h5>
        <ul class="list-unstyled d-grid gap-2">
          <li><i class="fa-solid fa-envelope me-2"></i><a href="mailto:<?= e($contact_email) ?>"><?= e($contact_email) ?></a></li>
          <?php if ($contact_phone): ?><li><i class="fa-solid fa-phone me-2"></i><span dir="ltr"><?= e($contact_phone) ?></span></li><?php endif; ?>
        </ul>
        <button class="btn btn-sm btn-outline-light rounded-pill d-none" data-install><i class="fa-solid fa-download"></i> <?= e(t('install_app')) ?></button>
      </div>
      <div class="col-lg-3">
        <h5><?= e(t('newsletter_title')) ?></h5>
        <p class="small"><?= e(t('newsletter_desc')) ?></p>
        <form id="nlForm" class="d-flex gap-2"><input type="email" name="email" required class="form-control rounded-pill" placeholder="<?= e(t('newsletter_ph')) ?>" aria-label="email"><button class="btn-aramio" type="submit"><?= e(t('newsletter_btn')) ?></button></form>
        <div id="nlMsg" class="small mt-2 text-success"></div>
      </div>
    </div>
    <hr class="border-secondary mt-4">
    <div class="text-center small">© <?= date('Y') ?> <?= e($siteName) ?> — <?= e(t('rights')) ?></div>
  </div>
</footer>

<div class="fab-stack no-print">
  <?php if ($wa): ?><a class="fab fab-wa" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a><?php endif; ?>
  <button class="fab fab-ai" data-ai-toggle aria-label="<?= e(t('ai_assistant')) ?>"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
</div>
<div class="chat-box" id="aiChat" role="dialog" aria-label="<?= e(t('ai_assistant')) ?>">
  <div class="chat-head"><div class="av"><i class="fa-solid fa-robot"></i></div><div><b><?= e(t('ai_assistant')) ?></b><div class="small opacity-75">Aramio AI</div></div><button class="x" aria-label="close">&times;</button></div>
  <div class="chat-body" aria-live="polite"></div>
  <div class="chat-note"><?= e(t('ai_disclaimer')) ?></div>
  <form class="chat-foot" autocomplete="off"><input type="text" maxlength="500" placeholder="<?= e(t('ai_placeholder')) ?>" aria-label="<?= e(t('ai_placeholder')) ?>"><button type="submit" aria-label="<?= e(t('send')) ?>"><i class="fa-solid fa-paper-plane"></i></button></form>
</div>
<script>window.AI_CFG={endpoint:'api/ai_chat.php',welcome:<?= json_encode($ai_on ? t('ai_welcome') : t('ai_unavailable'), JSON_UNESCAPED_UNICODE) ?>,error:<?= json_encode(t('ai_error'), JSON_UNESCAPED_UNICODE) ?>};</script>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js?v=2"></script>
<script src="assets/js/chat.js?v=2"></script>
<script>
document.getElementById('nlForm')?.addEventListener('submit',function(e){e.preventDefault();
  window.postJSON('api/newsletter.php',{email:this.email.value}).then(function(r){document.getElementById('nlMsg').textContent=r.ok?<?= json_encode(t('newsletter_ok'), JSON_UNESCAPED_UNICODE) ?>:(r.message||'')});});
</script>
<?= $footExtra ?? '' ?>
</body></html>
