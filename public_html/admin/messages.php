<?php
require_once 'admin_init.php';
if (isset($_GET['del'])) { $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$_GET['del']]); }
if (isset($_GET['read'])) { $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$_GET['read']]); }
$messages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
require_once 'includes/header.php';
?>
<div class="d-flex justify-content-between mb-4"><h3 class="fw-bold"><i class="fa-solid fa-envelope text-primary"></i> رسائل الزوار (تواصل معنا)</h3></div>
<div class="card p-0"><div class="table-responsive"><table class="table text-center mb-0"><thead class="table-light small"><tr><th>المرسل</th><th>الإيميل/الهاتف</th><th>الرسالة</th><th>التاريخ</th><th>إجراء</th></tr></thead><tbody>
    <?php foreach($messages as $m): ?>
    <tr class="<?php echo ($m['is_read'] == 0) ? 'fw-bold bg-light' : ''; ?>">
        <td class="text-start ps-3"><?php echo htmlspecialchars($m['full_name']); ?><br><?php if($m['is_read']==0) echo '<span class="badge bg-danger">جديدة</span>'; ?></td>
        <td dir="ltr"><?php echo htmlspecialchars($m['email']); ?><br><?php echo htmlspecialchars($m['phone']); ?></td>
        <td class="text-start"><small><?php echo nl2br(htmlspecialchars($m['message'])); ?></small></td>
        <td><?php echo date('Y-m-d', strtotime($m['created_at'])); ?></td>
        <td><?php if($m['is_read']==0): ?><a href="?read=<?php echo $m['id']; ?>" class="btn btn-sm btn-success rounded-circle" title="تحديد كمقروء"><i class="fa-solid fa-check"></i></a><?php endif; ?> <a href="?del=<?php echo $m['id']; ?>" class="btn btn-sm btn-danger rounded-circle" onclick="return confirm('حذف الرسالة؟')"><i class="fa-solid fa-trash"></i></a></td>
    </tr><?php endforeach; ?>
</tbody></table></div></div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script><script>document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); }); document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });</script></body></html>