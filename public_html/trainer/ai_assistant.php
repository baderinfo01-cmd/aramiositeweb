<?php
require_once 'trainer_init.php';
$mine = $pdo->prepare("SELECT id, title_ar, title_en FROM courses WHERE trainer_id=? ORDER BY id DESC"); $mine->execute([$trainer_id]); $mine = $mine->fetchAll();
$dashTitle = d('مساعد المدرب الذكي', 'AI Teaching Assistant');
require '../includes/partials/dash_header.php';
?>
<?php if (!ai_available()): ?><div class="alert alert-warning"><i class="fa-solid fa-plug"></i> <?= e(d('الذكاء الاصطناعي غير مفعّل بعد؛ اطلب من الإدارة تفعيله.', 'AI is not enabled yet; ask the administrator to enable it.')) ?></div><?php endif; ?>
<div class="row g-3">
 <div class="col-lg-6"><div class="dcard h-100"><div class="dcard-h"><span><i class="fa-solid fa-bullhorn sparkle"></i> <?= e(d('منشورات ترويجية لدورتك', 'Promo posts for your course')) ?></span></div><div class="dcard-b d-grid gap-2">
  <select id="pCourse" class="form-select"><?php foreach ($mine as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title_ar']) ?></option><?php endforeach; ?></select><button class="ai-btn align-self-start" id="pBtn"><?= e(d('ولّد المنشورات', 'Generate posts')) ?></button><div id="pOut" class="small"></div></div></div></div>
 <div class="col-lg-6"><div class="dcard h-100"><div class="dcard-h"><span><i class="fa-solid fa-language sparkle"></i> <?= e(d('ترجمة دورتي للغات الأخرى', 'Translate my course')) ?></span></div><div class="dcard-b d-grid gap-2">
  <select id="tCourse" class="form-select"><?php foreach ($mine as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title_ar']) ?></option><?php endforeach; ?></select><button class="ai-btn align-self-start" id="tBtn"><?= e(d('ترجم واحفظ (fr / tr / es / ms)', 'Translate & save (fr / tr / es / ms)')) ?></button><div id="tOut" class="small"></div></div></div></div>
 <div class="col-lg-6"><div class="dcard h-100"><div class="dcard-h"><span><i class="fa-solid fa-magnifying-glass-chart sparkle"></i> SEO</span></div><div class="dcard-b d-grid gap-2">
  <select id="sCourse" class="form-select"><?php foreach ($mine as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title_ar']) ?></option><?php endforeach; ?></select><button class="ai-btn align-self-start" id="sBtn"><?= e(d('اقترح وصف SEO وكلمات مفتاحية', 'Suggest meta & keywords')) ?></button><pre id="sOut" class="small mb-0" style="white-space:pre-wrap"></pre></div></div></div>
 <div class="col-lg-6"><div class="dcard h-100"><div class="dcard-b"><i class="fa-solid fa-list-check sparkle"></i> <b><?= e(d('اختبارات بالذكاء الاصطناعي', 'AI quizzes')) ?></b><p class="small text-muted mt-2"><?= e(d('أنشئ اختبارًا كاملاً لدورتك في ثوانٍ من صفحة الاختبارات.', 'Build a complete quiz for your course in seconds from the Quizzes page.')) ?></p><a class="btn-aramio" href="quizzes.php"><?= e(d('اذهب للاختبارات', 'Go to Quizzes')) ?></a></div></div></div>
</div>
<?php
$footExtra = '<script>
pBtn.onclick=function(){AI_ASK("../api/ai_tools.php",{action:"promo",course_id:pCourse.value},function(r){pOut.innerHTML="";if(!r.ok){pOut.textContent=r.message||"error";return}Object.keys(r.data).forEach(function(k){var d=document.createElement("div");d.className="ai-box mb-2";var b=document.createElement("b");b.textContent=k;var p=document.createElement("div");p.style.whiteSpace="pre-wrap";p.textContent=r.data[k];d.appendChild(b);d.appendChild(p);pOut.appendChild(d)})},this)};
tBtn.onclick=function(){AI_ASK("../api/ai_tools.php",{action:"translate_course",course_id:tCourse.value},function(r){tOut.textContent=r.ok?(' . json_encode(d('تم الحفظ: ', 'Saved: ')) . '+r.saved):(r.message||"error")},this)};
sBtn.onclick=function(){AI_ASK("../api/ai_tools.php",{action:"seo",course_id:sCourse.value},function(r){sOut.textContent=r.ok?JSON.stringify(r.data,null,2):(r.message||"error")},this)};</script>';
require '../includes/partials/dash_footer.php';
