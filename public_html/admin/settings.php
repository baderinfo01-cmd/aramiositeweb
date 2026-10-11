<?php
require_once 'admin_init.php';
$success_msg = ''; $error_msg = '';

// ==========================================
// 1. معالجة حفظ الإعدادات العامة
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_general_settings'])) {
    $keys = [
        'site_name', 'contact_email', 'phone_number', 'facebook_link', 'twitter_link', 'instagram_link', 'youtube_link',
        'promo_title', 'promo_subtitle', 'promo_end_date', 'promo_link',
        'promo_title_en', 'promo_subtitle_en', 'promo_link_en',
        'exchange_rate', 'stat_courses', 'stat_students', 'stat_trainers', 'stat_consultations',
        'msg_course_approved', 'msg_order_approved', 'msg_order_rejected', 'msg_trainer_paid'
    ];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value_ar) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value_ar = VALUES(setting_value_ar)")->execute([$key, $_POST[$key]]);
        }
    }
    $success_msg = "تم حفظ الإعدادات بنجاح!";
}

// ==========================================
// 2. معالجة رفع صورة جديدة (للمعرض أو التقييمات)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_image'])) {
    $image_type = $_POST['image_type']; // gallery أو review
    $folder = ($image_type == 'gallery') ? '../uploads/gallery/' : '../uploads/reviews/';
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            $new_name = time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $folder . $new_name)) {
                $pdo->prepare("INSERT INTO gallery_images (image_name, image_type) VALUES (?, ?)")->execute([$new_name, $image_type]);
                $success_msg = "تم رفع وإضافة الصورة بنجاح!";
            } else { $error_msg = "حدث خطأ أثناء حفظ الصورة."; }
        } else { $error_msg = "صيغة غير مدعومة. يرجى رفع (JPG, PNG, WEBP)."; }
    } else { $error_msg = "يرجى اختيار صورة أولاً."; }
}

// ==========================================
// 3. معالجة حذف صورة
// ==========================================
if (isset($_GET['delete_img'])) {
    $id = $_GET['delete_img'];
    $img_data = $pdo->query("SELECT image_name, image_type FROM gallery_images WHERE id = $id")->fetch();
    if ($img_data) {
        $folder = ($img_data['image_type'] == 'gallery') ? '../uploads/gallery/' : '../uploads/reviews/';
        if (file_exists($folder . $img_data['image_name'])) { unlink($folder . $img_data['image_name']); }
        $pdo->prepare("DELETE FROM gallery_images WHERE id = ?")->execute([$id]);
        $success_msg = "تم حذف الصورة نهائياً.";
    }
}

// جلب البيانات
$current_settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value_ar FROM settings");
while ($row = $stmt->fetch()) { $current_settings[$row['setting_key']] = $row['setting_value_ar']; }
function getVal($array, $key) { return isset($array[$key]) ? htmlspecialchars($array[$key]) : ''; }

$gallery_images = $pdo->query("SELECT * FROM gallery_images WHERE image_type = 'gallery' ORDER BY created_at DESC")->fetchAll();
$review_images = $pdo->query("SELECT * FROM gallery_images WHERE image_type = 'review' ORDER BY created_at DESC")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fa-solid fa-sliders text-primary me-2"></i> التحكم الشامل بالواجهة</h3>
</div>

<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>
<?php if($error_msg): ?> <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div> <?php endif; ?>

<!-- التبويبات الفخمة (Tabs) -->
<ul class="nav nav-tabs mb-4 border-0" id="adminTabs">
  <li class="nav-item"><a class="nav-link active fw-bold border-0 bg-white shadow-sm rounded-pill px-4 me-2" data-bs-toggle="tab" href="#general_tab"><i class="fa-solid fa-gear text-danger"></i> إعدادات الموقع والمالية</a></li>
  <li class="nav-item"><a class="nav-link fw-bold border-0 bg-white shadow-sm rounded-pill px-4 me-2 text-muted" data-bs-toggle="tab" href="#gallery_tab"><i class="fa-solid fa-images text-primary"></i> معرض الصور والميداني</a></li>
  <li class="nav-item"><a class="nav-link fw-bold border-0 bg-white shadow-sm rounded-pill px-4 text-muted" data-bs-toggle="tab" href="#reviews_tab"><i class="fa-solid fa-comment-dots text-warning"></i> صور التقييمات والمحادثات</a></li>
</ul>

