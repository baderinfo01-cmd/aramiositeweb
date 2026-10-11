<?php
require_once 'admin_init.php';
$success_msg = ''; $error_msg = '';

// 1. إضافة قسم جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $name_ar = trim($_POST['name_ar']);
    $name_en = trim($_POST['name_en']);
    $status = $_POST['status']; // 1 نشط، 0 مخفي
    
    // التحقق من عدم التكرار (بالعربي أو الإنجليزي)
    $check = $pdo->prepare("SELECT id FROM categories WHERE name_ar = ? OR name_en = ?");
    $check->execute([$name_ar, $name_en]);
    
    if ($check->rowCount() > 0) {
        $error_msg = 'عذراً، هذا القسم موجود مسبقاً!';
    } else {
        $insert = $pdo->prepare("INSERT INTO categories (name_ar, name_en, status) VALUES (?, ?, ?)");
        if ($insert->execute([$name_ar, $name_en, $status])) {
            $success_msg = 'تمت إضافة القسم بنجاح!';
        }
    }
}

// 2. تعديل قسم موجود
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_category'])) {
    $id = $_POST['cat_id'];
    $name_ar = trim($_POST['name_ar']);
    $name_en = trim($_POST['name_en']);
    $status = $_POST['status'];
    
    $update = $pdo->prepare("UPDATE categories SET name_ar = ?, name_en = ?, status = ? WHERE id = ?");
    if ($update->execute([$name_ar, $name_en, $status, $id])) {
        $success_msg = 'تم تحديث بيانات القسم بنجاح!';
    }
}

// 3. حذف قسم
if (isset($_GET['delete'])) {
    // 💡 تنبيه أمني: هل توجد دورات مرتبطة بهذا القسم؟
    $check_courses = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id = ?");
    $check_courses->execute([$_GET['delete']]);
    $count = $check_courses->fetchColumn();
    
    if ($count > 0) {
        $error_msg = "لا يمكنك حذف هذا القسم! يوجد بداخله ($count) دورة. قم بتغيير قسم تلك الدورات أولاً أو احذفها.";
    } else {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$_GET['delete']]);
        $success_msg = "تم حذف القسم بنجاح.";
    }
}

// جلب جميع الأقسام لعرضها في الجدول
$categories = $pdo->query("SELECT * FROM categories ORDER BY created_at DESC")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h3 class="fw-bold text-dark"><i class="fa-solid fa-tags text-primary me-2"></i> إدارة أقسام وتصنيفات الدورات</h3>
    <button class="btn btn-success rounded-pill fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCatModal">
        <i class="fa-solid fa-plus me-2"></i> إضافة قسم جديد
    </button>
</div>

<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>
<?php if($error_msg): ?> <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div> <?php endif; ?>

<div class="admin-card card p-0 overflow-hidden mb-5">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-list text-muted me-2"></i> جميع الأقسام</h5>
        <span class="badge bg-light text-dark border px-3 py-2 fw-bold"><?php echo count($categories); ?> قسم</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle text-center mb-0">
            <thead class="table-light text-muted small fw-bold text-uppercase">
                <tr>
                    <th class="py-3 text-start ps-4">الاسم (بالعربية)</th>
                    <th class="py-3">الاسم (بالإنجليزية)</th>
                    <th class="py-3">عدد الدورات المرتبطة به</th>
                    <th class="py-3">الحالة</th>
                    <th class="py-3">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($categories)): ?>
                    <tr><td colspan="5" class="py-5 text-muted fw-bold"><i class="fa-solid fa-folder-open fa-3x mb-3 opacity-25"></i><br>لا توجد أي أقسام في النظام. أضف قسمك الأول!</td></tr>
                <?php else: ?>
                    <?php foreach($categories as $cat): ?>
                    <tr class="<?php echo ($cat['status'] == 0) ? 'table-warning table-opacity-10' : ''; ?>">
                        
                        <td class="text-start ps-4 fw-bold text-dark">
                            <i class="fa-solid fa-folder text-primary opacity-50 me-1"></i> <?php echo htmlspecialchars($cat['name_ar']); ?>
                        </td>
                        
                        <td class="fw-bold text-muted" dir="ltr">
                            <?php echo htmlspecialchars($cat['name_en']); ?>
                        </td>
                        
                        <td class="fw-bold fs-5 text-primary">
                            <?php 
                                $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id = ?");
                                $count_stmt->execute([$cat['id']]);
                                echo $count_stmt->fetchColumn();
                            ?>
                        </td>
                        
                        <td>
                            <?php if($cat['status'] == 1): ?>
                                <span class="badge bg-success rounded-pill px-3 py-2 shadow-sm"><i class="fa-solid fa-eye"></i> ظاهر في المتجر</span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3 py-2"><i class="fa-solid fa-eye-slash"></i> مخفي</span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold me-1 shadow-sm" title="تعديل القسم" 
                                    onclick="openEditModal(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['name_ar']); ?>', '<?php echo addslashes($cat['name_en']); ?>', <?php echo $cat['status']; ?>)">
                                <i class="fa-solid fa-pen"></i> تعديل
                            </button>
                            <a href="?delete=<?php echo $cat['id']; ?>" class="btn btn-sm btn-danger rounded-circle shadow-sm" onclick="return confirm('تأكيد حذف هذا القسم نهائياً؟')" title="حذف القسم"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: إضافة قسم جديد -->
