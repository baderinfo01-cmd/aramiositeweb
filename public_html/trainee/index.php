<?php
require_once '../includes/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainee') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. جلب إحصائيات المتدرب
$stmt = $pdo->prepare("SELECT COUNT(*) FROM course_orders WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$user_id]);
$total_courses = $stmt->fetchColumn();

// 💡 التعديل هنا: جلب المبالغ بالدولار والدينار كل على حدة
$stmt_usd = $pdo->prepare("SELECT SUM(amount_usd) FROM course_orders WHERE user_id = ? AND status = 'approved'");
$stmt_usd->execute([$user_id]);
$total_spent_usd = $stmt_usd->fetchColumn() ?? 0;

$stmt_dzd = $pdo->prepare("SELECT SUM(amount_dzd) FROM course_orders WHERE user_id = ? AND status = 'approved'");
$stmt_dzd->execute([$user_id]);
$total_spent_dzd = $stmt_dzd->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM course_orders WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_orders = $stmt->fetchColumn();

// 2. جلب أحدث الدورات
$stmt = $pdo->prepare("
    SELECT o.status, o.created_at, c.title_ar, c.thumbnail, u.full_name AS trainer_name
    FROM course_orders o 
    JOIN courses c ON o.course_id = c.id 
    JOIN users u ON c.trainer_id = u.id
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC LIMIT 3
");
$stmt->execute([$user_id]);
$recent_courses = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<!-- بطاقة الترحيب -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4" style="background: linear-gradient(135deg, #0b1c3f 0%, #1a3673 100%); color: white;">
            <div class="card-body p-4 p-md-5 d-flex align-items-center justify-content-between flex-wrap">
                <div>
                    <h2 class="fw-bold mb-2">مستعد للتعلم اليوم؟ 🚀</h2>
                    <p class="mb-0 opacity-75 fs-5">تابع دوراتك، راجع مدفوعاتك، وطور مهاراتك مع أراميو.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="store.php" class="btn btn-success btn-lg rounded-pill fw-bold px-4" style="background-color: #1cc88a; border:none;">تصفح الدورات الجديدة</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- بطاقات الإحصائيات -->
<div class="row g-4 mb-5">
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="border-right: 5px solid #1cc88a !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <p class="text-muted fw-bold mb-1">دوراتي المفعلة</p>
                    <h2 class="fw-bold mb-0 text-dark"><?php echo $total_courses; ?></h2>
                </div>
                <div class="bg-light rounded-circle p-3 text-success d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;"><i class="fa-solid fa-graduation-cap fa-2x"></i></div>
            </div>
        </div>
    </div>
    
    <!-- 💡 بطاقة إجمالي المدفوعات المحدثة -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="border-right: 5px solid #f6c23e !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <p class="text-muted fw-bold mb-1">إجمالي المدفوعات</p>
                    <h4 class="fw-bold mb-0 text-dark">
                        <?php if($total_spent_usd > 0) echo '$'.number_format($total_spent_usd, 2); ?>
                        <?php if($total_spent_usd > 0 && $total_spent_dzd > 0) echo ' <span class="text-muted">+</span> '; ?>
                        <?php if($total_spent_dzd > 0) echo number_format($total_spent_dzd).' د.ج'; ?>
                        <?php if($total_spent_usd == 0 && $total_spent_dzd == 0) echo '$0.00'; ?>
                    </h4>
                </div>
                <div class="bg-light rounded-circle p-3 text-warning d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;"><i class="fa-solid fa-wallet fa-2x"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="border-right: 5px solid #e74a3b !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <p class="text-muted fw-bold mb-1">طلبات قيد المراجعة</p>
                    <h2 class="fw-bold mb-0 text-dark"><?php echo $pending_orders; ?></h2>
                </div>
                <div class="bg-light rounded-circle p-3 text-danger d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;"><i class="fa-solid fa-clock fa-2x"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- النشاط الأخير -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
        <h5 class="m-0 fw-bold text-primary"><i class="fa-solid fa-history me-2"></i> أحدث نشاطاتي</h5>
        <a href="my_courses.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">عرض الكل</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-light"><tr><th>اسم الدورة</th><th>المدرب</th><th>تاريخ الطلب</th><th>حالة الدورة</th><th>إجراء</th></tr></thead>
                <tbody>
                    <?php if(empty($recent_courses)): ?>
                        <tr><td colspan="5" class="py-4 text-muted">لا يوجد أي نشاط حالياً.</td></tr>
                    <?php else: ?>
                        <?php foreach($recent_courses as $course): ?>
                        <tr>
                            <td class="fw-bold text-start ps-4"><?php echo htmlspecialchars($course['title_ar']); ?></td>
                            <td class="text-muted"><i class="fa-solid fa-user-tie me-1"></i> <?php echo htmlspecialchars($course['trainer_name']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($course['created_at'])); ?></td>
                            <td>
                                <?php 
                                    if($course['status'] == 'approved') echo '<span class="badge bg-success rounded-pill px-3 py-2">مفعلة</span>';
                                    elseif($course['status'] == 'pending') echo '<span class="badge bg-warning text-dark rounded-pill px-3 py-2">قيد المراجعة</span>';
                                    elseif($course['status'] == 'rejected') echo '<span class="badge bg-danger rounded-pill px-3 py-2">مرفوضة</span>';
                                ?>
                            </td>
                            <td>
                                <?php if($course['status'] == 'approved'): ?>
                                    <a href="#" class="btn btn-sm btn-primary rounded-pill"><i class="fa-solid fa-play"></i> متابعة</a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary rounded-pill" disabled><i class="fa-solid fa-lock"></i> مقفلة</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const sidebar = document.getElementById("sidebar");
        const openBtn = document.getElementById("openSidebarBtn");
        const overlay = document.getElementById("sidebarOverlay");
        if(openBtn) {
            openBtn.addEventListener("click", () => { sidebar.classList.add("active"); overlay.classList.add("active"); });
            overlay.addEventListener("click", () => { sidebar.classList.remove("active"); overlay.classList.remove("active"); });
        }
    });
</script>
</body></html>