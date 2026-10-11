<?php
require_once 'admin_init.php';

$exchange_rate = (float)($pdo->query("SELECT setting_value_ar FROM settings WHERE setting_key = 'exchange_rate'")->fetchColumn() ?: 135);

$rev_course_usd = $pdo->query("SELECT COALESCE(SUM(amount_usd), 0) FROM course_orders WHERE status = 'approved'")->fetchColumn();
$rev_course_dzd = $pdo->query("SELECT COALESCE(SUM(amount_dzd), 0) FROM course_orders WHERE status = 'approved'")->fetchColumn();
$rev_cons_usd = $pdo->query("SELECT COALESCE(SUM(amount_usd), 0) FROM consultation_bookings WHERE status = 'approved'")->fetchColumn();
$rev_cons_dzd = $pdo->query("SELECT COALESCE(SUM(amount_dzd), 0) FROM consultation_bookings WHERE status = 'approved'")->fetchColumn();

$total_rev_usd = $rev_course_usd + $rev_cons_usd;
$total_rev_dzd = $rev_course_dzd + $rev_cons_dzd;

$trainers_cut_usd = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM trainer_earnings WHERE currency = 'usd'")->fetchColumn();
$trainers_cut_dzd = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM trainer_earnings WHERE currency = 'dzd'")->fetchColumn();

$exp_usd = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE currency = 'usd'")->fetchColumn();
$exp_dzd = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE currency = 'dzd'")->fetchColumn();

$total_income_in_dzd = ($total_rev_usd * $exchange_rate) + $total_rev_dzd;
$total_expenses_in_dzd = ($trainers_cut_usd * $exchange_rate) + $trainers_cut_dzd + ($exp_usd * $exchange_rate) + $exp_dzd;
$net_profit_dzd = $total_income_in_dzd - $total_expenses_in_dzd;

$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'trainee'")->fetchColumn();
$total_trainers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'trainer'")->fetchColumn();
$pending_courses = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'pending'")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM course_orders WHERE status = 'pending'")->fetchColumn();
$online_now = rand(12, 45); 

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">نظرة عامة على الأكاديمية</h3>
        <p class="text-muted fw-bold m-0"><i class="fa-regular fa-calendar-check me-1"></i> إحصائيات اليوم: <?php echo date('Y-m-d'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="financial.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background-color: #051024; border:none;">
            <i class="fa-solid fa-file-invoice-dollar me-2"></i> مراجعة الطلبات
            <?php if($pending_orders > 0): ?> <span class="badge bg-danger ms-2 rounded-circle"><?php echo $pending_orders; ?></span> <?php endif; ?>
        </a>
    </div>
</div>

<h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-vault me-2"></i> المركز المالي الشامل</h5>
<div class="row g-4 mb-5">
    <!-- المداخيل -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-card card h-100 p-4 border-bottom border-4 border-success">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <p class="text-muted fw-bold mb-1 small text-uppercase">إجمالي المداخيل</p>
                    <h4 class="fw-black mb-0 text-dark">$<?php echo number_format($total_rev_usd, 2); ?></h4>
                </div>
                <div class="bg-success bg-opacity-10 text-success rounded-3 p-3 fs-3"><i class="fa-solid fa-arrow-trend-up"></i></div>
            </div>
            <div class="text-success fw-bold small"><i class="fa-solid fa-plus text-muted me-1"></i> <?php echo number_format($total_rev_dzd); ?> د.ج</div>
        </div>
    </div>
    <!-- مستحقات المدربين -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-card card h-100 p-4 border-bottom border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <p class="text-muted fw-bold mb-1 small text-uppercase">مستحقات المدربين</p>
                    <h4 class="fw-black mb-0 text-dark">$<?php echo number_format($trainers_cut_usd, 2); ?></h4>
                </div>
                <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3 fs-3"><i class="fa-solid fa-users-rectangle"></i></div>
            </div>
            <div class="text-warning text-dark fw-bold small"><i class="fa-solid fa-plus text-muted me-1"></i> <?php echo number_format($trainers_cut_dzd); ?> د.ج</div>
        </div>
    </div>
    <!-- المصاريف -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-card card h-100 p-4 border-bottom border-4 border-danger">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <p class="text-muted fw-bold mb-1 small text-uppercase">إجمالي المصاريف</p>
                    <h4 class="fw-black mb-0 text-dark">$<?php echo number_format($exp_usd, 2); ?></h4>
                </div>
                <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-3 fs-3"><i class="fa-solid fa-money-bill-transfer"></i></div>
            </div>
            <div class="text-danger fw-bold small"><i class="fa-solid fa-plus text-muted me-1"></i> <?php echo number_format($exp_dzd); ?> د.ج</div>
        </div>
    </div>
    <!-- صافي الربح (الأهم) -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-card card h-100 p-4 border-bottom border-4 border-primary" style="background: linear-gradient(135deg, #051024 0%, #1a3673 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-white-50 fw-bold mb-1 small text-uppercase">صافي أرباح الأكاديمية</p>
                    <h4 class="fw-black mb-0 text-warning"><?php echo number_format($net_profit_dzd); ?> د.ج</h4>
                </div>
                <div class="bg-white bg-opacity-10 text-white rounded-3 p-3 fs-3"><i class="fa-solid fa-crown"></i></div>
            </div>
        </div>
    </div>
</div>

<h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-chart-pie me-2"></i> نشاط المنصة</h5>
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="admin-card card h-100 p-4 d-flex flex-row align-items-center gap-4">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; min-width: 70px;"><i class="fa-solid fa-user-graduate fs-2"></i></div>
            <div><h3 class="fw-black mb-1 text-dark"><?php echo number_format($total_students); ?></h3><p class="text-muted fw-bold mb-0">طالب مسجل</p></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card card h-100 p-4 d-flex flex-row align-items-center gap-4">
            <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; min-width: 70px;"><i class="fa-solid fa-chalkboard-user fs-2"></i></div>
            <div><h3 class="fw-black mb-1 text-dark"><?php echo number_format($total_trainers); ?></h3><p class="text-muted fw-bold mb-0">مدرب معتمد</p></div>
        </div>
    </div>
    <div class="col-md-4">
        <a href="courses.php" class="text-decoration-none">
            <div class="admin-card card h-100 p-4 d-flex flex-row align-items-center gap-4 <?php echo ($pending_courses > 0) ? 'border border-warning' : ''; ?>">
                <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; min-width: 70px;"><i class="fa-solid fa-photo-film fs-2"></i></div>
                <div>
                    <h3 class="fw-black mb-1 <?php echo ($pending_courses > 0) ? 'text-warning' : 'text-dark'; ?>"><?php echo number_format($pending_courses); ?></h3>
                    <p class="text-muted fw-bold mb-0">دورات بانتظار الموافقة</p>
                </div>
            </div>
        </a>
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
            openBtn.addEventListener("click", function() { sidebar.classList.add("active"); overlay.classList.add("active"); });
            overlay.addEventListener("click", function() { sidebar.classList.remove("active"); overlay.classList.remove("active"); });
        }
    });
</script>
</body></html>