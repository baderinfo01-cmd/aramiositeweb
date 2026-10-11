<?php
require_once 'admin_init.php';
$success_msg = '';
$whatsapp_buttons = ''; // 💡 المتغير السحري لأزرار الواتساب

// 1. تأكيد الدفع النهائي للاستشارة وتحديد العمولة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_approval'])) {
    $order_id = $_POST['order_id'];
    $trainer_id = $_POST['trainer_id'];
    $comm_type = $_POST['commission_type'];
    $comm_value = $_POST['commission_value'];
    
    // جلب بيانات المبلغ، العميل، والمدرب
    $stmt = $pdo->prepare("
        SELECT o.amount_usd, o.amount_dzd, 
               u.full_name AS trainee_name, u.whatsapp_number AS trainee_wa, 
               t.full_name AS trainer_name, t.whatsapp_number AS trainer_wa, 
               c.title_ar AS course_title
        FROM course_orders o
        JOIN users u ON o.user_id = u.id
        JOIN courses c ON o.course_id = c.id
        JOIN users t ON c.trainer_id = t.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if ($order) {
        $commission = 0; $commission_currency = 'usd';
        $paid_amount_str = ''; $comm_amount_str = '';

        if ($order['amount_usd'] > 0) {
            $commission = ($comm_type == 'percentage') ? ($order['amount_usd'] * $comm_value) / 100 : $comm_value;
            $commission_currency = 'usd';
            $paid_amount_str = '$' . (float)$order['amount_usd'];
            $comm_amount_str = '$' . (float)$commission;
        } elseif ($order['amount_dzd'] > 0) {
            $commission = ($comm_type == 'percentage') ? ($order['amount_dzd'] * $comm_value) / 100 : $comm_value;
            $commission_currency = 'dzd';
            $paid_amount_str = (float)$order['amount_dzd'] . ' د.ج';
            $comm_amount_str = (float)$commission . ' د.ج';
        }

        // تحديث حالة الطلب وإيداع العمولة
        $pdo->prepare("UPDATE course_orders SET status = 'approved' WHERE id = ?")->execute([$order_id]);
        $pdo->prepare("INSERT INTO trainer_earnings (trainer_id, order_id, amount, currency) VALUES (?, ?, ?, ?)")->execute([$trainer_id, $order_id, $commission, $commission_currency]);
        
        $success_msg = "تم تفعيل الدورة للمتدرب! وتم إيداع عمولة المدرب بنجاح.";

        // 💡 سحر الواتساب: زر المتدرب (تهنئة بالتفعيل)
        if (!empty($order['trainee_wa'])) {
            $wa_num_t = preg_replace('/[^0-9]/', '', $order['trainee_wa']);
            $wa_msg_t = "مرحباً *" . $order['trainee_name'] . "* 🎉\n\n";
            $wa_msg_t .= "تم تأكيد استلام الدفع بنجاح (مبلغ " . $paid_amount_str . ").\n";
            $wa_msg_t .= "دورتك 📖 *" . $order['course_title'] . "* مفعلة الآن في لوحتك الشخصية.\n\n";
            $wa_msg_t .= "نتمنى لك رحلة تعلم ممتعة مع أراميو! 🚀";

            $wa_link_t = "https://wa.me/" . $wa_num_t . "?text=" . urlencode($wa_msg_t);
            $whatsapp_buttons .= "<a href='$wa_link_t' target='_blank' class='btn btn-success fw-bold rounded-pill px-4 m-2 shadow-sm pulse-button'><i class='fa-brands fa-whatsapp fs-5 me-2'></i> أرسل للمتدرب (تأكيد التفعيل)</a>";
        }

        // 💡 سحر الواتساب: زر المدرب (تهنئة بالمبيعة الجديدة)
        if (!empty($order['trainer_wa'])) {
            $wa_num_tr = preg_replace('/[^0-9]/', '', $order['trainer_wa']);
            $wa_msg_tr = "مرحباً كوتش *" . $order['trainer_name'] . "* 💸\n\n";
            $wa_msg_tr .= "يسعدنا إبلاغك بوجود مبيعة جديدة لدورتك 📖 *" . $order['course_title'] . "*.\n";
            $wa_msg_tr .= "لقد تم إيداع عمولتك بقيمة *[" . $comm_amount_str . "]* في رصيدك المعلق بالمنصة.\n\n";
            $wa_msg_tr .= "إلى مزيد من النجاح والتألق! 🌟";

            $wa_link_tr = "https://wa.me/" . $wa_num_tr . "?text=" . urlencode($wa_msg_tr);
            $whatsapp_buttons .= "<a href='$wa_link_tr' target='_blank' class='btn btn-warning text-dark fw-bold rounded-pill px-4 m-2 shadow-sm pulse-button-trainer'><i class='fa-brands fa-whatsapp fs-5 me-2'></i> بشّر المدرب (مبيعة جديدة)</a>";
        }
    } else {
        $error_msg = "حدث خطأ أثناء استرجاع بيانات الطلب.";
    }
}

