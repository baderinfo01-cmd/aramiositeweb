<?php
require_once 'trainer_init.php';
$cid = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT id, title_ar, title_en, status FROM courses WHERE id=? AND trainer_id=?"); $st->execute([$cid, $trainer_id]); $course = $st->fetch();
if (!$course) redirect('my_courses.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $tAr = trim((string)$_POST['title_ar']); $tEn = trim((string)($_POST['title_en'] ?? '')) ?: $tAr; $url = trim((string)$_POST['video_url']);
        if ($tAr === '' || !url_ok($url)) flash('err', d('أدخل عنوانًا ورابط فيديو صالحًا (https://...).', 'Enter a title and a valid video URL (https://...).'));
        else { $pdo->prepare("INSERT INTO lessons (course_id, title_ar, title_en, video_url, lesson_order, is_free) VALUES (?,?,?,?,?,?)")->execute([$cid, $tAr, $tEn, $url, (int)$_POST['lesson_order'], !empty($_POST['is_free']) ? 1 : 0]); flash('ok', d('تمت إضافة الدرس.', 'Lesson added.')); }
    } elseif ($act === 'delete') { $pdo->prepare("DELETE FROM lessons WHERE id=? AND course_id=?")->execute([(int)$_POST['lesson_id'], $cid]); flash('ok', d('تم حذف الدرس.', 'Lesson deleted.')); }
    elseif ($act === 'free') { $pdo->prepare("UPDATE lessons SET is_free = 1 - is_free WHERE id=? AND course_id=?")->execute([(int)$_POST['lesson_id'], $cid]); }
    elseif ($act === 'bulk') { // إضافة دروس من مخطط الذكاء الاصطناعي (بدون فيديو بعد)
        $o = (int)$pdo->query("SELECT COALESCE(MAX(lesson_order),0) FROM lessons WHERE course_id=$cid")->fetchColumn(); $n = 0;
        foreach ((array)($_POST['l'] ?? []) as $l) { $a = trim((string)($l['ar'] ?? '')); if ($a === '') continue; $pdo->prepare("INSERT INTO lessons (course_id, title_ar, title_en, video_url, lesson_order, is_free) VALUES (?,?,?,?,?,0)")->execute([$cid, mb_substr($a, 0, 255), mb_substr(trim((string)($l['en'] ?? '')) ?: $a, 0, 255), 'https://example.com/replace-me', ++$o]); $n++; }
        flash('ok', d("أُضيف $n درسًا — لا تنسَ استبدال روابط الفيديو.", "$n lessons added — remember to replace the video URLs."));
    }
    redirect('manage_lessons.php?id=' . $cid);
}
$ls = $pdo->prepare("SELECT * FROM lessons WHERE course_id=? ORDER BY lesson_order, id"); $ls->execute([$cid]); $lessons = $ls->fetchAll();
$nextOrder = $lessons ? (int)end($lessons)['lesson_order'] + 1 : 1;
$dashTitle = d('استوديو الدورة: ', 'Course studio: ') . $course['title_ar']; $dashActive = 'my_courses.php';
require '../includes/partials/dash_header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3"><a href="my_courses.php" class="btn-soft btn-soft-info py-2"><i class="fa-solid fa-arrow-<?= $dir === 'rtl' ? 'right' : 'left' ?>"></i> <?= e(t('back')) ?></a>
  <div class="d-flex gap-2"><button class="ai-btn" id="outBtn"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= e(d('اقترح مخطط دروس', 'AI lesson outline')) ?></button><button class="btn-aramio" data-bs-toggle="modal" data-bs-target="#lModal"><i class="fa-solid fa-plus"></i> <?= e(d('إضافة درس', 'Add lesson')) ?></button></div></div>
<form method="post" id="bulkForm" class="dcard mb-3 d-none"><div class="dcard-h"><?= e(d('مخطط مقترح — عدّل ثم احفظ', 'Suggested outline — edit then save')) ?></div><div class="dcard-b"><?= csrf_field() ?><input type="hidden" name="act" value="bulk"><div id="bulkList"></div><button class="btn-aramio mt-2"><?= e(t('save')) ?></button></div></form>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th>#</th><th><?= e(d('عنوان الدرس', 'Lesson')) ?></th><th><?= e(d('الفيديو', 'Video')) ?></th><th><?= e(d('مجاني؟', 'Free?')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($lessons as $l): ?><tr><td><?= (int)$l['lesson_order'] ?></td><td class="fw-bold"><?= e($l['title_ar']) ?><div class="small text-muted"><?= e($l['title_en']) ?></div></td><td><a target="_blank" rel="noopener" href="<?= e($l['video_url']) ?>" class="small"><i class="fa-solid fa-play"></i> <?= e(mb_substr($l['video_url'], 0, 40)) ?></a></td>
  <td><?= action_btn($l['is_free'] ? d('نعم', 'Yes') : d('لا', 'No'), ['act' => 'free', 'lesson_id' => $l['id']], $l['is_free'] ? 'btn-soft-ok' : 'btn-soft-warn') ?></td><td class="text-end"><?= action_btn('', ['act' => 'delete', 'lesson_id' => $l['id']], 'btn-soft-bad', d('حذف الدرس؟', 'Delete lesson?'), 'fa-trash') ?></td></tr><?php endforeach; ?>
<?php if (!$lessons): ?><tr><td colspan="5" class="empty"><i class="fa-solid fa-video"></i><?= e(d('لا دروس بعد', 'No lessons yet')) ?></td></tr><?php endif; ?></tbody></table></div></div>
<div class="modal fade" id="lModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="act" value="add">
  <div class="modal-header"><h5 class="modal-title fw-bold"><?= e(d('درس جديد', 'New lesson')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body d-grid gap-2"><input name="title_ar" class="form-control" required placeholder="<?= e(d('عنوان الدرس (عربي)', 'Title (AR)')) ?>"><input name="title_en" class="form-control" dir="ltr" placeholder="Title (EN)"><input name="video_url" class="form-control" dir="ltr" required placeholder="https://youtube.com/... | vimeo | drive"><input type="number" name="lesson_order" value="<?= $nextOrder ?>" class="form-control"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_free" id="isf"><label for="isf" class="form-check-label"><?= e(d('معاينة مجانية', 'Free preview')) ?></label></div></div>
  <div class="modal-footer border-0"><button class="btn-aramio w-100"><?= e(t('save')) ?></button></div></form></div></div>
<?php
$footExtra = '<script>outBtn.addEventListener("click",function(){AI_ASK("../api/ai_tools.php",{action:"outline",title:' . json_encode($course['title_ar'] . ' / ' . $course['title_en'], JSON_UNESCAPED_UNICODE) . ',count:8},function(r){if(!r.ok){alert(r.message||"error");return}bulkForm.classList.remove("d-none");bulkList.innerHTML="";(r.data.lessons||[]).forEach(function(x,i){var d=document.createElement("div");d.className="row g-2 mb-2";d.innerHTML=\'<div class="col-6"><input class="form-control" name="l[\'+i+\'][ar]"></div><div class="col-6"><input dir="ltr" class="form-control" name="l[\'+i+\'][en]"></div>\';d.querySelectorAll("input")[0].value=x.title_ar||"";d.querySelectorAll("input")[1].value=x.title_en||"";bulkList.appendChild(d)})},this)});</script>';
require '../includes/partials/dash_footer.php';
