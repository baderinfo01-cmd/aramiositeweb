<?php
require_once 'admin_init.php';
$success_msg = ''; $error_msg = '';

// 1. معالجة حذف مستخدم (مع الحماية من حذف النفس)
if (isset($_GET['delete'])) {
    if ($_GET['delete'] != $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['delete']]);
        $success_msg = "تم حذف المستخدم من النظام بنجاح.";
    } else {
        $error_msg = "لا يمكنك حذف حساب الإدارة الخاص بك!";
    }
}

// 2. معالجة إضافة مستخدم جديد (مدرب أو موظف أو متدرب)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // التأكد من عدم تكرار الإيميل
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->rowCount() > 0) {
        $error_msg = 'عذراً، هذا البريد الإلكتروني مسجل في النظام مسبقاً!';
    } else {
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
        // جعل الحساب موثقاً تلقائياً (is_verified = 1) لأن الأدمن هو من أضافه
        $insert = $pdo->prepare("INSERT INTO users (full_name, email, password, role, is_verified) VALUES (?, ?, ?, ?, 1)");
        if ($insert->execute([$name, $email, $hashed_pass, $role])) {
            $success_msg = 'تمت إضافة المستخدم وتفعيله بنجاح!';
        }
    }
}

// 3. معالجة تعديل مستخدم موجود (الاسم، الصلاحية، الحالة)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $id = $_POST['user_id'];
    $name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $status = $_POST['status']; // 1 نشط، 0 محظور
    
    $update = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, status = ? WHERE id = ?");
    if ($update->execute([$name, $role, $status, $id])) {
        $success_msg = 'تم تحديث بيانات المستخدم بنجاح!';
    }
}

// جلب جميع المستخدمين (الأحدث أولاً)
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h3 class="fw-bold text-dark"><i class="fa-solid fa-users-gear text-primary me-2"></i> إدارة المستخدمين والصلاحيات</h3>
    <button class="btn btn-success rounded-pill fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fa-solid fa-user-plus me-2"></i> إضافة مستخدم جديد
    </button>
</div>

<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>
<?php if($error_msg): ?> <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div> <?php endif; ?>

