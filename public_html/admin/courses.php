<?php
require_once 'admin_init.php';
$success_msg = ''; $error_msg = '';
$whatsapp_btn = ''; // 💡 متغير زر الواتساب

// 1. الموافقة ونشر الدورة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['publish_course'])) {
    $course_id = $_POST['course_id'];
    $c_type_usd = !empty($_POST['comm_type_usd']) ? $_POST['comm_type_usd'] : null;
    $c_val_usd = !empty($_POST['comm_value_usd']) ? $_POST['comm_value_usd'] : null;
    $c_type_dzd = !empty($_POST['comm_type_dzd']) ? $_POST['comm_type_dzd'] : null;
    $c_val_dzd = !empty($_POST['comm_value_dzd']) ? $_POST['comm_value_dzd'] : null;
    
    $update = $pdo->prepare("UPDATE courses SET commission_type_usd = ?, commission_value_usd = ?, commission_type_dzd = ?, commission_value_dzd = ?, status = 'published' WHERE id = ?");
    if ($update->execute([$c_type_usd, $c_val_usd, $c_type_dzd, $c_val_dzd, $course_id])) {
        $success_msg = "تمت الموافقة وتحديد العمولات ونشر الدورة بنجاح!";
        
        // 💡 سحر الواتساب: إرسال تهنئة للمدرب
        $get_trainer = $pdo->prepare("SELECT u.full_name, u.whatsapp_number, c.title_ar FROM courses c JOIN users u ON c.trainer_id = u.id WHERE c.id = ?");
        $get_trainer->execute([$course_id]);
        $trainer_info = $get_trainer->fetch();

        if ($trainer_info && !empty($trainer_info['whatsapp_number'])) {
            $wa_num = preg_replace('/[^0-9]/', '', $trainer_info['whatsapp_number']);
            $wa_msg = "مرحباً كوتش *" . $trainer_info['full_name'] . "* 🌟\n\nيسعدنا إخبارك أنه تمت *الموافقة* على دورتك التدريبية:\n📖 *" . $trainer_info['title_ar'] . "*\n\nالدورة الآن منشورة ومتاحة للجمهور في منصة أراميو سمارت العالمية. نتمنى لك التوفيق والمبيعات العالية! 🚀";
            $wa_link = "https://wa.me/" . $wa_num . "?text=" . urlencode($wa_msg);
            $whatsapp_btn = "<a href='$wa_link' target='_blank' class='btn btn-success fw-bold rounded-pill px-4 mt-3 shadow-sm pulse-button'><i class='fa-brands fa-whatsapp fs-5 me-2'></i> أرسل التهنئة للمدرب (واتساب)</a>";
        }
    }
}

// 2. رفض أو حذف الدورة
if (isset($_GET['action']) && $_GET['action'] == 'reject') { $pdo->prepare("UPDATE courses SET status = 'rejected' WHERE id = ?")->execute([$_GET['id']]); $success_msg = "تم رفض الدورة."; }
if (isset($_GET['action']) && $_GET['action'] == 'delete') { $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$_GET['id']]); $success_msg = "تم حذف الدورة نهائياً."; }

