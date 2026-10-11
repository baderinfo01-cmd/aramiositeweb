<?php /** بطاقة دورة. المتغير المتوقع: $c (صف الدورة + cat_ar/cat_en/trainer_name/avg_rating/reviews_count) */
$thumb = course_thumb($c);
$ctitle = tr_field($c, 'title', 'course');
$ccat = $c['cat_' . ($lang === 'ar' ? 'ar' : 'en')] ?: $c['cat_ar'];
?>
<div class="col-md-6 col-lg-4 course-item reveal" data-type="<?= e($c['training_type']) ?>" data-cat="<?= (int)$c['category_id'] ?>" data-text="<?= e(mb_strtolower($ctitle . ' ' . $c['title_ar'] . ' ' . $c['title_en'] . ' ' . $ccat . ' ' . $c['trainer_name'])) ?>">
  <article class="card-x course-card d-flex flex-column">
    <a href="course.php?id=<?= (int)$c['id'] ?>" class="thumb" aria-label="<?= e($ctitle) ?>">
      <?php if ($thumb): ?><img src="<?= e($thumb) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><i class="fa-solid fa-graduation-cap"></i></span><?php endif; ?>
      <span class="badge-type"><i class="fa-solid <?= type_icon($c['training_type']) ?>"></i> <?= e(type_label($c['training_type'])) ?><?= $c['training_type'] === 'offline' && $c['city'] ? ' · ' . e($c['city']) : '' ?></span>
    </a>
    <div class="p-3 p-md-4 d-flex flex-column flex-grow-1">
      <div class="d-flex justify-content-between align-items-center"><span class="badge-cat"><?= e($ccat) ?></span>
        <span class="small text-warning fw-bold"><?= number_format((float)$c['avg_rating'], 1) ?> <i class="fa-solid fa-star"></i> <span class="text-muted fw-normal">(<?= (int)$c['reviews_count'] ?>)</span></span></div>
      <h3><a href="course.php?id=<?= (int)$c['id'] ?>" class="text-reset"><?= e($ctitle) ?></a></h3>
      <div class="meta-row mb-3"><span><i class="fa-solid fa-user-tie"></i> <?= e($c['trainer_name']) ?></span><?php if ($c['start_date']): ?><span><i class="fa-regular fa-calendar"></i> <?= e($c['start_date']) ?></span><?php endif; ?></div>
      <div class="mt-auto d-flex justify-content-between align-items-center pt-3 border-top">
        <span class="price-tag"><?= (float)$c['price_usd'] > 0 || (float)$c['price_dzd'] > 0 ? e(formatPrice($c['price_usd'], $c['price_dzd'])) : e(t('free')) ?></span>
        <a href="course.php?id=<?= (int)$c['id'] ?>" class="btn-aramio btn-sm py-1 px-3"><?= e(t('details')) ?></a>
      </div>
    </div>
  </article>
</div>
