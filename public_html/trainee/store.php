<?php
require_once 'trainee_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_course'])) {
    require_post_csrf();
    $cid = (int)$_POST['course_id'];
    $c = $pdo->prepare("SELECT id, price_usd, price_dzd, training_type, max_seats, title_ar FROM courses WHERE id=? AND status='published'"); $c->execute([$cid]); $c = $c->fetch();
    $dup = $pdo->prepare("SELECT status FROM course_orders WHERE user_id=? AND course_id=? AND status IN ('approved','pending','reserved')"); $dup->execute([$user_id, $cid]);
    if (!$c) flash('err', t('page_not_found'));
    elseif ($dup->fetch()) flash('warn', d('لديك حجز أو اشتراك قائم لهذه الدورة.', 'You already have a booking or enrolment for this course.'));
    else {
        $full = false;
        if ($c['training_type'] !== 'online' && (int)$c['max_seats'] > 0) { $b = $pdo->prepare("SELECT COUNT(*) FROM course_orders WHERE course_id=? AND status IN ('approved','reserved','pending')"); $b->execute([$cid]); $full = (int)$b->fetchColumn() >= (int)$c['max_seats']; }
        if ($full) flash('err', d('عذرًا، المقاعد مكتملة.', 'Sorry, the seats are full.'));
        else {
            $free = (float)$c['price_usd'] <= 0 && (float)$c['price_dzd'] <= 0;
            $pdo->prepare("INSERT INTO course_orders (user_id, course_id, amount_usd, amount_dzd, receipt_image, status) VALUES (?,?,?,?,?,?)")->execute([$user_id, $cid, $c['price_usd'], $c['price_dzd'], '', $free ? 'approved' : 'reserved']);
            flash('ok', $free ? d('تم تفعيل الدورة المجانية!', 'Free course activated!') : d('تم حجز مقعدك! ارفع إيصال الدفع خلال 60 دقيقة من "دوراتي".', 'Seat reserved! Upload your payment receipt within 60 minutes from "My courses".'));
            redirect('my_courses.php');
        }
    }
    redirect('store.php');
}
$all = $pdo->query("SELECT c.*, u.full_name AS trainer_name, cat.name_ar AS cat_ar, cat.name_en AS cat_en,
    (SELECT COALESCE(AVG(rating),0) FROM course_reviews WHERE course_id=c.id) AS avg_rating, (SELECT COUNT(*) FROM course_reviews WHERE course_id=c.id) AS reviews_count
    FROM courses c JOIN users u ON c.trainer_id=u.id JOIN categories cat ON c.category_id=cat.id WHERE c.status='published' ORDER BY c.created_at DESC")->fetchAll();
$mine = $pdo->prepare("SELECT course_id, status FROM course_orders WHERE user_id=?"); $mine->execute([$user_id]); $orders = [];
foreach ($mine->fetchAll() as $o) $orders[$o['course_id']] = $o['status'];
$dashTitle = d('متجر الدورات', 'Course store');
require '../includes/partials/dash_header.php';
?>
<div class="row g-3"><?php foreach ($all as $c): $st = $orders[$c['id']] ?? null; $th = course_thumb($c); ?><div class="col-md-6 col-xl-4"><div class="dcard h-100 overflow-hidden d-flex flex-column">
  <div style="aspect-ratio:16/8;background:var(--grad);position:relative"><?php if ($th): ?><img src="../<?= e($th) ?>" class="w-100 h-100" style="object-fit:cover" alt=""><?php endif; ?><span class="badge-type"><?= e(type_label($c['training_type'])) ?><?= $c['city'] && $c['training_type'] === 'offline' ? ' · ' . e($c['city']) : '' ?></span></div>
  <div class="dcard-b d-flex flex-column flex-grow-1"><div class="d-flex justify-content-between"><span class="badge-cat"><?= e($lang === 'ar' ? $c['cat_ar'] : ($c['cat_en'] ?: $c['cat_ar'])) ?></span><span class="small text-warning fw-bold"><?= number_format((float)$c['avg_rating'], 1) ?> <i class="fa-solid fa-star"></i></span></div>
    <h5 class="fw-bold my-2"><?= e(tr_field($c, 'title', 'course')) ?></h5><div class="small text-muted mb-3"><i class="fa-solid fa-user-tie"></i> <?= e($c['trainer_name']) ?></div>
    <div class="mt-auto d-flex justify-content-between align-items-center border-top pt-3"><b class="text-success"><?= (float)$c['price_usd'] > 0 || (float)$c['price_dzd'] > 0 ? e(formatPrice($c['price_usd'], $c['price_dzd'])) : e(t('free')) ?></b>
      <div class="d-flex gap-1"><a class="btn-soft btn-soft-info" target="_blank" href="../course.php?id=<?= (int)$c['id'] ?>"><i class="fa-solid fa-circle-info"></i></a>
      <?php if ($st === 'approved'): ?><a class="btn-soft btn-soft-ok" href="my_courses.php?course_id=<?= (int)$c['id'] ?>"><i class="fa-solid fa-play"></i> <?= e(d('ابدأ', 'Start')) ?></a>
      <?php elseif ($st === 'pending'): ?><span class="st st-info"><?= e(d('قيد المراجعة', 'In review')) ?></span>
      <?php elseif ($st === 'reserved'): ?><a class="btn-soft btn-soft-warn" href="my_courses.php"><i class="fa-solid fa-upload"></i> <?= e(d('أكمل الدفع', 'Pay now')) ?></a>
      <?php else: ?><form method="post" class="m-0"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>"><button class="btn-aramio py-1" name="reserve_course" value="1"><i class="fa-solid fa-cart-shopping"></i> <?= e(t('enroll_now')) ?></button></form><?php endif; ?></div></div></div></div></div><?php endforeach; ?>
<?php if (!$all): ?><div class="empty"><i class="fa-solid fa-store"></i><?= e(t('no_courses')) ?></div><?php endif; ?></div>
<?php require '../includes/partials/dash_footer.php';
