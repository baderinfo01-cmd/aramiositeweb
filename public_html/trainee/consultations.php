<?php
require_once '../includes/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainee') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = ''; $error_msg = ''; $searched_slot = null;

// ==========================================================
// 💡 نظام التنظيف والإلغاء (يتم عرض آخر 5 عمليات فقط في الجداول)
// ==========================================================
$stmt_expired = $pdo->prepare("SELECT id, availability_id FROM consultation_bookings WHERE status = 'reserved' AND created_at <= DATE_SUB(NOW(), INTERVAL 60 MINUTE)");
$stmt_expired->execute();
$expired_bookings = $stmt_expired->fetchAll();

foreach ($expired_bookings as $exp) {
    // إرجاع الموعد للمدرب
    $pdo->prepare("UPDATE trainer_availabilities SET is_booked = 0 WHERE id = ?")->execute([$exp['availability_id']]);
    // حذف الحجز التالف نهائيا من وجه المتدرب
    $pdo->prepare("DELETE FROM consultation_bookings WHERE id = ?")->execute([$exp['id']]);
}

// 1. البحث السريع بكود الاستشارة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_code'])) {
    $code = trim($_POST['slot_code']);
    $stmt = $pdo->prepare("SELECT a.*, u.full_name AS trainer_name, u.avatar FROM trainer_availabilities a JOIN users u ON a.trainer_id = u.id WHERE a.slot_code = ? AND a.is_booked = 0 AND a.available_date >= CURDATE()");
    $stmt->execute([$code]); $searched_slot = $stmt->fetch();
    if(!$searched_slot) $error_msg = "الكود غير صحيح أو أن الموعد قد تم حجزه.";
}

// 2. حجز الموعد المبدئي (من القائمة أو الكود)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reserve_appointment'])) {
    $availability_id = $_POST['availability_id'];
    $trainee_problem = trim($_POST['trainee_problem']); 
    
    $stmt = $pdo->prepare("SELECT price_usd, price_dzd FROM trainer_availabilities WHERE id = ? AND is_booked = 0"); 
    $stmt->execute([$availability_id]); 
    $price = $stmt->fetch();
    
    if ($price) {
        $insert = $pdo->prepare("INSERT INTO consultation_bookings (trainee_id, availability_id, amount_usd, amount_dzd, trainee_problem, status) VALUES (?, ?, ?, ?, ?, 'reserved')");
        if ($insert->execute([$user_id, $availability_id, $price['price_usd'], $price['price_dzd'], $trainee_problem])) {
            $pdo->prepare("UPDATE trainer_availabilities SET is_booked = 1 WHERE id = ?")->execute([$availability_id]);
            $success_msg = "تم حجز الموعد مبدئياً! لديك 60 دقيقة لرفع الإيصال وتأكيد الحجز.";
        }
    } else { $error_msg = "عذراً، هذا الموعد غير متاح."; }
}

// 3. طلب استشارة مخصصة للمدرب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_custom'])) {
    $req_trainer = $_POST['req_trainer_id']; $req_problem = trim($_POST['req_problem']);
    $insert = $pdo->prepare("INSERT INTO custom_consultation_requests (trainee_id, trainer_id, trainee_problem) VALUES (?, ?, ?)");
    if ($insert->execute([$user_id, $req_trainer, $req_problem])) {
        $success_msg = "تم إرسال طلبك للمدرب بنجاح! انتظر رده.";
    }
}

