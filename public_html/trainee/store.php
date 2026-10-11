<?php
require_once '../includes/init.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainee') { header('Location: ../login.php'); exit(); }
$user_id = $_SESSION['user_id'];
$success_msg = ''; $error_msg = '';

// 1. الإلغاء التلقائي للمقاعد المحجوزة التي لم تدفع خلال 60 دقيقة
$pdo->query("UPDATE course_orders SET status = 'canceled' WHERE status = 'reserved' AND created_at <= DATE_SUB(NOW(), INTERVAL 60 MINUTE)");

// 2. حجز المقعد المبدئي
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reserve_course'])) {
    $course_id = $_POST['course_id'];
    $stmt = $pdo->prepare("SELECT price_usd, price_dzd, training_type, max_seats FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if ($course) {
        $error_msg = "";
        // التحقق من المقاعد (للزوم والحضوري)
        if ($course['training_type'] != 'online' && $course['max_seats'] > 0) {
            $booked_seats = $pdo->query("SELECT COUNT(*) FROM course_orders WHERE course_id = $course_id AND status IN ('approved', 'reserved', 'pending')")->fetchColumn();
            if ($booked_seats >= $course['max_seats']) $error_msg = "عذراً، المقاعد مكتملة.";
        }

        if (empty($error_msg)) {
            $insert = $pdo->prepare("INSERT INTO course_orders (user_id, course_id, amount_usd, amount_dzd, status) VALUES (?, ?, ?, ?, 'reserved')");
            if ($insert->execute([$user_id, $course_id, $course['price_usd'], $course['price_dzd']])) {
                $success_msg = "تم حجز مقعدك بنجاح! انتقل الآن لـ (دوراتي التعليمية) لتأكيد الدفع قبل انتهاء الـ 60 دقيقة.";
            }
        }
    }
}

// 3. جلب جميع الدورات المنشورة مع متوسط تقييمها
$all_courses = $pdo->query("
    SELECT c.*, u.full_name AS trainer_name, cat.name_ar AS category_name,
           (SELECT COALESCE(AVG(rating), 0) FROM course_reviews WHERE course_id = c.id) AS avg_rating,
           (SELECT COUNT(*) FROM course_reviews WHERE course_id = c.id) AS reviews_count
    FROM courses c 
    JOIN users u ON c.trainer_id = u.id 
    JOIN categories cat ON c.category_id = cat.id 
    WHERE c.status = 'published' ORDER BY c.created_at DESC
")->fetchAll();

$user_orders = [];
$stmt = $pdo->prepare("SELECT course_id, status FROM course_orders WHERE user_id = ?"); $stmt->execute([$user_id]); while ($row = $stmt->fetch()) { $user_orders[$row['course_id']] = $row['status']; }
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4"><h3 class="fw-bold" style="color: #0b1c3f;"><i class="fa-solid fa-cart-shopping text-primary me-2"></i> متجر الدورات</h3></div>
<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>
<?php if($error_msg): ?> <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div> <?php endif; ?>

<div class="row g-4">
    <?php foreach($all_courses as $course): ?>
    <div class="col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
            <div class="position-relative">
                <img src="<?php echo ($course['thumbnail'] && $course['thumbnail'] != 'default_course.jpg') ? '../uploads/courses/'.$course['thumbnail'] : 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3'; ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                <div class="position-absolute top-0 end-0 m-3">
                    <?php if($course['training_type'] == 'online'): ?><span class="badge bg-primary px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-globe"></i> أونلاين مسجلة</span>
                    <?php elseif($course['training_type'] == 'online_live'): ?><span class="badge bg-danger px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-satellite-dish"></i> بث مباشر</span>
                    <?php else: ?><span class="badge bg-info text-dark px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-building"></i> حضوري (<?php echo htmlspecialchars($course['city']); ?>)</span><?php endif; ?>
                </div>
            </div>
            
            <div class="card-body p-4 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-light text-primary border align-self-start"><?php echo htmlspecialchars($course['category_name']); ?></span>
                    <!-- 💡 النجوم في المتجر -->
                    <div class="text-warning small fw-bold">
                        <span class="text-dark me-1"><?php echo round($course['avg_rating'], 1); ?></span>
                        <i class="fa-solid fa-star"></i> <span class="text-muted fw-normal">(<?php echo $course['reviews_count']; ?>)</span>
                    </div>
                </div>
                
                <h5 class="fw-bold mb-3 text-dark"><?php echo htmlspecialchars($course['title_ar']); ?></h5>
                <div class="d-flex align-items-center mb-3"><i class="fa-solid fa-user-tie text-muted me-2"></i><small class="text-muted fw-bold"><?php echo htmlspecialchars($course['trainer_name']); ?></small></div>
                <hr class="opacity-25 my-auto">
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="fs-6 fw-bold text-success">
                        <?php $prices = []; if($course['price_usd']) $prices[] = '$'.(float)$course['price_usd']; if($course['price_dzd']) $prices[] = (float)$course['price_dzd'].' د.ج'; echo implode(' <br> ', $prices); ?>
                    </span>
                    
                    <div class="d-flex gap-2">
                        <a href="../course.php?id=<?php echo $course['id']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-circle px-2" title="تفاصيل الدورة"><i class="fa-solid fa-circle-info"></i></a>
                        
                        <?php if(isset($user_orders[$course['id']])): ?>
                            <?php if($user_orders[$course['id']] == 'approved'): ?><span class="badge bg-success px-3 py-2 rounded-pill d-flex align-items-center"><i class="fa-solid fa-check me-1"></i> مفعلة</span>
                            <?php elseif($user_orders[$course['id']] == 'pending'): ?><span class="badge bg-info text-dark px-3 py-2 rounded-pill d-flex align-items-center"><i class="fa-solid fa-clock me-1"></i> مراجعة</span>
                            <?php elseif($user_orders[$course['id']] == 'reserved'): ?><a href="my_courses.php" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark"><i class="fa-solid fa-upload"></i> تأكيد الدفع</a>
                            <?php else: ?><form method="POST" class="m-0"><input type="hidden" name="course_id" value="<?php echo $course['id']; ?>"><button type="submit" name="reserve_course" class="btn btn-danger btn-sm rounded-pill fw-bold px-3">إعادة الحجز</button></form><?php endif; ?>
                        <?php else: ?>
                            <form method="POST" class="m-0"><input type="hidden" name="course_id" value="<?php echo $course['id']; ?>"><button type="submit" name="reserve_course" class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm" style="background-color: #1cc88a; border:none;">حجز مقعد</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

</div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); });</script>
</body></html>