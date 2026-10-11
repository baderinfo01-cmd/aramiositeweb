<?php
require_once '../includes/init.php';

// حماية الصفحة: للمتدربين فقط
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainee') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// جلب الشهادات المعتمدة الخاصة بهذا المتدرب فقط
$stmt = $pdo->prepare("
    SELECT e.certificate_code, e.issue_date, e.grade, c.title_ar AS course_title, t.full_name AS trainer_name
    FROM certificate_exams e
    JOIN courses c ON e.course_id = c.id
    JOIN users t ON e.trainer_id = t.id
    WHERE e.trainee_id = ? AND e.status = 'passed'
    ORDER BY e.issue_date DESC
");
$stmt->execute([$user_id]);
$my_certificates = $stmt->fetchAll();

// جلب اسم المتدرب كاملاً للطباعة
$stmt_user = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$trainee_name = $stmt_user->fetchColumn();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h3 class="fw-bold" style="color: #0b1c3f;"><i class="fa-solid fa-award text-warning me-2"></i> شهاداتي المعتمدة</h3>
    <span class="badge bg-success px-3 py-2 fs-6 shadow-sm rounded-pill">تمتلك <?php echo count($my_certificates); ?> شهادة</span>
</div>

<div class="row g-4 mb-5">
    <?php if(empty($my_certificates)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                <i class="fa-solid fa-certificate fa-4x text-muted opacity-25 mb-4"></i>
                <h4 class="fw-bold text-dark">لا توجد لديك شهادات معتمدة حتى الآن.</h4>
                <p class="text-muted mb-4">أكمل دوراتك بنسبة 100% واطلب اختبار الشهادة من مدربك.</p>
                <a href="my_courses.php" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" style="background-color: #0b1c3f; border:none;">متابعة التعلم</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach($my_certificates as $cert): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden" style="border-top: 5px solid #1cc88a !important; background: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%231cc88a\' fill-opacity=\'0.05\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E'); background-color: #fff;">
                <div class="card-body p-4 p-md-5 position-relative text-center">
                    
                    <div class="position-absolute top-0 end-0 m-3 text-warning fs-3 opacity-50"><i class="fa-solid fa-medal"></i></div>
                    
                    <img src="../assets/logo.png" alt="Aramio Logo" style="height: 40px; opacity: 0.8; margin-bottom: 20px;" onerror="this.style.display='none'">
                    
                    <h5 class="text-muted fw-bold mb-3 small text-uppercase" style="letter-spacing: 1px;">شهادة إتمام برنامج تدريبي</h5>
                    <h3 class="fw-black mb-4 text-dark" style="color: #0b1c3f !important;"><?php echo htmlspecialchars($cert['course_title']); ?></h3>
                    
                    <p class="text-muted fw-bold mb-1">يُشهد أن المتدرب(ة):</p>
                    <h4 class="fw-bold text-success mb-4"><?php echo htmlspecialchars($trainee_name); ?></h4>
                    
                    <div class="bg-light p-3 rounded-3 border d-inline-block text-start text-dark mb-4 shadow-sm w-100">
                        <div class="row">
                            <div class="col-6 mb-2 border-end"><small class="text-muted d-block">المدرب المشرف:</small> <span class="fw-bold"><?php echo htmlspecialchars($cert['trainer_name']); ?></span></div>
                            <div class="col-6 mb-2"><small class="text-muted d-block">التقدير (الدرجة):</small> <span class="fw-bold text-danger"><?php echo htmlspecialchars($cert['grade']); ?></span></div>
                        </div>
                        <hr class="my-2 opacity-25">
                        <div class="row">
                            <div class="col-6 border-end"><small class="text-muted d-block">تاريخ الإصدار:</small> <span class="fw-bold" dir="ltr"><?php echo date('Y-m-d', strtotime($cert['issue_date'])); ?></span></div>
                            <div class="col-6"><small class="text-muted d-block">رقم الشهادة المرجعي:</small> <span class="fw-bold" dir="ltr"><?php echo htmlspecialchars($cert['certificate_code']); ?></span></div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <button class="btn btn-outline-success rounded-pill px-4 fw-bold shadow-sm" onclick="window.print()"><i class="fa-solid fa-print me-2"></i> طباعة الشهادة</button>
                        <!-- زر للتحقق المباشر يرسل المتدرب للواجهة الرئيسية مع الكود -->
                        <a href="../index.php?cert_code=<?php echo $cert['certificate_code']; ?>#verify" target="_blank" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background-color: #0b1c3f; border:none;"><i class="fa-solid fa-search me-2"></i> التحقق أونلاين</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
    /* إخفاء عناصر الصفحة أثناء الطباعة لطباعة الشهادة فقط */
    @media print {
        body * { visibility: hidden; }
        .card, .card * { visibility: visible; }
        .card { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none !important; border: 1px solid #ccc !important; }
        .btn, .sidebar, .topbar { display: none !important; }
    }
</style>

<!-- إغلاق وسوم HTML -->
        </div> 
    </div> 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script> document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); }); document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); }); </script>
</body>
</html>