<?php
require_once '../includes/init.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainer') { header('Location: ../login.php'); exit(); }

$trainer_id = $_SESSION['user_id'];
$success_msg = ''; $error_msg = '';
$whatsapp_btn = ''; // 💡 المتغير السحري لزر الواتساب

// 1. إضافة موعد متاح للعموم
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_availability'])) {
    $date = $_POST['available_date']; $start_time = $_POST['start_time']; $end_time = $_POST['end_time'];
    $price_usd = !empty($_POST['price_usd']) ? $_POST['price_usd'] : null; $price_dzd = !empty($_POST['price_dzd']) ? $_POST['price_dzd'] : null;
    $consult_type = $_POST['consult_type']; $session_mode = $_POST['session_mode'];
    $city = ($session_mode == 'offline') ? trim($_POST['city']) : null; $venue_name = ($session_mode == 'offline') ? trim($_POST['venue_name']) : null; $map_link = ($session_mode == 'offline') ? trim($_POST['map_link']) : null;
    $slot_code = 'AR-' . strtoupper(substr(uniqid(), -5)) . rand(10, 99);

    if ($price_usd === null && $price_dzd === null) { $error_msg = "حدد السعر."; } elseif (strtotime($end_time) <= strtotime($start_time)) { $error_msg = "وقت النهاية خطأ."; } elseif ($session_mode == 'offline' && (empty($city) || empty($venue_name))) { $error_msg = "اكتب المدينة والقاعة."; } else {
        $pdo->prepare("INSERT INTO trainer_availabilities (trainer_id, consult_type, session_mode, city, venue_name, map_link, available_date, start_time, end_time, price_usd, price_dzd, is_booked, slot_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)")->execute([$trainer_id, $consult_type, $session_mode, $city, $venue_name, $map_link, $date, $start_time, $end_time, $price_usd, $price_dzd, $slot_code]);
        $success_msg = "تمت إضافة الموعد. كود الحجز المباشر: " . $slot_code;
    }
}

// 2. ✅ الموافقة على "طلب استشارة خاص" (Custom Request Accepted) + إشعار الواتساب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accept_custom'])) {
    $req_id = $_POST['request_id']; $trainee_id = $_POST['trainee_id']; $trainee_problem = $_POST['problem'];
    $date = $_POST['available_date']; $start_time = $_POST['start_time']; $end_time = $_POST['end_time'];
    $price_usd = !empty($_POST['price_usd']) ? $_POST['price_usd'] : null; $price_dzd = !empty($_POST['price_dzd']) ? $_POST['price_dzd'] : null;
    $consult_type = $_POST['consult_type']; $session_mode = $_POST['session_mode'];
    $city = ($session_mode == 'offline') ? trim($_POST['city']) : null; $venue_name = ($session_mode == 'offline') ? trim($_POST['venue_name']) : null;
    $slot_code = 'AR-C' . rand(1000, 9999);

    if ($price_usd === null && $price_dzd === null) { $error_msg = "حدد السعر."; } else {
        $pdo->prepare("INSERT INTO trainer_availabilities (trainer_id, consult_type, session_mode, city, venue_name, available_date, start_time, end_time, price_usd, price_dzd, is_booked, slot_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)")->execute([$trainer_id, $consult_type, $session_mode, $city, $venue_name, $date, $start_time, $end_time, $price_usd, $price_dzd, $slot_code]);
        $new_avail_id = $pdo->lastInsertId();
        
        $pdo->prepare("INSERT INTO consultation_bookings (trainee_id, availability_id, amount_usd, amount_dzd, trainee_problem, status) VALUES (?, ?, ?, ?, ?, 'reserved')")->execute([$trainee_id, $new_avail_id, $price_usd, $price_dzd, $trainee_problem]);
        $pdo->prepare("UPDATE custom_consultation_requests SET status = 'accepted' WHERE id = ?")->execute([$req_id]);
        
        $success_msg = "تمت الموافقة! أرسل الفاتورة الآن للمتدرب ليدفع خلال 60 دقيقة.";

        // 💡 سحر الواتساب: إشعار القبول وتحديد السعر للمتدرب
        $trainee_info = $pdo->query("SELECT full_name, whatsapp_number FROM users WHERE id = $trainee_id")->fetch();
        if ($trainee_info && !empty($trainee_info['whatsapp_number'])) {
            $wa_num = preg_replace('/[^0-9]/', '', $trainee_info['whatsapp_number']);
            $wa_msg = "مرحباً *" . $trainee_info['full_name'] . "* ✨\n\n";
            $wa_msg .= "لقد قرأت طلبك للاستشارة. وتم تحديد موعد لك يوم *" . $date . "* الساعة *" . date('h:i A', strtotime($start_time)) . "*.\n\n";
            $wa_msg .= "⚠️ *يرجى الانتباه:* يجب الدخول للوحتك في منصة أراميو لتأكيد الحجز والدفع خلال 60 دقيقة قبل إلغاء الموعد.\nبانتظار لقائك! 📅";
            $wa_link = "https://wa.me/" . $wa_num . "?text=" . urlencode($wa_msg);
            $whatsapp_btn = "<a href='$wa_link' target='_blank' class='btn btn-success fw-bold rounded-pill px-4 mt-3 shadow-sm pulse-button'><i class='fa-brands fa-whatsapp fs-5 me-2'></i> أرسل الفاتورة للمتدرب (واتساب)</a>";
        }
    }
}