<div class="admin-card card p-0 overflow-hidden mb-5">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-address-book text-muted me-2"></i> قاعدة بيانات المستخدمين</h5>
        <span class="badge bg-light text-dark border px-3 py-2 fw-bold"><?php echo count($users); ?> مستخدم</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle text-center mb-0">
            <thead class="table-light text-muted small fw-bold text-uppercase">
                <tr>
                    <th class="py-3 text-start ps-4">الاسم الكامل / البريد</th>
                    <th class="py-3">الصلاحية (الرتبة)</th>
                    <th class="py-3">الحالة (نشط/محظور)</th>
                    <th class="py-3">تاريخ الانضمام</th>
                    <th class="py-3">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr class="<?php echo ($u['status'] == 0) ? 'table-danger table-opacity-10' : ''; ?>">
                    <td class="text-start ps-4">
                        <strong class="text-dark"><i class="fa-solid fa-user-circle text-primary opacity-50 me-1"></i> <?php echo htmlspecialchars($u['full_name']); ?></strong><br>
                        <small class="text-muted fw-bold" dir="ltr"><?php echo htmlspecialchars($u['email']); ?></small>
                    </td>
                    
                    <td class="fw-bold">
                        <?php 
                            if($u['role'] == 'admin') echo '<span class="badge bg-danger rounded-pill px-3 shadow-sm"><i class="fa-solid fa-crown me-1"></i> مدير عام</span>';
                            elseif($u['role'] == 'staff') echo '<span class="badge bg-secondary rounded-pill px-3 shadow-sm">موظف</span>';
                            elseif($u['role'] == 'trainer') echo '<span class="badge bg-primary rounded-pill px-3 shadow-sm"><i class="fa-solid fa-chalkboard-user me-1"></i> مدرب</span>';
                            else echo '<span class="badge bg-light text-dark border rounded-pill px-3"><i class="fa-solid fa-user-graduate me-1"></i> متدرب</span>'; 
                        ?>
                    </td>
                    
                    <td>
                        <?php if($u['status'] == 1): ?>
                            <span class="text-success fw-bold small"><i class="fa-solid fa-circle-check"></i> نشط</span>
                        <?php else: ?>
                            <span class="text-danger fw-bold small"><i class="fa-solid fa-ban"></i> محظور</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="text-muted small fw-bold">
                        <?php echo date('Y-m-d', strtotime($u['created_at'])); ?>
                    </td>
                    
                    <td>
                        <!-- زر التعديل يفتح النافذة ويمرر البيانات الحالية للمستخدم -->
                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold me-1 shadow-sm" title="تعديل البيانات" 
                                onclick="openEditModal(<?php echo $u['id']; ?>, '<?php echo addslashes($u['full_name']); ?>', '<?php echo $u['role']; ?>', <?php echo $u['status']; ?>)">
                            <i class="fa-solid fa-pen"></i> تعديل
                        </button>
                        
                        <?php if($u['id'] != $_SESSION['user_id']): ?>
                            <!-- زر الحذف يظهر للجميع ما عدا حساب الأدمن الحالي -->
                            <a href="?delete=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger rounded-circle shadow-sm" onclick="return confirm('تأكيد حذف هذا المستخدم نهائياً؟')" title="حذف المستخدم"><i class="fa-solid fa-trash"></i></a>
                        <?php else: ?>
                            <!-- قفل لحماية حساب الإدارة -->
                            <span class="text-muted small ms-2 opacity-50" title="لا يمكنك حذف حسابك"><i class="fa-solid fa-lock"></i></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- النافذة المنبثقة 1: إضافة مستخدم جديد -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-success text-white border-0 rounded-top-4">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2"></i> إضافة مستخدم جديد للمنصة</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
          <div class="modal-body p-4">
              <div class="mb-3">
                  <label class="form-label text-muted small fw-bold">الاسم الكامل <span class="text-danger">*</span></label>
                  <input type="text" name="full_name" class="form-control fw-bold" required>
              </div>
              <div class="mb-3">
                  <label class="form-label text-muted small fw-bold">البريد الإلكتروني <span class="text-danger">*</span></label>
                  <input type="email" name="email" class="form-control fw-bold text-start" dir="ltr" required>
              </div>
              <div class="row mb-4">
                  <div class="col-md-6">
                      <label class="form-label text-muted small fw-bold">الرقم السري الافتراضي <span class="text-danger">*</span></label>
                      <input type="text" name="password" class="form-control text-center fw-bold text-primary" value="123456" required>
                      <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">يمكنه تغييره لاحقاً.</small>
                  </div>
                  <div class="col-md-6 mt-3 mt-md-0">
                      <label class="form-label text-muted small fw-bold">الصلاحية (الرتبة) <span class="text-danger">*</span></label>
                      <select name="role" class="form-select fw-bold text-success" required>
                          <option value="trainer">مدرب (Trainer)</option>
                          <option value="admin">مدير عام (Admin)</option>
                          <option value="staff">موظف (Staff)</option>
                          <option value="trainee">متدرب (Trainee)</option>
                      </select>
                  </div>
              </div>
          </div>
          <div class="modal-footer border-0 bg-light rounded-bottom-4">
              <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" name="add_user" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">حفظ وإضافة للمنصة</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- النافذة المنبثقة 2: تعديل بيانات وصلاحيات مستخدم (تظهر بالـ JS) -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-primary text-white border-0 rounded-top-4">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> تعديل بيانات المستخدم</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
          <div class="modal-body p-4">
              <input type="hidden" name="user_id" id="edit_id">
              
              <div class="mb-4">
                  <label class="form-label text-muted small fw-bold">الاسم الكامل</label>
                  <input type="text" name="full_name" id="edit_name" class="form-control fw-bold" required>
              </div>
              
              <div class="row mb-3 bg-light p-3 rounded-3 border">
                  <p class="fw-bold text-primary small mb-2"><i class="fa-solid fa-shield-halved"></i> منطقة التحكم الإداري</p>
                  <div class="col-6">
                      <label class="form-label text-muted small fw-bold">تغيير الصلاحية</label>
                      <select name="role" id="edit_role" class="form-select fw-bold text-primary" required>
                          <option value="trainer">مدرب</option>
                          <option value="admin">مدير</option>
                          <option value="staff">موظف</option>
                          <option value="trainee">متدرب</option>
                      </select>
                  </div>
                  <div class="col-6">
                      <label class="form-label text-muted small fw-bold">تغيير حالة الحساب</label>
                      <select name="status" id="edit_status" class="form-select fw-bold text-danger" required>
                          <option value="1">حساب نشط (مسموح بالدخول)</option>
                          <option value="0">حساب محظور (Banned)</option>
                      </select>
                  </div>
              </div>
              <small class="text-muted fw-bold d-block text-center"><i class="fa-solid fa-circle-info text-info"></i> حظر الحساب يمنع المستخدم من الدخول للمنصة نهائياً.</small>
          </div>
          <div class="modal-footer border-0 bg-light rounded-bottom-4">
              <button type="submit" name="edit_user" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm" style="background-color: #051024; border:none;">تحديث وحفظ البيانات</button>
          </div>
      </form>
    </div>
  </div>
</div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // الجافاسكربت الذكي لتمرير بيانات المستخدم للنافذة المنبثقة
    function openEditModal(id, name, role, status) { 
        document.getElementById('edit_id').value = id; 
        document.getElementById('edit_name').value = name; 
        document.getElementById('edit_role').value = role; 
        document.getElementById('edit_status').value = status; 
        new bootstrap.Modal(document.getElementById('editUserModal')).show(); 
    } 
    
    // تفعيل القائمة الجانبية للموبايل
    document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); }); 
    document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });
</script>
</body></html>