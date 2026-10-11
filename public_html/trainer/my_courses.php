<?php
require_once '../includes/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainer') {
    header('Location: ../login.php');
    exit();
}

$trainer_id = $_SESSION['user_id'];
$success_msg = ''; $error_msg = '';

// 1. معالجة إضافة دورة جديدة (بأنواعها الثلاثة)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course'])) {
    $title = trim($_POST['title']);
    $category_id = $_POST['category_id'];
    $description = trim($_POST['description']);
    $training_type = $_POST['training_type']; // online, offline, online_live
    $thumbnail = 'default_course.jpg';
    
    $price_usd = !empty($_POST['price_usd']) ? $_POST['price_usd'] : null;
    $price_dzd = !empty($_POST['price_dzd']) ? $_POST['price_dzd'] : null;
    
    // متغيرات التدريب المباشر والحضوري (تُعطى قيمة إذا تم اختيارها، وإلا تبقى فارغة)
    $start_date = ($training_type != 'online') ? $_POST['start_date'] : null;
    $max_seats = ($training_type != 'online') ? (int)$_POST['max_seats'] : 0;
    
    $live_time = ($training_type == 'online_live') ? $_POST['live_time'] : null;
    $live_link = ($training_type == 'online_live') ? trim($_POST['live_link']) : null;
    
    $city = ($training_type == 'offline') ? trim($_POST['city']) : null;
    $venue_name = ($training_type == 'offline') ? trim($_POST['venue_name']) : null;
    $map_link = ($training_type == 'offline') ? trim($_POST['map_link']) : null;

    // التحقق المنطقي قبل الإدخال
    if ($price_usd === null && $price_dzd === null) {
        $error_msg = "يجب تحديد سعر الدورة بالدولار أو بالدينار على الأقل!";
    } elseif ($training_type == 'offline' && (empty($city) || empty($venue_name) || empty($start_date))) {
        $error_msg = "الرجاء إكمال جميع بيانات التدريب الحضوري (المدينة، القاعة، وتاريخ البداية).";
    } elseif ($training_type == 'online_live' && (empty($start_date) || empty($live_time) || empty($live_link))) {
        $error_msg = "الرجاء إكمال بيانات البث المباشر (التاريخ، الوقت، ورابط المنصة الزووم/ميت).";
    } else {
        // معالجة الصورة
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), $allowed)) {
                $thumbnail = time() . '_' . rand(100, 999) . '.' . $ext;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], '../uploads/courses/' . $thumbnail);
            } else {
                $error_msg = "صيغة الصورة غير مدعومة. (JPG, PNG, WEBP).";
            }
        }

        if (empty($error_msg)) {
            // 💡 إدخال الدورة الشامل
            $insert = $pdo->prepare("
                INSERT INTO courses (trainer_id, category_id, training_type, city, venue_name, map_link, max_seats, start_date, live_time, live_link, title_ar, title_en, description_ar, price_usd, price_dzd, thumbnail, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            if ($insert->execute([$trainer_id, $category_id, $training_type, $city, $venue_name, $map_link, $max_seats, $start_date, $live_time, $live_link, $title, $title, $description, $price_usd, $price_dzd, $thumbnail])) {
                $success_msg = "تم إضافة الدورة بنجاح! بانتظار مراجعة الإدارة.";
            } else {
                $error_msg = "حدث خطأ أثناء حفظ الدورة.";
            }
        }
    }
}

// 2. جلب جميع دورات هذا المدرب
$stmt = $pdo->prepare("
    SELECT c.*, cat.name_ar AS category_name,
    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS lessons_count
    FROM courses c 
    JOIN categories cat ON c.category_id = cat.id 
    WHERE c.trainer_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$trainer_id]);
$my_courses = $stmt->fetchAll();

$categories = $pdo->query("SELECT id, name_ar FROM categories")->fetchAll();
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h3 class="fw-bold" style="color: #0b1c3f;"><i class="fa-solid fa-book-open text-primary me-2"></i> إدارة دوراتي</h3>
    <button class="btn btn-warning text-dark rounded-pill fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">
        <i class="fa-solid fa-plus me-2"></i> إنشاء دورة جديدة
    </button>
</div>

<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>
<?php if($error_msg): ?> <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div> <?php endif; ?>

