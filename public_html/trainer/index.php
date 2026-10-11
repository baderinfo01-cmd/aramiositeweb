<?php
require_once '../includes/init.php';

// حماية الصفحة
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainer') {
    header('Location: ../login.php');
    exit();
}

$trainer_id = $_SESSION['user_id'];

// 1. الإحصائيات العامة
$total_courses = $pdo->query("SELECT COUNT(*) FROM courses WHERE trainer_id = $trainer_id AND status = 'published'")->fetchColumn() ?: 0;
$total_students = $pdo->query("SELECT COUNT(DISTINCT o.user_id) FROM course_orders o JOIN courses c ON o.course_id = c.id WHERE c.trainer_id = $trainer_id AND o.status = 'approved'")->fetchColumn() ?: 0;

// 2. إحصائيات الأرباح (بدون أصفار عشرية زائدة بفضل استخدام float)
$earn_usd = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id = $trainer_id AND currency = 'usd'")->fetchColumn() ?: 0);
$earn_dzd = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id = $trainer_id AND currency = 'dzd'")->fetchColumn() ?: 0);

$pend_usd = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id = $trainer_id AND currency = 'usd' AND is_paid = 0")->fetchColumn() ?: 0);
$pend_dzd = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id = $trainer_id AND currency = 'dzd' AND is_paid = 0")->fetchColumn() ?: 0);

// 3. الاستشارات القادمة
$upcoming_consultations = $pdo->prepare("
    SELECT b.id, a.available_date, a.start_time, a.end_time, u.full_name AS trainee_name 
    FROM consultation_bookings b 
    JOIN trainer_availabilities a ON b.availability_id = a.id 
    JOIN users u ON b.trainee_id = u.id 
    WHERE a.trainer_id = ? AND b.status = 'approved' AND a.available_date >= CURDATE()
    ORDER BY a.available_date ASC, a.start_time ASC LIMIT 4
");
$upcoming_consultations->execute([$trainer_id]);
$upcoming_consultations = $upcoming_consultations->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold" style="color: #0b1c3f;">نظرة عامة (الداشبورد)</h3>
    <span class="badge bg-success bg-opacity-10 text-success fs-6 px-3 py-2 rounded-pill"><i class="fa-solid fa-signal me-2"></i> حسابك نشط</span>
</div>

<!-- بطاقات الإحصائيات (Stat Cards) -->
<div class="row g-4 mb-5">
    
    <!-- إجمالي الأرباح -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="border-right: 5px solid #1cc88a !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <p class="text-muted fw-bold mb-2 small text-uppercase">إجمالي أرباحك الكلية</p>
                    <h4 class="fw-bold text-success mb-1"><?php echo ($earn_usd > 0) ? '$'.$earn_usd : ''; ?></h4>
                    <h5 class="fw-bold text-success m-0"><?php echo ($earn_dzd > 0) ? number_format($earn_dzd).' د.ج' : ''; ?></h5>
                    <?php if($earn_usd == 0 && $earn_dzd == 0) echo '<h4 class="fw-bold text-success mb-0">$0</h4>'; ?>
                </div>
                <div class="bg-success bg-opacity-10 text-success rounded-circle p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- الأرباح المعلقة -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="border-right: 5px solid #f6c23e !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <p class="text-muted fw-bold mb-2 small text-uppercase">أرباح معلقة (بانتظار التحويل)</p>
                    <h4 class="fw-bold text-warning mb-1"><?php echo ($pend_usd > 0) ? '$'.$pend_usd : ''; ?></h4>
                    <h5 class="fw-bold text-warning m-0"><?php echo ($pend_dzd > 0) ? number_format($pend_dzd).' د.ج' : ''; ?></h5>
                    <?php if($pend_usd == 0 && $pend_dzd == 0) echo '<h4 class="fw-bold text-warning mb-0">$0</h4>'; ?>
                </div>
                <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- عدد المتدربين -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="border-right: 5px solid #4e73df !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <p class="text-muted fw-bold mb-2 small text-uppercase">إجمالي المتدربين</p>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $total_students; ?></h3>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- عدد الدورات -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="border-right: 5px solid #36b9cc !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <p class="text-muted fw-bold mb-2 small text-uppercase">دوراتي النشطة</p>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $total_courses; ?></h3>
                </div>
                <div class="bg-info bg-opacity-10 text-info rounded-circle p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <i class="fa-solid fa-book-open"></i>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- القسم السفلي -->
<div class="row g-4 mb-5">
    
    <!-- جدول الاستشارات القادمة -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-calendar-check text-primary me-2"></i> استشاراتي القادمة (المؤكدة)</h5>
                <a href="schedule.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">الذهاب للتقويم</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-center">
                        <thead class="table-light text-muted small fw-bold">
                            <tr>
                                <th class="py-3 text-start ps-4">اسم المتدرب</th>
                                <th class="py-3">التاريخ</th>
                                <th class="py-3">الوقت</th>
                                <th class="py-3">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($upcoming_consultations)): ?>
                                <tr><td colspan="4" class="py-5 text-muted"><h6 class="fw-bold mb-0">لا يوجد لديك استشارات مؤكدة في الأيام القادمة.</h6></td></tr>
                            <?php else: ?>
                                <?php foreach($upcoming_consultations as $consultation): ?>
                                <tr>
                                    <td class="fw-bold text-dark text-start ps-4"><i class="fa-solid fa-user-graduate text-muted me-2"></i> <?php echo htmlspecialchars($consultation['trainee_name']); ?></td>
                                    <td class="text-primary fw-bold"><?php echo date('Y-m-d', strtotime($consultation['available_date'])); ?></td>
                                    <td class="text-muted"><span dir="ltr"><?php echo date('h:i A', strtotime($consultation['start_time'])); ?> - <?php echo date('h:i A', strtotime($consultation['end_time'])); ?></span></td>
                                    <td><a href="schedule.php" class="btn btn-sm btn-success rounded-pill fw-bold px-3 shadow-sm">إدارة الرابط</a></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- بطاقة توجيهات سريعة -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #0b1c3f 0%, #1a3673 100%); color: white;">
            <div class="card-body p-4 text-center d-flex flex-column justify-content-center">
                <i class="fa-solid fa-chalkboard-user fa-4x mb-4 text-warning"></i>
                <h4 class="fw-bold mb-3">أهلاً بك في منصتك!</h4>
                <p class="opacity-75 mb-4 lh-lg">من هنا يمكنك إدارة دوراتك، متابعة أرباحك المالية بدقة، وتحديد أوقات فراغك لاستقبال طلبات الاستشارة من الطلاب.</p>
                <a href="my_courses.php" class="btn btn-warning rounded-pill fw-bold text-dark btn-lg shadow-sm">إضافة دورة جديدة</a>
            </div>
        </div>
    </div>

</div>

<!-- إغلاق وسوم الـ HTML המפתوحة في header.php -->
        </div> 
    </div> 
    
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
</body>
</html>