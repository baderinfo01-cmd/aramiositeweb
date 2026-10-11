<?php
require_once '../includes/init.php';

// حماية الصفحة
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainer') {
    header('Location: ../login.php');
    exit();
}

$trainer_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// التحقق من وجود رقم الدورة في الرابط
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: my_courses.php');
    exit();
}
$course_id = $_GET['id'];

// 🔒 أمنياً: التأكد أن الدورة المطلوبة تخص هذا المدرب فعلياً
$stmt = $pdo->prepare("SELECT id, title_ar, status FROM courses WHERE id = ? AND trainer_id = ?");
$stmt->execute([$course_id, $trainer_id]);
$course = $stmt->fetch();

if (!$course) {
    // إذا لم يجد الدورة (أو حاول تزوير الرقم في الرابط)، اطرده لصفحة دوراته
    header('Location: my_courses.php');
    exit();
}

// 1. معالجة إضافة درس جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_lesson'])) {
    $title = trim($_POST['title']);
    $video_url = trim($_POST['video_url']);
    $lesson_order = $_POST['lesson_order'];
    $is_free = isset($_POST['is_free']) ? 1 : 0; // إذا علم المربع يرسل 1 (مجاني)، وإلا 0 (مدفوع)

    // إدخال الدرس في القاعدة
    $insert = $pdo->prepare("INSERT INTO lessons (course_id, title_ar, title_en, video_url, lesson_order, is_free) VALUES (?, ?, ?, ?, ?, ?)");
    if ($insert->execute([$course_id, $title, $title, $video_url, $lesson_order, $is_free])) {
        $success_msg = "تمت إضافة الدرس (الفيديو) بنجاح!";
    } else {
        $error_msg = "حدث خطأ أثناء إضافة الدرس.";
    }
}

// 2. معالجة حذف درس
if (isset($_GET['delete_lesson'])) {
    $lesson_id = $_GET['delete_lesson'];
    $delete = $pdo->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?");
    if ($delete->execute([$lesson_id, $course_id])) {
        $success_msg = "تم حذف الدرس بنجاح.";
    }
}

// 3. جلب جميع الدروس الحالية لهذه الدورة
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY lesson_order ASC");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

