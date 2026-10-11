<?php
require_once '../includes/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainee') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_playing = false; 
$cert_msg = ''; 
$success_msg = ''; 
$error_msg = '';

// ==========================================
// 1. نظام الإلغاء التلقائي (للدورات المحجوزة بعد 60 دقيقة)
// ==========================================
$pdo->query("UPDATE course_orders SET status = 'canceled' WHERE status = 'reserved' AND created_at <= DATE_SUB(NOW(), INTERVAL 60 MINUTE)");

// ==========================================
// 2. معالجة تأكيد الدفع ورفع الوصل (من النافذة المنبثقة)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_payment'])) {
    $order_id = $_POST['order_id']; 
    $payment_method = $_POST['payment_method']; 
    $currency = $_POST['currency'];
    
    $amount_usd = ($currency == 'usd') ? $_POST['stored_usd'] : null; 
    $amount_dzd = ($currency == 'dzd') ? $_POST['stored_dzd'] : null;
    
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
        $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION); 
        $receipt_image = time() . '_' . rand(100, 999) . '.' . $ext;
        move_uploaded_file($_FILES['receipt']['tmp_name'], '../uploads/receipts/' . $receipt_image);
        
        $update = $pdo->prepare("UPDATE course_orders SET receipt_image = ?, payment_method = ?, amount_usd = ?, amount_dzd = ?, status = 'pending' WHERE id = ? AND user_id = ?");
        if ($update->execute([$receipt_image, $payment_method, $amount_usd, $amount_dzd, $order_id, $user_id])) {
            $success_msg = "تم رفع إيصال الدفع بنجاح. حجزك الآن (قيد المراجعة) بانتظار تأكيد الإدارة.";
        }
    } else { 
        $error_msg = "الرجاء رفع صورة الوصل."; 
    }
}

// ==========================================
// 3. معالجة تقييم الدورة
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    $course_id_rev = $_POST['review_course_id']; 
    $rating = (int)$_POST['rating']; 
    $comment = trim($_POST['comment']); 
    $review_img = null;
    
    if (isset($_FILES['review_image']) && $_FILES['review_image']['error'] == 0) {
        $ext = pathinfo($_FILES['review_image']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'])) {
            $review_img = time() . '_rev_' . rand(100, 999) . '.' . $ext;
            move_uploaded_file($_FILES['review_image']['tmp_name'], '../uploads/reviews/' . $review_img);
            $pdo->prepare("INSERT INTO gallery_images (image_name, image_type) VALUES (?, 'review')")->execute([$review_img]);
        }
    }
    
    $pdo->prepare("INSERT INTO course_reviews (course_id, trainee_id, rating, comment, review_image) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), review_image = COALESCE(VALUES(review_image), review_image)")->execute([$course_id_rev, $user_id, $rating, $comment, $review_img]);
    $cert_msg = "تم حفظ تقييمك للدورة بنجاح. شكراً لك!";
}

