<?php
require_once 'includes/init.php';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('courses.php');

$st = $pdo->prepare("SELECT c.*, u.id AS tid, u.full_name AS trainer_name, u.avatar AS trainer_avatar, u.bio AS trainer_bio, u.country AS trainer_country,
        cat.name_ar AS cat_ar, cat.name_en AS cat_en,
        (SELECT COALESCE(AVG(rating),0) FROM course_reviews WHERE course_id=c.id) AS avg_rating,
        (SELECT COUNT(*) FROM course_reviews WHERE course_id=c.id) AS reviews_count
    FROM courses c JOIN users u ON c.trainer_id=u.id JOIN categories cat ON c.category_id=cat.id WHERE c.id=? AND c.status='published'");
$st->execute([$id]);
$c = $st->fetch();
if (!$c) { http_response_code(404); $pageTitle = t('page_not_found'); require 'includes/partials/header.php'; echo '<div class="container text-center py-5 my-5"><h1 class="display-1">404</h1><p>' . e(t('page_not_found')) . '</p><a class="btn-aramio" href="courses.php">' . e(t('courses')) . '</a></div>'; require 'includes/partials/footer.php'; exit; }

$lessons = $pdo->prepare("SELECT id, title_ar, title_en, is_free FROM lessons WHERE course_id=? ORDER BY lesson_order, id"); $lessons->execute([$id]); $lessons = $lessons->fetchAll();
$reviews = $pdo->prepare("SELECT r.rating, r.comment, r.created_at, u.full_name FROM course_reviews r JOIN users u ON u.id=r.trainee_id WHERE r.course_id=? ORDER BY r.id DESC LIMIT 8"); $reviews->execute([$id]); $reviews = $reviews->fetchAll();
$related = $pdo->prepare("SELECT c.*, u.full_name AS trainer_name, cat.name_ar AS cat_ar, cat.name_en AS cat_en,
        (SELECT COALESCE(AVG(rating),0) FROM course_reviews WHERE course_id=c.id) AS avg_rating, (SELECT COUNT(*) FROM course_reviews WHERE course_id=c.id) AS reviews_count
    FROM courses c JOIN users u ON c.trainer_id=u.id JOIN categories cat ON c.category_id=cat.id
    WHERE c.status='published' AND c.category_id=? AND c.id<>? ORDER BY c.created_at DESC LIMIT 3");
$related->execute([$c['category_id'], $id]); $related = $related->fetchAll();

$order = null; $seatsLeft = null;
if ($me && $me['role'] === 'trainee') {
    $o = $pdo->prepare("SELECT status FROM course_orders WHERE user_id=? AND course_id=? ORDER BY id DESC LIMIT 1"); $o->execute([$me['id'], $id]); $order = $o->fetchColumn() ?: null;
}
if ($c['training_type'] !== 'online' && (int)$c['max_seats'] > 0) {
    $b = $pdo->prepare("SELECT COUNT(*) FROM course_orders WHERE course_id=? AND status IN ('approved','reserved','pending')"); $b->execute([$id]);
    $seatsLeft = max(0, (int)$c['max_seats'] - (int)$b->fetchColumn());
}

$title = tr_field($c, 'title', 'course');
$desc  = tr_field($c, 'description', 'course');
$cat   = $c['cat_' . ($lang === 'ar' ? 'ar' : 'en')] ?: $c['cat_ar'];
$thumb = course_thumb($c);
$free  = (float)$c['price_usd'] <= 0 && (float)$c['price_dzd'] <= 0;
$active = 'courses'; $pageTitle = $title; $metaDesc = $desc ?: $title;
if ($thumb) $ogImage = base_url($thumb);
$jsonLd = ['@context' => 'https://schema.org', '@type' => 'Course', 'name' => $title, 'description' => mb_substr(strip_tags($desc), 0, 300),
    'provider' => ['@type' => 'Organization', 'name' => $siteName, 'sameAs' => base_url()],
    'offers' => ['@type' => 'Offer', 'price' => (float)$c['price_usd'], 'priceCurrency' => 'USD', 'availability' => 'https://schema.org/InStock', 'url' => base_url('course.php?id=' . $id)]];
if ($c['reviews_count'] > 0) $jsonLd['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round($c['avg_rating'], 1), 'reviewCount' => (int)$c['reviews_count']];
require 'includes/partials/header.php';
?>
<section class="page-hero text-start"><div class="container">
  <nav class="small opacity-75 mb-2"><a class="text-white" href="courses.php"><?= e(t('courses')) ?></a> / <?= e($cat) ?></nav>
  <h1><?= e($title) ?></h1>
  <div class="d-flex flex-wrap gap-3 mt-3 opacity-90">
    <span><i class="fa-solid <?= type_icon($c['training_type']) ?>"></i> <?= e(type_label($c['training_type'])) ?></span>
    <span><i class="fa-solid fa-user-tie"></i> <?= e($c['trainer_name']) ?></span>
    <span class="text-warning"><?= stars((float)$c['avg_rating']) ?> <small class="text-white">(<?= (int)$c['reviews_count'] ?>)</small></span>
  </div>
</div></section>

<section class="section"><div class="container"><div class="row g-4">
  <div class="col-lg-8">
    <?php if ($thumb): ?><img src="<?= e($thumb) ?>" class="w-100 rounded-4 mb-4 shadow-sm" alt="<?= e($title) ?>"><?php endif; ?>
    <div class="card-x p-4 mb-4"><h2 class="h4 fw-bold mb-3"><?= e(t('details')) ?></h2><div style="white-space:pre-wrap;line-height:1.9"><?= e($desc) ?></div></div>

    <?php if ($lessons): ?>
    <div class="card-x p-4 mb-4"><h2 class="h4 fw-bold mb-3"><?= e(t('lessons')) ?> <small class="text-muted">(<?= count($lessons) ?>)</small></h2>
      <ol class="list-group list-group-numbered">
        <?php foreach ($lessons as $l): ?><li class="list-group-item d-flex justify-content-between align-items-center"><?= e(tr_field($l, 'title')) ?>
          <?= $l['is_free'] ? '<span class="badge bg-success">' . e(t('free_preview')) . '</span>' : '<i class="fa-solid fa-lock text-muted"></i>' ?></li><?php endforeach; ?>
      </ol></div>
    <?php endif; ?>

    <div class="card-x p-4 mb-4"><h2 class="h4 fw-bold mb-3"><?= e(t('trainer')) ?></h2>
      <div class="d-flex gap-3 align-items-start"><img src="<?= e(avatar_url($c['trainer_avatar'], $c['trainer_name'])) ?>" class="rounded-circle" width="76" height="76" style="object-fit:cover" alt="">
      <div><b><?= e($c['trainer_name']) ?> <?= country_flag((string)$c['trainer_country']) ?></b><p class="text-muted mb-0" style="white-space:pre-wrap"><?= e(mb_substr(strip_tags((string)$c['trainer_bio']), 0, 500)) ?></p></div></div></div>

    <?php if ($reviews): ?><div class="card-x p-4"><h2 class="h4 fw-bold mb-3"><?= e(t('reviews')) ?></h2>
      <?php foreach ($reviews as $r): ?><div class="border-bottom py-3"><div class="d-flex justify-content-between"><b><?= e($r['full_name']) ?></b><span class="text-warning"><?= stars((float)$r['rating']) ?></span></div><p class="text-muted mb-0"><?= e($r['comment']) ?></p></div><?php endforeach; ?></div><?php endif; ?>
  </div>

  <aside class="col-lg-4"><div class="card-x p-4" style="position:sticky;top:90px">
    <div class="display-6 fw-bold mb-3"><?= $free ? e(t('free')) : e(formatPrice($c['price_usd'], $c['price_dzd'])) ?></div>
    <ul class="list-unstyled d-grid gap-2 text-muted">
      <li><i class="fa-solid fa-tag w-25px"></i> <?= e($cat) ?></li>
      <li><i class="fa-solid <?= type_icon($c['training_type']) ?>"></i> <?= e(type_label($c['training_type'])) ?><?= $c['city'] ? ' — ' . e($c['city']) : '' ?></li>
      <?php if ($c['start_date']): ?><li><i class="fa-regular fa-calendar"></i> <?= e(t('start_date')) ?>: <b><?= e($c['start_date']) ?></b><?= $c['live_time'] ? ' ' . e(substr($c['live_time'], 0, 5)) : '' ?></li><?php endif; ?>
      <?php if ($c['venue_name']): ?><li><i class="fa-solid fa-location-dot"></i> <?= e($c['venue_name']) ?></li><?php endif; ?>
      <?php if ($seatsLeft !== null): ?><li><i class="fa-solid fa-chair"></i> <?= e(t('seats')) ?>: <b><?= $seatsLeft ?></b></li><?php endif; ?>
    </ul>
    <?php if ($me && $me['role'] === 'trainee'): ?>
      <?php if ($order === 'approved'): ?><a class="btn-aramio w-100 text-center d-block" href="trainee/my_courses.php"><i class="fa-solid fa-play"></i> <?= e(t('dashboard')) ?></a>
      <?php elseif (in_array($order, ['reserved', 'pending'], true)): ?><a class="btn-aramio w-100 text-center d-block" href="trainee/my_courses.php"><i class="fa-solid fa-clock"></i> <?= e(t('dashboard')) ?></a>
      <?php elseif ($seatsLeft === 0): ?><button class="btn btn-secondary w-100 rounded-pill" disabled><?= e(t('seats')) ?>: 0</button>
      <?php else: ?><form method="post" action="trainee/store.php"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= $id ?>"><button class="btn-aramio w-100 py-2" name="reserve_course" value="1"><i class="fa-solid fa-cart-shopping"></i> <?= e(t('enroll_now')) ?></button></form><?php endif; ?>
    <?php elseif ($me): ?><div class="alert alert-info small mb-0"><?= e(t('trainee_only')) ?></div>
    <?php else: ?><a class="btn-aramio w-100 text-center d-block py-2" href="login.php?next=<?= urlencode('course.php?id=' . $id) ?>"><?= e(t('enroll_now')) ?></a>
      <a class="d-block text-center mt-2 small" href="register.php"><?= e(t('register_free')) ?></a><?php endif; ?>
    <button class="btn btn-outline-secondary w-100 rounded-pill mt-3" data-ai-toggle><i class="fa-solid fa-wand-magic-sparkles"></i> <?= e(t('ask_ai')) ?></button>
  </div></aside>
</div></div></section>

<?php if ($related): ?><section class="section section-alt"><div class="container"><div class="section-head"><h2><?= e(t('related_courses')) ?></h2></div><div class="row g-4">
  <?php foreach ($related as $c): include 'includes/partials/course_card.php'; endforeach; ?></div></div></section><?php endif; ?>
<?php
$footExtra = '<script>window.AI_CFG.course=' . $id . ';(function(){var o=window.postJSON;window.postJSON=function(u,d){if(u.indexOf("ai_chat")>-1)d.course_id=' . $id . ';return o(u,d)}})();</script>';
require 'includes/partials/footer.php';
