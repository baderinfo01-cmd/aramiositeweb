  </div>
  <div class="auth-side">
    <img src="assets/logo-desktop.png" alt="" style="height:54px;width:auto;align-self:flex-start;margin-bottom:2rem">
    <h2 class="fw-bold mb-3"><?= e($sideTitle ?? $siteName) ?></h2>
    <p class="lead opacity-75"><?= e($sideDesc ?? t('hero_desc')) ?></p>
    <ul class="list-unstyled mt-4 d-grid gap-2"><?php foreach ([1, 2, 3] as $i): ?><li><i class="fa-solid fa-circle-check text-warning"></i> <?= e(t("why_{$i}_t")) ?></li><?php endforeach; ?></ul>
  </div>
</div></div>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js?v=2"></script>
</body></html>