<div class="tab-content">
  
  <!-- ========================================== -->
  <!-- 💡 التبويب 1: الإعدادات العامة (القديمة) -->
  <!-- ========================================== -->
  <div class="tab-pane fade show active" id="general_tab">
      <form method="POST">
          <div class="row g-4">
              <!-- القسم 1: البيانات الأساسية والمالية -->
              <div class="col-lg-6">
                  <div class="admin-card card h-100 border-left-danger p-4">
                      <h5 class="fw-bold text-danger mb-4"><i class="fa-solid fa-building-columns me-2"></i> البيانات والمالية</h5>
                      <label class="fw-bold text-muted small">اسم الأكاديمية</label>
                      <input type="text" name="site_name" class="form-control mb-3 fw-bold" value="<?php echo getVal($current_settings, 'site_name'); ?>">
                      
                      <div class="row mb-3">
                          <div class="col-md-6"><label class="fw-bold text-muted small">الإيميل الرسمي</label><input type="email" name="contact_email" class="form-control text-start" dir="ltr" value="<?php echo getVal($current_settings, 'contact_email'); ?>"></div>
                          <div class="col-md-6 mt-3 mt-md-0"><label class="fw-bold text-muted small">رقم الهاتف</label><input type="tel" name="phone_number" class="form-control text-start" dir="ltr" value="<?php echo getVal($current_settings, 'phone_number'); ?>"></div>
                      </div>

                      <div class="bg-light p-3 rounded-3 border mb-4 text-center">
                          <label class="fw-bold text-danger mb-2">سعر الصرف (1 دولار = ؟ د.ج)</label>
                          <input type="number" step="0.01" name="exchange_rate" class="form-control fw-bold text-center fs-5 text-danger mx-auto" style="max-width: 200px;" value="<?php echo getVal($current_settings, 'exchange_rate'); ?>" required>
                      </div>
                      
                      <h6 class="fw-bold text-primary mb-3 border-bottom pb-2"><i class="fa-solid fa-link me-2"></i> وسائل التواصل (للفوتر)</h6>
                      <div class="input-group mb-2"><span class="input-group-text bg-primary text-white border-0"><i class="fa-brands fa-facebook-f"></i></span><input type="url" name="facebook_link" class="form-control text-start" dir="ltr" value="<?php echo getVal($current_settings, 'facebook_link'); ?>"></div>
                      <div class="input-group mb-2"><span class="input-group-text text-white border-0" style="background:#000;"><i class="fa-brands fa-x-twitter"></i></span><input type="url" name="twitter_link" class="form-control text-start" dir="ltr" value="<?php echo getVal($current_settings, 'twitter_link'); ?>"></div>
                      <div class="input-group mb-2"><span class="input-group-text text-white border-0" style="background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);"><i class="fa-brands fa-instagram"></i></span><input type="url" name="instagram_link" class="form-control text-start" dir="ltr" value="<?php echo getVal($current_settings, 'instagram_link'); ?>"></div>
                      <div class="input-group mb-2"><span class="input-group-text bg-danger text-white border-0"><i class="fa-brands fa-youtube"></i></span><input type="url" name="youtube_link" class="form-control text-start" dir="ltr" value="<?php echo getVal($current_settings, 'youtube_link'); ?>"></div>
                  </div>
              </div>

              <!-- القسم 2: الإعلانات والإحصائيات -->
              <div class="col-lg-6">
                  <div class="admin-card card h-100 border-left-warning p-4">
                      <h5 class="fw-bold text-warning text-dark mb-4"><i class="fa-solid fa-bullhorn me-2"></i> شريط الحدث والإحصائيات</h5>
                      <label class="fw-bold text-dark small">تاريخ الانتهاء للعداد التنازلي</label>
                      <input type="datetime-local" name="promo_end_date" class="form-control mb-4 fw-bold border-warning" value="<?php echo getVal($current_settings, 'promo_end_date'); ?>" dir="ltr">
                      
                      <ul class="nav nav-tabs mb-3" id="promoTabs">
                          <li class="nav-item"><a class="nav-link active fw-bold" data-bs-toggle="tab" href="#ar_promo">الإعلان العربي</a></li>
                          <li class="nav-item"><a class="nav-link fw-bold text-muted" data-bs-toggle="tab" href="#en_promo">English Promo</a></li>
                      </ul>
                      <div class="tab-content mb-4 pb-3 border-bottom">
                          <div class="tab-pane fade show active" id="ar_promo">
                              <input type="text" name="promo_title" class="form-control mb-2 fw-bold" placeholder="العنوان" value="<?php echo getVal($current_settings, 'promo_title'); ?>">
                              <input type="text" name="promo_subtitle" class="form-control mb-2" placeholder="الوصف" value="<?php echo getVal($current_settings, 'promo_subtitle'); ?>">
                              <input type="text" name="promo_link" class="form-control text-start" dir="ltr" placeholder="رابط الزر" value="<?php echo getVal($current_settings, 'promo_link'); ?>">
                          </div>
                          <div class="tab-pane fade" id="en_promo" dir="ltr">
                              <input type="text" name="promo_title_en" class="form-control mb-2 fw-bold text-start" placeholder="Title (EN)" value="<?php echo getVal($current_settings, 'promo_title_en'); ?>">
                              <input type="text" name="promo_subtitle_en" class="form-control mb-2 text-start" placeholder="Subtitle (EN)" value="<?php echo getVal($current_settings, 'promo_subtitle_en'); ?>">
                              <input type="text" name="promo_link_en" class="form-control text-start" placeholder="Link (EN)" value="<?php echo getVal($current_settings, 'promo_link_en'); ?>">
                          </div>
                      </div>

                      <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-chart-pie me-2"></i> الأرقام المعروضة في الواجهة</h6>
                      <div class="row g-2">
                          <div class="col-6"><label class="small text-muted fw-bold">الدورات</label><input type="number" name="stat_courses" class="form-control text-center fw-bold" value="<?php echo getVal($current_settings, 'stat_courses'); ?>"></div>
                          <div class="col-6"><label class="small text-muted fw-bold">الطلاب</label><input type="number" name="stat_students" class="form-control text-center fw-bold" value="<?php echo getVal($current_settings, 'stat_students'); ?>"></div>
                          <div class="col-6 mt-2"><label class="small text-muted fw-bold">المدربين</label><input type="number" name="stat_trainers" class="form-control text-center fw-bold" value="<?php echo getVal($current_settings, 'stat_trainers'); ?>"></div>
                          <div class="col-6 mt-2"><label class="small text-muted fw-bold text-success">الاستشارات</label><input type="number" name="stat_consultations" class="form-control text-center fw-bold border-success text-success" value="<?php echo getVal($current_settings, 'stat_consultations'); ?>"></div>
                      </div>
                  </div>
              </div>

              <!-- القسم 3: قوالب الواتساب -->
              <div class="col-12 mt-2 mb-4">
                  <div class="admin-card card h-100 border-left-success p-4">
                      <h5 class="fw-bold text-success mb-4"><i class="fa-brands fa-whatsapp me-2"></i> قوالب رسائل الواتساب الأوتوماتيكية</h5>
                      <div class="row g-4">
                          <div class="col-md-6"><label class="fw-bold text-muted small">قبول دورة المدرب</label><textarea name="msg_course_approved" class="form-control fw-bold" rows="3" required><?php echo getVal($current_settings, 'msg_course_approved'); ?></textarea></div>
                          <div class="col-md-6"><label class="fw-bold text-muted small">تفعيل الدورة/الاستشارة للمتدرب</label><textarea name="msg_order_approved" class="form-control fw-bold" rows="3" required><?php echo getVal($current_settings, 'msg_order_approved'); ?></textarea></div>
                          <div class="col-md-6"><label class="fw-bold text-muted small">رفض إيصال المتدرب</label><textarea name="msg_order_rejected" class="form-control fw-bold" rows="3" required><?php echo getVal($current_settings, 'msg_order_rejected'); ?></textarea></div>
                          <div class="col-md-6"><label class="fw-bold text-muted small">تحويل أرباح المدرب</label><textarea name="msg_trainer_paid" class="form-control fw-bold" rows="3" required><?php echo getVal($current_settings, 'msg_trainer_paid'); ?></textarea></div>
                      </div>
                  </div>
              </div>
          </div>
          <div class="text-center mt-3 mb-5">
              <button type="submit" name="save_general_settings" class="btn btn-success btn-lg px-5 rounded-pill shadow-sm fw-bold"><i class="fa-solid fa-save me-2"></i> حفظ الإعدادات</button>
          </div>
      </form>
  </div>

  <!-- ========================================== -->
  <!-- 💡 التبويب 2: صور المعرض (الميدانية) -->
  <!-- ========================================== -->
  <div class="tab-pane fade" id="gallery_tab">
      <div class="card border-0 shadow-sm rounded-4 p-4 mb-5 bg-white">
          <div class="d-flex justify-content-between align-items-center mb-4">
              <h5 class="fw-bold text-primary m-0"><i class="fa-solid fa-camera"></i> صور الأنشطة والقاعات</h5>
              <button class="btn btn-primary rounded-pill fw-bold px-4" data-bs-toggle="modal" data-bs-target="#uploadGalleryModal"><i class="fa-solid fa-upload"></i> رفع صورة للمعرض</button>
          </div>
          <div class="row g-4">
              <?php if(empty($gallery_images)): ?> <div class="col-12"><div class="alert alert-secondary text-center fw-bold">لا توجد صور في المعرض حالياً.</div></div> <?php endif; ?>
              <?php foreach($gallery_images as $img): ?>
              <div class="col-md-3 col-6 text-center">
                  <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                      <img src="../uploads/gallery/<?php echo $img['image_name']; ?>" style="height: 150px; object-fit: cover; width: 100%;">
                      <div class="card-footer bg-white border-0 p-2"><a href="?delete_img=<?php echo $img['id']; ?>" class="btn btn-sm btn-danger rounded-pill w-100 fw-bold" onclick="return confirm('تأكيد الحذف؟')"><i class="fa-solid fa-trash"></i> حذف</a></div>
                  </div>
              </div>
              <?php endforeach; ?>
          </div>
      </div>
  </div>

  <!-- ========================================== -->
  <!-- 💡 التبويب 3: صور التقييمات (رسائل الواتساب) -->
  <!-- ========================================== -->
  <div class="tab-pane fade" id="reviews_tab">
      <div class="card border-0 shadow-sm rounded-4 p-4 mb-5 bg-white">
          <div class="d-flex justify-content-between align-items-center mb-4">
              <h5 class="fw-bold text-warning text-dark m-0"><i class="fa-solid fa-comment-sms"></i> صور رسائل وآراء المتدربين</h5>
              <button class="btn btn-warning text-dark rounded-pill fw-bold px-4" data-bs-toggle="modal" data-bs-target="#uploadReviewModal"><i class="fa-solid fa-upload"></i> رفع صورة تقييم</button>
          </div>
          <div class="row g-4">
              <?php if(empty($review_images)): ?> <div class="col-12"><div class="alert alert-secondary text-center fw-bold">لا توجد صور تقييمات حالياً.</div></div> <?php endif; ?>
              <?php foreach($review_images as $img): ?>
              <div class="col-md-3 col-6 text-center">
                  <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                      <img src="../uploads/reviews/<?php echo $img['image_name']; ?>" style="height: 250px; object-fit: contain; width: 100%; background: #f8f9fc; padding: 5px;">
                      <div class="card-footer bg-white border-0 p-2"><a href="?delete_img=<?php echo $img['id']; ?>" class="btn btn-sm btn-danger rounded-pill w-100 fw-bold" onclick="return confirm('تأكيد الحذف؟')"><i class="fa-solid fa-trash"></i> حذف</a></div>
                  </div>
              </div>
              <?php endforeach; ?>
          </div>
      </div>
  </div>

