<?php
require_once 'admin_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { require_post_csrf(); if (($_POST['act'] ?? '') === 'delete') $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id=?")->execute([(int)$_POST['id']]); redirect('newsletter.php'); }
$subs = $pdo->query("SELECT * FROM newsletter_subscribers ORDER BY id DESC LIMIT 1000")->fetchAll();
if (($_GET['export'] ?? '') === 'csv') { header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="subscribers.csv"'); $o = fopen('php://output', 'w'); fputcsv($o, ['email', 'lang', 'created_at']); foreach ($subs as $s) fputcsv($o, [$s['email'], $s['lang'], $s['created_at']]); exit; }
$dashTitle = d('النشرة البريدية', 'Newsletter'); require '../includes/partials/dash_header.php';
?>
<div class="d-flex justify-content-between mb-3"><span class="text-muted"><?= count($subs) ?> <?= e(d('مشترك', 'subscribers')) ?></span><a class="btn-soft btn-soft-ok py-2" href="?export=csv"><i class="fa-solid fa-file-csv"></i> CSV</a></div>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(t('email')) ?></th><th><?= e(t('language')) ?></th><th><?= e(t('date')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($subs as $s): ?><tr><td dir="ltr" class="text-start"><?= e($s['email']) ?></td><td><?= e(strtoupper($s['lang'])) ?></td><td><?= e(substr($s['created_at'], 0, 10)) ?></td><td class="text-end"><?= action_btn('', ['act' => 'delete', 'id' => $s['id']], 'btn-soft-bad', '?', 'fa-trash') ?></td></tr><?php endforeach; ?>
<?php if (!$subs): ?><tr><td colspan="4" class="empty"><i class="fa-solid fa-paper-plane"></i></td></tr><?php endif; ?></tbody></table></div></div>
<?php require '../includes/partials/dash_footer.php';
