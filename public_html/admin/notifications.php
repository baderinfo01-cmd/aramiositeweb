<?php
require_once 'admin_init.php';
$success_msg = '';

// 1. إضافة إشعار جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_noti'])) {
    $pdo->prepare("INSERT INTO popup_notifications (message_ar, message_en) VALUES (?, ?)")->execute([$_POST['msg_ar'], $_POST['msg_en']]);
    $success_msg = "تمت إضافة الإشعار بنجاح.";
}
// 2. تحديث وقت الظهور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_timer'])) {
    $pdo->prepare("UPDATE settings SET setting_value_ar = ? WHERE setting_key = 'popup_timer'")->execute([$_POST['popup_timer']]);
    $success_msg = "تم تحديث وقت ظهور الإشعارات.";
}
// 3. حذف إشعار
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM popup_notifications WHERE id = ?")->execute([$_GET['delete']]);
    $success_msg = "تم حذف الإشعار.";
}

$notifications = $pdo->query("SELECT * FROM popup_notifications ORDER BY created_at DESC")->fetchAll();
$current_timer = $pdo->query("SELECT setting_value_ar FROM settings WHERE setting_key = 'popup_timer'")->fetchColumn() ?: 10;
require_once 'includes/header.php';
?>
<div class="d-flex justify-content-between mb-4"><h3 class="fw-bold text-dark"><i class="fa-solid fa-bell text-warning me-2"></i> الإشعارات المنبثقة للزوار</h3></div>
<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>

<div class="row g-4">
    <!-- إعدادات الوقت -->
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white d-flex flex-row align-items-center justify-content-between">
            <h6 class="fw-bold m-0"><i class="fa-solid fa-stopwatch text-primary"></i> الفاصل الزمني بين ظهور إشعار وآخر:</h6>
            <form method="POST" class="d-flex gap-2">
                <input type="number" name="popup_timer" class="form-control text-center fw-bold" value="<?php echo $current_timer; ?>" min="3" style="width: 100px;">
                <span class="mt-2 text-muted fw-bold">ثواني</span>
                <button type="submit" name="update_timer" class="btn btn-primary fw-bold">تحديث الوقت</button>
            </form>
        </div>
    </div>
    
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-header bg-white py-3 border-0"><h5 class="fw-bold m-0 text-primary">الإشعارات الحالية</h5></div>
        <table class="table text-center align-middle mb-0"><thead class="table-light"><tr><th>النص العربي</th><th>النص الإنجليزي</th><th>إجراء</th></tr></thead><tbody>
            <?php foreach($notifications as $n): ?><tr><td class="fw-bold text-start ps-3"><?php echo htmlspecialchars($n['message_ar']); ?></td><td class="text-muted text-start ps-3" dir="ltr"><?php echo htmlspecialchars($n['message_en']); ?></td><td><a href="?delete=<?php echo $n['id']; ?>" class="btn btn-danger btn-sm rounded-circle"><i class="fa-solid fa-trash"></i></a></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
    
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 border-left-warning p-4">
            <h5 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-plus-circle text-warning"></i> إضافة إشعار جديد</h5>
            <form method="POST">
                <input type="text" name="msg_ar" class="form-control fw-bold mb-3" placeholder="مثال: اشترك محمد في الدورة (عربي)" required>
                <input type="text" name="msg_en" class="form-control mb-4 text-start" dir="ltr" placeholder="Example: Mohammed enrolled... (English)" required>
                <button type="submit" name="add_noti" class="btn btn-warning w-100 fw-bold rounded-pill shadow-sm text-dark">إضافة وعرض في الموقع</button>
            </form>
        </div>
    </div>
</div>
</div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>