// ==========================================
// 4. شاشة المُشغّل / قاعة الدورة (عند الدخول لدورة مفعلة)
// ==========================================
if (isset($_GET['course_id'])) {
    $course_id = $_GET['course_id'];
    
    // التحقق الأمني من ملكية الدورة
    $check_access = $pdo->prepare("SELECT id FROM course_orders WHERE user_id = ? AND course_id = ? AND status = 'approved'");
    $check_access->execute([$user_id, $course_id]);
    if ($check_access->rowCount() == 0) { header('Location: my_courses.php'); exit(); }
    
    $is_playing = true;
    
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?"); 
    $stmt->execute([$course_id]); 
    $current_course = $stmt->fetch();
    
    $lessons = []; $active_lesson = null; $progress_percent = 100;
    
    // إذا كانت دورة مسجلة
    if($current_course['training_type'] == 'online') {
        $stmt_l = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY lesson_order ASC"); 
        $stmt_l->execute([$course_id]); 
        $lessons = $stmt_l->fetchAll();
        
        if(isset($_GET['lesson_id'])){ 
            foreach($lessons as $lesson){ if($lesson['id'] == $_GET['lesson_id']){ $active_lesson = $lesson; break; } } 
        }
        if(!$active_lesson && count($lessons) > 0) { $active_lesson = $lessons[0]; }
        
        // تحديد كمكتمل
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_completed'])) { 
            $pdo->prepare("INSERT IGNORE INTO lesson_completions (trainee_id, lesson_id) VALUES (?, ?)")->execute([$user_id, $_POST['lesson_id']]); 
        }
        
        // حساب التقدم
        $completed_lessons = $pdo->prepare("SELECT COUNT(*) FROM lesson_completions WHERE trainee_id = ? AND lesson_id IN (SELECT id FROM lessons WHERE course_id = ?)"); 
        $completed_lessons->execute([$user_id, $course_id]); 
        $completed_lessons = $completed_lessons->fetchColumn();
        $progress_percent = (count($lessons) > 0) ? round(($completed_lessons / count($lessons)) * 100) : 0;
    }

    // طلب الشهادة
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_cert'])) {
        $check_cert = $pdo->prepare("SELECT id FROM certificate_exams WHERE trainee_id = ? AND course_id = ?"); 
        $check_cert->execute([$user_id, $course_id]);
        if($check_cert->rowCount() == 0) {
            $pdo->prepare("INSERT INTO certificate_exams (trainee_id, course_id, trainer_id, exam_date, notes) VALUES (?, ?, ?, ?, ?)")->execute([$user_id, $course_id, $current_course['trainer_id'], $_POST['exam_date'], $_POST['notes']]);
            $cert_msg = "تم إرسال طلب الشهادة بنجاح!";
        }
    }
} 
// ==========================================
// 5. شاشة العرض الرئيسية المقسمة (جدول الدفع + كروت المفعلة)
// ==========================================
else {
    // 💡 جلب الدورات التي بانتظار الدفع أو المراجعة (لعرضها في الجدول العلوي)
    $stmt_pending = $pdo->prepare("
        SELECT o.id AS order_id, o.status, o.amount_usd, o.amount_dzd, TIMESTAMPDIFF(SECOND, o.created_at, NOW()) AS seconds_passed, 
               c.id AS course_id, c.title_ar, c.training_type, c.start_date, c.live_time, c.city, u.full_name AS trainer_name 
        FROM course_orders o 
        JOIN courses c ON o.course_id = c.id 
        JOIN users u ON c.trainer_id = u.id 
        WHERE o.user_id = ? AND o.status IN ('reserved', 'pending', 'rejected') 
        ORDER BY o.created_at DESC
    ");
    $stmt_pending->execute([$user_id]); 
    $pending_orders = $stmt_pending->fetchAll();

    // جلب الدورات المفعلة (لعرضها ككروت في الأسفل)
    $stmt_approved = $pdo->prepare("
        SELECT c.*, u.full_name AS trainer_name 
        FROM course_orders o 
        JOIN courses c ON o.course_id = c.id 
        JOIN users u ON c.trainer_id = u.id 
        WHERE o.user_id = ? AND o.status = 'approved' 
        ORDER BY o.created_at DESC
    ");
    $stmt_approved->execute([$user_id]); 
    $approved_courses = $stmt_approved->fetchAll();
}

function getSettingVal($pdo, $key) { $stmt = $pdo->prepare("SELECT setting_value_ar FROM settings WHERE setting_key = ?"); $stmt->execute([$key]); return $stmt->fetchColumn(); }
$bank_details = getSettingVal($pdo, 'bank_details'); 
$paypal_email = getSettingVal($pdo, 'paypal_email'); 
$agent_details = getSettingVal($pdo, 'agent_details');

require_once 'includes/header.php';
?>

<style>
    .course-card { transition: 0.3s; border-radius: 15px; overflow: hidden; border: 1px solid #eee; }
    .course-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important; border-color: #1cc88a; }
    .play-btn-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(28, 200, 138, 0.9); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; opacity: 0; transition: 0.3s; }
    .course-card:hover .play-btn-overlay { opacity: 1; }
    .video-container { background: #000; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    .playlist-card { border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #eee; }
    .list-group-item-action { transition: 0.2s; border-left: 4px solid transparent; cursor: pointer; }
    .list-group-item-action:hover { background-color: #f8f9fc; border-left: 4px solid #1cc88a; }
    .list-group-item-action.active { background-color: #0b1c3f !important; border-color: #0b1c3f !important; border-left: 4px solid #1cc88a !important; color: white !important; }
</style>

<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>
<?php if($error_msg): ?> <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div> <?php endif; ?>

<?php if (!$is_playing): ?>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h3 class="fw-bold" style="color: #0b1c3f;"><i class="fa-solid fa-play-circle text-success me-2"></i> دوراتي التعليمية</h3>
        <a href="store.php" class="btn btn-primary rounded-pill fw-bold px-4 shadow-sm" style="background-color: #0b1c3f; border:none;"><i class="fa-solid fa-cart-plus me-2"></i> تصفح المتجر</a>
    </div>
    
    <!-- ========================================== -->
    <!-- 💡 القسم الأول: جدول الحجوزات بانتظار الدفع (المنطق الجديد) -->
    <!-- ========================================== -->
    <?php if(!empty($pending_orders)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-5 overflow-hidden border-warning" style="border-width: 2px !important;">
        <div class="card-header bg-warning bg-opacity-10 py-3 border-0">
            <h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-clock text-warning me-2"></i> حجوزات بانتظار الدفع والمراجعة</h5>
            <small class="text-danger fw-bold d-block mt-1"><i class="fa-solid fa-triangle-exclamation"></i> تنبيه: سيتم إلغاء أي حجز مبدئي تلقائياً بعد مرور 60 دقيقة في حال عدم إتمام عملية الدفع ورفع الوصل.</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-light small fw-bold text-muted">
                    <tr>
                        <th class="ps-4 text-start py-3">الدورة / المدرب</th>
                        <th class="py-3">نوع التدريب وموعده</th>
                        <th class="py-3">المبلغ المطلوب</th>
                        <th class="py-3">حالة الحجز</th>
                        <th class="py-3">الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_orders as $order): ?>
                    <?php $s_left = 3600 - (int)$order['seconds_passed']; if($s_left < 0) $s_left = 0; ?>
                    <tr class="<?php echo ($order['status'] == 'reserved') ? 'table-warning table-opacity-10' : ''; ?>">
                        
                        <td class="fw-bold text-dark text-start ps-4">
                            <?php echo htmlspecialchars($order['title_ar']); ?><br>
                            <small class="text-muted"><i class="fa-solid fa-user-tie opacity-50 me-1"></i> <?php echo htmlspecialchars($order['trainer_name']); ?></small>
                        </td>
                        
                        <td>
                            <?php if($order['training_type'] == 'online'): ?>
                                <span class="badge bg-primary px-3 py-2 rounded-pill"><i class="fa-solid fa-globe"></i> مسجلة</span>
                            <?php elseif($order['training_type'] == 'online_live'): ?>
                                <span class="badge bg-danger px-3 py-2 rounded-pill"><i class="fa-solid fa-satellite-dish"></i> مباشر (Zoom)</span><br>
                                <small class="text-muted fw-bold d-block mt-1" dir="ltr"><?php echo date('Y-m-d | h:i A', strtotime($order['start_date'] . ' ' . $order['live_time'])); ?></small>
                            <?php else: ?>
                                <span class="badge bg-info text-dark px-3 py-2 rounded-pill"><i class="fa-solid fa-building"></i> حضوري: <?php echo htmlspecialchars($order['city']); ?></span><br>
                                <small class="text-muted fw-bold d-block mt-1"><?php echo date('Y-m-d', strtotime($order['start_date'])); ?></small>
                            <?php endif; ?>
                        </td>
                        
                        <td class="fw-bold text-success fs-6">
                            <?php 
                                $prices = [];
                                if($order['amount_usd']) $prices[] = '$'.(float)$order['amount_usd'];
                                if($order['amount_dzd']) $prices[] = (float)$order['amount_dzd'].' د.ج';
                                echo implode(' <br> ', $prices);
                            ?>
                        </td>
                        
                        <td>
                            <?php 
                                if($order['status'] == 'pending') echo '<span class="badge bg-info text-dark rounded-pill px-3 py-2"><i class="fa-solid fa-hourglass-half"></i> مراجعة الإدارة</span>';
                                elseif($order['status'] == 'rejected') echo '<span class="badge bg-danger rounded-pill px-3 py-2"><i class="fa-solid fa-xmark"></i> الوصل مرفوض</span>';
                                elseif($order['status'] == 'canceled') echo '<span class="badge bg-secondary rounded-pill px-3 py-2"><i class="fa-solid fa-ban"></i> ألغيت (انتهى الوقت)</span>';
                                elseif($order['status'] == 'reserved') { 
                                    echo '<span class="badge bg-warning text-dark rounded-pill px-3 py-2 mb-1"><i class="fa-solid fa-clock"></i> بانتظار الدفع</span>'; 
                                    if($s_left > 0) echo "<br><small class='text-danger fw-bold timer' data-seconds='$s_left'>جاري الحساب...</small>"; 
                                    else echo "<br><small class='text-danger fw-bold'>انتهى الوقت</small>"; 
                                }
                            ?>
                        </td>
                        
                        <td>
                            <?php if($order['status'] == 'reserved' && $s_left > 0): ?>
                                <!-- زر الدفع العظيم -->
                                <button class="btn btn-sm btn-success rounded-pill fw-bold shadow-sm px-4 py-2" onclick="openPaymentModal(<?php echo $order['order_id']; ?>, '<?php echo $order['amount_usd']; ?>', '<?php echo $order['amount_dzd']; ?>')">
                                    <i class="fa-solid fa-upload"></i> تأكيد الحجز ورفع الوصل
                                </button>
                            <?php elseif($order['status'] == 'rejected'): ?>
                                <button class="btn btn-sm btn-outline-danger rounded-pill fw-bold px-3" onclick="openPaymentModal(<?php echo $order['order_id']; ?>, '<?php echo $order['amount_usd']; ?>', '<?php echo $order['amount_dzd']; ?>')">إعادة رفع وصل صحيح</button>
                            <?php else: ?>
                                <span class="text-muted small opacity-50"><i class="fa-solid fa-lock"></i> بانتظار الإدارة</span>
                            <?php endif; ?>
                        </td>
                        
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========================================== -->
    <!-- 💡 القسم الثاني: الدورات المفعلة (Approved Cards) -->
    <!-- ========================================== -->
    <h4 class="fw-bold mb-4" style="color: #0b1c3f;"><i class="fa-solid fa-play-circle text-success me-2"></i> دوراتي المفعلة (جاهزة للبدء)</h4>
    <div class="row g-4 mb-5">
        <?php if(empty($approved_courses)): ?>
            <div class="col-12 text-center py-5 bg-white rounded-4 shadow-sm border">
                <i class="fa-solid fa-folder-open text-muted fa-4x mb-3 opacity-25"></i>
                <h5 class="fw-bold text-muted">ليس لديك دورات مفعلة حالياً.</h5>
            </div>
        <?php else: foreach($approved_courses as $course): ?>
            <?php 
                $c_prog = 100;
                if($course['training_type'] == 'online') {
                    $c_comp = $pdo->prepare("SELECT COUNT(*) FROM lesson_completions WHERE trainee_id = ? AND lesson_id IN (SELECT id FROM lessons WHERE course_id = ?)"); $c_comp->execute([$user_id, $course['id']]); $c_completed = $c_comp->fetchColumn();
                    $c_tot = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?"); $c_tot->execute([$course['id']]); $c_total = $c_tot->fetchColumn();
                    $c_prog = ($c_total > 0) ? round(($c_completed / $c_total) * 100) : 0;
                }
            ?>
            <div class="col-md-4 col-sm-6">
                <a href="?course_id=<?php echo $course['id']; ?>" class="text-decoration-none">
                    <div class="card border-0 shadow-sm course-card h-100 position-relative">
                        <div class="position-relative">
                            <img src="<?php echo ($course['thumbnail'] && $course['thumbnail'] != 'default_course.jpg') ? '../uploads/courses/'.$course['thumbnail'] : 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3'; ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                            <div class="play-btn-overlay"><i class="fa-solid fa-<?php echo ($course['training_type'] == 'online') ? 'play' : 'door-open'; ?>"></i></div>
                            <div class="position-absolute top-0 end-0 m-3">
                                <?php if($course['training_type'] == 'online'): ?><span class="badge bg-primary px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-globe"></i> مسجلة</span>
                                <?php elseif($course['training_type'] == 'online_live'): ?><span class="badge bg-danger px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-satellite-dish"></i> مباشر (Zoom)</span>
                                <?php else: ?><span class="badge bg-info text-dark px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-building"></i> حضوري</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body p-4 text-center">
                            <h5 class="fw-bold mb-3 text-dark"><?php echo htmlspecialchars($course['title_ar']); ?></h5>
                            <div class="d-flex align-items-center justify-content-center mb-3 text-muted small fw-bold"><i class="fa-solid fa-user-tie text-primary me-2"></i> <?php echo htmlspecialchars($course['trainer_name']); ?></div>
                            
                            <?php if($course['training_type'] == 'online'): ?>
                                <div class="progress mt-3 mb-1" style="height: 6px;"><div class="progress-bar bg-success" style="width: <?php echo $c_prog; ?>%"></div></div>
                                <small class="text-muted fw-bold">مكتمل: <?php echo $c_prog; ?>%</small>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border d-block py-2 mb-2"><i class="fa-regular fa-calendar text-danger"></i> موعد الدورة: <?php echo date('Y-m-d', strtotime($course['start_date'])); ?></span>
                            <?php endif; ?>
                            
                            <button class="btn btn-success w-100 rounded-pill fw-bold shadow-sm mt-3"><i class="fa-solid fa-person-walking-arrow-right"></i> الدخول لقاعة الدورة</button>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Modal الدفع (للدورات) -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-0 bg-light rounded-top-4">
                    <h5 class="fw-bold text-success"><i class="fa-solid fa-upload me-2"></i> تأكيد دفع الدورة</h5>
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
                        
                        <input type="hidden" name="order_id" id="modalBookingId">
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

<?php else: ?>
    <!-- ========================================== -->
    <!-- شاشة قاعة الدورة (حسب النوع) -->
    <!-- ========================================== -->
    <?php if($cert_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $cert_msg; ?></div> <?php endif; ?>
    
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <h4 class="fw-bold m-0" style="color: #0b1c3f;"><a href="my_courses.php" class="text-muted text-decoration-none me-2"><i class="fa-solid fa-arrow-right"></i></a> <?php echo htmlspecialchars($current_course['title_ar']); ?></h4>
        
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-outline-warning text-dark rounded-pill fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#reviewModal"><i class="fa-solid fa-star text-warning"></i> تقييمك للدورة</button>
            <?php if ($progress_percent == 100): ?>
                <button class="btn btn-warning text-dark rounded-pill fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#certModal"><i class="fa-solid fa-certificate me-1"></i> طلب الشهادة</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 💡 الدورة مسجلة (أونلاين) -->
    <?php if ($current_course['training_type'] == 'online'): ?>
        <div class="progress mb-4 rounded-pill shadow-sm" style="height: 10px;"><div class="progress-bar bg-success" style="width: <?php echo $progress_percent; ?>%"></div></div>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <?php if ($active_lesson): ?>
                    <div class="video-container mb-3 shadow"><div class="ratio ratio-16x9"><iframe src="<?php echo htmlspecialchars($active_lesson['video_url']); ?>" allowfullscreen></iframe></div></div>
                    <div class="bg-white p-4 rounded-4 shadow-sm border d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h5 class="fw-bold text-primary m-0"><?php echo htmlspecialchars($active_lesson['title_ar']); ?></h5>
                        <?php 
                            $is_done = $pdo->prepare("SELECT 1 FROM lesson_completions WHERE trainee_id = ? AND lesson_id = ?"); $is_done->execute([$user_id, $active_lesson['id']]);
                        ?>
                        <?php if($is_done->fetchColumn()): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 fw-bold fs-6"><i class="fa-solid fa-check-double"></i> أتممت هذا الدرس</span>
                        <?php else: ?>
                            <form method="POST" class="m-0"><input type="hidden" name="lesson_id" value="<?php echo $active_lesson['id']; ?>"><button type="submit" name="mark_completed" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm"><i class="fa-solid fa-check me-2"></i> تحديد الدرس كمكتمل</button></form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning rounded-4 p-5 text-center shadow-sm"><h5 class="fw-bold m-0"><i class="fa-solid fa-video-slash fa-2x mb-3"></i><br>لا توجد دروس حالياً.</h5></div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <div class="card playlist-card">
                    <div class="card-header bg-white py-3 border-0"><h6 class="m-0 fw-bold text-dark"><i class="fa-solid fa-list-ol text-primary me-2"></i> قائمة الدروس</h6></div>
                    <div class="list-group list-group-flush" style="max-height: 600px; overflow-y: auto;">
                        <?php foreach($lessons as $index => $lesson): 
                            $is_d = $pdo->prepare("SELECT 1 FROM lesson_completions WHERE trainee_id = ? AND lesson_id = ?"); $is_d->execute([$user_id, $lesson['id']]); $done = $is_d->fetchColumn();
                        ?>
                            <a href="?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $lesson['id']; ?>" class="list-group-item list-group-item-action py-3 px-4 <?php echo ($active_lesson && $active_lesson['id'] == $lesson['id']) ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center"><div class="me-3 fs-5 <?php echo ($active_lesson && $active_lesson['id'] == $lesson['id']) ? 'text-success' : 'text-muted'; ?>"><i class="fa-regular fa-circle-play"></i></div><div><h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($lesson['title_ar']); ?></h6><small class="text-muted"><i class="fa-solid fa-clock"></i> الدرس <?php echo $index + 1; ?></small></div></div>
                                    <?php if($done): ?><i class="fa-solid fa-check-circle text-success fs-4"></i><?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    <!-- 💡 الدورة المباشرة (Zoom) أو الحضورية -->
    <?php else: ?>
        <div class="bg-white p-5 rounded-4 shadow-sm border text-center mt-4" style="border-top: 5px solid <?php echo ($current_course['training_type'] == 'online_live') ? '#e74a3b' : '#36b9cc'; ?> !important;">
            <i class="fa-solid <?php echo ($current_course['training_type'] == 'online_live') ? 'fa-satellite-dish text-danger pulse-button-danger rounded-circle p-4' : 'fa-building-user text-info'; ?> fa-4x mb-4"></i>
            <h3 class="fw-bold text-dark mb-4"><?php echo ($current_course['training_type'] == 'online_live') ? 'غرفة البث المباشر التفاعلية (Zoom)' : 'قاعة التدريب الحضوري'; ?></h3>
            
            <div class="bg-light p-4 rounded-4 d-inline-block text-start border mb-5 shadow-sm">
                <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-calendar-check me-2"></i> موعد الدورة الخاص بك:</h5>
                <p class="fs-5 m-0 fw-bold"><i class="fa-solid fa-calendar-day text-danger me-2"></i> تاريخ البداية: <?php echo date('Y-m-d', strtotime($current_course['start_date'])); ?></p>
                <?php if($current_course['training_type'] == 'online_live'): ?>
                    <p class="fs-5 m-0 fw-bold mt-2" dir="ltr"><i class="fa-solid fa-clock text-warning me-2"></i> وقت البث: <?php echo date('h:i A', strtotime($current_course['live_time'])); ?></p>
                <?php else: ?>
                    <p class="fs-5 m-0 fw-bold mt-2"><i class="fa-solid fa-location-dot text-info me-2"></i> المدينة: <?php echo htmlspecialchars($current_course['city']); ?></p>
                    <p class="fs-5 m-0 fw-bold mt-2"><i class="fa-solid fa-map-pin text-secondary me-2"></i> القاعة: <?php echo htmlspecialchars($current_course['venue_name']); ?></p>
                    <?php if(!empty($current_course['map_link'])): ?><a href="<?php echo htmlspecialchars($current_course['map_link']); ?>" target="_blank" class="btn btn-outline-info rounded-pill fw-bold mt-3"><i class="fa-solid fa-map-location-dot"></i> فتح خرائط جوجل</a><?php endif; ?>
                <?php endif; ?>
            </div>
            
            <br>
            <?php 
                if($current_course['training_type'] == 'online_live'):
                    $course_datetime = strtotime($current_course['start_date'] . ' ' . $current_course['live_time']);
                    $time_left = $course_datetime - time();
                    
                    if ($time_left > 3600): // يفتح الزر قبل الدورة بساعة واحدة
            ?>
                    <button class="btn btn-secondary btn-lg rounded-pill px-5 fw-bold shadow-sm" disabled><i class="fa-solid fa-lock me-2"></i> رابط البث مغلق حالياً (يفتح قبل الموعد بساعة)</button>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($current_course['live_link']); ?>" target="_blank" class="btn btn-danger btn-lg rounded-pill px-5 fw-bold shadow pulse-button-danger"><i class="fa-solid fa-video me-2"></i> الدخول لقاعة البث المباشر الآن</a>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info d-inline-block fw-bold rounded-pill px-4"><i class="fa-solid fa-info-circle"></i> يرجى الحضور في الوقت والمكان المحددين للتفاعل مع المدرب مباشرة.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Modal طلب الشهادة -->
    <div class="modal fade" id="certModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4 shadow-lg border-warning" style="border-width: 3px !important;"><div class="modal-header bg-warning border-0"><h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-certificate"></i> طلب اعتماد الشهادة</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body p-4"><div class="alert alert-success fw-bold small"><i class="fa-solid fa-party-horn"></i> تهانينا لإتمام الدورة! يرجى إرسال هذا الطلب ليقوم المدرب باعتماد شهادتك.</div><label class="fw-bold mb-2 small text-muted">اقترح تاريخاً لاختبارك (إن لزم) <span class="text-danger">*</span></label><input type="date" name="exam_date" class="form-control fw-bold mb-3 border-warning text-dark" required min="<?php echo date('Y-m-d'); ?>"><label class="fw-bold mb-2 small text-muted">ملاحظات للمدرب</label><textarea name="notes" class="form-control fw-bold" rows="3" placeholder="أرغب في الحصول على الشهادة في أقرب وقت..."></textarea></div><div class="modal-footer bg-light border-0"><button type="submit" name="request_cert" class="btn btn-warning text-dark w-100 fw-bold rounded-pill shadow-sm">إرسال الطلب للمدرب</button></div></form></div></div></div>

    <!-- Modal التقييم -->
    <div class="modal fade" id="reviewModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4 shadow-lg"><div class="modal-header bg-light border-0"><h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-star text-warning"></i> تقييم تجربتك</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" enctype="multipart/form-data"><div class="modal-body p-4 text-center"><input type="hidden" name="review_course_id" value="<?php echo $course_id; ?>"><label class="fw-bold mb-3 d-block text-primary">ما هو تقييمك العام للدورة؟ <span class="text-danger">*</span></label><select name="rating" class="form-select form-select-lg fw-bold text-warning mx-auto mb-4" style="max-width: 200px; text-align:center;" required><option value="5">⭐⭐⭐⭐⭐ ممتاز</option><option value="4">⭐⭐⭐⭐ جيد جداً</option><option value="3">⭐⭐⭐ جيد</option><option value="2">⭐⭐ مقبول</option><option value="1">⭐ ضعيف</option></select><label class="fw-bold mb-2 small text-muted text-start w-100">اكتب رأيك (يظهر للزوار في صفحة الدورة)</label><textarea name="comment" class="form-control fw-bold mb-3" rows="3" placeholder="هذه الدورة ساعدتني في... المدرب كان رائعاً..." required></textarea><label class="fw-bold mb-2 small text-muted text-start w-100"><i class="fa-solid fa-image text-info"></i> هل تود إرفاق صورة مع التقييم؟ (اختياري)</label><input type="file" name="review_image" class="form-control" accept="image/jpeg, image/png, image/webp"></div><div class="modal-footer bg-light border-0"><button type="submit" name="submit_review" class="btn btn-warning text-dark w-100 fw-bold rounded-pill shadow-sm">نشر التقييم</button></div></form></div></div></div>

<?php endif; ?>

</div> </div> 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // الجافاسكربت الخاص بالدفع והعداد للدورات 
    function openPaymentModal(id, usd, dzd){ document.getElementById('modalBookingId').value=id; document.getElementById('storedUsd').value=usd; document.getElementById('storedDzd').value=dzd; let oU=document.getElementById('optUsd'), oD=document.getElementById('optDzd'); if(usd){oU.style.display='block'; oU.innerHTML='دولار ($'+parseFloat(usd)+')';}else oU.style.display='none'; if(dzd){oD.style.display='block'; oD.innerHTML='دينار ('+parseFloat(dzd)+' د.ج)';}else oD.style.display='none'; document.getElementById('currencySelect').value=''; document.getElementById('submitBtn').disabled=true; new bootstrap.Modal(document.getElementById('paymentModal')).show(); } 
    function updatePaymentDetails(){ let m=document.getElementById('methodSelect').value, c=document.getElementById('currencySelect').value; document.getElementById('details_bank').style.display=(m==='bank')?'block':'none'; document.getElementById('details_paypal').style.display=(m==='paypal')?'block':'none'; document.getElementById('details_agent').style.display=(m==='agent')?'block':'none'; document.getElementById('submitBtn').disabled=(c===''); } 
    
    document.addEventListener("DOMContentLoaded",()=>{ document.querySelectorAll('.timer').forEach(t=>{ setInterval(()=>{ let s=parseInt(t.getAttribute('data-seconds')); if(s>0){ s--; t.setAttribute('data-seconds',s); let m=Math.floor(s/60), secs=s%60; t.innerText="متبقي: "+m+":"+(secs<10?"0":"")+secs; }else{ t.innerText="انتهى الوقت!"; setTimeout(()=>window.location.reload(),2000); } },1000); }); });
    document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); });
</script>
</body></html>