<?php
require_once '../includes/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainer') {
    header('Location: ../login.php');
    exit();
}

$trainer_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$trainer_id]);
$user_data = $stmt->fetch();

// 1. معالجة تحديث البيانات الشخصية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']); 
    $phone = trim($_POST['phone']);
    $whatsapp_number = trim($_POST['whatsapp_number']); // 💡 استلام رقم الواتساب
    $country = trim($_POST['country']);
    $bio = trim($_POST['bio']);
    $avatar = $user_data['avatar']; 

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), $allowed)) {
            $new_avatar = time() . '_' . $trainer_id . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], '../uploads/avatars/' . $new_avatar)) {
                if ($avatar && $avatar != 'default_avatar.png' && file_exists('../uploads/avatars/' . $avatar)) {
                    unlink('../uploads/avatars/' . $avatar);
                }
                $avatar = $new_avatar;
            }
        }
    }

    // 💡 تحديث قاعدة البيانات ليشمل حقل whatsapp_number
    $update = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, whatsapp_number = ?, country = ?, bio = ?, avatar = ? WHERE id = ?");
    if ($update->execute([$full_name, $phone, $whatsapp_number, $country, $bio, $avatar, $trainer_id])) {
        $_SESSION['user_name'] = $full_name;
        $success_msg = "تم تحديث ملفك وسيرتك الذاتية ورقم الواتساب بنجاح!";
        $stmt->execute([$trainer_id]);
        $user_data = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password']; $new_password = $_POST['new_password']; $confirm_password = $_POST['confirm_password'];
    if (password_verify($current_password, $user_data['password'])) {
        if ($new_password === $confirm_password) {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($new_password, PASSWORD_DEFAULT), $trainer_id]);
            $success_msg = "تم تغيير كلمة المرور بنجاح!";
        } else { $error_msg = "كلمات المرور الجديدة غير متطابقة!"; }
    } else { $error_msg = "كلمة المرور الحالية غير صحيحة!"; }
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h3 class="fw-bold" style="color: #0b1c3f;"><i class="fa-solid fa-user-gear text-warning me-2"></i> إعدادات حساب المدرب</h3>
</div>

<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>
<?php if($error_msg): ?> <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div> <?php endif; ?>

<div class="row g-4 mb-5">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-0"><h5 class="m-0 fw-bold text-dark"><i class="fa-regular fa-id-badge text-primary me-2"></i> الهوية والسيرة الذاتية (تظهر للطلاب)</h5></div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                        <?php $avatar_src = ($user_data['avatar'] && $user_data['avatar'] != 'default_avatar.png') ? '../uploads/avatars/' . $user_data['avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['full_name']) . '&background=f6c23e&color=fff&size=150'; ?>
                        <img src="<?php echo $avatar_src; ?>" class="rounded-circle shadow-sm me-4" style="width: 100px; height: 100px; object-fit: cover; border: 4px solid #f6c23e;" alt="Trainer Avatar">
                        <div><h6 class="fw-bold mb-2">صورة العرض</h6><input type="file" name="avatar" class="form-control form-control-sm" accept="image/jpeg, image/png"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">الاسم الكامل <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control fw-bold" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6"><label class="form-label text-muted small fw-bold">رقم الهاتف (للاتصال)</label><input type="tel" name="phone" class="form-control text-start fw-bold" dir="ltr" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"></div>
                        <div class="col-md-6"><label class="form-label text-muted small fw-bold">البلد (يظهر العلم للطلاب)</label>
                            <select name="country" class="form-select fw-bold">
                                <option value="<?php echo htmlspecialchars($user_data['country'] ?? ''); ?>"><?php echo htmlspecialchars($user_data['country'] ?? 'اختر بلدك...'); ?></option>
                                <option value="السعودية">السعودية</option><option value="الجزائر">الجزائر</option><option value="مصر">مصر</option><option value="الإمارات">الإمارات</option><option value="المغرب">المغرب</option><option value="تونس">تونس</option><option value="قطر">قطر</option><option value="أخرى">أخرى</option>
                            </select>
                        </div>
                    </div>

                    <!-- 💡 حقل الواتساب الجديد للمدرب -->
                    <div class="mb-4 bg-success bg-opacity-10 p-3 rounded-3 border border-success">
                        <label class="form-label text-success small fw-bold"><i class="fa-brands fa-whatsapp fs-5 me-1"></i> رقم الواتساب الخاص بك <span class="text-danger">*</span></label>
                        <input type="tel" name="whatsapp_number" class="form-control text-start fw-bold border-success" dir="ltr" placeholder="+..." value="<?php echo htmlspecialchars($user_data['whatsapp_number'] ?? ''); ?>" required>
                        <small class="text-muted mt-2 d-block">سترسل لك إدارة الأكاديمية التنبيهات وإشعارات تحويل الأرباح إلى هذا الرقم.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">السيرة الذاتية المهنية (Bio) <span class="text-danger">*</span></label>
                        <textarea name="bio" class="form-control fw-bold" rows="5" placeholder="اكتب نبذة احترافية عن خبراتك وشهاداتك لزيادة ثقة المتدربين بك..." required><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="text-end"><button type="submit" name="update_profile" class="btn btn-warning rounded-pill px-5 fw-bold text-dark shadow-sm">تحديث بياناتي</button></div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- بطاقة الأمان وتغيير كلمة المرور -->
    <div class="col-lg-4">
        <!-- ...نفس كود تغيير الباسورد المعتاد... -->
        <div class="card border-0 shadow-sm rounded-4 h-100 border-left-danger"><div class="card-header bg-white py-3 border-0"><h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-shield-halved text-danger me-2"></i> تغيير كلمة المرور</h5></div><div class="card-body p-4"><form method="POST"><input type="password" name="current_password" class="form-control mb-3 text-start" dir="ltr" placeholder="كلمة المرور الحالية" required><input type="password" name="new_password" class="form-control mb-3 text-start" dir="ltr" placeholder="الجديدة" required minlength="6"><input type="password" name="confirm_password" class="form-control mb-4 text-start" dir="ltr" placeholder="تأكيد الجديدة" required minlength="6"><button type="submit" name="update_password" class="btn btn-danger w-100 rounded-pill fw-bold shadow-sm">حفظ التغيير</button></form></div></div>
    </div>
</div>
</div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script><script>document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); }); document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });</script></body></html>