<div class="modal fade" id="addCatModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-success text-white border-0 rounded-top-4">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-folder-plus me-2"></i> إضافة قسم جديد للدورات</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
          <div class="modal-body p-4">
              <div class="mb-3">
                  <label class="form-label text-muted small fw-bold">اسم القسم (بالعربية) <span class="text-danger">*</span></label>
                  <input type="text" name="name_ar" class="form-control fw-bold" placeholder="مثال: تطوير الذات" required>
              </div>
              <div class="mb-3">
                  <label class="form-label text-muted small fw-bold">اسم القسم (بالإنجليزية) <span class="text-danger">*</span></label>
                  <input type="text" name="name_en" class="form-control fw-bold text-start" dir="ltr" placeholder="Example: Self Development" required>
              </div>
              <div class="mb-4">
                  <label class="form-label text-muted small fw-bold">حالة القسم <span class="text-danger">*</span></label>
                  <select name="status" class="form-select fw-bold text-success" required>
                      <option value="1">ظاهر في المتجر</option>
                      <option value="0">مخفي (لا يظهر للجمهور)</option>
                  </select>
              </div>
          </div>
          <div class="modal-footer border-0 bg-light rounded-bottom-4">
              <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" name="add_category" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">حفظ وإضافة</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal 2: تعديل قسم -->
<div class="modal fade" id="editCatModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-primary text-white border-0 rounded-top-4">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> تعديل بيانات القسم</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
          <div class="modal-body p-4">
              <input type="hidden" name="cat_id" id="edit_id">
              <div class="mb-3">
                  <label class="form-label text-muted small fw-bold">الاسم (بالعربية)</label>
                  <input type="text" name="name_ar" id="edit_ar" class="form-control fw-bold" required>
              </div>
              <div class="mb-3">
                  <label class="form-label text-muted small fw-bold">الاسم (بالإنجليزية)</label>
                  <input type="text" name="name_en" id="edit_en" class="form-control fw-bold text-start" dir="ltr" required>
              </div>
              <div class="mb-4">
                  <label class="form-label text-muted small fw-bold">حالة القسم</label>
                  <select name="status" id="edit_status" class="form-select fw-bold" required>
                      <option value="1" class="text-success">ظاهر في المتجر</option>
                      <option value="0" class="text-danger">مخفي</option>
                  </select>
              </div>
          </div>
          <div class="modal-footer border-0 bg-light rounded-bottom-4">
              <button type="submit" name="edit_category" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm" style="background-color: #051024; border:none;">تحديث التغييرات</button>
          </div>
      </form>
    </div>
  </div>
</div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // الجافاسكربت الذكي لتمرير بيانات القسم للنافذة المنبثقة
    function openEditModal(id, ar, en, status) { 
        document.getElementById('edit_id').value = id; 
        document.getElementById('edit_ar').value = ar; 
        document.getElementById('edit_en').value = en; 
        document.getElementById('edit_status').value = status; 
        new bootstrap.Modal(document.getElementById('editCatModal')).show(); 
    } 
    
    // تفعيل القائمة الجانبية للموبايل
    document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); }); 
    document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });
</script>
</body></html>