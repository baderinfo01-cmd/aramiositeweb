<?php
require_once 'includes/init.php';

// توافق مع الروابط القديمة: index.php?cert_code=XXX
if (!empty($_GET['cert_code'])) redirect('verify_certificate.php?code=' . urlencode($_GET['cert_code']));

$courses = $pdo->query("SELECT c.*, u.full_name AS trainer_name, cat.name_ar AS cat_ar, cat.name_en AS cat_en,
        (SELECT COALESCE(AVG(rating),0) FROM course_reviews WHERE course_id=c.id) AS avg_rating,
        (SELECT COUNT(*) FROM course_reviews WHERE course_id=c.id) AS reviews_count
    FROM courses c JOIN users u ON c.trainer_id=u.id JOIN categories cat ON c.category_id=cat.id
    WHERE c.status='published' ORDER BY c.created_at DESC LIMIT 6")->fetchAll();
$trainers = $pdo->query("SELECT id, full_name, avatar, bio, country FROM users WHERE role='trainer' AND status=1 ORDER BY id LIMIT 8")->fetchAll();
$testimonials = $pdo->query("SELECT * FROM testimonials WHERE status=1 ORDER BY id DESC LIMIT 6")->fetchAll();
$db_gallery = $pdo->query("SELECT image_name FROM gallery_images WHERE image_type='gallery' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_COLUMN);
$db_reviews = $pdo->query("SELECT image_name FROM gallery_images WHERE image_type='review' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_COLUMN);
$popups = $pdo->query("SELECT message_" . ($lang === 'ar' ? 'ar' : 'en') . " AS msg FROM popup_notifications WHERE status=1 ORDER BY RAND() LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
$faqs = $pdo->query("SELECT * FROM faqs WHERE status=1 ORDER BY sort_order, id")->fetchAll();

$stats = [
    ['fa-book-open', (int)getSetting($pdo, 'stat_courses'), t('stats_courses')],
    ['fa-user-graduate', (int)getSetting($pdo, 'stat_students'), t('stats_students')],
    ['fa-chalkboard-user', (int)getSetting($pdo, 'stat_trainers'), t('stats_trainers')],
    ['fa-comments', (int)getSetting($pdo, 'stat_consultations'), t('stats_cons')],
];
$pk = $lang === 'ar' ? '' : '_en';
$promo_title = getSetting($pdo, "promo_title$pk", $lang) ?: getSetting($pdo, 'promo_title', 'ar');
$promo_sub   = getSetting($pdo, "promo_subtitle$pk", $lang) ?: getSetting($pdo, 'promo_subtitle', 'ar');
$promo_link  = getSetting($pdo, "promo_link$pk", $lang) ?: getSetting($pdo, 'promo_link', 'ar');
$promo_date  = getSetting($pdo, 'promo_end_date', 'ar');
$popup_delay = max(4, (int)(getSetting($pdo, 'popup_timer') ?: 10));

$active = 'home';
$pageTitle = t('home');
$jsonLd = ['@context' => 'https://schema.org', '@type' => 'EducationalOrganization', 'name' => $siteName, 'url' => base_url(),
           'logo' => base_url('assets/logo-desktop.png'), 'email' => $contact_email, 'telephone' => $contact_phone];
require 'includes/partials/header.php';
?>
<section class="hero text-center">
  <div class="hero-grid"></div>
  <div class="container position-relative">
    <span class="hero-badge reveal"><i class="fa-solid fa-sparkles text-warning"></i> <?= e(t('hero_badge')) ?></span>
    <h1 class="reveal"><?= e(t('hero_title')) ?></h1>
    <p class="lead reveal"><?= e(t('hero_desc')) ?></p>
    <div class="d-flex gap-3 justify-content-center flex-wrap mt-4 reveal">
      <a href="courses.php" class="btn-aramio fs-5 px-4"><i class="fa-solid fa-graduation-cap"></i> <?= e(t('browse_courses')) ?></a>
      <button class="btn-ghost fs-5 px-4" data-ai-toggle><i class="fa-solid fa-wand-magic-sparkles"></i> <?= e(t('ask_ai')) ?></button>
    </div>
    <div class="hero-orbit reveal">
      <?php foreach ([['fa-certificate', t('why_1_t')], ['fa-robot', t('why_2_t')], ['fa-language', t('why_3_t')], ['fa-video', t('why_4_t')]] as [$ic, $tx]): ?>
        <span class="hero-chip"><i class="fa-solid <?= $ic ?>"></i><?= e($tx) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="container stats-wrap reveal">
  <div class="row g-3">
    <?php foreach ($stats as [$ic, $n, $lb]): ?>
      <div class="col-6 col-md-3"><div class="stat-tile"><div class="ico"><i class="fa-solid <?= $ic ?>"></i></div><div class="num" data-count="<?= $n ?>">0</div><div class="lbl"><?= e($lb) ?></div></div></div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($promo_date && strtotime($promo_date) > time() && $promo_title): ?>
<section class="promo text-center mt-5"><div class="container">
  <h3 class="fw-bold"><?= e($promo_title) ?></h3><p class="mb-0 opacity-75"><?= e($promo_sub) ?></p>
  <div class="countdown" data-countdown="<?= e($promo_date) ?>" data-ended="<?= e(t('promo_ended')) ?>">
    <?php foreach (['days', 'hours', 'mins', 'secs'] as $u): ?><div class="cd-box"><b data-u>00</b><small><?= e(t($u)) ?></small></div><?php endforeach; ?>
  </div>
  <?php if ($promo_link): ?><a class="btn-aramio mt-4 px-5" href="<?= e($promo_link) ?>"><?= e(t('enroll_now')) ?></a><?php endif; ?>
</div></section>
<?php endif; ?>

<section class="section" id="why"><div class="container">
  <div class="section-head reveal"><h2><?= e(t('why_title')) ?></h2></div>
  <div class="row g-4">
    <?php foreach ([['fa-certificate',1],['fa-robot',2],['fa-language',3],['fa-video',4],['fa-handshake-angle',5],['fa-chart-line',6]] as [$ic,$i]): ?>
      <div class="col-md-6 col-lg-4 reveal"><div class="card-x feat"><div class="ico"><i class="fa-solid <?= $ic ?>"></i></div><h3><?= e(t("why_{$i}_t")) ?></h3><p><?= e(t("why_{$i}_d")) ?></p></div></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section section-alt" id="courses"><div class="container">
  <div class="section-head reveal"><h2><?= e(t('latest_courses')) ?></h2><p><?= e(t('latest_courses_sub')) ?></p></div>
  <div class="row g-4">
    <?php foreach ($courses as $c): include 'includes/partials/course_card.php'; endforeach; ?>
  </div>
  <div class="text-center mt-5"><a href="courses.php" class="btn-aramio px-5"><?= e(t('show_more')) ?> <i class="fa-solid fa-arrow-<?= $dir === 'rtl' ? 'left' : 'right' ?>"></i></a></div>
</div></section>

<section class="section"><div class="container">
  <div class="ai-panel text-center reveal">
    <h2 class="fw-bold"><i class="fa-solid fa-wand-magic-sparkles sparkle"></i> <?= e(t('ai_reco_title')) ?></h2>
    <p class="opacity-75 mb-4"><?= e(t('ai_reco_desc')) ?></p>
    <form id="recoForm" class="row g-2 justify-content-center"><div class="col-md-7"><input name="q" class="form-control form-control-lg" required maxlength="300" placeholder="<?= e(t('ai_reco_ph')) ?>"></div>
      <div class="col-md-auto"><button class="btn-aramio py-3 px-4" type="submit"><?= e(t('ai_reco_btn')) ?></button></div></form>
    <div id="recoOut" class="mx-auto mt-3" style="max-width:780px"></div>
  </div>
</div></section>

<?php if ($trainers): ?>
<section class="section section-alt" id="trainers"><div class="container">
  <div class="section-head reveal"><h2><?= e(t('trainers_title')) ?></h2></div>
  <div class="row g-4 justify-content-center">
    <?php foreach ($trainers as $tr): $av = avatar_url($tr['avatar'], $tr['full_name']); ?>
      <div class="col-6 col-lg-3 reveal"><button type="button" class="card-x trainer-card w-100 border-0" data-bs-toggle="modal" data-bs-target="#trainerModal"
          data-name="<?= e($tr['full_name']) ?>" data-img="<?= e($av) ?>" data-flag="<?= country_flag((string)$tr['country']) ?>" data-bio="<?= e(strip_tags((string)$tr['bio'])) ?>">
        <img src="<?= e($av) ?>" alt="<?= e($tr['full_name']) ?>" loading="lazy"><h4><?= e($tr['full_name']) ?> <?= country_flag((string)$tr['country']) ?></h4>
        <small><?= e(mb_substr(strip_tags((string)$tr['bio']), 0, 60)) ?>…</small></button></div>
    <?php endforeach; ?>
  </div>
</div></section>
<div class="modal fade" id="trainerModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0">
  <div class="modal-header border-0"><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body text-center px-4 pb-4 pt-0"><img id="tmImg" class="rounded-circle mb-3" style="width:120px;height:120px;object-fit:cover" alt=""><h4 class="fw-bold"><span id="tmName"></span> <span id="tmFlag"></span></h4>
  <p id="tmBio" class="text-muted text-start" style="white-space:pre-wrap"></p></div></div></div></div>
<?php endif; ?>

<?php if ($testimonials): ?>
<section class="section" id="testimonials"><div class="container">
  <div class="section-head reveal"><h2><?= e(t('testimonials_title')) ?></h2></div>
  <div class="row g-4">
    <?php foreach (array_slice($testimonials, 0, 3) as $q): ?>
      <div class="col-md-4 reveal"><div class="card-x quote"><div class="stars mb-2"><?= stars((float)$q['rating']) ?></div><p>“<?= e($q['feedback_text']) ?>”</p>
        <div class="d-flex align-items-center gap-3 mt-3"><img src="https://ui-avatars.com/api/?background=1cc88a&color=fff&bold=true&name=<?= urlencode($q['student_name']) ?>" width="44" height="44" class="rounded-circle" alt="">
        <div><b><?= e($q['student_name']) ?></b><div class="small text-muted"><?= e($q['course_name']) ?></div></div></div></div></div>
    <?php endforeach; ?>
  </div>
  <?php if ($db_reviews): ?><div class="swiper reviewsSwiper mt-5"><div class="swiper-wrapper">
    <?php foreach ($db_reviews as $img): ?><div class="swiper-slide text-center"><img src="uploads/reviews/<?= e($img) ?>" loading="lazy" class="rounded-4 border" style="max-height:380px;object-fit:contain;background:#fff" alt=""></div><?php endforeach; ?>
  </div><div class="swiper-pagination"></div></div><?php endif; ?>
</div></section>
<?php endif; ?>

<?php if ($db_gallery): ?>
<section class="section section-alt"><div class="container">
  <div class="section-head reveal"><h2><?= e(t('gallery_title')) ?></h2></div>
  <div class="swiper gallerySwiper"><div class="swiper-wrapper">
    <?php foreach ($db_gallery as $img): ?><div class="swiper-slide"><img src="uploads/gallery/<?= e($img) ?>" loading="lazy" class="w-100 rounded-4" style="height:250px;object-fit:cover" alt=""></div><?php endforeach; ?>
  </div><div class="swiper-button-next"></div><div class="swiper-button-prev"></div></div>
</div></section>
<?php endif; ?>

<section class="section"><div class="container">
  <div class="card-x p-4 p-md-5 text-center" style="background:var(--grad);color:#fff;border:0">
    <h2 class="fw-bold"><i class="fa-solid fa-certificate text-warning"></i> <?= e(t('verify_title')) ?></h2><p class="opacity-75"><?= e(t('verify_desc')) ?></p>
    <form action="verify_certificate.php" method="get" class="d-flex gap-2 justify-content-center flex-wrap mx-auto" style="max-width:560px">
      <input name="code" class="form-control form-control-lg rounded-pill text-center" dir="ltr" required maxlength="50" placeholder="<?= e(t('verify_placeholder')) ?>" style="flex:1;min-width:220px">
      <button class="btn-aramio px-4"><?= e(t('verify_btn')) ?></button></form>
  </div>
</div></section>

<?php if ($faqs): ?>
<section class="section section-alt faq"><div class="container" style="max-width:860px">
  <div class="section-head reveal"><h2><?= e(t('faq_title')) ?></h2></div>
  <div class="accordion" id="faqAcc">
  <?php foreach ($faqs as $i => $f): $l = $lang === 'ar' ? 'ar' : 'en'; ?>
    <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button <?= $i ? 'collapsed' : '' ?>" data-bs-toggle="collapse" data-bs-target="#f<?= $i ?>"><?= e($f["question_$l"]) ?></button></h3>
    <div id="f<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faqAcc"><div class="accordion-body"><?= e($f["answer_$l"]) ?></div></div></div>
  <?php endforeach; ?></div>
</div></section>
<?php endif; ?>

<div id="toasts" style="position:fixed;bottom:90px;inset-inline-end:16px;z-index:1050;display:grid;gap:8px"></div>
<?php
$headExtra = '<link rel="stylesheet" href="assets/vendor/swiper/swiper-bundle.min.css">';
$footExtra = '<script src="assets/vendor/swiper/swiper-bundle.min.js"></script><script>
document.getElementById("trainerModal")?.addEventListener("show.bs.modal",function(ev){var b=ev.relatedTarget;tmImg.src=b.dataset.img;tmName.textContent=b.dataset.name;tmFlag.textContent=b.dataset.flag;tmBio.textContent=b.dataset.bio;});
if(window.Swiper){document.querySelector(".gallerySwiper")&&new Swiper(".gallerySwiper",{slidesPerView:1.2,spaceBetween:14,loop:true,autoplay:{delay:3200},navigation:{nextEl:".swiper-button-next",prevEl:".swiper-button-prev"},breakpoints:{768:{slidesPerView:3},1100:{slidesPerView:4}}});
document.querySelector(".reviewsSwiper")&&new Swiper(".reviewsSwiper",{slidesPerView:1.3,spaceBetween:14,pagination:{el:".swiper-pagination",clickable:true},breakpoints:{768:{slidesPerView:3}}});}
var popups=' . json_encode($popups, JSON_UNESCAPED_UNICODE) . ',pi=0,delay=' . ($popup_delay * 1000) . ';
function showPopup(){if(!popups.length)return;var d=document.createElement("div");d.className="card-x p-3 d-flex gap-3 align-items-center";d.style.maxWidth="320px";
d.innerHTML=\'<span class="stat-tile ico" style="width:40px;height:40px;margin:0;padding:0;border-radius:50%;display:grid;place-items:center;background:var(--grad-accent);color:#fff"><i class="fa-solid fa-bell"></i></span><div><div class="small fw-bold"></div><small class="text-muted"></small></div>\';
d.querySelector(".fw-bold").textContent=popups[pi];d.querySelector("small").textContent=' . json_encode(t('now')) . ';toasts.appendChild(d);setTimeout(function(){d.remove()},6000);pi=(pi+1)%popups.length;}
if(popups.length)setTimeout(function(){showPopup();setInterval(showPopup,delay)},3000);
document.getElementById("recoForm").addEventListener("submit",function(ev){ev.preventDefault();var out=document.getElementById("recoOut");out.innerHTML=\'<div class="p-3"><span class="typing"><i></i><i></i><i></i></span></div>\';
window.postJSON("api/ai_recommend.php",{q:this.q.value}).then(function(r){out.innerHTML="";if(!r.ok||!r.items.length){out.innerHTML=\'<div class="ai-result">\'+(r.message||' . json_encode(t('ai_reco_none'), JSON_UNESCAPED_UNICODE) . ')+"</div>";return}
r.items.forEach(function(it){var a=document.createElement("a");a.className="ai-result text-decoration-none";a.href="course.php?id="+it.id;a.innerHTML=\'<i class="fa-solid fa-graduation-cap fs-3" style="color:var(--emerald)"></i><div><b class="d-block"></b><div class="why"></div></div>\';a.querySelector("b").textContent=it.title;a.querySelector(".why").textContent=it.why||"";out.appendChild(a)})}).catch(function(){out.innerHTML=""});});
</script>';
require 'includes/partials/footer.php';
