<?php
require_once '../includes/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainer') {
    header('Location: ../login.php');
    exit();
}

$trainer_id = $_SESSION['user_id'];

// إحصائيات الأرباح 
$earn_usd = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id = $trainer_id AND currency = 'usd'")->fetchColumn() ?: 0);
$earn_dzd = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id = $trainer_id AND currency = 'dzd'")->fetchColumn() ?: 0);

$pend_usd = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id = $trainer_id AND currency = 'usd' AND is_paid = 0")->fetchColumn() ?: 0);
$pend_dzd = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM trainer_earnings WHERE trainer_id = $trainer_id AND currency = 'dzd' AND is_paid = 0")->fetchColumn() ?: 0);

$paid_usd = $earn_usd - $pend_usd;
$paid_dzd = $earn_dzd - $pend_dzd;

// جلب السجل المفصل
$earnings_history = $pdo->prepare("
    SELECT e.*, c.title_ar, u.full_name AS trainee_name, o.amount_usd, o.amount_dzd 
    FROM trainer_earnings e 
    JOIN course_orders o ON e.order_id = o.id 
    JOIN courses c ON o.course_id = c.id 
    JOIN users u ON o.user_id = u.id 
    WHERE e.trainer_id = ? 
    ORDER BY e.created_at DESC
");
$earnings_history->execute([$trainer_id]);
$earnings_history = $earnings_history->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h3 class="fw-bold" style="color: #0b1c3f;"><i class="fa-solid fa-wallet text-warning me-2"></i> الأرباح والمستحقات</h3>
    <!-- زر طلب سحب الرصيد -->
    <button class="btn btn-warning text-dark rounded-pill fw-bold px-4 shadow-sm" <?php echo ($pend_usd <= 0 && $pend_dzd <= 0) ? 'disabled' : ''; ?> onclick="alert('سيتم إرسال طلب للإدارة لتحويل رصيدك المعلق إلى حسابك البنكي.')">
        <i class="fa-solid fa-money-bill-transfer me-2"></i> طلب سحب الأرباح
    </button>
</div>

<!-- بطاقات الإحصائيات المالية الملونة -->
<div class="row g-4 mb-5">
    
    <!-- بطاقة الرصيد المعلق (أهم بطاقة للمدرب) -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #1cc88a 0%, #159f6d 100%); color: white;">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <p class="mb-1 opacity-75 fw-bold">الرصيد المتاح للسحب (معلق)</p>
                        <h3 class="fw-bold mb-1"><?php echo ($pend_usd > 0) ? '$'.$pend_usd : ''; ?></h3>
                        <h4 class="fw-bold m-0"><?php echo ($pend_dzd > 0) ? number_format($pend_dzd).' د.ج' : ''; ?></h4>
                        <?php if($pend_usd == 0 && $pend_dzd == 0) echo '<h3 class="fw-bold mb-0">$0</h3>'; ?>
                    </div>
                    <div class="bg-white rounded-circle p-3 text-success d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-sack-dollar fa-2x"></i>
                    </div>
                </div>
                <small class="opacity-75 fw-bold"><i class="fa-solid fa-circle-info"></i> هذا الرصيد جاهز للتحويل إلى حسابك.</small>
            </div>
        </div>
    </div>

    <!-- بطاقة الأرباح المحولة (المستلمة) -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 border-left-secondary" style="border-right: 4px solid #858796;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <p class="text-muted fw-bold mb-2 small text-uppercase">المبالغ المستلمة (تم تحويلها)</p>
                    <h4 class="fw-bold text-dark mb-1"><?php echo ($paid_usd > 0) ? '$'.$paid_usd : ''; ?></h4>
                    <h5 class="fw-bold text-dark m-0"><?php echo ($paid_dzd > 0) ? number_format($paid_dzd).' د.ج' : ''; ?></h5>
                    <?php if($paid_usd == 0 && $paid_dzd == 0) echo '<h4 class="fw-bold text-dark mb-0">$0</h4>'; ?>
                </div>
                <div class="bg-light rounded-circle p-3 text-secondary d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <i class="fa-solid fa-building-columns fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- بطاقة إجمالي الأرباح الكلية -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 border-left-primary" style="border-right: 4px solid #4e73df;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <p class="text-muted fw-bold mb-2 small text-uppercase">إجمالي المبيعات الكلي</p>
                    <h4 class="fw-bold text-primary mb-1"><?php echo ($earn_usd > 0) ? '$'.$earn_usd : ''; ?></h4>
                    <h5 class="fw-bold text-primary m-0"><?php echo ($earn_dzd > 0) ? number_format($earn_dzd).' د.ج' : ''; ?></h5>
                    <?php if($earn_usd == 0 && $earn_dzd == 0) echo '<h4 class="fw-bold text-primary mb-0">$0</h4>'; ?>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <i class="fa-solid fa-chart-line fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- جدول سجل الأرباح المفصل -->
<div class="card border-0 shadow-sm rounded-4 mb-5 overflow-hidden">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-clock-rotate-left text-muted me-2"></i> السجل المفصل للمبيعات والأرباح</h5>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-light text-muted small fw-bold">
                    <tr>
                        <th class="py-3 text-start ps-4">اسم الدورة المُباعة</th>
                        <th class="py-3">المشتري (الطالب)</th>
                        <th class="py-3">تاريخ البيع</th>
                        <th class="py-3">المبلغ الذي دفع للمنصة</th>
                        <th class="py-3">عمولتك الصافية</th>
                        <th class="py-3">حالة الرصيد</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($earnings_history)): ?>
                        <tr><td colspan="6" class="py-5 text-muted">
                            <i class="fa-solid fa-coins fa-3x mb-3 opacity-25"></i>
                            <h6 class="fw-bold">لم تحقق أي مبيعات حتى الآن.</h6>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach($earnings_history as $earn): ?>
                        <tr>
                            <td class="fw-bold text-dark text-start ps-4">
                                <i class="fa-solid fa-book text-warning opacity-75 me-2"></i> <?php echo htmlspecialchars($earn['title_ar']); ?>
                            </td>
                            <td class="text-muted fw-bold">
                                <i class="fa-solid fa-user-graduate me-1 opacity-50"></i> <?php echo htmlspecialchars($earn['trainee_name']); ?>
                            </td>
                            <td class="text-muted small fw-bold">
                                <?php echo date('Y-m-d', strtotime($earn['created_at'])); ?>
                            </td>
                            
                            <!-- السعر الذي دُفع للمنصة (بدون أصفار زائدة) -->
                            <td class="text-muted fw-bold fs-6">
                                <?php 
                                    if($earn['amount_usd']) echo '$'.(float)$earn['amount_usd'];
                                    elseif($earn['amount_dzd']) echo number_format((float)$earn['amount_dzd']).' د.ج';
                                ?>
                            </td>
                            
                            <!-- عمولة المدرب الصافية (باللون الأخضر) -->
                            <td class="fw-bold fs-5 text-success">
                                <?php 
                                    if($earn['currency'] == 'usd') echo '+ $'.(float)$earn['amount'];
                                    else echo '+ '.number_format((float)$earn['amount']).' د.ج';
                                ?>
                            </td>
                            
                            <td>
                                <?php if($earn['is_paid'] == 1): ?>
                                    <span class="badge bg-secondary rounded-pill px-3 py-2 shadow-sm"><i class="fa-solid fa-check-double"></i> تم التحويل</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold shadow-sm" style="background-color: #f6c23e;"><i class="fa-solid fa-hourglass-half"></i> معلق بانتظار السحب</span>
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
            const overlay = document.getElementById("sidebarOverlay");
            if(openBtn) {
                openBtn.addEventListener("click", () => { sidebar.classList.add("active"); overlay.classList.add("active"); });
                overlay.addEventListener("click", () => { sidebar.classList.remove("active"); overlay.classList.remove("active"); });
            }
        });
    </script>
</body>
</html>