// جلب آخر رقم ترتيب (لتسهيل الإدخال للمدرب)
$last_order = 1;
if (!empty($lessons)) {
    $last_order = end($lessons)['lesson_order'] + 1; // نهاية المصفوفة + 1
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <a href="my_courses.php" class="text-muted text-decoration-none me-2 fs-5"><i class="fa-solid fa-arrow-right"></i></a>
        <h3 class="fw-bold d-inline" style="color: #0b1c3f;"><i class="fa-solid fa-photo-film text-warning me-2"></i> استوديو الدورة: <?php echo htmlspecialchars($course['title_ar']); ?></h3>
    </div>
    
    <!-- زر الإضافة يفتح الـ Modal -->
    <button class="btn btn-warning text-dark rounded-pill fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addLessonModal">
        <i class="fa-solid fa-plus me-2"></i> إضافة فيديو جديد
    </button>
</div>

<?php if($success_msg): ?>
    <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div>
<?php endif; ?>
<?php if($error_msg): ?>
    <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div>
<?php endif; ?>

<!-- شريط حالة الدورة التنبيهي -->
<div class="alert <?php echo ($course['status'] == 'published') ? 'alert-success border-success' : 'alert-warning border-warning'; ?> rounded-4 shadow-sm mb-4">
    <div class="d-flex align-items-center gap-3">
        <?php if($course['status'] == 'published'): ?>
            <i class="fa-solid fa-circle-check fa-2x text-success"></i>
            <div>
                <h6 class="fw-bold mb-0">الدورة منشورة!</h6>
                <small class="text-muted">أي درس تقوم بإضافته أو حذفه الآن سيؤثر فوراً على المتدربين الذين اشتروا الدورة.</small>
            </div>
        <?php else: ?>
            <i class="fa-solid fa-circle-exclamation fa-2x text-warning"></i>
            <div>
                <h6 class="fw-bold mb-0 text-dark">الدورة (<?php echo ($course['status'] == 'pending') ? 'قيد المراجعة' : 'مسودة/مرفوضة'; ?>)</h6>
                <small class="text-dark opacity-75">المتدربون لا يرون هذه الدروس حتى توافق الإدارة على نشر الدورة في المتجر.</small>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- جدول ترتيب الفيديوهات والدروس -->
<div class="card border-0 shadow-sm rounded-4 mb-5 overflow-hidden">
    <div class="card-header bg-white py-3 border-0">
        <h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-list-ol text-muted me-2"></i> محتوى الدورة (المسار التعليمي)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-light text-muted small fw-bold">
                    <tr>
                        <th class="py-3">الترتيب</th>
                        <th class="py-3 text-start ps-4">عنوان الدرس</th>
                        <th class="py-3">رابط الفيديو / المعاينة</th>
                        <th class="py-3">حالة العرض</th>
                        <th class="py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($lessons)): ?>
                        <tr><td colspan="5" class="py-5 text-muted">
                            <i class="fa-solid fa-film fa-3x mb-3 opacity-25"></i>
                            <h6 class="fw-bold">لم تقم برفع أي فيديوهات لهذه الدورة بعد.</h6>
                            <p class="small">اضغط على "إضافة فيديو جديد" لتبدأ بناء محتواك.</p>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach($lessons as $lesson): ?>
                        <tr>
                            <td class="fw-bold text-muted">
                                <span class="bg-light rounded-circle px-3 py-2 border shadow-sm"><?php echo $lesson['lesson_order']; ?></span>
                            </td>
                            
                            <td class="fw-bold text-dark text-start ps-4 fs-6">
                                <?php echo htmlspecialchars($lesson['title_ar']); ?>
                            </td>
                            
                            <td>
                                <!-- زر يفتح رابط الفيديو في تبويب جديد للمعاينة -->
                                <a href="<?php echo htmlspecialchars($lesson['video_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm fw-bold">
                                    <i class="fa-solid fa-play"></i> تشغيل
                                </a>
                            </td>
                            
                            <td>
                                <?php if($lesson['is_free']): ?>
                                    <span class="badge bg-success rounded-pill px-3 py-2"><i class="fa-solid fa-unlock-keyhole"></i> مجاني للمعاينة</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill px-3 py-2"><i class="fa-solid fa-lock"></i> مدفوع (مغلق)</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <a href="?id=<?php echo $course_id; ?>&delete_lesson=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-outline-danger rounded-pill fw-bold shadow-sm px-3" onclick="return confirm('هل أنت متأكد من حذف هذا الدرس بشكل نهائي؟')">
                                    <i class="fa-solid fa-trash"></i> حذف
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

<!-- النافذة المنبثقة (Modal) لإضافة درس جديد -->
<div class="modal fade" id="addLessonModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-light border-0 rounded-top-4">
        <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-video text-warning me-2"></i> إضافة فيديو تعليمي جديد</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
          <div class="modal-body p-4">
              
              <div class="row">
                  <div class="col-md-9 mb-3">
                      <label class="form-label fw-bold text-muted small">عنوان الدرس <span class="text-danger">*</span></label>
                      <input type="text" name="title" class="form-control" placeholder="مثال: الدرس الأول - أساسيات البرمجة" required>
                  </div>
                  <div class="col-md-3 mb-3">
                      <label class="form-label fw-bold text-muted small">ترتيب الدرس <span class="text-danger">*</span></label>
                      <!-- يقرأ الترتيب الأخير تلقائيا من المتغير $last_order -->
                      <input type="number" name="lesson_order" class="form-control text-center fw-bold" value="<?php echo $last_order; ?>" required min="1">
                  </div>
              </div>

              <div class="mb-4">
                  <label class="form-label fw-bold text-muted small">رابط الفيديو (URL) <span class="text-danger">*</span></label>
                  <!-- ملاحظة للمدرب لاستخدام روابط صحيحة -->
                  <div class="input-group">
                      <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-link text-muted"></i></span>
                      <input type="url" name="video_url" class="form-control border-start-0" dir="ltr" placeholder="مثال: https://www.youtube.com/embed/xyz123" required>
                  </div>
                  <small class="text-muted d-block mt-2"><i class="fa-solid fa-circle-info text-info"></i> يُفضل استخدام رابط (يوتيوب) بصيغة التضمين (Embed)، أو رابط فيديو مباشر (MP4) من سيرفر خارجي.</small>
              </div>

              <div class="form-check form-switch p-3 bg-light rounded-3 border">
                  <!-- زر التبديل بين مجاني ومدفوع -->
                  <input class="form-check-input ms-0 me-3 fs-4" type="checkbox" role="switch" name="is_free" id="freeSwitch">
                  <label class="form-check-label fw-bold mt-1" for="freeSwitch" style="cursor: pointer;">
                      <span class="text-success"><i class="fa-solid fa-unlock-keyhole"></i> جعل هذا الدرس مجانياً!</span> 
                      <small class="d-block text-muted fw-normal mt-1">إذا قمت بالتفعيل، سيتمكن أي زائر من مشاهدة هذا الفيديو قبل شراء الدورة كعينة تسويقية.</small>
                  </label>
              </div>

          </div>
          <div class="modal-footer border-0 bg-light rounded-bottom-4">
              <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" name="add_lesson" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm text-dark"><i class="fa-solid fa-cloud-arrow-up me-1"></i> حفظ ورفع الفيديو</button>
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