$courses = $pdo->query("SELECT c.*, u.full_name AS trainer_name, cat.name_ar AS category_name FROM courses c JOIN users u ON c.trainer_id = u.id JOIN categories cat ON c.category_id = cat.id ORDER BY c.created_at DESC")->fetchAll();
require_once 'includes/header.php';
?>
<style>
    @keyframes pulse { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); } 70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(37, 211, 102, 0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); } }
    .pulse-button { animation: pulse 2s infinite; background-color: #25d366 !important; border-color: #25d366 !important; color: white !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4"><h3 class="fw-bold text-dark"><i class="fa-solid fa-photo-film text-primary me-2"></i> إدارة الدورات والمحتوى</h3></div>

<?php if($success_msg): ?> 
    <div class="alert alert-success rounded-4 fw-bold shadow-sm border-0 text-center p-4 mb-4">
        <i class="fa-solid fa-check-circle fa-3x mb-3 text-success"></i><br>
        <span class="fs-5 text-dark"><?php echo $success_msg; ?></span><br>
        <?php echo $whatsapp_btn; ?>
    </div>
<?php endif; ?>

<div class="admin-card card p-0 overflow-hidden mb-5"><div class="table-responsive"><table class="table table-hover align-middle text-center mb-0"><thead class="table-light text-muted small fw-bold"><tr><th class="py-3 text-start ps-4">الدورة / القسم</th><th class="py-3">المدرب</th><th class="py-3">السعر المقترح</th><th class="py-3">العمولة</th><th class="py-3">الحالة</th><th class="py-3">إجراءات</th></tr></thead><tbody>
    <?php foreach($courses as $course): ?><tr>
        <td class="text-start ps-4 fw-bold text-dark"><?php echo htmlspecialchars($course['title_ar']); ?><br><span class="badge bg-light text-secondary border mt-1"><?php echo htmlspecialchars($course['category_name']); ?></span></td>
        <td class="fw-bold text-muted"><i class="fa-solid fa-user-tie text-primary opacity-50 me-1"></i> <?php echo htmlspecialchars($course['trainer_name']); ?></td>
        <td class="fw-bold text-success fs-6"><?php if($course['price_usd']) echo '$'.$course['price_usd'].'<br>'; if($course['price_dzd']) echo number_format($course['price_dzd']).' د.ج'; ?></td>
        <td class="fw-bold small text-primary"><?php if($course['status'] == 'pending') echo '<span class="text-muted opacity-50">لم تحدد</span>'; else { if($course['commission_type_usd']) echo ($course['commission_type_usd'] == 'percentage') ? $course['commission_value_usd'].'% ($)<br>' : '$'.$course['commission_value_usd'].'<br>'; if($course['commission_type_dzd']) echo ($course['commission_type_dzd'] == 'percentage') ? $course['commission_value_dzd'].'% (د.ج)' : $course['commission_value_dzd'].' د.ج'; } ?></td>
        <td><?php if($course['status'] == 'published') echo '<span class="badge bg-success rounded-pill px-3 py-2">منشورة</span>'; elseif($course['status'] == 'pending') echo '<span class="badge bg-warning text-dark rounded-pill px-3 py-2">بانتظار الموافقة</span>'; else echo '<span class="badge bg-danger rounded-pill px-3 py-2">مرفوضة</span>'; ?></td>
        <td>
            <?php if($course['status'] == 'pending'): ?>
                <button class="btn btn-sm btn-success rounded-pill fw-bold px-3 shadow-sm mb-1 d-block mx-auto" onclick="openPublishModal(<?php echo $course['id']; ?>, '<?php echo addslashes($course['title_ar']); ?>', '<?php echo $course['price_usd']; ?>', '<?php echo $course['price_dzd']; ?>')">مراجعة ونشر</button>
                <a href="?action=reject&id=<?php echo $course['id']; ?>" class="btn btn-sm btn-warning text-dark rounded-circle me-1" title="رفض الدورة"><i class="fa-solid fa-xmark"></i></a>
            <?php else: ?><span class="text-muted small opacity-50 d-block mb-1"><i class="fa-solid fa-lock"></i> تم الإجراء</span><?php endif; ?>
            <a href="?action=delete&id=<?php echo $course['id']; ?>" class="btn btn-sm btn-danger rounded-circle" onclick="return confirm('حذف نهائي؟')"><i class="fa-solid fa-trash"></i></a>
        </td>
    </tr><?php endforeach; ?>
</tbody></table></div></div>

<div class="modal fade" id="publishModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header bg-light border-0 rounded-top-4"><h5 class="modal-title fw-bold text-success"><i class="fa-solid fa-check-double me-2"></i> الموافقة وتحديد العمولة</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST"><div class="modal-body p-4"><h5 class="fw-bold mb-4 text-center text-primary" id="modalCourseTitle"></h5>
    <div id="usd_section" class="bg-light p-3 rounded-3 mb-3 border border-success" style="display: none;"><p class="fw-bold text-success mb-2"><i class="fa-solid fa-dollar-sign"></i> إعدادات عمولة (الدولار) - السعر: $<span id="lbl_price_usd"></span></p><div class="row"><div class="col-6"><select name="comm_type_usd" id="inp_type_usd" class="form-select fw-bold text-success"><option value="percentage">نسبة مئوية (%)</option><option value="fixed">مبلغ ثابت مقطوع ($)</option></select></div><div class="col-6"><input type="number" step="0.01" name="comm_value_usd" id="inp_val_usd" class="form-control fw-bold text-center text-success" placeholder="العمولة"></div></div></div>
    <div id="dzd_section" class="bg-light p-3 rounded-3 mb-3 border border-primary" style="display: none;"><p class="fw-bold text-primary mb-2"><i class="fa-solid fa-money-bill"></i> إعدادات عمولة (الدينار) - السعر: <span id="lbl_price_dzd"></span> د.ج</p><div class="row"><div class="col-6"><select name="comm_type_dzd" id="inp_type_dzd" class="form-select fw-bold text-primary"><option value="percentage">نسبة مئوية (%)</option><option value="fixed">مبلغ ثابت مقطوع (د.ج)</option></select></div><div class="col-6"><input type="number" step="0.01" name="comm_value_dzd" id="inp_val_dzd" class="form-control fw-bold text-center text-primary" placeholder="العمولة"></div></div></div>
    <input type="hidden" name="course_id" id="modalCourseId"></div><div class="modal-footer border-0"><button type="submit" name="publish_course" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm">تأكيد ونشر الدورة</button></div></form>
</div></div></div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script><script> function openPublishModal(id, title, usd, dzd) { document.getElementById('modalCourseId').value = id; document.getElementById('modalCourseTitle').innerText = title; let usdSec = document.getElementById('usd_section'); let dzdSec = document.getElementById('dzd_section'); if (usd > 0) { usdSec.style.display = 'block'; document.getElementById('lbl_price_usd').innerText = usd; document.getElementById('inp_type_usd').required = true; document.getElementById('inp_val_usd').required = true; } else { usdSec.style.display = 'none'; document.getElementById('inp_type_usd').required = false; document.getElementById('inp_val_usd').required = false; } if (dzd > 0) { dzdSec.style.display = 'block'; document.getElementById('lbl_price_dzd').innerText = dzd; document.getElementById('inp_type_dzd').required = true; document.getElementById('inp_val_dzd').required = true; } else { dzdSec.style.display = 'none'; document.getElementById('inp_type_dzd').required = false; document.getElementById('inp_val_dzd').required = false; } new bootstrap.Modal(document.getElementById('publishModal')).show(); } document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); }); document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });</script></body></html>