<?php
require_once 'admin_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $id = (int)($_POST['id'] ?? 0);
    if (($_POST['act'] ?? '') === 'read') $pdo->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$id]);
    elseif (($_POST['act'] ?? '') === 'delete') $pdo->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
    elseif (($_POST['act'] ?? '') === 'read_all') $pdo->exec("UPDATE contact_messages SET is_read=1");
    redirect('messages.php');
}
$msgs = $pdo->query("SELECT * FROM contact_messages ORDER BY is_read ASC, created_at DESC LIMIT 300")->fetchAll();
$dashTitle = d('رسائل الزوار', 'Visitor messages');
require '../includes/partials/dash_header.php';
?>
<div class="d-flex justify-content-between mb-3"><span class="text-muted"><?= count($msgs) ?></span><?= action_btn(d('تعليم الكل كمقروء', 'Mark all read'), ['act' => 'read_all'], 'btn-soft-ok') ?></div>
<div class="row g-3"><?php foreach ($msgs as $m): ?><div class="col-lg-6"><div class="dcard <?= $m['is_read'] ? '' : 'border-warning' ?>"><div class="dcard-b">
  <div class="d-flex justify-content-between"><div><b><?= e($m['full_name']) ?></b> <?= $m['is_read'] ? '' : '<span class="st st-bad">' . e(d('جديدة', 'New')) . '</span>' ?><div class="small text-muted" dir="ltr"><?= e($m['email']) ?> <?= e($m['phone']) ?></div></div><small class="text-muted"><?= e(substr($m['created_at'], 0, 16)) ?></small></div>
  <p class="mt-3 mb-3" style="white-space:pre-wrap"><?= e($m['message']) ?></p>
  <div class="ai-box d-none mb-3" id="draft<?= (int)$m['id'] ?>"><div class="small fw-bold mb-1"><i class="fa-solid fa-wand-magic-sparkles sparkle"></i> <?= e(d('مسودة رد ذكية', 'AI draft reply')) ?></div><div class="small" style="white-space:pre-wrap" data-txt></div><a class="btn-soft btn-soft-info mt-2" data-mail href="#"><i class="fa-solid fa-envelope"></i> <?= e(d('فتح في البريد', 'Open in mail')) ?></a></div>
  <div class="d-flex flex-wrap gap-2"><button class="ai-btn ai-reply" data-id="<?= (int)$m['id'] ?>" data-mail="<?= e($m['email']) ?>"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= e(d('اقترح ردًا', 'Draft reply')) ?></button>
    <a class="btn-soft btn-soft-info" href="mailto:<?= e($m['email']) ?>"><i class="fa-solid fa-reply"></i></a>
    <?php if (!$m['is_read']) echo action_btn(d('مقروءة', 'Read'), ['act' => 'read', 'id' => $m['id']], 'btn-soft-ok', '', 'fa-check'); ?>
    <?= action_btn('', ['act' => 'delete', 'id' => $m['id']], 'btn-soft-bad', d('حذف الرسالة؟', 'Delete message?'), 'fa-trash') ?></div></div></div></div><?php endforeach; ?>
<?php if (!$msgs): ?><div class="empty"><i class="fa-solid fa-envelope-open"></i><?= e(d('لا رسائل', 'No messages')) ?></div><?php endif; ?></div>
<?php
$footExtra = '<script>document.querySelectorAll(".ai-reply").forEach(function(b){b.addEventListener("click",function(){var s=this;AI_ASK("../api/ai_tools.php",{action:"reply",id:s.dataset.id},function(r){if(!r.ok){alert(r.message||"error");return}var bx=document.getElementById("draft"+s.dataset.id);bx.classList.remove("d-none");bx.querySelector("[data-txt]").textContent=r.text;bx.querySelector("[data-mail]").href="mailto:"+s.dataset.mail+"?subject=Re&body="+encodeURIComponent(r.text)},s)})});</script>';
require '../includes/partials/dash_footer.php';
