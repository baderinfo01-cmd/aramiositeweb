<?php
require_once 'admin_init.php'; $success_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $pdo->prepare("INSERT INTO expenses (title, amount, currency, expense_date) VALUES (?, ?, ?, ?)")->execute([$_POST['title'], $_POST['amount'], $_POST['currency'], $_POST['expense_date']]);
    $success_msg = "تم تسجيل المنصرف بنجاح.";
}

// 💡 التعديل: تصفية حساب المدرب بناءً على العملة
if (isset($_GET['pay_trainer']) && isset($_GET['curr'])) {
    $pdo->prepare("UPDATE trainer_earnings SET is_paid = 1 WHERE trainer_id = ? AND currency = ? AND is_paid = 0")->execute([$_GET['pay_trainer'], $_GET['curr']]);
    $success_msg = "تم تصفية حساب المدرب لعملة (".$_GET['curr'].") بنجاح.";
}

$expenses = $pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC")->fetchAll();

// جلب ديون المدربين (مفصلة لكل عملة)
$trainer_debts = $pdo->query("
    SELECT u.id, u.full_name, 
           SUM(CASE WHEN e.currency = 'usd' THEN e.amount ELSE 0 END) AS owed_usd, 
           SUM(CASE WHEN e.currency = 'dzd' THEN e.amount ELSE 0 END) AS owed_dzd 
    FROM trainer_earnings e JOIN users u ON e.trainer_id = u.id 
    WHERE e.is_paid = 0 
    GROUP BY u.id HAVING owed_usd > 0 OR owed_dzd > 0
")->fetchAll();

require_once 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4"><h3 class="fw-bold text-dark"><i class="fa-solid fa-money-bill-trend-up text-danger me-2"></i> المصاريف وتسديد المدربين</h3></div>
<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>

<div class="row g-4 mb-5">
    
    <!-- قسم السداد (الدينار والدولار بشكل منفصل) -->
    <div class="col-lg-7">
        <div class="admin-card card h-100 border-left-warning p-0 overflow-hidden">
            <div class="card-header bg-white py-3 border-0"><h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-users-rectangle text-warning me-2"></i> مستحقات المدربين (يجب تحويلها لهم)</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle text-center mb-0">
                    <thead class="table-light text-muted small fw-bold"><tr><th class="text-start ps-4">المدرب</th><th>الرصيد المستحق ($)</th><th>الرصيد المستحق (د.ج)</th></tr></thead>
                    <tbody>
                        <?php if(empty($trainer_debts)): ?>
                            <tr><td colspan="3" class="py-5 text-success fw-bold"><i class="fa-solid fa-check-double fa-2x mb-2"></i><br>تم سداد جميع المدربين. لا توجد ديون!</td></tr>
                        <?php else: ?>
                            <?php foreach($trainer_debts as $debt): ?>
                            <tr>
                                <td class="fw-bold text-dark text-start ps-4"><i class="fa-solid fa-user-tie text-muted me-2"></i> <?php echo htmlspecialchars($debt['full_name']); ?></td>
                                
                                <!-- السداد بالدولار -->
                                <td class="fw-bold text-danger fs-5">
                                    <?php if($debt['owed_usd'] > 0): ?>
                                        -$<?php echo (float)$debt['owed_usd']; ?><br>
                                        <a href="?pay_trainer=<?php echo $debt['id']; ?>&curr=usd" class="btn btn-sm btn-outline-danger rounded-pill px-3 mt-2 fw-bold" onclick="return confirm('تأكيد التحويل؟')">تأكيد سداد ($)</a>
                                    <?php else: ?> <span class="text-muted opacity-25">-</span> <?php endif; ?>
                                </td>
                                
                                <!-- السداد بالدينار -->
                                <td class="fw-bold text-danger fs-5">
                                    <?php if($debt['owed_dzd'] > 0): ?>
                                        -<?php echo (float)$debt['owed_dzd']; ?> د.ج<br>
                                        <a href="?pay_trainer=<?php echo $debt['id']; ?>&curr=dzd" class="btn btn-sm btn-outline-danger rounded-pill px-3 mt-2 fw-bold" onclick="return confirm('تأكيد التحويل؟')">تأكيد سداد (د.ج)</a>
                                    <?php else: ?> <span class="text-muted opacity-25">-</span> <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- قسم تسجيل المصاريف -->
    <div class="col-lg-5">
        <div class="admin-card card h-100 border-left-danger p-4">
            <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-minus-circle text-danger me-2"></i> تسجيل منصرف جديد</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">بيان الصرف (مثال: إعلانات، سيرفر)</label>
                    <input type="text" name="title" class="form-control fw-bold" required>
                </div>
                <div class="row mb-4">
                    <div class="col-6">
                        <label class="form-label text-muted small fw-bold">المبلغ</label>
                        <input type="number" step="0.01" name="amount" class="form-control text-danger fw-bold text-center fs-5" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-muted small fw-bold">العملة</label>
                        <select name="currency" class="form-select fw-bold text-danger" required>
                            <option value="usd">دولار ($)</option>
                            <option value="dzd">دينار (د.ج)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small fw-bold">التاريخ</label>
                    <input type="date" name="expense_date" class="form-control fw-bold text-center" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <button type="submit" name="add_expense" class="btn btn-danger w-100 rounded-pill fw-bold shadow-sm py-2">تسجيل وخصم من الأرباح</button>
            </form>
        </div>
    </div>
</div>

</div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script><script>document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); }); document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });</script></body></html>