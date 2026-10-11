<?php
require_once '../includes/init.php';

// حماية الصفحة: للمدربين فقط
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainer') {
    header('Location: ../login.php');
    exit();
}

$trainer_id = $_SESSION['user_id'];

// جلب قائمة المتدربين المرتبطين بهذا المدرب (اشتروا دورة مفعلة أو حجزوا استشارة مؤكدة)
// نستخدم UNION لدمج نتائج جدولين (طلبات الدورات + حجوزات الاستشارات) بدون تكرار
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.phone, u.country, u.avatar, MAX(o.created_at) AS last_activity, 'course' AS source
    FROM course_orders o
    JOIN courses c ON o.course_id = c.id
    JOIN users u ON o.user_id = u.id
    WHERE c.trainer_id = ? AND o.status = 'approved'
    GROUP BY u.id
    
    UNION
    
    SELECT u.id, u.full_name, u.email, u.phone, u.country, u.avatar, MAX(b.created_at) AS last_activity, 'consultation' AS source
    FROM consultation_bookings b
    JOIN trainer_availabilities a ON b.availability_id = a.id
    JOIN users u ON b.trainee_id = u.id
    WHERE a.trainer_id = ? AND b.status IN ('approved', 'completed')
    GROUP BY u.id
    
    ORDER BY last_activity DESC
");
$stmt->execute([$trainer_id, $trainer_id]);

// بما أننا استخدمنا UNION، قد يظهر المتدرب مرتين (مرة لأنه اشترى دورة ومرة لأنه حجز استشارة).
// سنقوم بتصفية المصفوفة لضمان ظهور كل متدرب مرة واحدة فقط في الجدول.
$raw_students = $stmt->fetchAll();
$unique_students = [];
$processed_ids = [];

foreach ($raw_students as $student) {
    if (!in_array($student['id'], $processed_ids)) {
        $unique_students[] = $student;
        $processed_ids[] = $student['id'];
    }
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h3 class="fw-bold" style="color: #0b1c3f;"><i class="fa-solid fa-users text-primary me-2"></i> متدربيني (قاعدة بيانات الطلاب)</h3>
    
    <!-- إحصائية صغيرة في الأعلى -->
    <div class="bg-white px-4 py-2 rounded-pill shadow-sm border border-primary border-opacity-25 d-flex align-items-center gap-3">
        <div class="text-primary fs-3"><i class="fa-solid fa-user-graduate"></i></div>
        <div>
            <span class="d-block text-muted small fw-bold mb-0">إجمالي طلابك</span>
            <span class="fw-bold fs-5 text-dark"><?php echo count($unique_students); ?> متدرب</span>
        </div>
    </div>
</div>

<!-- رسالة تشجيعية للمدرب -->
<div class="alert alert-info rounded-4 shadow-sm border-0 d-flex align-items-center gap-3 mb-4">
    <i class="fa-solid fa-lightbulb fa-2x text-info"></i>
    <div>
        <h6 class="fw-bold mb-1">تواصل مع طلابك!</h6>
        <small class="text-muted">هذه القائمة تعرض جميع المتدربين الذين اشتروا دوراتك أو حجزوا استشارات معك. يمكنك استخدام البريد الإلكتروني للتواصل معهم وإرسال تحديثات الدورة.</small>
    </div>
</div>

<!-- جدول المتدربين -->
<div class="card border-0 shadow-sm rounded-4 mb-5 overflow-hidden">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-address-book text-muted me-2"></i> دليل المتدربين</h5>
        <span class="badge bg-light text-dark border px-3 py-2"><i class="fa-solid fa-arrow-down-short-wide text-primary me-1"></i> الأحدث نشاطاً</span>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-light text-muted small fw-bold">
                    <tr>
                        <th class="py-3 text-start ps-4">اسم المتدرب / الصورة</th>
                        <th class="py-3">البريد الإلكتروني</th>
                        <th class="py-3">رقم الهاتف</th>
                        <th class="py-3">البلد</th>
                        <th class="py-3">تاريخ آخر نشاط (شراء/حجز)</th>
                        <th class="py-3">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($unique_students)): ?>
                        <tr><td colspan="6" class="py-5 text-muted">
                            <i class="fa-solid fa-users-slash fa-3x mb-3 opacity-25"></i>
                            <h6 class="fw-bold">ليس لديك أي متدربين حالياً.</h6>
                            <p class="small mb-0">قم بنشر المزيد من الدورات القيمة لجذب الطلاب.</p>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach($unique_students as $student): ?>
                        <tr>
                            <!-- الاسم والصورة -->
                            <td class="fw-bold text-dark text-start ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <?php 
                                        $avatar_src = ($student['avatar'] && $student['avatar'] != 'default_avatar.png') 
                                                      ? '../uploads/avatars/' . $student['avatar'] 
                                                      : 'https://ui-avatars.com/api/?name=' . urlencode($student['full_name']) . '&background=0b1c3f&color=fff'; 
                                    ?>
                                    <img src="<?php echo $avatar_src; ?>" class="rounded-circle shadow-sm" style="width: 45px; height: 45px; object-fit: cover; border: 2px solid #f6c23e;" alt="Student">
                                    <span><?php echo htmlspecialchars($student['full_name']); ?></span>
                                </div>
                            </td>
                            
                            <!-- الإيميل -->
                            <td class="text-muted fw-bold" dir="ltr">
                                <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" class="text-decoration-none text-primary">
                                    <i class="fa-solid fa-envelope me-1 opacity-50"></i> <?php echo htmlspecialchars($student['email']); ?>
                                </a>
                            </td>
                            
                            <!-- الهاتف -->
                            <td class="text-muted small fw-bold" dir="ltr">
                                <?php if($student['phone']): ?>
                                    <i class="fa-solid fa-phone me-1 opacity-50"></i> <?php echo htmlspecialchars($student['phone']); ?>
                                <?php else: ?>
                                    <span class="opacity-50">غير متوفر</span>
                                <?php endif; ?>
                            </td>

                            <!-- البلد -->
                            <td class="text-muted small fw-bold">
                                <?php if($student['country']): ?>
                                    <i class="fa-solid fa-earth-americas me-1 opacity-50"></i> <?php echo htmlspecialchars($student['country']); ?>
                                <?php else: ?>
                                    <span class="opacity-50">غير محدد</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- تاريخ الانضمام / آخر نشاط -->
                            <td class="text-muted small">
                                <span class="d-block fw-bold text-dark"><i class="fa-regular fa-calendar me-1"></i> <?php echo date('Y-m-d', strtotime($student['last_activity'])); ?></span>
                            </td>
                            
                            <!-- أزرار الإجراء (مراسلة سريعة) -->
                            <td>
                                <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold shadow-sm" title="إرسال إيميل">
                                    <i class="fa-solid fa-paper-plane"></i> مراسلة
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- إغلاق وسوم HTML المفتوحة في header.php -->
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