// 3. رفض طلب خاص
if (isset($_GET['reject_req'])) { $pdo->prepare("UPDATE custom_consultation_requests SET status = 'rejected' WHERE id = ? AND trainer_id = ?")->execute([$_GET['reject_req'], $trainer_id]); $success_msg = "تم إرسال الاعتذار."; }

// 4. ✅ إرسال رابط اجتماع الزووم (Meeting Link) + إشعار الواتساب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_meeting_link'])) {
    $booking_id = $_POST['booking_id']; $meeting_link = trim($_POST['meeting_link']);
    
    $stmt = $pdo->prepare("SELECT b.trainee_id, u.full_name AS trainee_name, u.whatsapp_number, a.available_date, a.start_time, a.session_mode FROM consultation_bookings b JOIN trainer_availabilities a ON b.availability_id = a.id JOIN users u ON b.trainee_id = u.id WHERE b.id = ? AND a.trainer_id = ? AND b.status = 'approved'");
    $stmt->execute([$booking_id, $trainer_id]); $booking_data = $stmt->fetch();

    if ($booking_data) {
        $pdo->prepare("UPDATE consultation_bookings SET meeting_link = ? WHERE id = ?")->execute([$meeting_link, $booking_id]); 
        $success_msg = "تم حفظ الرابط/الملاحظة بنجاح.";

        // 💡 سحر الواتساب: إرسال الرابط أو الملاحظة للمتدرب
        if (!empty($booking_data['whatsapp_number'])) {
            $wa_num = preg_replace('/[^0-9]/', '', $booking_data['whatsapp_number']);
            $wa_msg = "مرحباً *" . $booking_data['trainee_name'] . "* 🌟\n\n";
            $wa_msg .= "تذكير بموعد جلستنا الاستشارية يوم *" . date('Y-m-d', strtotime($booking_data['available_date'])) . "* الساعة *" . date('h:i A', strtotime($booking_data['start_time'])) . "*.\n\n";
            
            if($booking_data['session_mode'] == 'online') {
                $wa_msg .= "🔗 *رابط الدخول المباشر للاجتماع هو:*\n" . $meeting_link . "\n\n";
                $wa_msg .= "يرجى التواجد في الموعد المحدد. ⏱️";
            } else {
                $wa_msg .= "📍 *ملاحظة هامة بخصوص الجلسة الحضورية:*\n" . $meeting_link . "\n\n";
                $wa_msg .= "ننتظر حضورك في الموعد. ⏱️";
            }

            $wa_link = "https://wa.me/" . $wa_num . "?text=" . urlencode($wa_msg);
            $whatsapp_btn = "<a href='$wa_link' target='_blank' class='btn btn-success fw-bold rounded-pill px-4 mt-3 shadow-sm pulse-button'><i class='fa-brands fa-whatsapp fs-5 me-2'></i> أرسل الرابط/الملاحظة للمتدرب (واتساب)</a>";
        }
    }
}

// 5. حذف موعد متاح
if (isset($_GET['delete_avail'])) { $pdo->prepare("DELETE FROM trainer_availabilities WHERE id = ? AND trainer_id = ? AND is_booked = 0")->execute([$_GET['delete_avail'], $trainer_id]); }