// 4. تأكيد الدفع ورفع الوصل للادارة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_payment'])) {
    $booking_id = $_POST['booking_id']; 
    $payment_method = $_POST['payment_method']; 
    $currency = $_POST['currency'];
    
    $amount_usd = ($currency == 'usd') ? $_POST['stored_usd'] : null; 
    $amount_dzd = ($currency == 'dzd') ? $_POST['stored_dzd'] : null;
    
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
        $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION); 
        $receipt_image = time() . '_' . rand(100, 999) . '.' . $ext;
        move_uploaded_file($_FILES['receipt']['tmp_name'], '../uploads/receipts/' . $receipt_image);
        
        $pdo->prepare("UPDATE consultation_bookings SET receipt_image = ?, payment_method = ?, amount_usd = ?, amount_dzd = ?, status = 'pending' WHERE id = ?")->execute([$receipt_image, $payment_method, $amount_usd, $amount_dzd, $booking_id]);
        $success_msg = "تم رفع إيصال الدفع بنجاح. يرجى انتظار تأكيد الإدارة للموعد.";
    } else { $error_msg = "يرجى رفع صورة وصل الدفع."; }
}

// ==========================================
// 💡 جلب البيانات (أحدث 5 عمليات فقط لنظافة اللوحة)
// ==========================================
$my_bookings = $pdo->prepare("
    SELECT b.*, a.available_date, a.start_time, a.end_time, a.consult_type, a.session_mode, a.city, a.venue_name, 
           u.full_name AS trainer_name, TIMESTAMPDIFF(SECOND, b.created_at, NOW()) AS seconds_passed 
    FROM consultation_bookings b 
    JOIN trainer_availabilities a ON b.availability_id = a.id 
    JOIN users u ON a.trainer_id = u.id 
    WHERE b.trainee_id = ? 
    ORDER BY b.created_at DESC LIMIT 5
"); 
$my_bookings->execute([$user_id]); 
$my_bookings = $my_bookings->fetchAll();

$my_custom_requests = $pdo->prepare("
    SELECT r.*, u.full_name AS trainer_name 
    FROM custom_consultation_requests r 
    JOIN users u ON r.trainer_id = u.id 
    WHERE r.trainee_id = ? 
    ORDER BY r.created_at DESC LIMIT 5
"); 
$my_custom_requests->execute([$user_id]); 
$my_custom_requests = $my_custom_requests->fetchAll();

$all_trainers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'trainer' AND status = 1")->fetchAll();
$avails = $pdo->query("SELECT id, trainer_id, available_date, start_time, end_time, price_usd, price_dzd, consult_type, session_mode FROM trainer_availabilities WHERE is_booked = 0 AND available_date >= CURDATE() ORDER BY available_date ASC, start_time ASC")->fetchAll();

function getSettingVal($pdo, $key) { $stmt = $pdo->prepare("SELECT setting_value_ar FROM settings WHERE setting_key = ?"); $stmt->execute([$key]); return $stmt->fetchColumn(); }
$bank_details = getSettingVal($pdo, 'bank_details'); $paypal_email = getSettingVal($pdo, 'paypal_email'); $agent_details = getSettingVal($pdo, 'agent_details');
$types_dict = ['family' => 'أسرية', 'self_dev' => 'تطوير ذات', 'personal' => 'فردية', 'other' => 'عامة'];

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h3 class="fw-bold" style="color: #0b1c3f;"><i class="fa-solid fa-calendar-check text-primary me-2"></i> استشاراتي الخاصة</h3>
    <button class="btn btn-success rounded-pill fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#resModal">
        <i class="fa-solid fa-magnifying-glass me-2"></i> بحث أو طلب استشارة
    </button>
</div>

<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>
<?php if($error_msg): ?> <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div> <?php endif; ?>

<!-- نتيجة البحث بالكود -->
<?php if($searched_slot): ?>
<div class="card border-success shadow-sm rounded-4 mb-4 bg-success bg-opacity-10">
    <div class="card-body p-4 text-center">
        <h5 class="fw-bold text-success mb-3"><i class="fa-solid fa-check-circle"></i> الموعد متاح للحجز!</h5>
        <img src="<?php echo ($searched_slot['avatar'] != 'default_avatar.png') ? '../uploads/avatars/'.$searched_slot['avatar'] : 'https://ui-avatars.com/api/?name='.urlencode($searched_slot['trainer_name']); ?>" class="rounded-circle border border-3 border-success mb-2" style="width:80px;height:80px;object-fit:cover;">
        <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($searched_slot['trainer_name']); ?></h5>
        
        <p class="mb-3 text-muted fw-bold small">
            <span class="badge bg-secondary"><?php echo $types_dict[$searched_slot['consult_type']]; ?></span>
            <?php if($searched_slot['session_mode'] == 'online'): ?>
                <span class="badge bg-primary"><i class="fa-solid fa-globe"></i> أونلاين</span>
            <?php else: ?>
                <span class="badge bg-info text-dark"><i class="fa-solid fa-building"></i> حضوري (<?php echo htmlspecialchars($searched_slot['city']); ?>)</span>
            <?php endif; ?>
        </p>

        <p class="fw-bold text-primary mb-4"><i class="fa-regular fa-calendar"></i> <?php echo $searched_slot['available_date']; ?> | <i class="fa-regular fa-clock"></i> <span dir="ltr"><?php echo date('h:i A', strtotime($searched_slot['start_time'])); ?> - <?php echo date('h:i A', strtotime($searched_slot['end_time'])); ?></span></p>
        
        <form method="POST">
            <input type="hidden" name="availability_id" value="<?php echo $searched_slot['id']; ?>">
            <label class="form-label fw-bold text-dark small text-start w-100 ps-2"><i class="fa-solid fa-pen-to-square text-success"></i> اذكر مشكلتك أو الهدف من الجلسة ليقرأها المدرب: <span class="text-danger">*</span></label>
            <textarea name="trainee_problem" class="form-control mb-3 fw-bold" rows="3" placeholder="أعاني من مشكلة في... وأريد تحقيق..." required></textarea>
            <button type="submit" name="reserve_appointment" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm">تأكيد حجز هذا الموعد الآن</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- 1. جدول الطلبات الخاصة (أحدث 5) -->
<?php if(!empty($my_custom_requests)): ?>
<h5 class="fw-bold mb-3 mt-4 text-primary"><i class="fa-solid fa-paper-plane"></i> طلباتي الخاصة المرسلة للمدربين</h5>
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden border-primary">
    <div class="table-responsive">
        <table class="table align-middle text-center mb-0">
            <thead class="table-light small fw-bold text-muted">
                <tr><th class="ps-4 text-start py-3">المدرب المطلوب</th><th>نص الطلب (المشكلة)</th><th>حالة الطلب</th></tr>
            </thead>
            <tbody>
                <?php foreach($my_custom_requests as $req): ?>
                <tr>
                    <td class="fw-bold text-dark text-start ps-4"><i class="fa-solid fa-user-tie text-muted me-1"></i> <?php echo htmlspecialchars($req['trainer_name']); ?></td>
                    <td><small class="text-muted fw-bold"><?php echo mb_substr(htmlspecialchars($req['trainee_problem']), 0, 60); ?>...</small></td>
                    <td>
                        <?php 
                            if($req['status'] == 'pending_trainer') echo '<span class="badge bg-warning text-dark rounded-pill shadow-sm py-2 px-3"><i class="fa-solid fa-clock"></i> بانتظار رد المدرب</span>';
                            elseif($req['status'] == 'accepted') echo '<span class="badge bg-success rounded-pill shadow-sm py-2 px-3"><i class="fa-solid fa-check"></i> وافق المدرب (راجع الجدول السفلي للدفع)</span>';
                            elseif($req['status'] == 'rejected') echo '<span class="badge bg-danger rounded-pill shadow-sm py-2 px-3"><i class="fa-solid fa-ban"></i> اعتذر المدرب</span>'; 
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 2. جدول المواعيد والحجوزات (أحدث 5) -->
<h5 class="fw-bold mb-3 mt-4 text-dark"><i class="fa-solid fa-calendar-days"></i> الحجوزات والمواعيد المؤكدة</h5>
<div class="card border-0 shadow-sm rounded-4 mb-5 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 text-center">
            <thead class="table-light small fw-bold text-muted">
                <tr>
                    <th class="ps-4 text-start py-3">المدرب / نوع الجلسة</th>
                    <th class="py-3">موعد ومكان الجلسة</th>
                    <th class="py-3">السعر المتفق عليه</th>
                    <th class="py-3">حالة الحجز</th>
                    <th class="py-3">رابط / إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($my_bookings)): ?>
                    <tr><td colspan="5" class="py-5 text-muted fw-bold"><i class="fa-solid fa-calendar-xmark fa-3x mb-3 opacity-25"></i><br>لا توجد حجوزات حالية.</td></tr>
                <?php else: ?>
                    <?php foreach($my_bookings as $b): ?>
                    <?php $s_left = 3600 - (int)$b['seconds_passed']; if($s_left < 0) $s_left = 0; ?>
                    <tr class="<?php echo ($b['status'] == 'reserved') ? 'table-warning table-opacity-10' : ''; ?>">
                        
                        <td class="fw-bold text-dark text-start ps-4">
                            <i class="fa-solid fa-user-tie text-muted me-1"></i> <?php echo htmlspecialchars($b['trainer_name']); ?><br>
                            <span class="badge bg-light text-secondary border mt-1"><?php echo $types_dict[$b['consult_type']]; ?></span>
                        </td>
                        
                        <td class="fw-bold text-primary">
                            <span class="d-block"><i class="fa-regular fa-calendar"></i> <?php echo date('Y-m-d', strtotime($b['available_date'])); ?></span>
                            <small dir="ltr" class="text-muted"><i class="fa-regular fa-clock"></i> <?php echo date('h:i A', strtotime($b['start_time'])) . ' - ' . date('h:i A', strtotime($b['end_time'])); ?></small>
                            <div class="mt-1">
                                <?php if($b['session_mode'] == 'online'): ?>
                                    <span class="badge bg-primary px-2"><i class="fa-solid fa-globe"></i> أونلاين</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark px-2"><i class="fa-solid fa-building"></i> حضوري: <?php echo htmlspecialchars($b['city']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <td class="fw-bold text-success fs-6">
                            <?php 
                                if($b['amount_usd']) echo '$'.(float)$b['amount_usd'];
                                elseif($b['amount_dzd']) echo (float)$b['amount_dzd'].' د.ج';
                            ?>
                        </td>
                        
                        <!-- 💡 تم إصلاح الحالات لتظهر بشكل سليم من القاعدة المحدثة -->
                        <td>
                            <?php 
                                if($b['status'] == 'approved') echo '<span class="badge bg-success rounded-pill px-3 py-2"><i class="fa-solid fa-check"></i> مؤكد</span>';
                                elseif($b['status'] == 'pending') echo '<span class="badge bg-info text-dark rounded-pill px-3 py-2"><i class="fa-solid fa-hourglass-half"></i> مراجعة الإدارة</span>';
                                elseif($b['status'] == 'rejected') echo '<span class="badge bg-danger rounded-pill px-3 py-2"><i class="fa-solid fa-xmark"></i> الوصل مرفوض</span>';
                                elseif($b['status'] == 'canceled') echo '<span class="badge bg-danger rounded-pill px-3 py-2"><i class="fa-solid fa-ban"></i> ملغية (انتهى الوقت)</span>';
                                elseif($b['status'] == 'reserved') { 
                                    echo '<span class="badge bg-warning text-dark rounded-pill px-3 py-2 mb-1"><i class="fa-solid fa-clock"></i> بانتظار الدفع</span>'; 
                                    if($s_left > 0) echo "<br><small class='text-danger fw-bold timer' data-seconds='$s_left'>جاري الحساب...</small>"; 
                                    else echo "<br><small class='text-danger fw-bold'>انتهى الوقت</small>"; 
                                }
                            ?>
                        </td>
                        
                        <td>
                            <?php if($b['status'] == 'reserved' && $s_left > 0): ?>
                                <button class="btn btn-sm btn-success rounded-pill fw-bold shadow-sm px-3" onclick="openPaymentModal(<?php echo $b['id']; ?>, '<?php echo $b['amount_usd']; ?>', '<?php echo $b['amount_dzd']; ?>')">
                                    <i class="fa-solid fa-upload"></i> تأكيد الدفع
                                </button>
                            <?php elseif($b['status'] == 'approved'): ?>
                                <?php if($b['session_mode'] == 'online'): ?>
                                    <?php if(!empty($b['meeting_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($b['meeting_link']); ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill fw-bold px-3 shadow-sm"><i class="fa-solid fa-video"></i> الدخول للاجتماع</a>
                                    <?php else: ?>
                                        <span class="text-muted small fw-bold opacity-75">الرابط قريباً</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- הגلسة حضورية: عرض الملاحظات -->
                                    <?php if(!empty($b['meeting_link'])): ?>
                                        <button class="btn btn-sm btn-outline-info rounded-pill fw-bold px-3 shadow-sm" onclick="alert('ملاحظة من المدرب: \n<?php echo addslashes(htmlspecialchars($b['meeting_link'])); ?>')"><i class="fa-solid fa-envelope-open-text"></i> رسالة المدرب</button>
                                    <?php else: ?>
                                        <span class="text-muted small fw-bold d-block lh-sm">القاعة:<br><?php echo htmlspecialchars($b['venue_name']); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small opacity-25"><i class="fa-solid fa-lock"></i> مقفل</span>
                            <?php endif; ?>
                        </td>
                        
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: الحجز والبحث -->
<div class="modal fade" id="resModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 bg-light rounded-top-4">
                <h5 class="fw-bold text-dark"><i class="fa-solid fa-magnifying-glass text-primary me-2"></i> بحث، حجز، أو طلب استشارة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                
                <form method="POST" class="mb-4 pb-4 border-bottom">
                    <label class="fw-bold text-primary mb-2 small"><i class="fa-solid fa-bolt"></i> 1. الحجز السريع بكود الموعد (إذا أعطاك إياه المدرب)</label>
                    <div class="input-group">
                        <input type="text" name="slot_code" class="form-control fw-bold border-success text-center" placeholder="مثال: AR-A1B2C" required>
                        <button type="submit" name="search_code" class="btn btn-success fw-bold px-4 shadow-sm">بحث وحجز</button>
                    </div>
                </form>
                
                <form method="POST" class="mb-4 pb-4 border-bottom">
                    <label class="fw-bold text-dark mb-3 small"><i class="fa-solid fa-list"></i> 2. أو احجز من المواعيد المفتوحة للجمهور حالياً</label>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <select id="trainerSelect" class="form-select fw-bold" required onchange="filterAvailabilities()">
                                <option value="">-- 1. اختر المدرب --</option>
                                <?php foreach($all_trainers as $tr): ?>
                                    <option value="<?php echo $tr['id']; ?>"><?php echo htmlspecialchars($tr['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <select name="availability_id" id="availabilitySelect" class="form-select fw-bold text-primary" required disabled onchange="enableReserveBtn()">
                                <option value="">-- 2. ثم اختر الموعد المناسب --</option>
                                <?php foreach($avails as $a): ?>
                                    <option value="<?php echo $a['id']; ?>" data-trainer="<?php echo $a['trainer_id']; ?>" style="display: none;">
                                        <?php echo date('Y-m-d', strtotime($a['available_date'])); ?> | <?php echo date('h:i A', strtotime($a['start_time'])); ?> 
                                        [<?php echo ($a['session_mode']=='online') ? 'أونلاين':'حضوري'; ?> - <?php echo $types_dict[$a['consult_type']]; ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="problemSection" class="bg-light p-3 rounded-3 mb-3 border border-success" style="display: none;">
                        <label class="form-label fw-bold text-success small"><i class="fa-solid fa-pen-to-square"></i> ما هو الهدف من الجلسة أو المشكلة التي تواجهها؟ <span class="text-danger">*</span></label>
                        <textarea name="trainee_problem" id="traineeProblem" class="form-control fw-bold" rows="2" placeholder="اشرح مشكلتك ليقرأها المدرب ويستعد للجلسة..." required disabled></textarea>
                    </div>

                    <button type="submit" name="reserve_appointment" id="reserveBtn" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm" style="background-color: #0b1c3f;" disabled>تأكيد الحجز المبدئي (للدفع)</button>
                </form>

                <form method="POST">
                    <label class="fw-bold text-danger mb-3 small"><i class="fa-solid fa-paper-plane"></i> 3. لم تجد موعداً يناسبك؟ أرسل طلب استشارة خاص للمدرب</label>
                    <select name="req_trainer_id" class="form-select fw-bold mb-2 border-danger" required>
                        <option value="">-- اختر المدرب الذي تريد مراسلته --</option>
                        <?php foreach($all_trainers as $tr): ?>
                            <option value="<?php echo $tr['id']; ?>"><?php echo htmlspecialchars($tr['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <textarea name="req_problem" class="form-control fw-bold mb-3 border-danger" rows="3" placeholder="اشرح مشكلتك واقترح أوقاتاً وتواريخ تناسبك ليراها المدرب ويحدد لك موعداً وسعراً..." required></textarea>
                    <button type="submit" name="request_custom" class="btn btn-danger w-100 rounded-pill fw-bold shadow-sm">إرسال الطلب للمدرب</button>
                </form>

            </div>
        </div>
    </div>
</div>

<!-- Modal 2: الدفع -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 bg-light rounded-top-4">
                <h5 class="fw-bold text-success"><i class="fa-solid fa-upload me-2"></i> تأكيد الدفع لرفع الإيصال</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <label class="form-label fw-bold text-muted small">1. اختر عملة الدفع</label>
                    <select name="currency" id="currencySelect" class="form-select fw-bold text-success mb-3" required onchange="updatePaymentDetails()">
                        <option value="">-- اضغط للاختيار --</option>
                        <option value="usd" id="optUsd" style="display:none;"></option>
                        <option value="dzd" id="optDzd" style="display:none;"></option>
                    </select>

                    <label class="form-label fw-bold text-muted small">2. طريقة الدفع</label>
                    <select name="payment_method" id="methodSelect" class="form-select fw-bold mb-3" required onchange="updatePaymentDetails()">
                        <option value="bank">🏦 تحويل بنكي</option>
                        <option value="paypal">💳 بايبال</option>
                        <option value="agent">🤝 وكيل معتمد</option>
                    </select>

                    <div id="paymentDetailsBox" class="p-3 mb-3 bg-light rounded-3 border">
                        <div id="details_bank" class="small fw-bold lh-lg"><?php echo $bank_details; ?></div>
                        <div id="details_paypal" class="small fw-bold text-center fs-5" dir="ltr" style="display:none;"><?php echo htmlspecialchars($paypal_email); ?></div>
                        <div id="details_agent" class="small fw-bold lh-lg" style="display:none;"><?php echo $agent_details; ?></div>
                    </div>

                    <label class="form-label fw-bold text-dark small">3. ارفع صورة الإيصال أو الشاشة <span class="text-danger">*</span></label>
                    <input type="file" name="receipt" class="form-control" accept="image/*,.pdf" required>
                    
                    <input type="hidden" name="booking_id" id="modalBookingId">
                    <input type="hidden" name="stored_usd" id="storedUsd">
                    <input type="hidden" name="stored_dzd" id="storedDzd">
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="submit" name="confirm_payment" id="submitBtn" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm" disabled>تأكيد ورفع الإيصال للإدارة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- إغلاق وسوم HTML -->
        </div> 
    </div> 
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); }); 
        document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });

        function filterAvailabilities() {
            var selectedTrainer = document.getElementById('trainerSelect').value;
            var availabilitySelect = document.getElementById('availabilitySelect');
            var options = availabilitySelect.options;
            
            availabilitySelect.value = '';
            document.getElementById('reserveBtn').disabled = true;
            document.getElementById('problemSection').style.display = 'none';
            document.getElementById('traineeProblem').disabled = true;

            if (selectedTrainer === '') {
                availabilitySelect.disabled = true;
                options[0].text = '-- 1. اختر المدرب أولاً --';
                for (var i = 1; i < options.length; i++) { options[i].style.display = 'none'; }
                return;
            }

            availabilitySelect.disabled = false;
            options[0].text = '-- 2. اختر الموعد المناسب لك --';
            var hasAvails = false;

            for (var i = 1; i < options.length; i++) {
                if (options[i].getAttribute('data-trainer') === selectedTrainer) {
                    options[i].style.display = 'block';
                    hasAvails = true;
                } else {
                    options[i].style.display = 'none';
                }
            }
            if (!hasAvails) {
                options[0].text = '-- لا توجد مواعيد متاحة حالياً --';
                availabilitySelect.disabled = true;
            }
        }

        function enableReserveBtn() {
            var isSelected = (document.getElementById('availabilitySelect').value !== '');
            document.getElementById('reserveBtn').disabled = !isSelected;
            
            var probSec = document.getElementById('problemSection');
            var probInp = document.getElementById('traineeProblem');
            if (isSelected) {
                probSec.style.display = 'block';
                probInp.disabled = false;
            } else {
                probSec.style.display = 'none';
                probInp.disabled = true;
            }
        }

        function openPaymentModal(id, usd, dzd) {
            document.getElementById('modalBookingId').value = id;
            document.getElementById('storedUsd').value = usd;
            document.getElementById('storedDzd').value = dzd;
            
            let oU = document.getElementById('optUsd'), oD = document.getElementById('optDzd');
            if(usd) { oU.style.display = 'block'; oU.innerHTML = 'دولار ($' + parseFloat(usd) + ')'; } else { oU.style.display = 'none'; }
            if(dzd) { oD.style.display = 'block'; oD.innerHTML = 'دينار (' + parseFloat(dzd) + ' د.ج)'; } else { oD.style.display = 'none'; }
            
            document.getElementById('currencySelect').value = '';
            document.getElementById('submitBtn').disabled = true;
            new bootstrap.Modal(document.getElementById('paymentModal')).show();
        }

        function updatePaymentDetails() {
            let m = document.getElementById('methodSelect').value, c = document.getElementById('currencySelect').value;
            document.getElementById('details_bank').style.display = (m === 'bank') ? 'block' : 'none';
            document.getElementById('details_paypal').style.display = (m === 'paypal') ? 'block' : 'none';
            document.getElementById('details_agent').style.display = (m === 'agent') ? 'block' : 'none';
            document.getElementById('submitBtn').disabled = (c === '');
        }

        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll('.timer').forEach(t => {
                setInterval(() => {
                    let s = parseInt(t.getAttribute('data-seconds'));
                    if(s > 0) {
                        s--; t.setAttribute('data-seconds', s);
                        let m = Math.floor(s / 60), secs = s % 60;
                        t.innerText = "متبقي: " + m + ":" + (secs < 10 ? "0" : "") + secs;
                    } else {
                        t.innerText = "انتهى الوقت!";
                        setTimeout(() => window.location.reload(), 2000);
                    }
                }, 1000);
            });
        });
    </script>
</body>
</html>