<?php
require_once 'includes/init.php';
http_response_code(404);
$pageTitle = t('page_not_found'); $noindex = true;
require 'includes/partials/header.php';
?>
<section class="section text-center"><div class="container py-5"><div class="display-1 fw-bold sparkle">404</div><h1 class="h3"><?= e(t('page_not_found')) ?></h1>
<p class="text-muted"><?= e(t('ai_reco_desc')) ?></p><a class="btn-aramio px-5" href="index.php"><?= e(t('go_home')) ?></a> <button class="btn btn-outline-secondary rounded-pill" data-ai-toggle><?= e(t('ask_ai')) ?></button></div></section>
<?php require 'includes/partials/footer.php';