// جلب البيانات للجدول
$custom_requests = $pdo->prepare("SELECT r.*, u.full_name AS trainee_name FROM custom_consultation_requests r JOIN users u ON r.trainee_id = u.id WHERE r.trainer_id = ? AND r.status = 'pending_trainer' ORDER BY r.created_at ASC"); $custom_requests->execute([$trainer_id]); $custom_requests = $custom_requests->fetchAll();
$availabilities = $pdo->prepare("SELECT * FROM trainer_availabilities WHERE trainer_id = ? AND is_booked = 0 AND available_date >= CURDATE() ORDER BY available_date ASC, start_time ASC"); $availabilities->execute([$trainer_id]); $availabilities = $availabilities->fetchAll();
$booked_appointments = $pdo->prepare("SELECT b.id AS booking_id, b.meeting_link, b.trainee_problem, a.available_date, a.start_time, a.end_time, a.consult_type, a.session_mode, a.city, a.venue_name, u.full_name AS trainee_name FROM consultation_bookings b JOIN trainer_availabilities a ON b.availability_id = a.id JOIN users u ON b.trainee_id = u.id WHERE a.trainer_id = ? AND b.status = 'approved' AND a.available_date >= CURDATE() ORDER BY a.available_date ASC, a.start_time ASC"); $booked_appointments->execute([$trainer_id]); $booked_appointments = $booked_appointments->fetchAll();
$types_dict = ['family' => 'أسرية', 'self_dev' => 'تطوير ذات', 'personal' => 'فردية', 'other' => 'أخرى'];

require_once 'includes/header.php';
?>

