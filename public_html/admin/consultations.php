<?php
require_once 'admin_init.php';
$success_msg = '';

// 1. تأكيد الدفع النهائي للاستشارة وتحديد العمولة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_consultation'])) {
    $booking_id = $_POST['booking_id'];
    $trainer_id = $_POST['trainer_id'];
    $comm_type = $_POST['commission_type'];
    $comm_value = $_POST['commission_value'];
    
    // جلب بيانات المبلغ من الحجز
    $stmt = $pdo->prepare("SELECT amount_usd, amount_dzd FROM consultation_bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    $commission = 0;
    $commission_currency = 'usd';

    if ($booking['amount_usd'] > 0) {
        $commission = ($comm_type == 'percentage') ? ($booking['amount_usd'] * $comm_value) / 100 : $comm_value;
        $commission_currency = 'usd';
    } elseif ($booking['amount_dzd'] > 0) {
        $commission = ($comm_type == 'percentage') ? ($booking['amount_dzd'] * $comm_value) / 100 : $comm_value;
        $commission_currency = 'dzd';
    }

    // تحديث حالة الحجز إلى 'مقبول'
    $pdo->prepare("UPDATE consultation_bookings SET status = 'approved' WHERE id = ?")->execute([$booking_id]);
    
    // إيداع الأرباح في رصيد المدرب
    $pdo->prepare("INSERT INTO trainer_earnings (trainer_id, booking_id, amount, currency) VALUES (?, ?, ?, ?)")->execute([$trainer_id, $booking_id, $commission, $commission_currency]);
    
    $success_msg = "تم تأكيد حجز الاستشارة! وتم إيداع عمولة المدرب بنجاح.";
}

// 2. رفض الوصل
if (isset($_GET['action']) && $_GET['action'] == 'reject') {
    $pdo->prepare("UPDATE consultation_bookings SET status = 'rejected' WHERE id = ?")->execute([$_GET['id']]);
    // إعادة فتح الموعد في التقويم ليحجزه شخص آخر
    $booking_info = $pdo->query("SELECT availability_id FROM consultation_bookings WHERE id = ".$_GET['id'])->fetch();
    $pdo->prepare("UPDATE trainer_availabilities SET is_booked = 0 WHERE id = ?")->execute([$booking_info['availability_id']]);
    
    $success_msg = "تم رفض الوصل وإعادة إتاحة الموعد في التقويم.";
}

