<?php
require_once 'trainee_init.php';
$qid = (int)($_GET['id'] ?? $_POST['quiz_id'] ?? 0);
$z = $pdo->prepare("SELECT q.*, c.title_ar AS course_title FROM quizzes q JOIN courses c ON c.id=q.course_id WHERE q.id=?"); $z->execute([$qid]); $quiz = $z->fetch();
if (!$quiz || !owns_course($user_id, (int)$quiz['course_id'])) redirect('my_courses.php');
$qs = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id=? ORDER BY id"); $qs->execute([$qid]); $questions = $qs->fetchAll();
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $right = 0; $detail = [];
    foreach ($questions as $q) {
        $opts = json_decode($q['options'], true) ?: []; $pick = isset($_POST['a'][$q['id']]) ? (int)$_POST['a'][$q['id']] : -1; $ok = $pick === (int)$q['correct_index'];
        if ($ok) $right++;
        $detail[] = ['q' => $q['question'], 'pick' => $opts[$pick] ?? '—', 'correct' => $opts[(int)$q['correct_index']] ?? '', 'ok' => $ok, 'why' => $q['explanation']];
    }
    $score = $questions ? (int)round($right / count($questions) * 100) : 0; $passed = $score >= (int)$quiz['pass_score'] ? 1 : 0;
    $pdo->prepare("INSERT INTO quiz_attempts (quiz_id, trainee_id, score, passed) VALUES (?,?,?,?)")->execute([$qid, $user_id, $score, $passed]);
    $result = ['score' => $score, 'passed' => $passed, 'right' => $right, 'detail' => $detail];
}
$dashTitle = $quiz['title']; $dashActive = 'my_courses.php';
require '../includes/partials/dash_header.php';
?>
<a href="my_courses.php?course_id=<?= (int)$quiz['course_id'] ?>" class="btn-soft btn-soft-info py-2 mb-3 d-inline-block"><i class="fa-solid fa-arrow-<?= $dir === 'rtl' ? 'right' : 'left' ?>"></i> <?= e($quiz['course_title']) ?></a>
<?php if ($result): ?>
  <div class="dcard mb-3 text-center p-4" style="border-color:<?= $result['passed'] ? 'var(--emerald)' : '#ef4444' ?>"><div class="display-3 fw-bold <?= $result['passed'] ? 'text-success' : 'text-danger' ?>"><?= $result['score'] ?>%</div><div class="fs-5"><?= e($result['passed'] ? d('🎉 أحسنت! نجحت في الاختبار', '🎉 Well done! You passed') : d('لم تبلغ درجة النجاح — راجع الدروس وأعد المحاولة', 'Below pass mark — review and try again')) ?> (<?= $result['right'] ?>/<?= count($questions) ?>)</div>
    <a class="btn-aramio mt-3" href="quiz.php?id=<?= $qid ?>"><?= e(d('إعادة المحاولة', 'Retry')) ?></a></div>
  <?php foreach ($result['detail'] as $i => $x): ?><div class="dcard mb-2"><div class="dcard-b"><b><?= ($i + 1) . '. ' . e($x['q']) ?></b><div class="mt-2 small"><?= $x['ok'] ? '<span class="st st-ok">✔ ' . e($x['pick']) . '</span>' : '<span class="st st-bad">✖ ' . e($x['pick']) . '</span> <span class="st st-ok">✔ ' . e($x['correct']) . '</span>' ?></div><?php if ($x['why']): ?><div class="small text-muted mt-2"><i class="fa-solid fa-lightbulb text-warning"></i> <?= e($x['why']) ?></div><?php endif; ?></div></div><?php endforeach; ?>
<?php else: ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="quiz_id" value="<?= $qid ?>">
  <?php foreach ($questions as $i => $q): $opts = json_decode($q['options'], true) ?: []; ?><div class="dcard mb-3"><div class="dcard-b"><b class="d-block mb-3"><?= ($i + 1) . '. ' . e($q['question']) ?></b>
    <?php foreach ($opts as $k => $o): ?><label class="d-flex gap-2 align-items-center border rounded-3 p-2 mb-2" style="cursor:pointer"><input type="radio" class="form-check-input mt-0" name="a[<?= (int)$q['id'] ?>]" value="<?= $k ?>" required><span><?= e($o) ?></span></label><?php endforeach; ?></div></div><?php endforeach; ?>
  <?php if ($questions): ?><button class="btn-aramio px-5 py-2"><?= e(d('تسليم الإجابات', 'Submit answers')) ?></button><?php else: ?><div class="empty"><?= e(d('هذا الاختبار فارغ.', 'This quiz is empty.')) ?></div><?php endif; ?></form>
<?php endif; ?>
<?php require '../includes/partials/dash_footer.php';
