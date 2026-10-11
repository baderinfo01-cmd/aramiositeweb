<?php
require_once 'trainee_init.php';

/* ---------------- الإجراءات (POST) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $act = $_POST['act'] ?? ''; $back = 'my_courses.php';
    if ($act === 'pay') {
        $oid = (int)$_POST['order_id'];
        $o = $pdo->prepare("SELECT o.id, c.price_usd, c.price_dzd FROM course_orders o JOIN courses c ON c.id=o.course_id WHERE o.id=? AND o.user_id=? AND o.status='reserved'"); $o->execute([$oid, $user_id]); $o = $o->fetch();
        $cur = $_POST['currency'] ?? '';
        if (!$o) flash('err', d('الحجز غير متاح أو انتهت مهلته.', 'Booking unavailable or expired.'));
        elseif (!in_array($_POST['payment_method'] ?? '', ['bank', 'paypal', 'agent'], true) || !in_array($cur, ['usd', 'dzd'], true) || (float)$o["price_$cur"] <= 0) flash('err', t('err_required'));
        elseif (!($f = pay_receipt_save())) flash('err', d('ارفع صورة/PDF واضح للوصل (حتى 6MB).', 'Upload a clear image/PDF receipt (≤6MB).'));
        else {
            $pdo->prepare("UPDATE course_orders SET receipt_image=?, payment_method=?, amount_usd=?, amount_dzd=?, status='pending' WHERE id=? AND user_id=? AND status='reserved'")
                ->execute([$f, $_POST['payment_method'], $cur === 'usd' ? $o['price_usd'] : null, $cur === 'dzd' ? $o['price_dzd'] : null, $oid, $user_id]);
            foreach ($pdo->query("SELECT id FROM users WHERE role IN ('admin','staff')")->fetchAll(PDO::FETCH_COLUMN) as $aid) notify_user((int)$aid, d('إيصال دفع جديد', 'New payment receipt'), $me['full_name'], 'financial.php?s=pending');
            flash('ok', d('تم رفع الإيصال، حجزك الآن قيد المراجعة.', 'Receipt uploaded; your booking is under review.'));
        }
    } elseif ($act === 'complete') {
        $cid = (int)$_POST['course_id']; $back = 'my_courses.php?course_id=' . $cid . '&lesson_id=' . (int)$_POST['lesson_id'];
        if (owns_course($user_id, $cid)) $pdo->prepare("INSERT IGNORE INTO lesson_completions (trainee_id, lesson_id) SELECT ?, id FROM lessons WHERE id=? AND course_id=?")->execute([$user_id, (int)$_POST['lesson_id'], $cid]);
    } elseif ($act === 'cert') {
        $cid = (int)$_POST['course_id']; $back = 'my_courses.php?course_id=' . $cid;
        $c = $pdo->prepare("SELECT trainer_id, title_ar FROM courses WHERE id=?"); $c->execute([$cid]); $c = $c->fetch();
        $ex = $pdo->prepare("SELECT id FROM certificate_exams WHERE trainee_id=? AND course_id=? AND status IN ('pending','passed')"); $ex->execute([$user_id, $cid]);
        if (!$c || !owns_course($user_id, $cid)) flash('err', 'forbidden');
        elseif ($ex->fetch()) flash('warn', d('لديك طلب شهادة قائم لهذه الدورة.', 'You already have a certificate request for this course.'));
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['exam_date'] ?? '')) flash('err', t('err_required'));
        else {
            $pdo->prepare("INSERT INTO certificate_exams (trainee_id, course_id, trainer_id, exam_date, notes) VALUES (?,?,?,?,?)")->execute([$user_id, $cid, $c['trainer_id'], $_POST['exam_date'], mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 1000)]);
            notify_user((int)$c['trainer_id'], d('طلب شهادة جديد', 'New certificate request'), $me['full_name'] . ' — ' . $c['title_ar'], 'certificates.php');
            flash('ok', d('تم إرسال طلب الشهادة للمدرب.', 'Certificate request sent to the trainer.'));
        }
    } elseif ($act === 'review') {
        $cid = (int)$_POST['course_id']; $back = 'my_courses.php?course_id=' . $cid;
        if (owns_course($user_id, $cid)) {
            $rate = max(1, min(5, (int)$_POST['rating'])); $img = null;
            if (!empty($_FILES['review_image']['name'])) { $img = save_upload('review_image', 'reviews'); if ($img) $pdo->prepare("INSERT INTO gallery_images (image_name, image_type) VALUES (?, 'review')")->execute([$img]); }
            $ex = $pdo->prepare("SELECT id FROM course_reviews WHERE course_id=? AND trainee_id=?"); $ex->execute([$cid, $user_id]);
            if ($rid = $ex->fetchColumn()) $pdo->prepare("UPDATE course_reviews SET rating=?, comment=?, review_image=COALESCE(?, review_image) WHERE id=?")->execute([$rate, mb_substr(trim((string)$_POST['comment']), 0, 1000), $img, $rid]);
            else $pdo->prepare("INSERT INTO course_reviews (course_id, trainee_id, rating, comment, review_image) VALUES (?,?,?,?,?)")->execute([$cid, $user_id, $rate, mb_substr(trim((string)$_POST['comment']), 0, 1000), $img]);
            flash('ok', d('شكرًا على تقييمك!', 'Thanks for your review!'));
        }
    }
    redirect($back);
}

/* ---------------- وضع المشاهدة ---------------- */
$playing = isset($_GET['course_id']);
if ($playing) {
    $cid = (int)$_GET['course_id'];
    if (!owns_course($user_id, $cid)) redirect('my_courses.php');
    $c = $pdo->prepare("SELECT c.*, u.full_name AS trainer_name FROM courses c JOIN users u ON u.id=c.trainer_id WHERE c.id=?"); $c->execute([$cid]); $course = $c->fetch();
    $lessons = []; $active = null; $doneIds = [];
    if ($course['training_type'] === 'online') {
        $l = $pdo->prepare("SELECT * FROM lessons WHERE course_id=? ORDER BY lesson_order, id"); $l->execute([$cid]); $lessons = $l->fetchAll();
        foreach ($lessons as $x) if (isset($_GET['lesson_id']) && (int)$_GET['lesson_id'] === (int)$x['id']) $active = $x;
        if (!$active && $lessons) $active = $lessons[0];
        $d = $pdo->prepare("SELECT lesson_id FROM lesson_completions WHERE trainee_id=?"); $d->execute([$user_id]); $doneIds = $d->fetchAll(PDO::FETCH_COLUMN);
    }
    [$total, $done, $pct] = course_progress($user_id, $cid);
    $cert = $pdo->prepare("SELECT status, certificate_code FROM certificate_exams WHERE trainee_id=? AND course_id=? ORDER BY id DESC LIMIT 1"); $cert->execute([$user_id, $cid]); $cert = $cert->fetch();
    $quizzes = $pdo->prepare("SELECT q.id, q.title, (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=q.id) nq, (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id=q.id AND trainee_id=?) best FROM quizzes q WHERE q.course_id=?"); $quizzes->execute([$user_id, $cid]); $quizzes = $quizzes->fetchAll();
    $myRev = $pdo->prepare("SELECT rating, comment FROM course_reviews WHERE course_id=? AND trainee_id=?"); $myRev->execute([$cid, $user_id]); $myRev = $myRev->fetch();
    $dashTitle = tr_field($course, 'title', 'course'); $dashActive = 'my_courses.php';
} else {
    $st = $pdo->prepare("SELECT o.id AS oid, o.status, o.amount_usd, o.amount_dzd, o.receipt_image, TIMESTAMPDIFF(SECOND, o.created_at, NOW()) AS secs, c.id AS cid, c.title_ar, c.title_en, c.price_usd, c.price_dzd, c.training_type, c.start_date, c.city, u.full_name AS trainer_name
        FROM course_orders o JOIN courses c ON c.id=o.course_id JOIN users u ON u.id=c.trainer_id WHERE o.user_id=? AND o.status IN ('reserved','pending','rejected') ORDER BY o.created_at DESC"); $st->execute([$user_id]); $waiting = $st->fetchAll();
    $ap = $pdo->prepare("SELECT c.*, u.full_name AS trainer_name FROM course_orders o JOIN courses c ON c.id=o.course_id JOIN users u ON u.id=c.trainer_id WHERE o.user_id=? AND o.status='approved' ORDER BY o.created_at DESC"); $ap->execute([$user_id]); $approved = $ap->fetchAll();
    $dashTitle = d('دوراتي', 'My courses');
}
require '../includes/partials/dash_header.php';
?>
<?php if (!$playing): ?>
  <?php foreach ($waiting as $w): ?>
  <div class="dcard mb-3 <?= $w['status'] === 'reserved' ? 'border-warning' : '' ?>"><div class="dcard-b"><div class="row g-3 align-items-start">
    <div class="col-lg-5"><div class="d-flex justify-content-between"><b class="fs-5"><?= e($w['title_ar']) ?></b><?= status_badge($w['status']) ?></div><div class="small text-muted"><i class="fa-solid fa-user-tie"></i> <?= e($w['trainer_name']) ?></div>
      <?php if ($w['status'] === 'reserved'): $left = max(0, 3600 - (int)$w['secs']); ?><div class="mt-2 alert alert-warning py-2 small mb-0"><i class="fa-solid fa-clock"></i> <?= e(d('الوقت المتبقي لتأكيد الدفع:', 'Time left to pay:')) ?> <b class="cd" data-left="<?= $left ?>">--:--</b></div>
      <?php elseif ($w['status'] === 'pending'): ?><p class="small text-muted mt-2 mb-0"><?= e(d('تم استلام الوصل وهو قيد المراجعة من الإدارة.', 'Receipt received, under admin review.')) ?></p>
      <?php else: ?><p class="small text-danger mt-2 mb-0"><?= e(d('رُفض الوصل. يمكنك العودة للمتجر وإعادة الحجز.', 'Receipt rejected. You can book again from the store.')) ?></p><a class="btn-soft btn-soft-info mt-2" href="store.php"><?= e(d('المتجر', 'Store')) ?></a><?php endif; ?></div>
    <?php if ($w['status'] === 'reserved'): ?><div class="col-lg-7"><?php pay_form('order', (int)$w['oid'], (float)$w['price_usd'], (float)$w['price_dzd'], 'o' . $w['oid']); ?></div><?php endif; ?></div></div></div>
  <?php endforeach; ?>
  <div class="row g-3"><?php foreach ($approved as $c): [$tl, $dn, $pc] = course_progress($user_id, (int)$c['id']); $th = course_thumb($c); ?><div class="col-md-6 col-xl-4"><div class="dcard h-100 overflow-hidden d-flex flex-column">
    <div style="aspect-ratio:16/7;background:var(--grad)"><?php if ($th): ?><img src="../<?= e($th) ?>" class="w-100 h-100" style="object-fit:cover" alt=""><?php endif; ?></div>
    <div class="dcard-b d-flex flex-column flex-grow-1"><h5 class="fw-bold"><?= e(tr_field($c, 'title', 'course')) ?></h5><div class="small text-muted mb-2"><i class="fa-solid fa-user-tie"></i> <?= e($c['trainer_name']) ?> · <?= e(type_label($c['training_type'])) ?></div>
      <?php if ($c['training_type'] === 'online'): ?><div class="progress mb-1"><div class="progress-bar" style="width:<?= $pc ?>%"></div></div><div class="small text-muted mb-3"><?= $dn ?>/<?= $tl ?> <?= e(t('lessons')) ?> · <?= $pc ?>%</div><?php else: ?><div class="small mb-3"><i class="fa-regular fa-calendar"></i> <?= e($c['start_date']) ?></div><?php endif; ?>
      <a class="btn-aramio mt-auto text-center" href="my_courses.php?course_id=<?= (int)$c['id'] ?>"><i class="fa-solid fa-play"></i> <?= e($pc > 0 ? d('تابع التعلّم', 'Continue') : d('ابدأ', 'Start')) ?></a></div></div></div><?php endforeach; ?>
  <?php if (!$approved && !$waiting): ?><div class="col-12"><div class="dcard"><div class="empty"><i class="fa-solid fa-book-open-reader"></i><p><?= e(d('لم تشترك في أي دورة بعد.', 'You have no courses yet.')) ?></p><a class="btn-aramio" href="store.php"><?= e(d('تصفّح الدورات', 'Browse courses')) ?></a></div></div></div><?php endif; ?></div>

<?php else: ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><a href="my_courses.php" class="btn-soft btn-soft-info py-2"><i class="fa-solid fa-arrow-<?= $dir === 'rtl' ? 'right' : 'left' ?>"></i> <?= e(t('back')) ?></a>
    <div class="d-flex gap-2"><button class="btn-soft btn-soft-warn py-2" data-bs-toggle="modal" data-bs-target="#revModal"><i class="fa-solid fa-star"></i> <?= e($myRev ? d('عدّل تقييمي', 'Edit my review') : d('قيّم الدورة', 'Rate course')) ?></button>
      <?php if ($cert && $cert['status'] === 'passed'): ?><a class="btn-aramio py-1" target="_blank" href="../certificate.php?code=<?= urlencode($cert['certificate_code']) ?>"><i class="fa-solid fa-award"></i> <?= e(t('cert_print')) ?></a>
      <?php elseif ($cert && $cert['status'] === 'pending'): ?><span class="st st-warn py-2"><?= e(d('طلب الشهادة قيد المراجعة', 'Certificate request pending')) ?></span>
      <?php else: ?><button class="btn-aramio py-1" data-bs-toggle="modal" data-bs-target="#certModal"><i class="fa-solid fa-award"></i> <?= e(d('اطلب الشهادة', 'Request certificate')) ?></button><?php endif; ?></div></div>
  <div class="row g-3">
    <div class="col-lg-8">
      <?php if ($course['training_type'] === 'online'): ?>
        <?php if ($active): [$vt, $vs] = video_embed($active['video_url']); ?>
          <div class="dcard overflow-hidden mb-3"><?php if ($vt === 'iframe'): ?><div class="ratio ratio-16x9"><iframe src="<?= e($vs) ?>" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" allow="fullscreen; picture-in-picture; encrypted-media"></iframe></div>
            <?php elseif ($vt === 'video'): ?><video class="w-100" controls controlsList="nodownload" src="<?= e($vs) ?>"></video>
            <?php elseif ($vt === 'link'): ?><div class="empty"><i class="fa-solid fa-link"></i><a class="btn-aramio" target="_blank" rel="noopener noreferrer" href="<?= e($vs) ?>"><?= e(d('افتح الدرس في نافذة جديدة', 'Open lesson in a new tab')) ?></a></div><?php endif; ?>
            <div class="dcard-b d-flex justify-content-between align-items-center flex-wrap gap-2"><h5 class="m-0 fw-bold"><?= e(tr_field($active, 'title')) ?></h5>
              <?php if (in_array($active['id'], $doneIds)): ?><span class="st st-ok"><i class="fa-solid fa-check"></i> <?= e(d('مكتمل', 'Completed')) ?></span><?php else: ?><form method="post" class="m-0"><?= csrf_field() ?><input type="hidden" name="act" value="complete"><input type="hidden" name="course_id" value="<?= $cid ?>"><input type="hidden" name="lesson_id" value="<?= (int)$active['id'] ?>"><button class="btn-soft btn-soft-ok"><i class="fa-solid fa-check"></i> <?= e(d('أنهيت الدرس', 'Mark complete')) ?></button></form><?php endif; ?></div></div>
        <?php else: ?><div class="dcard"><div class="empty"><i class="fa-solid fa-video-slash"></i><?= e(d('لم يضف المدرب دروسًا بعد.', 'No lessons yet.')) ?></div></div><?php endif; ?>
      <?php else: ?>
        <div class="dcard mb-3"><div class="dcard-b"><h5 class="fw-bold"><i class="fa-solid <?= type_icon($course['training_type']) ?>"></i> <?= e(type_label($course['training_type'])) ?></h5>
          <ul class="list-unstyled d-grid gap-2 mb-0"><li><i class="fa-regular fa-calendar"></i> <?= e($course['start_date']) ?> <?= $course['live_time'] ? '<b dir="ltr">' . e(substr($course['live_time'], 0, 5)) . '</b>' : '' ?></li>
          <?php if ($course['training_type'] === 'online_live' && url_ok_pub($course['live_link'])): ?><li><a class="btn-aramio" target="_blank" rel="noopener noreferrer" href="<?= e($course['live_link']) ?>"><i class="fa-solid fa-video"></i> <?= e(d('انضم للبث المباشر', 'Join live session')) ?></a></li><?php endif; ?>
          <?php if ($course['training_type'] === 'offline'): ?><li><i class="fa-solid fa-location-dot"></i> <?= e($course['city'] . ' — ' . $course['venue_name']) ?></li><?php if (url_ok_pub($course['map_link'])): ?><li><a target="_blank" rel="noopener noreferrer" href="<?= e($course['map_link']) ?>"><i class="fa-solid fa-map"></i> <?= e(d('الموقع على الخريطة', 'Open map')) ?></a></li><?php endif; endif; ?></ul></div></div>
      <?php endif; ?>

      <ul class="nav nav-pills gap-1 mb-3"><li class="nav-item"><button class="nav-link active" style="background:var(--emerald)" data-bs-toggle="pill" data-bs-target="#tTutor"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= e(d('المعلّم الذكي', 'AI Tutor')) ?></button></li>
        <li class="nav-item"><button class="nav-link" style="color:var(--text)" data-bs-toggle="pill" data-bs-target="#tQuiz"><i class="fa-solid fa-list-check"></i> <?= e(d('الاختبارات', 'Quizzes')) ?> <span class="badge bg-secondary bg-opacity-25 text-reset"><?= count($quizzes) ?></span></button></li></ul>
      <div class="tab-content"><div class="tab-pane fade show active" id="tTutor"><div class="dcard"><div class="dcard-b">
        <div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ([[d('لخّص هذا الدرس', 'Summarise this lesson'), 'summary'], [d('اشرح لي ببساطة', 'Explain simply'), 'explain'], [d('اختبرني بـ 5 أسئلة', 'Quiz me (5 Qs)'), 'quiz'], [d('خطة دراسة أسبوعية', 'Weekly study plan'), 'plan']] as [$lb, $m]): ?><button class="btn-soft btn-soft-info tutor-q" data-mode="<?= $m ?>"><?= e($lb) ?></button><?php endforeach; ?></div>
        <div id="tOut" class="ai-box" style="white-space:pre-wrap;min-height:90px"><?= e(ai_available() ? d('اختر إجراءً أو اكتب سؤالك أدناه.', 'Pick an action or type your question below.') : t('ai_unavailable')) ?></div>
        <form id="tForm" class="d-flex gap-2 mt-3"><input id="tQ" class="form-control" maxlength="500" placeholder="<?= e(t('ai_placeholder')) ?>"><button class="btn-aramio"><i class="fa-solid fa-paper-plane"></i></button></form></div></div></div>
      <div class="tab-pane fade" id="tQuiz"><div class="dcard"><div class="dcard-b"><?php foreach ($quizzes as $q): ?><div class="d-flex justify-content-between align-items-center border-bottom py-2"><div><b><?= e($q['title']) ?></b><div class="small text-muted"><?= (int)$q['nq'] ?> <?= e(d('سؤال', 'questions')) ?><?= $q['best'] !== null ? ' · ' . e(d('أفضل نتيجة', 'Best')) . ' ' . (int)$q['best'] . '%' : '' ?></div></div><a class="btn-soft btn-soft-ok" href="quiz.php?id=<?= (int)$q['id'] ?>"><i class="fa-solid fa-play"></i> <?= e(d('ابدأ', 'Start')) ?></a></div><?php endforeach; ?>
        <?php if (!$quizzes): ?><div class="empty"><i class="fa-solid fa-list-check"></i><?= e(d('لا اختبارات لهذه الدورة بعد.', 'No quizzes yet.')) ?></div><?php endif; ?></div></div></div></div>
    </div>
    <div class="col-lg-4"><div class="dcard"><div class="dcard-h"><?= e(t('lessons')) ?> <span class="st st-ok"><?= $pct ?>%</span></div><div class="px-3 pt-3"><div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div></div>
      <div class="list-group list-group-flush mt-2" style="max-height:520px;overflow:auto"><?php foreach ($lessons as $i => $l): $isDone = in_array($l['id'], $doneIds); ?><a class="list-group-item d-flex gap-2 align-items-center <?= $active && $active['id'] === $l['id'] ? 'active' : '' ?>" href="?course_id=<?= $cid ?>&lesson_id=<?= (int)$l['id'] ?>"><i class="fa-<?= $isDone ? 'solid fa-circle-check text-success' : 'regular fa-circle' ?>"></i><span><?= ($i + 1) . '. ' . e(tr_field($l, 'title')) ?></span></a><?php endforeach; ?>
      <?php if (!$lessons): ?><div class="empty py-4"><i class="fa-solid fa-video"></i></div><?php endif; ?></div></div></div>
  </div>

  <div class="modal fade" id="certModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="cert"><input type="hidden" name="course_id" value="<?= $cid ?>"><div class="modal-header"><h5 class="modal-title fw-bold"><?= e(d('طلب شهادة / موعد امتحان', 'Certificate / exam request')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body d-grid gap-2"><?php if ($course['training_type'] === 'online' && $pct < 100): ?><div class="alert alert-warning small mb-0"><?= e(d("تقدّمك $pct% — يُفضّل إنهاء كل الدروس قبل الطلب.", "Your progress is $pct% — finishing all lessons first is recommended.")) ?></div><?php endif; ?><label class="form-label small fw-bold"><?= e(d('موعدك المقترح للامتحان', 'Preferred exam date')) ?></label><input type="date" name="exam_date" class="form-control" required min="<?= date('Y-m-d') ?>"><textarea name="notes" rows="3" class="form-control" placeholder="<?= e(d('ملاحظات للمدرب', 'Notes for the trainer')) ?>"></textarea></div><div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(t('send')) ?></button></div></form></div></div>
  <div class="modal fade" id="revModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" enctype="multipart/form-data" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="review"><input type="hidden" name="course_id" value="<?= $cid ?>"><div class="modal-header"><h5 class="modal-title fw-bold"><?= e(d('قيّم هذه الدورة', 'Rate this course')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body d-grid gap-2"><select name="rating" class="form-select"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= ($myRev['rating'] ?? 5) == $i ? 'selected' : '' ?>><?= str_repeat('★', $i) ?></option><?php endfor; ?></select><textarea name="comment" rows="3" class="form-control" placeholder="<?= e(d('رأيك…', 'Your feedback…')) ?>"><?= e($myRev['comment'] ?? '') ?></textarea><label class="small fw-bold"><?= e(d('صورة (اختياري)', 'Image (optional)')) ?></label><input type="file" name="review_image" accept="image/jpeg,image/png,image/webp" class="form-control"></div><div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(t('save')) ?></button></div></form></div></div>
<?php endif; ?>
<?php
$tutorJs = '';
if ($playing) {
    $tutorJs = '<script>(function(){var cid=' . (int)$cid . ',lid=' . (int)($active['id'] ?? 0) . ',out=document.getElementById("tOut");
function ask(p,btn){out.textContent=' . json_encode(t('loading')) . ';AI_ASK("../api/ai_tutor.php",Object.assign({course_id:cid,lesson_id:lid},p),function(r){out.textContent=r.ok?r.text:(r.message||"error")},btn)}
document.querySelectorAll(".tutor-q").forEach(function(b){b.addEventListener("click",function(){ask({mode:this.dataset.mode},this)})});
tForm.addEventListener("submit",function(e){e.preventDefault();if(!tQ.value.trim())return;ask({mode:"chat",q:tQ.value});tQ.value=""});})();</script>';
}
$footExtra = pay_js() . $tutorJs . '<script>document.querySelectorAll(".cd").forEach(function(el){var s=parseInt(el.dataset.left,10);(function t(){if(s<=0){location.reload();return}el.textContent=String(Math.floor(s/60)).padStart(2,"0")+":"+String(s%60).padStart(2,"0");s--;setTimeout(t,1000)})()});</script>';
require '../includes/partials/dash_footer.php';