// 3. جلب طلبات الاستشارات (جميع الحالات)
$bookings = $pdo->query("
    SELECT b.*, u.full_name AS trainee_name, 
           a.trainer_id, t.full_name AS trainer_name, a.available_date, a.start_time
    FROM consultation_bookings b 
    JOIN users u ON b.trainee_id = u.id 
    JOIN trainer_availabilities a ON b.availability_id = a.id
    JOIN users t ON a.trainer_id = t.id
    ORDER BY b.created_at DESC
")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fa-solid fa-calendar-check text-success me-2"></i> مراجعة مدفوعات الاستشارات</h3>
</div>

<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>

<div class="admin-card card p-0 overflow-hidden mb-5">
    <div class="table-responsive">
        <table class="table table-hover align-middle text-center mb-0">
            <thead class="table-light text-muted small fw-bold">
                <tr>
                    <th class="py-3 text-start ps-4">المتدرب / موعد الجلسة</th>
                    <th class="py-3">المبلغ المدفوع</th>
                    <th class="py-3">طريقة الدفع / الوصل</th>
                    <th class="py-3">المدرب (المستشار)</th>
                    <th class="py-3">الإجراء / الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($bookings)): ?>
                    <tr><td colspan="5" class="py-5 text-muted fw-bold">لا توجد طلبات حجز استشارات في النظام حالياً.</td></tr>
                <?php else: ?>
                    <?php foreach($bookings as $book): ?>
                    <tr class="<?php echo ($book['status'] == 'pending') ? 'table-warning table-opacity-10' : ''; ?>">
                        
                        <!-- بيانات المتدرب والموعد -->
                        <td class="text-start ps-4">
                            <strong class="text-dark"><i class="fa-solid fa-user-graduate text-primary opacity-50 me-1"></i> <?php echo htmlspecialchars($book['trainee_name']); ?></strong><br>
                            <small class="text-primary fw-bold"><i class="fa-regular fa-calendar me-1"></i> <?php echo date('Y-m-d', strtotime($book['available_date'])); ?> | <span dir="ltr"><?php echo date('h:i A', strtotime($book['start_time'])); ?></span></small>
                        </td>
                        
                        <!-- السعر الفعلي -->
                        <td class="fw-bold text-success fs-5">
                            <?php 
                                $paid_amount = 0; $paid_curr = '';
                                if($book['amount_usd']) { $paid_amount = (float)$book['amount_usd']; $paid_curr = '$'; echo '$'.$paid_amount; }
                                elseif($book['amount_dzd']) { $paid_amount = (float)$book['amount_dzd']; $paid_curr = 'د.ج'; echo $paid_amount.' د.ج'; }
                            ?>
                        </td>
                        
                        <!-- حالة الدفع والوصل -->
                        <td>
                            <?php if($book['status'] == 'reserved'): ?>
                                <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><i class="fa-solid fa-clock"></i> لم يرفع الوصل بعد</span>
                            <?php elseif($book['status'] == 'canceled'): ?>
                                <span class="badge bg-danger rounded-pill px-3 py-2"><i class="fa-solid fa-ban"></i> ملغية (تأخر في الدفع)</span>
                            <?php else: ?>
                                <?php 
                                    if($book['payment_method'] == 'bank') echo '<span class="badge bg-light text-primary border mb-2 d-block"><i class="fa-solid fa-building-columns"></i> بنك</span>';
                                    elseif($book['payment_method'] == 'paypal') echo '<span class="badge bg-light text-info border mb-2 d-block"><i class="fa-brands fa-paypal"></i> بايبال</span>';
                                    elseif($book['payment_method'] == 'agent') echo '<span class="badge bg-light text-secondary border mb-2 d-block"><i class="fa-solid fa-handshake"></i> وكيل</span>';
                                ?>
                                <?php if(!empty($book['receipt_image'])): ?>
                                    <a href="../uploads/receipts/<?php echo $book['receipt_image']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold shadow-sm"><i class="fa-solid fa-image"></i> الوصل</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        
                        <!-- المدرب -->
                        <td class="fw-bold text-dark">
                            <i class="fa-solid fa-user-tie text-muted me-1"></i> <?php echo htmlspecialchars($book['trainer_name']); ?>
                        </td>
                        
                        <!-- الإجراء أو الحالة النهائية -->
                        <td>
                            <?php if($book['status'] == 'pending'): ?>
                                <button class="btn btn-sm btn-success rounded-pill fw-bold shadow-sm mb-1 d-block mx-auto" 
                                    onclick="openConsModal(<?php echo $book['id']; ?>, <?php echo $book['trainer_id']; ?>, '<?php echo addslashes($book['trainee_name']); ?>', '<?php echo $paid_amount; ?>', '<?php echo $paid_curr; ?>')">
                                    <i class="fa-solid fa-check me-1"></i> مراجعة وتحديد العمولة
                                </button>
                                <a href="?action=reject&id=<?php echo $book['id']; ?>" class="btn btn-sm btn-danger rounded-circle" onclick="return confirm('تأكيد رفض الإيصال وإلغاء الحجز؟')" title="رفض"><i class="fa-solid fa-xmark"></i></a>
                            <?php elseif($book['status'] == 'approved' || $book['status'] == 'completed'): ?>
                                <span class="badge bg-success rounded-pill px-3 py-2"><i class="fa-solid fa-check-double"></i> مكتمل</span>
                            <?php elseif($book['status'] == 'rejected'): ?>
                                <span class="badge bg-danger rounded-pill px-3 py-2"><i class="fa-solid fa-ban"></i> مرفوض</span>
                            <?php elseif($book['status'] == 'reserved' || $book['status'] == 'canceled'): ?>
                                <span class="text-muted small opacity-50"><i class="fa-solid fa-lock"></i> لا يوجد إجراء</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal تأكيد الاستشارة وتحديد العمولة -->
<div class="modal fade" id="consModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-success text-white border-0 rounded-top-4">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-check-double me-2"></i> تأكيد حجز الاستشارة</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
          <div class="modal-body p-4 text-center">
              <h5 class="fw-bold text-dark mb-4">هل أنت متأكد من تأكيد الموعد للمتدرب: <br><span id="modalTraineeName" class="text-primary"></span>؟</h5>
              <h3 class="fw-bold text-success text-center mb-4" dir="ltr"><span id="modalPaidAmount"></span> <span id="modalPaidCurr"></span></h3>
              
              <div class="bg-light p-3 rounded-3 border border-warning mb-4">
                  <p class="text-muted fw-bold mb-2"><i class="fa-solid fa-hand-holding-dollar text-warning me-1"></i> حدد نصيب المدرب (المستشار) من هذه الجلسة:</p>
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
                          <input type="number" step="0.01" name="commission_value" class="form-control fw-bold text-center" placeholder="مثال: 70" required>
                      </div>
                  </div>
              </div>
              
              <input type="hidden" name="booking_id" id="modalBookingId">
              <input type="hidden" name="trainer_id" id="modalTrainerId">
          </div>
          <div class="modal-footer border-0 bg-light rounded-bottom-4">
              <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" name="confirm_consultation" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">حفظ وتأكيد الموعد</button>
          </div>
      </form>
    </div>
  </div>
</div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openConsModal(bookingId, trainerId, traineeName, paidAmount, paidCurr) {
        document.getElementById('modalBookingId').value = bookingId;
        document.getElementById('modalTrainerId').value = trainerId;
        document.getElementById('modalTraineeName').innerText = traineeName;
        document.getElementById('modalPaidAmount').innerText = paidAmount;
        document.getElementById('modalPaidCurr').innerText = paidCurr;
        new bootstrap.Modal(document.getElementById('consModal')).show();
    }
    
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