// 2. رفض الوصل
if (isset($_GET['action']) && $_GET['action'] == 'reject' && isset($_GET['id'])) {
    $order_id = $_GET['id'];
    $pdo->prepare("UPDATE course_orders SET status = 'rejected' WHERE id = ?")->execute([$order_id]);
    $success_msg = "تم رفض الوصل بنجاح.";

    // 💡 سحر الواتساب: رسالة الرفض للمتدرب
    $order_info = $pdo->query("SELECT u.full_name, u.whatsapp_number FROM course_orders o JOIN users u ON o.user_id = u.id WHERE o.id = $order_id")->fetch();
    
    if ($order_info && !empty($order_info['whatsapp_number'])) {
        $wa_num = preg_replace('/[^0-9]/', '', $order_info['whatsapp_number']);
        $wa_msg = "عذراً *" . $order_info['full_name'] . "* ⚠️\n\n";
        $wa_msg .= "واجهتنا مشكلة في تأكيد إيصال الدفع الخاص بطلبك رقم [#" . str_pad($order_id, 5, '0', STR_PAD_LEFT) . "].\n";
        $wa_msg .= "يرجى التأكد من وضوح الصورة وإعادة رفعها من المتجر، أو تزويدنا بتفاصيل الحوالة لمساعدتك. 🤝";

        $wa_link = "https://wa.me/" . $wa_num . "?text=" . urlencode($wa_msg);
        $whatsapp_buttons .= "<a href='$wa_link' target='_blank' class='btn btn-danger fw-bold rounded-pill px-4 mt-3 shadow-sm pulse-button-danger'><i class='fa-brands fa-whatsapp fs-5 me-2'></i> تنبيه المتدرب بالرفض (واتساب)</a>";
    }
}