<style>
    @keyframes pulse { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); } 70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(37, 211, 102, 0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); } }
    .pulse-button { animation: pulse 2s infinite; background-color: #25d366 !important; border-color: #25d366 !important; color: white !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h3 class="fw-bold" style="color: #0b1c3f;"><i class="fa-solid fa-calendar-days text-warning me-2"></i> إدارة جدول الاستشارات</h3>
    <button class="btn btn-warning text-dark rounded-pill fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addAvailModal">إضافة موعد متاح (للعموم)</button>
</div>

<?php if($success_msg): ?> 
    <div class="alert alert-success rounded-4 fw-bold shadow-sm border-0 text-center p-4 mb-4">
        <i class="fa-solid fa-check-circle fa-3x mb-3 text-success"></i><br>
        <span class="fs-5 text-dark"><?php echo $success_msg; ?></span><br>
        <?php echo $whatsapp_btn; ?> <!-- 💡 زر الواتساب יظهر هنا بقوة -->
    </div>
<?php endif; ?>
<?php if($error_msg): ?> <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div> <?php endif; ?>

<!-- 💡 قسم الطلبات الخاصة الواردة -->
<?php if(!empty($custom_requests)): ?>
<div class="card border-0 shadow-sm rounded-4 mb-5 border-danger">
    <div class="card-header bg-white py-3 border-0"><h5 class="fw-bold text-danger m-0"><i class="fa-solid fa-envelope-open-text me-2"></i> طلبات استشارة خاصة (بانتظار ردك وتحديدك للسعر)</h5></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table text-center align-middle mb-0"><thead class="table-light text-muted small fw-bold"><tr><th class="ps-4 text-start">المتدرب</th><th>المشكلة / موضوع الجلسة</th><th>إجراءك</th></tr></thead><tbody>
        <?php foreach($custom_requests as $req): ?><tr>
            <td class="text-start ps-4 fw-bold text-dark"><i class="fa-solid fa-user-graduate text-muted me-1"></i> <?php echo htmlspecialchars($req['trainee_name']); ?></td>
            <td class="text-muted"><small class="fw-bold"><?php echo htmlspecialchars($req['trainee_problem']); ?></small></td>
            <td>
                <button class="btn btn-sm btn-success fw-bold rounded-pill px-3 shadow-sm" onclick="openAcceptModal(<?php echo $req['id']; ?>, <?php echo $req['trainee_id']; ?>, '<?php echo addslashes($req['trainee_problem']); ?>')"><i class="fa-solid fa-check"></i> موافقة وتحديد السعر</button>
                <a href="?reject_req=<?php echo $req['id']; ?>" class="btn btn-sm btn-danger rounded-circle shadow-sm ms-1" onclick="return confirm('تأكيد الاعتذار للمتدرب؟')" title="اعتذار"><i class="fa-solid fa-xmark"></i></a>
            </td>
        </tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<?php endif; ?>

<div class="row g-4 mb-5">
    
    <!-- 1. استشارات قادمة مؤكدة -->
    <div class="col-lg-7"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-header bg-white py-3 border-0"><h5 class="m-0 fw-bold text-primary"><i class="fa-solid fa-handshake me-2"></i> استشارات قادمة (مدفوعة ومؤكدة)</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle text-center mb-0"><thead class="table-light small text-muted fw-bold"><tr><th class="ps-4 text-start">المتدرب والنوع</th><th>التاريخ والوقت</th><th>طريقة الجلسة</th><th>الرابط / الإجراء</th></tr></thead><tbody>
        <?php if(empty($booked_appointments)): ?><tr><td colspan="4" class="py-5 text-muted"><i class="fa-solid fa-mug-hot fa-3x mb-3 opacity-25"></i><h6 class="fw-bold">ليس لديك استشارات مؤكدة حالياً.</h6></td></tr><?php else: foreach($booked_appointments as $booked): ?><tr>
            <td class="fw-bold text-start ps-4"><span class="text-dark d-block"><i class="fa-solid fa-user-tie text-muted me-1"></i> <?php echo htmlspecialchars($booked['trainee_name']); ?></span><span class="badge bg-light text-secondary border mt-1"><?php echo $types_dict[$booked['consult_type']]; ?></span><?php if(!empty($booked['trainee_problem'])): ?><button class="btn btn-link btn-sm text-primary p-0 ms-2 fw-bold" onclick="showProblem('<?php echo htmlspecialchars(addslashes($booked['trainee_problem'])); ?>')"><i class="fa-solid fa-circle-info"></i> رسالة المتدرب</button><?php endif; ?></td>
            <td class="fw-bold"><span class="text-primary d-block mb-1"><i class="fa-regular fa-calendar"></i> <?php echo date('Y-m-d', strtotime($booked['available_date'])); ?></span><small dir="ltr" class="text-muted"><i class="fa-regular fa-clock"></i> <?php echo date('h:i A', strtotime($booked['start_time'])) . ' - ' . date('h:i A', strtotime($booked['end_time'])); ?></small></td>
            <td><?php if($booked['session_mode'] == 'online'): ?><span class="badge bg-primary rounded-pill px-3 py-2"><i class="fa-solid fa-globe"></i> أونلاين</span><?php else: ?><span class="badge bg-info text-dark rounded-pill px-3 py-2"><i class="fa-solid fa-building"></i> حضوري</span><br><small class="text-muted fw-bold d-block mt-1"><?php echo htmlspecialchars($booked['city']); ?></small><?php endif; ?></td>
            <td>
                <?php if($booked['session_mode'] == 'online'): ?>
                    <?php if(empty($booked['meeting_link'])): ?><button class="btn btn-sm btn-success rounded-pill fw-bold shadow-sm" onclick="openLinkModal(<?php echo $booked['booking_id']; ?>, '<?php echo addslashes($booked['trainee_name']); ?>', 'online')"><i class="fa-solid fa-link"></i> وضع رابط Zoom</button><?php else: ?><span class="badge bg-success rounded-pill mb-1 d-block w-75 mx-auto">الرابط جاهز</span><button class="btn btn-link text-primary btn-sm p-0 fw-bold" onclick="openLinkModal(<?php echo $booked['booking_id']; ?>, '<?php echo addslashes($booked['trainee_name']); ?>', 'online', '<?php echo addslashes($booked['meeting_link']); ?>')">تعديل الرابط</button><?php endif; ?>
                <?php else: ?>
                    <?php if(empty($booked['meeting_link'])): ?><button class="btn btn-link btn-sm text-primary p-0 fw-bold" onclick="openLinkModal(<?php echo $booked['booking_id']; ?>, '<?php echo addslashes($booked['trainee_name']); ?>', 'offline')"><i class="fa-solid fa-bell"></i> أرسل ملاحظة</button><?php else: ?><span class="badge bg-success rounded-pill px-3 py-2">تم إرسال الملاحظات</span><?php endif; ?>
                <?php endif; ?>
            </td>
        </tr><?php endforeach; endif; ?>
    </tbody></table></div></div></div></div>
    
    <!-- 2. المواعيد المتاحة -->
    <div class="col-lg-5"><div class="card border-0 shadow-sm rounded-4 h-100 border-left-success" style="border-right: 4px solid #1cc88a;"><div class="card-header bg-white py-3 border-0"><h5 class="m-0 fw-bold text-success"><i class="fa-solid fa-clock-rotate-left me-2"></i> مواعيد متاحة للحجز המباشر</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle text-center mb-0"><thead class="table-light small text-muted fw-bold"><tr><th class="ps-4 text-start">الوقت / كود الحجز</th><th>السعر / النوع</th><th>حذف</th></tr></thead><tbody>
        <?php if(empty($availabilities)): ?><tr><td colspan="3" class="py-4 text-muted small fw-bold">تقويمك فارغ. أضف مواعيد ليحجزها المتدربون.</td></tr><?php else: foreach($availabilities as $avail): ?><tr>
            <td class="text-start ps-4 fw-bold"><span class="text-dark d-block"><i class="fa-regular fa-calendar text-success"></i> <?php echo date('Y-m-d', strtotime($avail['available_date'])); ?></span><small dir="ltr" class="text-muted opacity-75"><i class="fa-regular fa-clock"></i> <?php echo date('h:i A', strtotime($avail['start_time'])); ?></small><br><span class="badge bg-dark mt-2 fw-normal user-select-all border" title="كود الحجز السريع"><i class="fa-solid fa-copy text-warning"></i> <?php echo $avail['slot_code']; ?></span></td>
            <td class="fw-bold text-success fs-6"><?php if($avail['price_usd']) echo '$'.$avail['price_usd'].'<br>'; if($avail['price_dzd']) echo number_format($avail['price_dzd']).' د.ج<br>'; echo '<span class="badge '.($avail['session_mode']=='online'?'bg-primary':'bg-secondary text-dark').' mt-1">'.$types_dict[$avail['consult_type']].'</span>'; ?></td>
            <td><a href="?delete_avail=<?php echo $avail['id']; ?>" class="btn btn-sm btn-outline-danger rounded-circle shadow-sm" onclick="return confirm('إزالة هذا الموعد من التقويم؟')"><i class="fa-solid fa-trash"></i></a></td>
        </tr><?php endforeach; endif; ?>
    </tbody></table></div></div></div></div>

</div>

<!-- Modal 1: إضافة موعد متاح جديد -->
<div class="modal fade" id="addAvailModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header bg-light border-0 rounded-top-4"><h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-calendar-plus text-warning me-2"></i> فتح موعد متاح للحجز</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST"><div class="modal-body p-4"><div class="row mb-3"><div class="col-6"><label class="form-label fw-bold text-muted small">نوع الاستشارة</label><select name="consult_type" class="form-select fw-bold" required><option value="personal">جلسة فردية عامة</option><option value="family">استشارة أسرية</option><option value="self_dev">تطوير ذات</option><option value="other">أخرى</option></select></div><div class="col-6"><label class="form-label fw-bold text-muted small">طريقة الجلسة</label><select name="session_mode" id="sessionModeSelect" class="form-select fw-bold text-primary" required onchange="toggleOfflineFields('sessionModeSelect', 'offlineFields', 'inpCity', 'inpVenue')"><option value="online">💻 أونلاين (Zoom)</option><option value="offline">🏫 حضوري (مكتب)</option></select></div></div><div id="offlineFields" class="bg-light p-3 rounded-3 mb-3 border border-secondary" style="display: none;"><p class="fw-bold text-secondary mb-2 small"><i class="fa-solid fa-location-dot"></i> تفاصيل المكتب أو القاعة</p><div class="row"><div class="col-md-6 mb-2"><input type="text" name="city" id="inpCity" class="form-control form-control-sm" placeholder="المدينة (مثال: الجزائر العاصمة)"></div><div class="col-md-6 mb-2"><input type="text" name="venue_name" id="inpVenue" class="form-control form-control-sm" placeholder="اسم المكتب/المركز"></div><div class="col-12"><input type="url" name="map_link" class="form-control form-control-sm text-start" dir="ltr" placeholder="رابط خرائط جوجل (اختياري)"></div></div></div><div class="mb-3"><label class="form-label fw-bold text-muted small">تاريخ الجلسة</label><input type="date" name="available_date" class="form-control fw-bold text-primary" required min="<?php echo date('Y-m-d'); ?>"></div><div class="row mb-4"><div class="col-6"><label class="form-label fw-bold text-muted small">من الساعة</label><input type="time" name="start_time" class="form-control text-center fw-bold" dir="ltr" required></div><div class="col-6"><label class="form-label fw-bold text-muted small">إلى الساعة</label><input type="time" name="end_time" class="form-control text-center fw-bold" dir="ltr" required></div></div><div class="row bg-light p-3 rounded-3 border border-warning"><p class="fw-bold small text-dark mb-2"><i class="fa-solid fa-tags text-warning"></i> السعر (املأ حقلاً أو كلاهما)</p><div class="col-6"><input type="number" step="0.01" name="price_usd" class="form-control fw-bold text-success text-start" dir="ltr" placeholder="$ دولار"></div><div class="col-6"><input type="number" step="0.01" name="price_dzd" class="form-control fw-bold text-success text-start" dir="ltr" placeholder="دينار (د.ج)"></div></div></div><div class="modal-footer border-0 bg-light rounded-bottom-4"><button type="submit" name="add_availability" class="btn btn-warning w-100 rounded-pill fw-bold text-dark shadow-sm">حفظ وإتاحة للمتدربين</button></div></form></div></div></div>

<!-- Modal 2: قبول طلب خاص -->
<div class="modal fade" id="acceptModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4 border-success" style="border-width: 3px !important;"><div class="modal-header bg-success text-white border-0 rounded-top-3"><h5 class="modal-title fw-bold"><i class="fa-solid fa-hand-holding-dollar me-2"></i> قبول وتحديد فاتورة للمتدرب</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<form method="POST"><div class="modal-body p-4"><input type="hidden" name="request_id" id="accReqId"><input type="hidden" name="trainee_id" id="accTraineeId"><input type="hidden" name="problem" id="accProblem"><div class="alert alert-info small fw-bold"><i class="fa-solid fa-circle-info"></i> سيتم حجز الموعد وإرسال الفاتورة للمتدرب. وسيبدأ عداد (60 دقيقة) في لوحته لكي يدفع.</div><div class="row mb-3"><div class="col-6"><label class="form-label fw-bold text-muted small">نوع الاستشارة</label><select name="consult_type" class="form-select fw-bold" required><option value="personal">فردية</option><option value="family">أسرية</option><option value="self_dev">تطوير ذات</option><option value="other">أخرى</option></select></div><div class="col-6"><label class="form-label fw-bold text-muted small">طريقة الجلسة</label><select name="session_mode" id="accSessionMode" class="form-select fw-bold" required onchange="toggleOfflineFields('accSessionMode', 'accOfflineFields', 'accCity', 'accVenue')"><option value="online">💻 أونلاين</option><option value="offline">🏫 حضوري</option></select></div></div><div id="accOfflineFields" class="bg-light p-2 rounded-3 mb-3 border" style="display: none;"><div class="row"><div class="col-6"><input type="text" name="city" id="accCity" class="form-control form-control-sm" placeholder="المدينة"></div><div class="col-6"><input type="text" name="venue_name" id="accVenue" class="form-control form-control-sm" placeholder="القاعة/المكتب"></div></div></div><div class="mb-3"><label class="form-label fw-bold text-muted small">حدد التاريخ</label><input type="date" name="available_date" class="form-control fw-bold" required min="<?php echo date('Y-m-d'); ?>"></div><div class="row mb-3"><div class="col-6"><label class="form-label fw-bold text-muted small">من الساعة</label><input type="time" name="start_time" class="form-control text-center fw-bold" dir="ltr" required></div><div class="col-6"><label class="form-label fw-bold text-muted small">إلى الساعة</label><input type="time" name="end_time" class="form-control text-center fw-bold" dir="ltr" required></div></div><div class="row bg-light p-3 rounded-3 border border-success"><p class="fw-bold small text-success mb-2"><i class="fa-solid fa-file-invoice-dollar"></i> حدد السعر المطلوب (الفاتورة)</p><div class="col-6"><input type="number" step="0.01" name="price_usd" class="form-control fw-bold text-success text-center" dir="ltr" placeholder="$ دولار"></div><div class="col-6"><input type="number" step="0.01" name="price_dzd" class="form-control fw-bold text-success text-center" dir="ltr" placeholder="دينار"></div></div></div><div class="modal-footer border-0 bg-light rounded-bottom-4"><button type="submit" name="accept_custom" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm">تأكيد الموافقة وإرسال الفاتورة للمتدرب</button></div></form></div></div></div>

<!-- Modal 3: قراءة مشكلة المتدرب -->
<div class="modal fade" id="problemModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header bg-info text-dark border-0 rounded-top-4"><h5 class="fw-bold mb-0"><i class="fa-solid fa-circle-info"></i> رسالة المتدرب (الهدف من الجلسة)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-4 text-center"><p id="modalProblemText" class="text-dark lh-lg fw-bold p-3 bg-light rounded border" style="white-space: pre-wrap; font-size: 1.1rem;"></p></div></div></div></div>

<!-- Modal 4: إرسال رابط Zoom -->
<div class="modal fade" id="linkModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header bg-light border-0 rounded-top-4"><h5 class="fw-bold text-success" id="linkModalTitle"><i class="fa-solid fa-link me-2"></i> بيانات الاجتماع</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body p-4"><p class="text-muted fw-bold small text-center mb-4">أرسل الرابط/الملاحظة للمتدرب: <span id="modalTraineeName" class="text-primary fs-6"></span></p><div class="mb-3"><label class="form-label fw-bold" id="linkModalLabel"></label><input type="text" name="meeting_link" id="modalMeetingLink" class="form-control bg-light" required><small class="text-muted mt-2 d-block" id="linkModalHelp"></small></div><input type="hidden" name="booking_id" id="modalLinkBookingId"></div><div class="modal-footer border-0 bg-light rounded-bottom-4"><button type="submit" name="add_meeting_link" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm">حفظ وإرسال للمتدرب</button></div></form></div></div></div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); }); 
    document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });
    function toggleOfflineFields(selectId, fieldsId, cityId, venueId) { const isOffline = document.getElementById(selectId).value === 'offline'; const fields = document.getElementById(fieldsId); if (isOffline) { fields.style.display = 'block'; document.getElementById(cityId).required = true; document.getElementById(venueId).required = true; } else { fields.style.display = 'none'; document.getElementById(cityId).required = false; document.getElementById(venueId).required = false; } }
    function openAcceptModal(reqId, traineeId, problem) { document.getElementById('accReqId').value = reqId; document.getElementById('accTraineeId').value = traineeId; document.getElementById('accProblem').value = problem; new bootstrap.Modal(document.getElementById('acceptModal')).show(); }
    function showProblem(text) { document.getElementById('modalProblemText').innerText = text; new bootstrap.Modal(document.getElementById('problemModal')).show(); }
    function openLinkModal(bookingId, traineeName, mode, currentLink = '') { document.getElementById('modalLinkBookingId').value = bookingId; document.getElementById('modalTraineeName').innerText = traineeName; document.getElementById('modalMeetingLink').value = currentLink; if (mode === 'offline') { document.getElementById('linkModalTitle').innerHTML = '<i class="fa-solid fa-bell me-2 text-info"></i> إرسال ملاحظة للمتدرب'; document.getElementById('linkModalLabel').innerHTML = 'اكتب ملاحظة (مثال: يرجى الحضور مبكراً) <span class="text-danger">*</span>'; document.getElementById('modalMeetingLink').dir = 'rtl'; document.getElementById('modalMeetingLink').placeholder = 'ملاحظاتك هنا...'; document.getElementById('linkModalHelp').innerHTML = 'ستظهر هذه الملاحظة للمتدرب في لوحته ليرتب أموره.'; } else { document.getElementById('linkModalTitle').innerHTML = '<i class="fa-solid fa-link me-2 text-primary"></i> رابط اجتماع الزووم'; document.getElementById('linkModalLabel').innerHTML = 'رابط الاجتماع (Zoom / Meet) <span class="text-danger">*</span>'; document.getElementById('modalMeetingLink').dir = 'ltr'; document.getElementById('modalMeetingLink').placeholder = 'https://zoom.us/...'; document.getElementById('linkModalHelp').innerHTML = '<i class="fa-solid fa-circle-info text-info"></i> سيظهر الرابط للمتدرب في لوحته ليدخل منه فوراً.'; } new bootstrap.Modal(document.getElementById('linkModal')).show(); }
</script>
</body></html>