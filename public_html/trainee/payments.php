<?php
require_once '../includes/init.php';

// حماية الصفحة: للمتدربين فقط
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainee') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. جلب إجمالي المدفوعات (المقبولة فقط) لعرضها كإحصائية
$stmt_usd = $pdo->prepare("
    SELECT 
        (SELECT COALESCE(SUM(amount_usd), 0) FROM course_orders WHERE user_id = ? AND status = 'approved') +
        (SELECT COALESCE(SUM(amount_usd), 0) FROM consultation_bookings WHERE trainee_id = ? AND status = 'approved')
");
$stmt_usd->execute([$user_id, $user_id]);
$total_approved_usd = $stmt_usd->fetchColumn() ?? 0;

$stmt_dzd = $pdo->prepare("
    SELECT 
        (SELECT COALESCE(SUM(amount_dzd), 0) FROM course_orders WHERE user_id = ? AND status = 'approved') +
        (SELECT COALESCE(SUM(amount_dzd), 0) FROM consultation_bookings WHERE trainee_id = ? AND status = 'approved')
");
$stmt_dzd->execute([$user_id, $user_id]);
$total_approved_dzd = $stmt_dzd->fetchColumn() ?? 0;

// ==========================================
// 2. 💡 السحر هنا: جلب العمليات بشكل منفصل ودمجها برمجياً لضمان عدم انهيار الـ SQL
// ==========================================
$all_payments = [];

// أ. جلب مدفوعات الدورات
$stmt_courses = $pdo->prepare("
    SELECT o.id, o.amount_usd, o.amount_dzd, o.payment_method, o.status, o.created_at, c.title_ar AS item_title, 'course' AS item_type
    FROM course_orders o 
    JOIN courses c ON o.course_id = c.id 
    WHERE o.user_id = ?
");
$stmt_courses->execute([$user_id]);
$course_payments = $stmt_courses->fetchAll();

foreach ($course_payments as $cp) {
    $all_payments[] = $cp;
}

// ب. جلب مدفوعات الاستشارات
$stmt_consults = $pdo->prepare("
    SELECT b.id, b.amount_usd, b.amount_dzd, b.payment_method, b.status, b.created_at, CONCAT('استشارة مع: ', u.full_name) AS item_title, 'consultation' AS item_type
    FROM consultation_bookings b
    JOIN trainer_availabilities a ON b.availability_id = a.id
    JOIN users u ON a.trainer_id = u.id
    WHERE b.trainee_id = ?
");
$stmt_consults->execute([$user_id]);
$consult_payments = $stmt_consults->fetchAll();

foreach ($consult_payments as $cp) {
    $all_payments[] = $cp;
}

// ج. ترتيب المصفوفة المدمجة تنازلياً حسب التاريخ (من الأحدث للأقدم)
usort($all_payments, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

require_once 'includes/header.php';
?>

<!-- رأس الصفحة -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h3 class="fw-bold" style="color: #0b1c3f;"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> المدفوعات والفواتير</h3>
    
    <!-- إحصائية صغيرة في الأعلى -->
    <div class="bg-white px-4 py-2 rounded-pill shadow-sm border border-success border-opacity-25 d-flex align-items-center gap-3">
        <div class="text-success fs-3"><i class="fa-solid fa-sack-dollar"></i></div>
        <div>
            <span class="d-block text-muted small fw-bold mb-0">إجمالي المدفوعات المعتمدة للمنصة</span>
            <span class="fw-bold fs-5 text-dark">
                <?php 
                    $totals = [];
                    if($total_approved_usd > 0) $totals[] = '$'.number_format($total_approved_usd, 2);
                    if($total_approved_dzd > 0) $totals[] = number_format($total_approved_dzd).' د.ج';
                    echo empty($totals) ? '$0.00' : implode(' <span class="text-muted fs-6 mx-1">+</span> ', $totals);
                ?>
            </span>
        </div>
    </div>
</div>

<!-- رسالة تنبيهية تظهر فقط إذا كان هناك طلب مرفوض -->
<?php 
$has_rejected = array_search('rejected', array_column($all_payments, 'status'));
if ($has_rejected !== false): 
?>
<div class="alert alert-danger rounded-4 shadow-sm border-0 d-flex align-items-center gap-3 mb-4">
    <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
    <div>
        <h6 class="fw-bold mb-1">يوجد إيصال دفع مرفوض!</h6>
        <small>لقد تم رفض أحد إيصالاتك من قبل الإدارة. يرجى التأكد من وضوح الصورة، أو إعادة الشراء ورفع الإيصال الصحيح.</small>
    </div>
</div>
<?php endif; ?>

<!-- جدول المدفوعات (الفواتير) -->
<div class="card border-0 shadow-sm rounded-4 mb-5 overflow-hidden">
    <div class="card-header bg-white py-3 border-0">
        <h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-clock-rotate-left text-muted me-2"></i> سجل العمليات المالية الشامل</h5>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-light text-muted small fw-bold">
                    <tr>
                        <th class="py-3">الرقم</th>
                        <th class="py-3 text-start ps-4">بيان الدفع (المنتج)</th>
                        <th class="py-3">المبلغ المدفوع</th>
                        <th class="py-3">طريقة الدفع</th>
                        <th class="py-3">التاريخ</th>
                        <th class="py-3">حالة الإيصال</th>
                        <th class="py-3">الفاتورة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($all_payments)): ?>
                        <tr><td colspan="7" class="py-5 text-muted">
                            <i class="fa-solid fa-receipt fa-3x mb-3 opacity-25"></i>
                            <h6 class="fw-bold">لا توجد أي مدفوعات مسجلة باسمك حتى الآن.</h6>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach($all_payments as $payment): ?>
                        <tr class="<?php echo ($payment['status'] == 'rejected') ? 'table-danger table-opacity-10' : ''; ?>">
                            
                            <td class="fw-bold text-muted">
                                #<?php echo ($payment['item_type'] == 'course' ? 'C-' : 'S-') . str_pad($payment['id'], 5, '0', STR_PAD_LEFT); ?>
                            </td>
                            
                            <td class="fw-bold text-dark text-start ps-4">
                                <?php if($payment['item_type'] == 'course'): ?>
                                    <i class="fa-solid fa-book text-primary opacity-50 me-2"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-user-tie text-info opacity-50 me-2"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($payment['item_title']); ?>
                            </td>
                            
                            <!-- عرض المبلغ بدون أصفار عشرية للدينار -->
                            <td class="fw-bold fs-6 <?php echo ($payment['status'] == 'approved') ? 'text-success' : 'text-dark'; ?>">
                                <?php 
                                    if($payment['amount_usd']) echo '$'.(float)$payment['amount_usd'];
                                    elseif($payment['amount_dzd']) echo (float)$payment['amount_dzd'].' د.ج';
                                ?>
                            </td>

                            <!-- طريقة الدفع -->
                            <td>
                                <?php 
                                    if($payment['payment_method'] == 'bank') echo '<span class="badge bg-light text-primary border px-2 py-1"><i class="fa-solid fa-building-columns"></i> بنك</span>';
                                    elseif($payment['payment_method'] == 'paypal') echo '<span class="badge bg-light text-info border px-2 py-1"><i class="fa-brands fa-paypal"></i> بايبال</span>';
                                    elseif($payment['payment_method'] == 'agent') echo '<span class="badge bg-light text-secondary border px-2 py-1"><i class="fa-solid fa-handshake"></i> وكيل</span>';
                                    else echo '<span class="text-muted small">-</span>';
                                ?>
                            </td>
                            
                            <td class="text-muted small">
                                <span class="d-block fw-bold"><i class="fa-regular fa-calendar me-1"></i> <?php echo date('Y-m-d', strtotime($payment['created_at'])); ?></span>
                            </td>
                            
                            <td>
                                <?php 
                                    if($payment['status'] == 'approved') echo '<span class="badge bg-success rounded-pill px-3 py-2 shadow-sm"><i class="fa-solid fa-check"></i> مكتمل</span>';
                                    elseif($payment['status'] == 'pending') echo '<span class="badge bg-warning text-dark rounded-pill px-3 py-2 shadow-sm"><i class="fa-solid fa-clock"></i> قيد المراجعة</span>';
                                    elseif($payment['status'] == 'rejected') echo '<span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm"><i class="fa-solid fa-ban"></i> مرفوض</span>';
                                    elseif($payment['status'] == 'canceled') echo '<span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm"><i class="fa-solid fa-ban"></i> ملغية</span>';
                                    else echo '<span class="badge bg-secondary rounded-pill px-3 py-2">'.$payment['status'].'</span>';
                                ?>
                            </td>
                            
                            <td>
                                <!-- زر طباعة الفاتورة יظهر فقط إذا كان الدفع مكتملا -->
                                <?php if($payment['status'] == 'approved'): ?>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold shadow-sm" onclick="window.print()">
                                        <i class="fa-solid fa-print"></i> طباعة
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small opacity-50"><i class="fa-solid fa-lock"></i> غير متاحة</span>
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

<!-- إغلاق وسوم HTML -->
        </div> 
    </div> 
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const sidebar = document.getElementById("sidebar");
            const openBtn = document.getElementById("openSidebarBtn");
            const closeBtn = document.getElementById("closeSidebarBtn");
            const overlay = document.getElementById("sidebarOverlay");
            if(openBtn) {
                openBtn.addEventListener("click", function() { sidebar.classList.add("active"); overlay.classList.add("active"); });
                closeBtn.addEventListener("click", function() { sidebar.classList.remove("active"); overlay.classList.remove("active"); });
                overlay.addEventListener("click", function() { sidebar.classList.remove("active"); overlay.classList.remove("active"); });
            }
        });
    </script>
</body>
</html>