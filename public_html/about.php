<?php
require_once 'includes/init.php';
$A = (array)require __DIR__ . '/lang/about.' . ($lang === 'ar' ? 'ar' : 'en') . '.php';
$gallery = $pdo->query("SELECT image_name FROM gallery_images WHERE image_type='gallery' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_COLUMN);
$reviews = $pdo->query("SELECT image_name FROM gallery_images WHERE image_type='review' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_COLUMN);
$about_extra = trim((string)getSetting($pdo, 'about_us', $lang));
$programs = [
  ['fa-leaf', 'p1', ['الحجامة التشخيصية والعلاجية','التدليك العلاجي وتقنيات الاسترخاء','شمع الهوبي وتنظيف الأذن بالطاقة','الإبر الصينية والمسارات الطاقية','الموكسا (الحرارة الشافية)','العلاج بسم النحل']],
  ['fa-spa', 'p2', ['التجميل الكلاسيكي والعناية بالبشرة','التجميل اللاجراحي والتقنيات الحديثة','صناعة مواد التجميل الطبيعية منزليًا ومهنيًا']],
  ['fa-child-reaching', 'p3', ['دورة مربية أطفال محترفة','مسيرة رياض أطفال (مديرة حضانة)','منهج المنتسوري من التأسيس إلى التطبيق']],
  ['fa-medal', 'p4', ['الخبير الدولي المعتمد','المستشار الدولي المعتمد','المدرب الدولي المعتمد (CIT)','الدكتوراه الفخرية المهنية','الماجستير المهني القيادي','دبلوم إعداد المستشار الأسري']],
  ['fa-brain', 'p5', ['اختاري نفسك وازهري معي: تفعيل الطاقة الأنثوية برقي وتوازن.','أي بُنيّتي... توصيات لحياة ناعمة: للفتيات من سن 12 لبناء وعي متوازن.','اسأل تُعطى... لا مستحيل مع الله: برنامج إيماني تحويلي للنية والدعاء.','حين تنطق الندوب: التشافي من جروح الطفولة وبناء اتزان نفسي.']],
];
$specialties = ['التغذية المتوازنة','التسويق الإلكتروني','التأهيل الحركي','الطب البديل','الطب الصيني','العلوم الاستشارية','الطفولة المبكرة','استشارات الأعمال','الدراسات الإسلامية','علم النفس','العلاقات العامة','الموارد البشرية','الطب التجميلي','إدارة الأعمال'];
$active = 'about'; $pageTitle = t('about'); $metaDesc = $A['about_desc'];
$headExtra = '<link rel="stylesheet" href="assets/vendor/swiper/swiper-bundle.min.css">';
require 'includes/partials/header.php';
?>
<section class="page-hero"><div class="container"><span class="hero-badge mb-3">ARAMIO SMART GLOBAL ACADEMY</span><h1><?= e($A['about_title']) ?></h1><p class="lead opacity-75 mx-auto" style="max-width:720px"><?= e($A['about_desc']) ?></p></div></section>

<section class="section"><div class="container"><div class="row align-items-center g-5">
  <div class="col-lg-4 text-center reveal"><img src="uploads/1.jpg" onerror="this.src='https://ui-avatars.com/api/?size=320&background=0b1c3f&color=fff&name=Aramio'" class="rounded-circle shadow-lg" style="width:260px;height:260px;object-fit:cover;border:6px solid var(--surface)" alt="<?= e($A['dr_title']) ?>"></div>
  <div class="col-lg-8 reveal"><h6 class="fw-bold" style="color:var(--emerald)"><?= e($A['dr_role']) ?></h6><h2 class="fw-bold mb-3"><?= e($A['dr_title']) ?></h2>
    <p class="text-muted lh-lg"><?= $A['dr_bio'] /* نص ثابت موثوق من ملف اللغة */ ?></p>
    <blockquote class="card-x p-3 fst-italic fw-bold" style="border-inline-start:5px solid var(--emerald)"><?= e($A['dr_quote']) ?></blockquote>
    <h6 class="fw-bold mt-4"><i class="fa-solid fa-certificate text-warning"></i> <?= e($A['dr_certs']) ?></h6>
    <ul class="list-unstyled text-muted"><li><i class="fa-solid fa-check text-success"></i> <?= e($A['cert_1']) ?></li><li><i class="fa-solid fa-check text-success"></i> <?= e($A['cert_2']) ?></li></ul></div>
</div></div></section>

<?php if ($about_extra): ?><section class="section pt-0"><div class="container" style="max-width:860px"><div class="card-x p-4" style="white-space:pre-wrap"><?= e(strip_tags($about_extra)) ?></div></div></section><?php endif; ?>

<section class="section section-alt"><div class="container"><div class="section-head"><h2><?= e($A['val_title']) ?></h2></div><div class="row g-3 justify-content-center">
  <?php foreach ([['fa-star',1],['fa-rocket',2],['fa-hands-holding-child',3],['fa-gem',4],['fa-globe',5]] as [$ic,$i]): ?>
    <div class="col-6 col-md-4 col-lg-2 reveal"><div class="card-x feat text-center"><div class="ico mx-auto"><i class="fa-solid <?= $ic ?>"></i></div><h3 class="h6"><?= e($A["v$i"]) ?></h3><p class="small"><?= e($A["v{$i}_sub"]) ?></p></div></div>
  <?php endforeach; ?></div></div></section>

<section class="section"><div class="container" style="max-width:900px"><div class="section-head"><h2><?= e($A['prog_title']) ?></h2><p><?= e($A['prog_desc']) ?></p></div>
  <div class="accordion faq" id="progAcc">
  <?php foreach ($programs as $i => [$ic, $k, $items]): ?>
    <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button <?= $i ? 'collapsed' : '' ?>" data-bs-toggle="collapse" data-bs-target="#pg<?= $i ?>"><i class="fa-solid <?= $ic ?> mx-3" style="color:var(--emerald)"></i><?= e($A[$k]) ?></button></h3>
    <div id="pg<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#progAcc"><div class="accordion-body"><ul class="row list-unstyled mb-0"><?php foreach ($items as $it): ?><li class="col-md-6 mb-2"><i class="fa-solid fa-check text-success mx-2"></i><?= e($it) ?></li><?php endforeach; ?></ul></div></div></div>
  <?php endforeach; ?></div></div></section>

<section class="section section-alt text-center"><div class="container"><div class="section-head"><h2><?= e($A['spec_title']) ?></h2></div>
  <div class="d-flex flex-wrap justify-content-center gap-2"><?php foreach ($specialties as $s): ?><span class="badge-cat fs-6 py-2 px-3"><?= e($s) ?></span><?php endforeach; ?></div>
  <a href="courses.php" class="btn-aramio mt-5 px-5 py-2 fs-5"><?= e($A['btn_start']) ?></a></div></section>

<?php if ($reviews): ?><section class="section"><div class="container"><div class="section-head"><h2><?= e(t('testimonials_title')) ?></h2></div><div class="swiper reviewsSwiper"><div class="swiper-wrapper">
  <?php foreach ($reviews as $img): ?><div class="swiper-slide text-center"><img src="uploads/reviews/<?= e($img) ?>" loading="lazy" class="rounded-4 border" style="max-height:380px;object-fit:contain;background:#fff" alt=""></div><?php endforeach; ?></div><div class="swiper-pagination"></div></div></div></section><?php endif; ?>
<?php if ($gallery): ?><section class="section section-alt"><div class="container"><div class="section-head"><h2><?= e(t('gallery_title')) ?></h2></div><div class="swiper gallerySwiper"><div class="swiper-wrapper">
  <?php foreach ($gallery as $img): ?><div class="swiper-slide"><img src="uploads/gallery/<?= e($img) ?>" loading="lazy" class="w-100 rounded-4" style="height:250px;object-fit:cover" alt=""></div><?php endforeach; ?></div></div></div></section><?php endif; ?>
<?php
$footExtra = '<script src="assets/vendor/swiper/swiper-bundle.min.js"></script><script>
document.querySelector(".gallerySwiper")&&new Swiper(".gallerySwiper",{slidesPerView:1.2,spaceBetween:14,loop:true,autoplay:{delay:3000},breakpoints:{768:{slidesPerView:3},1100:{slidesPerView:4}}});
document.querySelector(".reviewsSwiper")&&new Swiper(".reviewsSwiper",{slidesPerView:1.3,spaceBetween:14,pagination:{el:".swiper-pagination",clickable:true},breakpoints:{768:{slidesPerView:3}}});</script>';
require 'includes/partials/footer.php';