</div> <!-- نهاية tab-content -->

<!-- Modal 1: رفع صورة معرض -->
<div class="modal fade" id="uploadGalleryModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header bg-primary text-white border-0 rounded-top-4"><h5 class="modal-title fw-bold"><i class="fa-solid fa-camera"></i> إضافة للمعرض</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form method="POST" enctype="multipart/form-data"><div class="modal-body p-4"><input type="hidden" name="image_type" value="gallery"><label class="form-label fw-bold">اختر الصورة <span class="text-danger">*</span></label><input type="file" name="image" class="form-control" accept="image/*" required></div><div class="modal-footer border-0 bg-light rounded-bottom-4"><button type="submit" name="upload_image" class="btn btn-primary w-100 rounded-pill fw-bold">رفع للموقع</button></div></form></div></div></div>

<!-- Modal 2: رفع صورة تقييم -->
<div class="modal fade" id="uploadReviewModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header bg-warning text-dark border-0 rounded-top-4"><h5 class="modal-title fw-bold"><i class="fa-solid fa-comment-sms"></i> إضافة صورة تقييم</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" enctype="multipart/form-data"><div class="modal-body p-4"><input type="hidden" name="image_type" value="review"><label class="form-label fw-bold">اختر لقطة الشاشة (Screenshot) <span class="text-danger">*</span></label><input type="file" name="image" class="form-control" accept="image/*" required></div><div class="modal-footer border-0 bg-light rounded-bottom-4"><button type="submit" name="upload_image" class="btn btn-warning w-100 rounded-pill fw-bold text-dark">رفع للموقع</button></div></form></div></div></div>

</div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); });
    document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });
    // تفعيل التبويبات الفخمة
    var triggerTabList = [].slice.call(document.querySelectorAll('#adminTabs a'))
    triggerTabList.forEach(function (triggerEl) { var tabTrigger = new bootstrap.Tab(triggerEl); triggerEl.addEventListener('click', function (event) { event.preventDefault(); tabTrigger.show(); }); });
</script></body></html>