<!-- شبكة عرض دورات المدرب -->
<div class="row g-4 mb-5">
    <?php if(empty($my_courses)): ?>
        <div class="col-12 text-center py-5 bg-white rounded-4 shadow-sm border">
            <i class="fa-solid fa-photo-film text-muted fa-4x mb-3 opacity-50"></i>
            <h5 class="fw-bold text-muted">لم تقم بإضافة أي دورات حتى الآن.</h5>
            <p class="text-muted mb-4">ابدأ رحلتك كمدرب وأضف دورتك الأولى ليشتريها آلاف المتدربين.</p>
            <button class="btn btn-warning text-dark rounded-pill fw-bold px-5 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">إنشاء دورتي الأولى</button>
        </div>
    <?php else: ?>
        <?php foreach($my_courses as $course): ?>
        <div class="col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden" style="transition: 0.3s;">
                <div class="position-relative">
                    <img src="<?php echo ($course['thumbnail'] && $course['thumbnail'] != 'default_course.jpg') ? '../uploads/courses/'.$course['thumbnail'] : 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3'; ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                    
                    <!-- حالة الدورة -->
                    <div class="position-absolute top-0 start-0 m-3">
                        <?php 
                            if($course['status'] == 'published') echo '<span class="badge bg-success px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-check-double"></i> منشورة</span>';
                            elseif($course['status'] == 'pending') echo '<span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-clock"></i> قيد المراجعة</span>';
                            else echo '<span class="badge bg-danger px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-ban"></i> مرفوضة</span>';
                        ?>
                    </div>
                    
                    <!-- 💡 شارة (حضوري / أونلاين / بث مباشر) -->
                    <div class="position-absolute top-0 end-0 m-3">
                        <?php if($course['training_type'] == 'online'): ?>
                            <span class="badge bg-primary px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-globe"></i> مسجلة</span>
                        <?php elseif($course['training_type'] == 'online_live'): ?>
                            <span class="badge bg-danger px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-satellite-dish"></i> بث مباشر</span>
                        <?php else: ?>
                            <span class="badge bg-info text-dark px-3 py-2 rounded-pill shadow"><i class="fa-solid fa-location-dot"></i> حضوري (<?php echo htmlspecialchars($course['city']); ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-body p-4 d-flex flex-column">
                    <span class="badge bg-light text-primary border mb-2 align-self-start"><?php echo htmlspecialchars($course['category_name']); ?></span>
                    <h5 class="fw-bold mb-3 text-dark"><?php echo htmlspecialchars($course['title_ar']); ?></h5>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3 text-muted small fw-bold">
                        <?php if($course['training_type'] == 'online'): ?>
                            <span><i class="fa-solid fa-video text-primary me-1"></i> <?php echo $course['lessons_count']; ?> دروس مسجلة</span>
                        <?php else: ?>
                            <span class="text-danger"><i class="fa-regular fa-calendar me-1"></i> تبدأ: <?php echo date('Y-m-d', strtotime($course['start_date'])); ?></span>
                        <?php endif; ?>
                        
                        <span class="text-success fs-6">
                            <?php 
                                $prices = [];
                                if($course['price_usd']) $prices[] = '$'.(float)$course['price_usd'];
                                if($course['price_dzd']) $prices[] = (float)$course['price_dzd'].' د.ج';
                                echo implode(' | ', $prices);
                            ?>
                        </span>
                    </div>
                    
                    <?php if($course['training_type'] != 'online'): ?>
                        <div class="bg-light p-2 rounded small fw-bold text-center mb-3 border d-flex justify-content-around">
                            <span class="text-warning"><i class="fa-solid fa-chair"></i> سعة القاعة/البث: <?php echo $course['max_seats']; ?></span>
                            <?php if($course['training_type'] == 'online_live'): ?>
                                <span class="text-primary" dir="ltr"><i class="fa-regular fa-clock"></i> <?php echo date('h:i A', strtotime($course['live_time'])); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <hr class="opacity-25 my-auto mb-3">
                    
                    <!-- أزرار الإجراءات حسب نوع الدورة -->
                    <?php if($course['training_type'] == 'online'): ?>
                        <a href="manage_lessons.php?id=<?php echo $course['id']; ?>" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm" style="background-color: #0b1c3f; border:none;"><i class="fa-solid fa-photo-film me-1"></i> إدارة الدروس والفيديوهات</a>
                    <?php else: ?>
                        <!-- 💡 زر إدارة حضور المتدربين (للدورات المباشرة والحضورية) -->
                        <a href="course_students.php?id=<?php echo $course['id']; ?>" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-users me-1"></i> إدارة المسجلين (وإرسال الرابط)</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal إضافة دورة (النسخة الذكية والمحدثة) -->
<div class="modal fade" id="addCourseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-light border-0 rounded-top-4">
        <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-plus-circle text-warning me-2"></i> إعداد برنامج تدريبي جديد</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
          <div class="modal-body p-4">
              
              <!-- 💡 اختيار نوع الدورة (3 خيارات الآن) -->
              <div class="mb-4 text-center">
                  <label class="form-label fw-bold text-primary mb-3 fs-5"><i class="fa-solid fa-chalkboard"></i> 1. ما هو نوع هذا التدريب؟</label>
                  <div class="d-flex justify-content-center flex-wrap gap-2">
                      <div class="form-check bg-light px-4 py-2 rounded-pill border">
                          <input class="form-check-input ms-2 mt-1" type="radio" name="training_type" id="typeOnline" value="online" checked onchange="toggleCourseFields()">
                          <label class="form-check-label fw-bold text-dark" for="typeOnline" style="cursor: pointer;">💻 دورة مسجلة (فيديوهات)</label>
                      </div>
                      <div class="form-check bg-light px-4 py-2 rounded-pill border border-danger">
                          <input class="form-check-input ms-2 mt-1" type="radio" name="training_type" id="typeLive" value="online_live" onchange="toggleCourseFields()">
                          <label class="form-check-label fw-bold text-danger" for="typeLive" style="cursor: pointer;">📡 بث مباشر (Zoom/Meet)</label>
                      </div>
                      <div class="form-check bg-light px-4 py-2 rounded-pill border border-info">
                          <input class="form-check-input ms-2 mt-1" type="radio" name="training_type" id="typeOffline" value="offline" onchange="toggleCourseFields()">
                          <label class="form-check-label fw-bold text-info text-dark" for="typeOffline" style="cursor: pointer;">🏫 حضوري (في قاعة)</label>
                      </div>
                  </div>
              </div>

              <!-- حقول التدريب الحضوري والمباشر (مخفية افتراضياً) -->
              <div id="extraFields" class="bg-light bg-opacity-50 p-4 rounded-4 mb-4 border border-secondary" style="display: none;">
                  <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-calendar-day text-primary"></i> بيانات الحجز والمقاعد</h6>
                  
                  <div class="row mb-3">
                      <div class="col-md-4 mb-3">
                          <label class="form-label fw-bold small text-muted">تاريخ البداية (اليوم الأول) <span class="text-danger">*</span></label>
                          <input type="date" name="start_date" id="inpDate" class="form-control fw-bold text-primary" min="<?php echo date('Y-m-d'); ?>">
                      </div>
                      <div class="col-md-4 mb-3" id="timeSection" style="display: none;">
                          <label class="form-label fw-bold small text-muted">توقيت البث/الجلسة <span class="text-danger">*</span></label>
                          <input type="time" name="live_time" id="inpTime" class="form-control fw-bold text-center text-primary" dir="ltr">
                      </div>
                      <div class="col-md-4 mb-3">
                          <label class="form-label fw-bold small text-muted">إجمالي المقاعد المتاحة</label>
                          <input type="number" name="max_seats" class="form-control fw-bold text-center" placeholder="مثال: 50">
                          <small class="text-danger d-block mt-1" style="font-size:0.7rem;"><i class="fa-solid fa-lock"></i> نظام حجز آمن (60 دقيقة للدفع أو يُلغى الحجز)</small>
                      </div>
                  </div>

                  <!-- حقول الحضوري فقط -->
                  <div id="offlineOnlyFields" class="row border-top pt-3 mt-2" style="display: none;">
                      <h6 class="fw-bold text-info text-dark mb-3"><i class="fa-solid fa-map-location-dot"></i> تفاصيل القاعة</h6>
                      <div class="col-md-6 mb-3">
                          <input type="text" name="city" id="inpCity" class="form-control fw-bold" placeholder="المدينة / المحافظة *">
                      </div>
                      <div class="col-md-6 mb-3">
                          <input type="text" name="venue_name" id="inpVenue" class="form-control fw-bold" placeholder="اسم القاعة أو الفندق *">
                      </div>
                      <div class="col-12">
                          <input type="url" name="map_link" class="form-control text-start" dir="ltr" placeholder="رابط خرائط جوجل للقاعة (اختياري)">
                      </div>
                  </div>

                  <!-- حقول البث المباشر فقط -->
                  <div id="liveOnlyFields" class="row border-top pt-3 mt-2" style="display: none;">
                      <div class="col-12">
                          <label class="form-label fw-bold text-danger small"><i class="fa-solid fa-link"></i> رابط قاعة البث السري (Zoom / Google Meet) <span class="text-danger">*</span></label>
                          <input type="url" name="live_link" id="inpLiveLink" class="form-control border-danger text-start fw-bold" dir="ltr" placeholder="https://zoom.us/...">
                          <small class="text-muted d-block mt-1"><i class="fa-solid fa-shield-halved text-success"></i> هذا الرابط مخفي تماماً ولن يظهر للمتدرب إلا قبل البث بساعة.</small>
                      </div>
                  </div>
              </div>
              
              <div style="width: 80px; height: 3px; background: var(--accent); margin: 20px auto;"></div>

              <!-- 2. البيانات الأساسية المشتركة -->
              <div class="row">
                  <div class="col-md-12 mb-3">
                      <label class="form-label fw-bold text-muted small">2. عنوان الدورة <span class="text-danger">*</span></label>
                      <input type="text" name="title" class="form-control fw-bold fs-5" required>
                  </div>
              </div>

              <!-- הסعر بالعملتين -->
              <div class="row bg-light p-3 rounded-3 mb-3 border">
                  <p class="fw-bold small text-primary mb-2"><i class="fa-solid fa-tags"></i> 3. تسعير الدورة (يمكنك ملء حقل واحد أو كلاهما)</p>
                  <div class="col-md-6 mb-3 mb-md-0">
                      <label class="form-label fw-bold text-muted small">السعر بالدولار ($)</label>
                      <input type="number" step="0.01" name="price_usd" class="form-control text-center fw-bold text-success fs-5" dir="ltr" placeholder="50.00">
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold text-muted small">السعر بالدينار (د.ج)</label>
                      <input type="number" step="0.01" name="price_dzd" class="form-control text-center fw-bold text-success fs-5" dir="ltr" placeholder="10000">
                  </div>
              </div>

              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold text-muted small">القسم (التصنيف) <span class="text-danger">*</span></label>
                      <select name="category_id" class="form-select fw-bold" required>
                          <option value="">-- اختر القسم --</option>
                          <?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name_ar']); ?></option><?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold text-muted small">صورة الغلاف التسويقية <span class="text-danger">*</span></label>
                      <input type="file" name="thumbnail" class="form-control" accept="image/jpeg, image/png, image/webp" required>
                  </div>
              </div>

              <div class="mb-3">
                  <label class="form-label fw-bold text-muted small">وصف الدورة (ماذا سيتعلم الطالب؟) <span class="text-danger">*</span></label>
                  <textarea name="description" class="form-control fw-bold" rows="5" required></textarea>
              </div>

          </div>
          <div class="modal-footer border-0 bg-light rounded-bottom-4">
              <button type="submit" name="add_course" class="btn btn-warning rounded-pill px-5 py-3 fw-bold shadow-sm text-dark mx-auto d-block w-100 fs-5"><i class="fa-solid fa-cloud-arrow-up me-2"></i> حفظ الدورة وإرسالها لمراجعة الإدارة</button>
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

        // 💡 السكريبت الذكي لإظهار حقول البث המباشر والحضوري
        function toggleCourseFields() {
            const type = document.querySelector('input[name="training_type"]:checked').value;
            const extraFields = document.getElementById('extraFields');
            const offlineOnly = document.getElementById('offlineOnlyFields');
            const liveOnly = document.getElementById('liveOnlyFields');
            const timeSection = document.getElementById('timeSection');
            
            // الحقول المطلوبة
            const inpDate = document.getElementById('inpDate');
            const inpTime = document.getElementById('inpTime');
            const inpCity = document.getElementById('inpCity');
            const inpVenue = document.getElementById('inpVenue');
            const inpLiveLink = document.getElementById('inpLiveLink');

            if (type === 'online') {
                extraFields.style.display = 'none';
                inpDate.required = false; inpTime.required = false; inpCity.required = false; inpVenue.required = false; inpLiveLink.required = false;
            } else if (type === 'offline') {
                extraFields.style.display = 'block';
                offlineOnly.style.display = 'flex';
                liveOnly.style.display = 'none';
                timeSection.style.display = 'none';
                
                inpDate.required = true; inpTime.required = false; inpCity.required = true; inpVenue.required = true; inpLiveLink.required = false;
            } else if (type === 'online_live') {
                extraFields.style.display = 'block';
                offlineOnly.style.display = 'none';
                liveOnly.style.display = 'flex';
                timeSection.style.display = 'block';
                
                inpDate.required = true; inpTime.required = true; inpCity.required = false; inpVenue.required = false; inpLiveLink.required = true;
            }
        }
    </script>
</body>
</html>