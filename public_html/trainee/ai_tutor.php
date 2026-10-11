<?php
require_once 'trainee_init.php';
$st = $pdo->prepare("SELECT c.id, c.title_ar, c.title_en FROM course_orders o JOIN courses c ON c.id=o.course_id WHERE o.user_id=? AND o.status='approved' ORDER BY o.created_at DESC"); $st->execute([$user_id]); $mine = $st->fetchAll();
$dashTitle = d('المعلّم الذكي', 'AI Tutor');
require '../includes/partials/dash_header.php';
?>
<?php if (!ai_available()): ?><div class="alert alert-warning"><i class="fa-solid fa-plug"></i> <?= e(t('ai_unavailable')) ?></div><?php endif; ?>
<?php if (!$mine): ?><div class="dcard"><div class="empty"><i class="fa-solid fa-wand-magic-sparkles"></i><p><?= e(d('اشترك في دورة لتستفيد من المعلّم الذكي.', 'Enroll in a course to use the AI tutor.')) ?></p><a class="btn-aramio" href="store.php"><?= e(d('المتجر', 'Store')) ?></a></div></div>
<?php else: ?>
<div class="dcard"><div class="dcard-b">
  <div class="row g-2 mb-3"><div class="col-md-6"><select id="course" class="form-select"><?php foreach ($mine as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($lang === 'ar' ? $c['title_ar'] : ($c['title_en'] ?: $c['title_ar'])) ?></option><?php endforeach; ?></select></div></div>
  <div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ([[d('لخّص الدورة', 'Summarise course'), 'summary'], [d('اشرح لي ببساطة', 'Explain simply'), 'explain'], [d('اختبرني', 'Quiz me'), 'quiz'], [d('خطة دراسة 4 أسابيع', '4-week study plan'), 'plan']] as [$lb, $m]): ?><button class="btn-soft btn-soft-info t-q" data-mode="<?= $m ?>"><?= e($lb) ?></button><?php endforeach; ?></div>
  <div id="out" class="ai-box" style="white-space:pre-wrap;min-height:160px"><?= e(d('اختر دورة ثم إجراءً، أو اكتب سؤالك.', 'Pick a course and an action, or type your question.')) ?></div>
  <form id="f" class="d-flex gap-2 mt-3"><input id="q" class="form-control" maxlength="500" placeholder="<?= e(t('ai_placeholder')) ?>"><button class="btn-aramio"><i class="fa-solid fa-paper-plane"></i></button></form></div></div>
<?php endif; ?>
<?php
$footExtra = '<script>(function(){var o=document.getElementById("out");if(!o)return;function ask(p,b){o.textContent=' . json_encode(t('loading')) . ';AI_ASK("../api/ai_tutor.php",Object.assign({course_id:document.getElementById("course").value},p),function(r){o.textContent=r.ok?r.text:(r.message||"error")},b)}
document.querySelectorAll(".t-q").forEach(function(b){b.addEventListener("click",function(){ask({mode:this.dataset.mode},this)})});document.getElementById("f").addEventListener("submit",function(e){e.preventDefault();var q=document.getElementById("q");if(!q.value.trim())return;ask({mode:"chat",q:q.value});q.value=""})})();</script>';
require '../includes/partials/dash_footer.php';
