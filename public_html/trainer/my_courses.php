<?php
require_once 'trainer_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $type = in_array($_POST['training_type'] ?? '', ['online', 'offline', 'online_live'], true) ? $_POST['training_type'] : 'online';
        $tAr = trim((string)$_POST['title_ar']); $tEn = trim((string)($_POST['title_en'] ?? '')) ?: $tAr;
        $dAr = trim((string)$_POST['description_ar']); $dEn = trim((string)($_POST['description_en'] ?? '')) ?: $dAr;
        $cat = (int)$_POST['category_id'];
        $usd = (isset($_POST['price_usd']) && $_POST['price_usd'] !== '' && (float)$_POST['price_usd'] >= 0) ? (float)$_POST['price_usd'] : null;
        $dzd = (isset($_POST['price_dzd']) && $_POST['price_dzd'] !== '' && (float)$_POST['price_dzd'] >= 0) ? (float)$_POST['price_dzd'] : null;
        $start = $type !== 'online' ? trim((string)($_POST['start_date'] ?? '')) : null; $seats = $type !== 'online' ? max(0, (int)($_POST['max_seats'] ?? 0)) : 0;
        $ltime = $type === 'online_live' ? trim((string)($_POST['live_time'] ?? '')) : null; $llink = $type === 'online_live' ? trim((string)($_POST['live_link'] ?? '')) : null;
        $city = $type === 'offline' ? trim((string)($_POST['city'] ?? '')) : null; $venue = $type === 'offline' ? trim((string)($_POST['venue_name'] ?? '')) : null; $map = $type === 'offline' ? trim((string)($_POST['map_link'] ?? '')) : null;
        $err = '';
        if ($tAr === '' || $cat <= 0) $err = t('err_required');
        elseif ($usd === null && $dzd === null) $err = d('حدد سعر الدورة بالدولار أو الدينار (0 = مجانية).', 'Set a price in USD or DZD (0 = free).');
        elseif ($type === 'offline' && ($city === '' || $venue === '' || !$start)) $err = d('أكمل بيانات التدريب الحضوري (المدينة، القاعة، تاريخ البداية).', 'Complete the in-person details (city, venue, start date).');
        elseif ($type === 'online_live' && (!$start || !$ltime || !url_ok((string)$llink))) $err = d('أكمل بيانات البث (التاريخ، الوقت، رابط صالح).', 'Complete live details (date, time, valid link).');
        elseif ($map && !url_ok($map)) $err = d('رابط الخريطة غير صالح.', 'Invalid map link.');
        if ($err) flash('err', $err);
        else {
            $thumb = 'default_course.jpg';
            if (!empty($_FILES['thumbnail']['name'])) { $n = save_upload('thumbnail', 'courses'); if ($n) $thumb = $n; else flash('warn', d('تعذّر رفع الصورة (JPG/PNG/WEBP حتى 5MB). سُجّلت الدورة بدونها.', 'Image upload failed (JPG/PNG/WEBP ≤5MB). Course saved without it.')); }
            $pdo->prepare("INSERT INTO courses (trainer_id, category_id, training_type, city, venue_name, map_link, max_seats, start_date, live_time, live_link, title_ar, title_en, description_ar, description_en, price_usd, price_dzd, thumbnail, status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pending')")->execute([$trainer_id, $cat, $type, $city, $venue, $map, $seats, $start, $ltime, $llink, $tAr, $tEn, $dAr, $dEn, $usd, $dzd, $thumb]);
            foreach ($pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN) as $aid) notify_user((int)$aid, d('دورة جديدة بانتظار المراجعة', 'New course awaiting review'), $tAr, 'courses.php?s=pending');
            flash('ok', d('تمت إضافة الدورة وهي بانتظار موافقة الإدارة.', 'Course submitted for admin review.'));
        }
    } elseif ($act === 'delete') {
        $cid = (int)$_POST['course_id'];
        $n = $pdo->prepare("SELECT COUNT(*) FROM course_orders WHERE course_id=?"); $n->execute([$cid]);
        if ((int)$n->fetchColumn() > 0) flash('err', d('لا يمكن حذف دورة لديها طلبات.', 'Cannot delete a course that has orders.'));
        else { $pdo->prepare("DELETE FROM courses WHERE id=? AND trainer_id=?")->execute([$cid, $trainer_id]); flash('ok', d('تم حذف الدورة.', 'Course deleted.')); }
    }
    redirect('my_courses.php');
}
$st = $pdo->prepare("SELECT c.*, cat.name_ar, cat.name_en AS cname_en, (SELECT COUNT(*) FROM lessons WHERE course_id=c.id) AS lessons_count, (SELECT COUNT(*) FROM course_orders WHERE course_id=c.id AND status='approved') AS students
    FROM courses c JOIN categories cat ON cat.id=c.category_id WHERE c.trainer_id=? ORDER BY c.created_at DESC"); $st->execute([$trainer_id]); $my = $st->fetchAll();
$cats = $pdo->query("SELECT id, name_ar, name_en FROM categories WHERE status=1")->fetchAll();
$dashTitle = d('دوراتي', 'My courses');
require '../includes/partials/dash_header.php';
?>
<div class="d-flex justify-content-between mb-3"><span class="text-muted"><?= count($my) ?></span><button class="btn-aramio" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fa-solid fa-plus"></i> <?= e(d('إنشاء دورة', 'New course')) ?></button></div>
<div class="row g-3">
<?php foreach ($my as $c): $th = course_thumb($c); ?><div class="col-md-6 col-xl-4"><div class="dcard h-100 overflow-hidden d-flex flex-column">
  <div style="aspect-ratio:16/8;background:var(--grad);position:relative"><?php if ($th): ?><img src="../<?= e($th) ?>" class="w-100 h-100" style="object-fit:cover" alt=""><?php endif; ?><div class="position-absolute top-0 start-0 m-2"><?= status_badge($c['status']) ?></div><span class="badge-type"><?= e(type_label($c['training_type'])) ?></span></div>
  <div class="dcard-b d-flex flex-column flex-grow-1"><span class="badge-cat align-self-start"><?= e($lang === 'ar' ? $c['name_ar'] : ($c['cname_en'] ?: $c['name_ar'])) ?></span><h5 class="fw-bold my-2"><?= e($c['title_ar']) ?></h5>
    <div class="small text-muted d-flex flex-wrap gap-3 mb-3"><span><i class="fa-solid fa-video"></i> <?= (int)$c['lessons_count'] ?></span><span><i class="fa-solid fa-user-graduate"></i> <?= (int)$c['students'] ?></span><span class="fw-bold text-success"><?= $c['price_usd'] !== null ? e(money_usd($c['price_usd'])) : '' ?> <?= $c['price_dzd'] !== null ? e(money_dzd($c['price_dzd'])) : '' ?></span></div>
    <?php if ($c['training_type'] !== 'online' && $c['start_date']): ?><div class="small mb-2"><i class="fa-regular fa-calendar"></i> <?= e($c['start_date']) ?> <?= $c['live_time'] ? '· ' . e(substr($c['live_time'], 0, 5)) : '' ?></div><?php endif; ?>
    <div class="mt-auto d-flex flex-wrap gap-2"><a class="btn-soft btn-soft-info" href="manage_lessons.php?id=<?= (int)$c['id'] ?>"><i class="fa-solid fa-photo-film"></i> <?= e(d('الدروس', 'Lessons')) ?></a><a class="btn-soft btn-soft-ok" href="quizzes.php?course=<?= (int)$c['id'] ?>"><i class="fa-solid fa-list-check"></i> <?= e(d('اختبارات', 'Quizzes')) ?></a>
      <?php if ($c['status'] === 'published'): ?><a class="btn-soft btn-soft-warn" target="_blank" href="../course.php?id=<?= (int)$c['id'] ?>"><i class="fa-solid fa-eye"></i></a><?php endif; ?>
      <?= action_btn('', ['act' => 'delete', 'course_id' => $c['id']], 'btn-soft-bad', d('حذف الدورة؟', 'Delete this course?'), 'fa-trash') ?></div></div></div></div><?php endforeach; ?>
<?php if (!$my): ?><div class="col-12"><div class="dcard"><div class="empty"><i class="fa-solid fa-photo-film"></i><p><?= e(d('لم تضف أي دورة بعد.', 'No courses yet.')) ?></p><button class="btn-aramio" data-bs-toggle="modal" data-bs-target="#addModal"><?= e(d('أنشئ دورتك الأولى', 'Create your first course')) ?></button></div></div></div><?php endif; ?>
</div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form method="post" enctype="multipart/form-data" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="add">
  <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(d('إنشاء دورة جديدة', 'Create a new course')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(d('عنوان الدورة (عربي)', 'Title (Arabic)')) ?> *</label><input name="title_ar" id="tAr" class="form-control" required></div><div class="col-md-6"><label class="form-label">Title (English)</label><input name="title_en" id="tEn" class="form-control" dir="ltr"></div>
    <div class="col-12"><div class="ai-box d-flex justify-content-between align-items-center flex-wrap gap-2"><span class="small"><i class="fa-solid fa-wand-magic-sparkles sparkle"></i> <?= e(d('اكتب العنوان ثم دع الذكاء الاصطناعي يكتب الوصف بالعربية والإنجليزية', 'Type a title and let AI write the description in Arabic & English')) ?></span><button type="button" class="ai-btn" id="aiDesc"><?= e(d('اكتب الوصف', 'Write description')) ?></button></div></div>
    <div class="col-md-6"><label class="form-label"><?= e(d('الوصف (عربي)', 'Description (Arabic)')) ?></label><textarea name="description_ar" id="dAr" rows="5" class="form-control"></textarea></div><div class="col-md-6"><label class="form-label">Description (English)</label><textarea name="description_en" id="dEn" rows="5" class="form-control" dir="ltr"></textarea></div>
    <div class="col-md-6"><label class="form-label"><?= e(t('category')) ?> *</label><select name="category_id" class="form-select" required><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($lang === 'ar' ? $c['name_ar'] : ($c['name_en'] ?: $c['name_ar'])) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label"><?= e(d('نوع التدريب', 'Training type')) ?></label><select name="training_type" id="tType" class="form-select"><option value="online"><?= e(d('أونلاين (دروس مسجلة)', 'Online (recorded)')) ?></option><option value="online_live"><?= e(d('بث مباشر', 'Live stream')) ?></option><option value="offline"><?= e(d('حضوري', 'In person')) ?></option></select></div>
    <div class="col-md-6"><label class="form-label"><?= e(d('السعر بالدولار ($)', 'Price USD')) ?></label><input type="number" step="0.01" min="0" name="price_usd" class="form-control"></div><div class="col-md-6"><label class="form-label"><?= e(d('السعر بالدينار (د.ج)', 'Price DZD')) ?></label><input type="number" step="0.01" min="0" name="price_dzd" class="form-control"></div>
    <div class="col-12 d-none" id="boxLive"><div class="ai-box"><div class="row g-2"><div class="col-md-4"><label class="form-label"><?= e(t('start_date')) ?></label><input type="date" name="start_date" class="form-control x-start"></div><div class="col-md-3"><label class="form-label"><?= e(d('الوقت', 'Time')) ?></label><input type="time" name="live_time" class="form-control"></div><div class="col-md-2"><label class="form-label"><?= e(t('seats')) ?></label><input type="number" min="0" name="max_seats" class="form-control x-seats"></div><div class="col-md-3 live-only"><label class="form-label">Zoom/Meet</label><input name="live_link" class="form-control" dir="ltr" placeholder="https://"></div>
      <div class="col-md-4 off-only d-none"><label class="form-label"><?= e(d('المدينة', 'City')) ?></label><input name="city" class="form-control"></div><div class="col-md-4 off-only d-none"><label class="form-label"><?= e(d('القاعة', 'Venue')) ?></label><input name="venue_name" class="form-control"></div><div class="col-md-4 off-only d-none"><label class="form-label"><?= e(d('رابط الخريطة', 'Map link')) ?></label><input name="map_link" class="form-control" dir="ltr"></div></div></div></div>
    <div class="col-12"><label class="form-label"><?= e(d('صورة الدورة', 'Cover image')) ?></label><input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp" class="form-control"></div>
  </div></div><div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(d('إرسال للمراجعة', 'Submit for review')) ?></button></div></form></div></div>
<?php
$footExtra = '<script>tType.addEventListener("change",function(){var v=this.value;boxLive.classList.toggle("d-none",v==="online");document.querySelectorAll(".live-only").forEach(function(x){x.classList.toggle("d-none",v!=="online_live")});document.querySelectorAll(".off-only").forEach(function(x){x.classList.toggle("d-none",v!=="offline")})});
aiDesc.addEventListener("click",function(){AI_ASK("../api/ai_tools.php",{action:"describe",title:tAr.value||tEn.value},function(r){if(!r.ok){alert(r.message||"error");return}dAr.value=r.data.ar||"";dEn.value=r.data.en||"";if(!tEn.value)tEn.value=r.data.title_en||"";if(!tAr.value)tAr.value=r.data.title_ar||""},this)});</script>';
require '../includes/partials/dash_footer.php';
