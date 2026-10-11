<?php
require_once 'includes/init.php';
$cats = $pdo->query("SELECT id, name_ar, name_en FROM categories WHERE status=1 ORDER BY id DESC")->fetchAll();
$courses = $pdo->query("SELECT c.*, u.full_name AS trainer_name, cat.name_ar AS cat_ar, cat.name_en AS cat_en,
        (SELECT COALESCE(AVG(rating),0) FROM course_reviews WHERE course_id=c.id) AS avg_rating,
        (SELECT COUNT(*) FROM course_reviews WHERE course_id=c.id) AS reviews_count
    FROM courses c JOIN users u ON c.trainer_id=u.id JOIN categories cat ON c.category_id=cat.id
    WHERE c.status='published' ORDER BY c.created_at DESC")->fetchAll();
$active = 'courses'; $pageTitle = t('courses'); $metaDesc = t('latest_courses_sub');
require 'includes/partials/header.php';
?>
<section class="page-hero"><div class="container"><h1><?= e(t('courses')) ?></h1><p class="opacity-75 mb-0"><?= e(t('latest_courses_sub')) ?></p></div></section>
<div class="container" style="margin-top:-36px;position:relative;z-index:5">
  <div class="card-x p-3"><div class="row g-2">
    <div class="col-md-5"><input id="fq" class="form-control form-control-lg rounded-pill" placeholder="<?= e(t('search_courses')) ?>" aria-label="search"></div>
    <div class="col-md-4"><select id="fc" class="form-select form-select-lg rounded-pill"><option value=""><?= e(t('filter_all')) ?></option>
      <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($lang === 'ar' ? $c['name_ar'] : ($c['name_en'] ?: $c['name_ar'])) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><select id="ft" class="form-select form-select-lg rounded-pill"><option value=""><?= e(t('type_all')) ?></option>
      <?php foreach (['online', 'online_live', 'offline'] as $ty): ?><option value="<?= $ty ?>"><?= e(type_label($ty)) ?></option><?php endforeach; ?></select></div>
  </div></div>
</div>
<section class="section"><div class="container">
  <div class="row g-4" id="grid">
    <?php foreach ($courses as $c): include 'includes/partials/course_card.php'; endforeach; ?>
  </div>
  <p id="none" class="text-center text-muted py-5 d-none"><i class="fa-regular fa-face-frown fa-2x d-block mb-2"></i><?= e(t('no_courses')) ?></p>
</div></section>
<?php
$footExtra = '<script>(function(){var q=document.getElementById("fq"),c=document.getElementById("fc"),t=document.getElementById("ft"),items=[].slice.call(document.querySelectorAll(".course-item"));
function f(){var s=q.value.trim().toLowerCase(),n=0;items.forEach(function(i){var ok=(!s||i.dataset.text.indexOf(s)>-1)&&(!c.value||i.dataset.cat===c.value)&&(!t.value||i.dataset.type===t.value);i.classList.toggle("d-none",!ok);if(ok)n++});document.getElementById("none").classList.toggle("d-none",n>0)}
[q,c,t].forEach(function(x){x.addEventListener("input",f)});})();</script>';
require 'includes/partials/footer.php';
