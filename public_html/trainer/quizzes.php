<?php
require_once 'trainer_init.php';
$mine = $pdo->prepare("SELECT id, title_ar, title_en FROM courses WHERE trainer_id=? ORDER BY id DESC"); $mine->execute([$trainer_id]); $mine = $mine->fetchAll();
$ids = array_column($mine, 'id');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        $cid = (int)$_POST['course_id'];
        if (!in_array($cid, array_map('intval', $ids), true)) { flash('err', 'forbidden'); redirect('quizzes.php'); }
        $title = trim((string)$_POST['title']); $pass = max(1, min(100, (int)($_POST['pass_score'] ?? 60)));
        $qs = [];
        foreach ((array)($_POST['q'] ?? []) as $q) {
            $text = trim((string)($q['text'] ?? '')); $opts = array_values(array_filter(array_map('trim', (array)($q['opt'] ?? [])), fn($o) => $o !== ''));
            $ci = (int)($q['correct'] ?? 0);
            if ($text !== '' && count($opts) >= 2 && $ci >= 0 && $ci < count($opts)) $qs[] = [$text, $opts, $ci, trim((string)($q['why'] ?? ''))];
        }
        if ($title === '' || !$qs) flash('err', d('أدخل عنوانًا وسؤالًا واحدًا على الأقل (بخيارين فأكثر).', 'Enter a title and at least one question with 2+ options.'));
        else {
            $pdo->prepare("INSERT INTO quizzes (course_id, title, pass_score, created_by) VALUES (?,?,?,?)")->execute([$cid, mb_substr($title, 0, 255), $pass, $trainer_id]); $qid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question, options, correct_index, explanation) VALUES (?,?,?,?,?)");
            foreach ($qs as [$text, $opts, $ci, $why]) $ins->execute([$qid, $text, json_encode($opts, JSON_UNESCAPED_UNICODE), $ci, $why]);
            flash('ok', d('تم حفظ الاختبار (' . count($qs) . ' سؤال).', 'Quiz saved (' . count($qs) . ' questions).'));
        }
    } elseif ($act === 'delete') {
        $pdo->prepare("DELETE q FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE q.id=? AND c.trainer_id=?")->execute([(int)$_POST['quiz_id'], $trainer_id]);
        $pdo->exec("DELETE FROM quiz_questions WHERE quiz_id NOT IN (SELECT id FROM quizzes)"); flash('ok', d('تم حذف الاختبار.', 'Quiz deleted.'));
    }
    redirect('quizzes.php' . (!empty($_POST['course_id']) ? '?course=' . (int)$_POST['course_id'] : ''));
}
$fc = (int)($_GET['course'] ?? 0);
$list = [];
if ($ids) {
    $in = implode(',', array_map('intval', $ids));
    $list = $pdo->query("SELECT q.*, c.title_ar, (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=q.id) nq, (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=q.id) na, (SELECT ROUND(AVG(score)) FROM quiz_attempts WHERE quiz_id=q.id) avg_s
        FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE q.course_id IN ($in)" . ($fc ? " AND q.course_id=$fc" : '') . " ORDER BY q.id DESC")->fetchAll();
}
$dashTitle = d('اختبارات الدورات', 'Course quizzes');
require '../includes/partials/dash_header.php';
?>
<div class="dcard mb-4"><div class="dcard-h"><span><i class="fa-solid fa-wand-magic-sparkles sparkle"></i> <?= e(d('منشئ الاختبارات', 'Quiz builder')) ?></span><span class="small text-muted"><?= e(d('أنشئ بالذكاء الاصطناعي أو يدويًا', 'Generate with AI or build manually')) ?></span></div>
 <form method="post" class="dcard-b" id="qForm"><?= csrf_field() ?><input type="hidden" name="act" value="save">
  <div class="row g-2 mb-3"><div class="col-md-4"><select name="course_id" id="qCourse" class="form-select" required><?php foreach ($mine as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $fc === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title_ar']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><input name="title" class="form-control" required placeholder="<?= e(d('عنوان الاختبار', 'Quiz title')) ?>"></div><div class="col-md-2"><input type="number" name="pass_score" value="60" min="1" max="100" class="form-control" title="<?= e(d('درجة النجاح %', 'Pass score %')) ?>"></div>
    <div class="col-md-2"><input id="qN" type="number" value="8" min="3" max="15" class="form-control" title="<?= e(d('عدد الأسئلة', 'Questions')) ?>"></div>
    <div class="col-12 d-flex flex-wrap gap-2"><input id="qTopic" class="form-control" style="max-width:360px" placeholder="<?= e(d('ركّز على موضوع (اختياري)', 'Focus topic (optional)')) ?>"><button type="button" class="ai-btn" id="qGen"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= e(d('ولّد الأسئلة', 'Generate questions')) ?></button><button type="button" class="btn-soft btn-soft-info" id="qAdd"><i class="fa-solid fa-plus"></i> <?= e(d('سؤال يدوي', 'Manual question')) ?></button></div></div>
  <div id="qList"></div><button class="btn-aramio mt-3 d-none" id="qSave"><?= e(d('حفظ الاختبار', 'Save quiz')) ?></button></form></div>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(d('الاختبار', 'Quiz')) ?></th><th><?= e(d('الدورة', 'Course')) ?></th><th><?= e(d('الأسئلة', 'Questions')) ?></th><th><?= e(d('المحاولات', 'Attempts')) ?></th><th><?= e(d('متوسط الدرجات', 'Avg score')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($list as $q): ?><tr><td class="fw-bold"><?= e($q['title']) ?></td><td><?= e($q['title_ar']) ?></td><td><?= (int)$q['nq'] ?></td><td><?= (int)$q['na'] ?></td><td><?= $q['avg_s'] !== null ? (int)$q['avg_s'] . '%' : '—' ?></td><td class="text-end"><?= action_btn('', ['act' => 'delete', 'quiz_id' => $q['id']], 'btn-soft-bad', d('حذف الاختبار؟', 'Delete quiz?'), 'fa-trash') ?></td></tr><?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="6" class="empty"><i class="fa-solid fa-list-check"></i><?= e(d('لا اختبارات بعد', 'No quizzes yet')) ?></td></tr><?php endif; ?></tbody></table></div></div>
<?php
$L = ['q' => d('نص السؤال', 'Question'), 'o' => d('الخيار', 'Option'), 'w' => d('شرح الإجابة (اختياري)', 'Explanation (optional)'), 'ok' => d('الصحيح', 'Correct')];
$footExtra = '<script>var L=' . json_encode($L, JSON_UNESCAPED_UNICODE) . ',qi=0;
function addQ(q){q=q||{};var i=qi++,d=document.createElement("div");d.className="ai-box mb-3";var h=\'<input class="form-control mb-2" name="q[\'+i+\'][text]" placeholder="\'+L.q+\'">\';
for(var k=0;k<4;k++)h+=\'<div class="input-group mb-1"><span class="input-group-text"><input type="radio" name="q[\'+i+\'][correct]" value="\'+k+\'" \'+(k==(q.correct_index||0)?"checked":"")+\' title="\'+L.ok+\'"></span><input class="form-control" name="q[\'+i+\'][opt][]" placeholder="\'+L.o+\' \'+(k+1)+\'"></div>\';
h+=\'<input class="form-control mt-1" name="q[\'+i+\'][why]" placeholder="\'+L.w+\'">\';d.innerHTML=h;d.querySelector("input.form-control").value=q.question||"";var o=d.querySelectorAll("input[name$=\'[opt][]\']");(q.options||[]).forEach(function(t,k){if(o[k])o[k].value=t});d.querySelector("input[name$=\'[why]\']").value=q.explanation||"";qList.appendChild(d);qSave.classList.remove("d-none")}
qAdd.onclick=function(){addQ()};
qGen.onclick=function(){qList.innerHTML="";AI_ASK("../api/ai_tools.php",{action:"quiz",course_id:qCourse.value,count:qN.value,topic:qTopic.value,qlang:"' . $lang . '"},function(r){if(!r.ok){alert(r.message||"error");return}(r.data.questions||[]).forEach(addQ)},this)};</script>';
require '../includes/partials/dash_footer.php';
