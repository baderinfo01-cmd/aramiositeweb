<?php
require_once 'admin_init.php';
$courses = $pdo->query("SELECT id, title_ar, title_en FROM courses ORDER BY id DESC LIMIT 200")->fetchAll();
$dashTitle = d('استوديو الذكاء الاصطناعي', 'AI Studio');
require '../includes/partials/dash_header.php';
$on = ai_available();
?>
<?php if (!$on): ?><div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2"><span><i class="fa-solid fa-plug"></i> <?= e(d('الذكاء الاصطناعي غير مفعّل بعد. أضف مفتاح API لتشغيل كل الأدوات.', 'AI is not configured yet. Add an API key to enable all tools.')) ?></span><a class="btn-aramio py-1" href="settings.php#ai"><?= e(d('الإعدادات', 'Settings')) ?></a></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-6"><div class="dcard h-100"><div class="dcard-h"><span><i class="fa-solid fa-pen-nib sparkle"></i> <?= e(d('كاتب وصف الدورات', 'Course description writer')) ?></span></div><div class="dcard-b d-grid gap-2">
    <input id="dTitle" class="form-control" placeholder="<?= e(d('عنوان الدورة', 'Course title')) ?>"><textarea id="dKw" rows="2" class="form-control" placeholder="<?= e(d('ملاحظات/كلمات مفتاحية (اختياري)', 'Notes / keywords (optional)')) ?>"></textarea>
    <button class="ai-btn align-self-start" id="dBtn"><?= e(d('ولّد الوصف', 'Generate')) ?></button><div id="dOut" class="small" style="white-space:pre-wrap"></div></div></div></div>

  <div class="col-lg-6"><div class="dcard h-100"><div class="dcard-h"><span><i class="fa-solid fa-language sparkle"></i> <?= e(d('مترجم المحتوى (6 لغات)', 'Content translator (6 languages)')) ?></span></div><div class="dcard-b d-grid gap-2">
    <textarea id="tTxt" rows="3" class="form-control" placeholder="<?= e(d('الصق النص هنا', 'Paste text here')) ?>"></textarea>
    <div class="d-flex flex-wrap gap-2"><?php foreach (SUPPORTED_LANGS as $c => $i): ?><label class="form-check-label small"><input type="checkbox" class="form-check-input tl" value="<?= $c ?>" <?= in_array($c, ['en', 'fr', 'tr']) ? 'checked' : '' ?>> <?= $i['flag'] ?> <?= e($i['name']) ?></label><?php endforeach; ?></div>
    <button class="ai-btn align-self-start" id="tBtn"><?= e(d('ترجم', 'Translate')) ?></button><div id="tOut" class="small" style="white-space:pre-wrap"></div></div></div></div>

  <div class="col-lg-6"><div class="dcard h-100"><div class="dcard-h"><span><i class="fa-solid fa-list-ol sparkle"></i> <?= e(d('مخطط دروس مقترح', 'Lesson outline')) ?></span></div><div class="dcard-b d-grid gap-2">
    <input id="oTitle" class="form-control" placeholder="<?= e(d('عنوان الدورة', 'Course title')) ?>"><input id="oN" type="number" min="4" max="15" value="8" class="form-control" style="max-width:120px">
    <button class="ai-btn align-self-start" id="oBtn"><?= e(d('اقترح', 'Suggest')) ?></button><div id="oOut" class="small"></div></div></div></div>

  <div class="col-lg-6"><div class="dcard h-100"><div class="dcard-h"><span><i class="fa-solid fa-magnifying-glass-chart sparkle"></i> <?= e(d('أدوات على دورة موجودة', 'Tools for an existing course')) ?></span></div><div class="dcard-b d-grid gap-2">
    <select id="cSel" class="form-select"><?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title_ar'] ?: $c['title_en']) ?></option><?php endforeach; ?></select>
    <div class="d-flex flex-wrap gap-2"><button class="ai-btn" id="seoBtn">SEO</button><button class="ai-btn" id="trBtn"><?= e(d('ترجمة الدورة وحفظها (fr/tr/es/ms)', 'Translate & save (fr/tr/es/ms)')) ?></button></div><div id="cOut" class="small" style="white-space:pre-wrap"></div></div></div></div>

  <div class="col-12"><div class="dcard"><div class="dcard-h"><span><i class="fa-solid fa-chart-line sparkle"></i> <?= e(d('تحليل الأداء', 'Performance insights')) ?></span><button class="ai-btn" id="iBtn"><?= e(d('حلّل', 'Analyse')) ?></button></div><div class="dcard-b" id="iOut" style="white-space:pre-wrap"><?= e(d('اضغط حلّل للحصول على رؤى وإجراءات مقترحة.', 'Click Analyse for insights and suggested actions.')) ?></div></div></div>
</div>
<?php
$footExtra = '<script>
function J(o){return JSON.stringify(o,null,2)}
dBtn.onclick=function(){AI_ASK("../api/ai_tools.php",{action:"describe",title:dTitle.value,keywords:dKw.value},function(r){dOut.textContent=r.ok?("AR:\n"+r.data.ar+"\n\nEN:\n"+r.data.en):(r.message||"error")},this)};
tBtn.onclick=function(){var l=[].slice.call(document.querySelectorAll(".tl:checked")).map(function(x){return x.value});AI_ASK("../api/ai_tools.php",{action:"translate",fields:{text:tTxt.value},langs:l},function(r){if(!r.ok){tOut.textContent=r.message||"error";return}var s="";Object.keys(r.data).forEach(function(k){s+="["+k+"]\n"+(r.data[k].text||J(r.data[k]))+"\n\n"});tOut.textContent=s},this)};
oBtn.onclick=function(){AI_ASK("../api/ai_tools.php",{action:"outline",title:oTitle.value,count:oN.value},function(r){oOut.innerHTML="";if(!r.ok){oOut.textContent=r.message||"error";return}var ol=document.createElement("ol");(r.data.lessons||[]).forEach(function(x){var li=document.createElement("li");li.textContent=(x.title_ar||"")+" — "+(x.title_en||"");ol.appendChild(li)});oOut.appendChild(ol)},this)};
seoBtn.onclick=function(){AI_ASK("../api/ai_tools.php",{action:"seo",course_id:cSel.value},function(r){cOut.textContent=r.ok?J(r.data):(r.message||"error")},this)};
trBtn.onclick=function(){AI_ASK("../api/ai_tools.php",{action:"translate_course",course_id:cSel.value},function(r){cOut.textContent=r.ok?(' . json_encode(d('تم الحفظ: ', 'Saved: ')) . '+r.saved):(r.message||"error")},this)};
iBtn.onclick=function(){AI_ASK("../api/ai_tools.php",{action:"insights"},function(r){iOut.textContent=r.ok?r.text:(r.message||"error")},this)};
</script>';
require '../includes/partials/dash_footer.php';
