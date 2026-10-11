<?php
require_once 'trainer_init.php';
$st = $pdo->prepare("SELECT u.id, u.full_name, u.last_name, u.email, u.phone, u.whatsapp_number, u.country, u.avatar, MAX(x.last) AS last_activity, GROUP_CONCAT(DISTINCT x.src) AS sources FROM (
    SELECT o.user_id AS uid, MAX(o.created_at) AS last, 'course' AS src FROM course_orders o JOIN courses c ON c.id=o.course_id WHERE c.trainer_id=? AND o.status='approved' GROUP BY o.user_id
    UNION ALL
    SELECT b.trainee_id, MAX(b.created_at), 'consultation' FROM consultation_bookings b JOIN trainer_availabilities a ON a.id=b.availability_id WHERE a.trainer_id=? AND b.status IN ('approved','completed') GROUP BY b.trainee_id) x
    JOIN users u ON u.id=x.uid GROUP BY u.id, u.full_name, u.last_name, u.email, u.phone, u.whatsapp_number, u.country, u.avatar ORDER BY last_activity DESC");
$st->execute([$trainer_id, $trainer_id]); $students = $st->fetchAll();
$prog = $pdo->prepare("SELECT o.user_id, c.id cid, c.title_ar, (SELECT COUNT(*) FROM lessons WHERE course_id=c.id) tl, (SELECT COUNT(*) FROM lesson_completions lc JOIN lessons l ON l.id=lc.lesson_id WHERE l.course_id=c.id AND lc.trainee_id=o.user_id) done FROM course_orders o JOIN courses c ON c.id=o.course_id WHERE c.trainer_id=? AND o.status='approved'");
$prog->execute([$trainer_id]); $byUser = [];
foreach ($prog->fetchAll() as $p) $byUser[$p['user_id']][] = $p;
$dashTitle = d('طلابي', 'My students');
require '../includes/partials/dash_header.php';
?>
<div class="dcard"><div class="table-wrap"><table class="dtable"><thead><tr><th><?= e(d('الطالب', 'Student')) ?></th><th><?= e(t('country')) ?></th><th><?= e(d('التقدّم', 'Progress')) ?></th><th><?= e(d('تواصل', 'Contact')) ?></th><th><?= e(d('آخر نشاط', 'Last activity')) ?></th></tr></thead><tbody>
<?php foreach ($students as $s): ?><tr><td><div class="d-flex align-items-center gap-2"><img src="<?= e(avatar_url($s['avatar'], $s['full_name'], '../')) ?>" width="38" height="38" class="rounded-circle" style="object-fit:cover" alt=""><div><b><?= e(trim($s['full_name'] . ' ' . $s['last_name'])) ?></b><div class="small text-muted" dir="ltr"><?= e($s['email']) ?></div></div></div></td>
  <td><?= e(country_flag((string)$s['country'])) ?> <?= e($s['country']) ?></td>
  <td style="min-width:200px"><?php foreach ($byUser[$s['id']] ?? [] as $p): $pc = $p['tl'] ? round($p['done'] / $p['tl'] * 100) : 0; ?><div class="small fw-bold"><?= e(mb_substr($p['title_ar'], 0, 28)) ?> <span class="text-muted"><?= $pc ?>%</span></div><div class="progress mb-1"><div class="progress-bar" style="width:<?= $pc ?>%"></div></div><?php endforeach; if (empty($byUser[$s['id']])) echo '<span class="st st-info">' . e(d('استشارة', 'Consultation')) . '</span>'; ?></td>
  <td class="text-nowrap"><?php if ($s['whatsapp_number']): ?><a class="btn-soft btn-soft-ok" target="_blank" href="<?= e(wa_link($s['whatsapp_number'])) ?>"><i class="fa-brands fa-whatsapp"></i></a> <?php endif; ?><a class="btn-soft btn-soft-info" href="mailto:<?= e($s['email']) ?>"><i class="fa-solid fa-envelope"></i></a></td><td class="small text-muted"><?= e(substr($s['last_activity'], 0, 10)) ?></td></tr><?php endforeach; ?>
<?php if (!$students): ?><tr><td colspan="5" class="empty"><i class="fa-solid fa-user-graduate"></i><?= e(d('لا طلاب بعد', 'No students yet')) ?></td></tr><?php endif; ?></tbody></table></div></div>
<?php require '../includes/partials/dash_footer.php';
