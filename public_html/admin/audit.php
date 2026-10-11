<?php
require_once 'admin_init.php'; admin_only();
$logs = $pdo->query("SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 300")->fetchAll();
$ai = $pdo->query("SELECT feature, COUNT(*) n, SUM(ok=0) fails FROM ai_logs WHERE created_at >= (NOW() - INTERVAL 30 DAY) GROUP BY feature ORDER BY n DESC")->fetchAll();
$dashTitle = d('سجل الأمان والنشاط', 'Security & activity log'); require '../includes/partials/dash_header.php';
?>
<div class="row g-3"><div class="col-lg-8"><div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(t('date')) ?></th><th><?= e(d('المستخدم', 'User')) ?></th><th><?= e(d('الحدث', 'Event')) ?></th><th><?= e(d('التفاصيل', 'Detail')) ?></th><th>IP</th></tr></thead><tbody>
<?php foreach ($logs as $l): ?><tr class="<?= strpos($l['action'], 'failed') !== false ? 'bg-danger bg-opacity-10' : '' ?>"><td class="small text-nowrap"><?= e($l['created_at']) ?></td><td><?= e($l['full_name'] ?: '—') ?></td><td><code><?= e($l['action']) ?></code></td><td class="small"><?= e($l['detail']) ?></td><td class="small" dir="ltr"><?= e($l['ip']) ?></td></tr><?php endforeach; ?>
<?php if (!$logs): ?><tr><td colspan="5" class="empty"><i class="fa-solid fa-shield-halved"></i></td></tr><?php endif; ?></tbody></table></div></div></div>
<div class="col-lg-4"><div class="dcard"><div class="dcard-h"><?= e(d('استخدام الذكاء الاصطناعي (30 يومًا)', 'AI usage (30 days)')) ?></div><div class="dcard-b"><?php foreach ($ai as $a): ?><div class="d-flex justify-content-between border-bottom py-2"><code><?= e($a['feature']) ?></code><span><b><?= (int)$a['n'] ?></b> <?= $a['fails'] ? '<span class="st st-bad">' . (int)$a['fails'] . '</span>' : '' ?></span></div><?php endforeach; if (!$ai) echo '<div class="empty">—</div>'; ?></div></div></div></div>
<?php require '../includes/partials/dash_footer.php';