// جلب جميع الطلبات
$orders = $pdo->query("
    SELECT o.*, u.full_name AS trainee_name, c.title_ar AS course_title, 
           c.trainer_id, t.full_name AS trainer_name
    FROM course_orders o 
    JOIN users u ON o.user_id = u.id 
    JOIN courses c ON o.course_id = c.id 
    JOIN users t ON c.trainer_id = t.id
    ORDER BY o.created_at DESC
")->fetchAll();

require_once 'includes/header.php';
?>

<style>
    /* أنيميشن النبض لأزرار الواتساب (3 ألوان مختلفة) */
    @keyframes pulse { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); } 70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(37, 211, 102, 0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); } }
    @keyframes pulseTrainer { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(246, 194, 62, 0.7); } 70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(246, 194, 62, 0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(246, 194, 62, 0); } }
    @keyframes pulseDanger { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
    
    .pulse-button { animation: pulse 2s infinite; background-color: #25d366 !important; border-color: #25d366 !important; color: white !important; }
    .pulse-button:hover { background-color: #128c7e !important; }
    
    .pulse-button-trainer { animation: pulseTrainer 2.2s infinite; background-color: #f6c23e !important; border-color: #f6c23e !important; }
    
    .pulse-button-danger { animation: pulseDanger 2s infinite; color: white !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> مراجعة طلبات دفع الدورات وتحديد العمولة</h3>
</div>

<?php if(isset($error_msg) && $error_msg): ?> 
    <div class="alert alert-danger rounded-4 fw-bold shadow-sm border-0 p-4 mb-4 text-center">
        <i class="fa-solid fa-triangle-exclamation fa-2x mb-3 text-danger"></i><br>
        <span class="fs-5 text-dark"><?php echo $error_msg; ?></span>
    </div>
<?php endif; ?>

<!-- 💡 منطقة الإشعارات (تظهر الأزرار هنا) -->
<?php if($success_msg): ?> 
    <div class="alert alert-success rounded-4 fw-bold shadow-sm border-0 text-center p-4 mb-4">
        <i class="fa-solid fa-check-circle fa-3x mb-3 text-success"></i><br>
        <span class="fs-5 text-dark mb-3 d-block"><?php echo $success_msg; ?></span>
        <div class="d-flex justify-content-center flex-wrap gap-2">
            <?php echo $whatsapp_buttons; ?>
        </div>
    </div>
<?php endif; ?>

<div class="admin-card card p-0 overflow-hidden mb-5">
    <div class="table-responsive">
        <table class="table table-hover align-middle text-center mb-0">
            <thead class="table-light text-muted small fw-bold">
                <tr>
                    <th class="py-3">الطلب</th>
                    <th class="py-3 text-start ps-4">المتدرب / الدورة</th>
                    <th class="py-3">المبلغ المدفوع (للمنصة)</th>
                    <th class="py-3">طريقة الدفع / الوصل</th>
                    <th class="py-3">المدرب (صاحب الدورة)</th>
                    <th class="py-3">الإجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($orders)): ?>
                    <tr><td colspan="6" class="py-5 text-muted fw-bold">لا توجد طلبات شراء دورات حالياً.</td></tr>
                <?php else: ?>
                    <?php foreach($orders as $order): ?>
                    <tr class="<?php echo ($order['status'] == 'pending') ? 'table-warning table-opacity-10' : ''; ?>">
                        
                        <td class="fw-bold text-muted">#<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        
                        <td class="text-start ps-4">
                            <strong class="text-dark"><i class="fa-solid fa-user-graduate text-primary opacity-50 me-1"></i> <?php echo htmlspecialchars($order['trainee_name']); ?></strong><br>
                            <small class="text-muted fw-bold"><?php echo htmlspecialchars($order['course_title']); ?></small>
                        </td>
                        
                        <td class="fw-bold text-success fs-5">
                            <?php 
                                $paid_amount = 0; $paid_curr = '';
                                if($order['amount_usd']) { $paid_amount = (float)$order['amount_usd']; $paid_curr = '$'; echo '$'.$paid_amount; }
                                elseif($order['amount_dzd']) { $paid_amount = (float)$order['amount_dzd']; $paid_curr = 'د.ج'; echo $paid_amount.' د.ج'; }
                            ?>
                        </td>
                        
                        <td>
                            <?php 
                                if($order['payment_method'] == 'bank') echo '<span class="badge bg-light text-primary border mb-2 d-block"><i class="fa-solid fa-building-columns"></i> بنك</span>';
                                elseif($order['payment_method'] == 'paypal') echo '<span class="badge bg-light text-info border mb-2 d-block"><i class="fa-brands fa-paypal"></i> بايبال</span>';
                                elseif($order['payment_method'] == 'agent') echo '<span class="badge bg-light text-secondary border mb-2 d-block"><i class="fa-solid fa-handshake"></i> وكيل</span>';
                            ?>
                            <a href="../uploads/receipts/<?php echo $order['receipt_image']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold shadow-sm"><i class="fa-solid fa-image"></i> الوصل</a>
                        </td>
                        
                        <td class="fw-bold text-dark">
                            <i class="fa-solid fa-user-tie text-muted me-1"></i> <?php echo htmlspecialchars($order['trainer_name']); ?>
                        </td>
                        
                        <td>
                            <?php if($order['status'] == 'pending'): ?>
                                <button class="btn btn-sm btn-success rounded-pill fw-bold shadow-sm mb-1 d-block mx-auto" 
                                    onclick="openApproveModal(<?php echo $order['id']; ?>, <?php echo $order['trainer_id']; ?>, '<?php echo addslashes($order['trainee_name']); ?>', '<?php echo $paid_amount; ?>', '<?php echo $paid_curr; ?>')">
                                    <i class="fa-solid fa-check me-1"></i> تأكيد وتحديد العمولة
                                </button>
                                <a href="?action=reject&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-danger rounded-circle" onclick="return confirm('تأكيد رفض الإيصال؟')" title="رفض الوصل"><i class="fa-solid fa-xmark"></i></a>
                            <?php elseif($order['status'] == 'approved'): ?>
                                <span class="badge bg-success rounded-pill px-3 py-2"><i class="fa-solid fa-check-double"></i> مكتمل</span>
                            <?php else: ?>
                                <span class="badge bg-danger rounded-pill px-3 py-2"><i class="fa-solid fa-ban"></i> مرفوض</span>
                            <?php endif; ?>
                        </td>
                        
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal تأكيد الدفع وتحديد العمولة -->
<div class="modal fade" id="approveModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-success text-white border-0 rounded-top-4">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-check-double me-2"></i> تأكيد وتفعيل الدورة</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
          <div class="modal-body p-4 text-center">
              <h5 class="fw-bold text-dark mb-4">دفع المتدرب: <span id="modalTraineeName" class="text-primary"></span></h5>
              <h3 class="fw-bold text-success text-center mb-4" dir="ltr"><span id="modalPaidAmount"></span> <span id="modalPaidCurr"></span></h3>
              
              <div class="bg-light p-3 rounded-3 border border-warning mb-4">
                  <p class="text-muted fw-bold mb-2"><i class="fa-solid fa-hand-holding-dollar text-warning me-1"></i> حدد نصيب المدرب من هذه المبيعة:</p>
                  <div class="row">
                      <div class="col-6 mb-2">
                          <label class="form-label fw-bold small text-muted">نوع العمولة</label>
                          <select name="commission_type" class="form-select fw-bold" required>
                              <option value="percentage">نسبة مئوية (%)</option>
                              <option value="fixed">مبلغ ثابت مقطوع</option>
                          </select>
                      </div>
                      <div class="col-6 mb-2">
                          <label class="form-label fw-bold small text-muted">القيمة</label>
                          <input type="number" step="0.01" name="commission_value" class="form-control fw-bold text-center" placeholder="مثال: 60" required>
                      </div>
                  </div>
              </div>
              
              <input type="hidden" name="order_id" id="modalOrderId">
              <input type="hidden" name="trainer_id" id="modalTrainerId">
          </div>
          <div class="modal-footer border-0 bg-light rounded-bottom-4">
              <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" name="confirm_approval" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">حفظ وتفعيل الدورة</button>
          </div>
      </form>
    </div>
  </div>
</div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openApproveModal(orderId, trainerId, traineeName, paidAmount, paidCurr) {
        document.getElementById('modalOrderId').value = orderId;
        document.getElementById('modalTrainerId').value = trainerId;
        document.getElementById('modalTraineeName').innerText = traineeName;
        document.getElementById('modalPaidAmount').innerText = paidAmount;
        document.getElementById('modalPaidCurr').innerText = paidCurr;
        new bootstrap.Modal(document.getElementById('approveModal')).show();
    }
    
    document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); });
    document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });
</script>
</body></html>