<?php
require_once '../includes/init.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainee') { header('Location: ../login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$success_msg = ''; $error_msg = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$user_id]); $user_data = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']); $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']); 
    $whatsapp_number = trim($_POST['whatsapp_number']); // 💡 استلام رقم الواتساب
    $country = trim($_POST['country']);
    $avatar = $user_data['avatar']; 

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])) {
            $new_avatar = time() . '_' . $user_id . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], '../uploads/avatars/' . $new_avatar)) {
                if ($avatar && $avatar != 'default_avatar.png' && file_exists('../uploads/avatars/' . $avatar)) { unlink('../uploads/avatars/' . $avatar); }
                $avatar = $new_avatar;
            }
        }
    }
    
    $full_name = $first_name . ' ' . $last_name;
    // 💡 تحديث قاعدة البيانات ليشمل حقل whatsapp_number
    $update = $pdo->prepare("UPDATE users SET full_name = ?, last_name = ?, phone = ?, whatsapp_number = ?, country = ?, avatar = ? WHERE id = ?");
    if ($update->execute([$full_name, $last_name, $phone, $whatsapp_number, $country, $avatar, $user_id])) {
        $_SESSION['user_name'] = $first_name; $success_msg = "تم تحديث البيانات بنجاح!";
        $stmt->execute([$user_id]); $user_data = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $cur = $_POST['current_password']; $new = $_POST['new_password']; $conf = $_POST['confirm_password'];
    if (password_verify($cur, $user_data['password'])) {
        if ($new === $conf) {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
            $success_msg = "تم تغيير كلمة المرور بنجاح!";
        } else { $error_msg = "الكلمات الجديدة غير متطابقة."; }
    } else { $error_msg = "كلمة المرور الحالية غير صحيحة."; }
}

require_once 'includes/header.php';
$name_parts = explode(' ', $user_data['full_name'], 2);
$f_name = $name_parts[0];
$l_name = $user_data['last_name'] ?? (isset($name_parts[1]) ? $name_parts[1] : '');
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3"><h3 class="fw-bold" style="color: #0b1c3f;"><i class="fa-solid fa-user-gear text-primary me-2"></i> إعدادات حسابي</h3></div>
<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><?php echo $success_msg; ?></div> <?php endif; ?>
<?php if($error_msg): ?> <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><?php echo $error_msg; ?></div> <?php endif; ?>

<div class="row g-4 mb-5">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-header bg-white py-3 border-0"><h5 class="m-0 fw-bold text-dark"><i class="fa-regular fa-id-card text-primary me-2"></i> البيانات الأساسية (تطبع في الشهادة)</h5></div>
            <div class="card-body p-4"><form method="POST" enctype="multipart/form-data">
                <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                    <?php $avatar_src = ($user_data['avatar'] && $user_data['avatar'] != 'default_avatar.png') ? '../uploads/avatars/' . $user_data['avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['full_name']) . '&background=1cc88a&color=fff&size=150'; ?>
                    <img src="<?php echo $avatar_src; ?>" class="rounded-circle shadow-sm me-4" style="width:100px; height:100px; object-fit:cover; border:4px solid #1cc88a;">
                    <div><h6 class="fw-bold mb-2">الصورة الشخصية</h6><input type="file" name="avatar" class="form-control form-control-sm" accept="image/jpeg, image/png"><small class="text-muted d-block mt-1">يُفضل رفع صورة واضحة لملفك.</small></div>
                </div>
                <div class="row mb-3"><div class="col-md-6"><label class="form-label text-muted small fw-bold">الاسم الأول <span class="text-danger">*</span></label><input type="text" name="first_name" class="form-control fw-bold" value="<?php echo htmlspecialchars($f_name); ?>" required></div><div class="col-md-6"><label class="form-label text-muted small fw-bold">اللقب <span class="text-danger">*</span></label><input type="text" name="last_name" class="form-control fw-bold" value="<?php echo htmlspecialchars($l_name); ?>" required></div></div>
                <div class="row mb-3"><div class="col-md-6"><label class="form-label text-muted small fw-bold">رقم الهاتف للاتصال</label><input type="tel" name="phone" class="form-control text-start fw-bold" dir="ltr" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"></div><div class="col-md-6"><label class="form-label text-muted small fw-bold">البلد</label><input type="text" name="country" class="form-control fw-bold" value="<?php echo htmlspecialchars($user_data['country'] ?? ''); ?>"></div></div>
                
                <!-- 💡 حقل الواتساب الجديد للمتدرب -->
                <div class="mb-4 bg-success bg-opacity-10 p-3 rounded-3 border border-success">
                    <label class="form-label text-success small fw-bold"><i class="fa-brands fa-whatsapp fs-5 me-1"></i> رقم الواتساب (للتنبيهات والروابط) <span class="text-danger">*</span></label>
                    <input type="tel" name="whatsapp_number" class="form-control text-start fw-bold border-success" dir="ltr" placeholder="+..." value="<?php echo htmlspecialchars($user_data['whatsapp_number'] ?? ''); ?>" required>
                    <small class="text-muted mt-2 d-block">سيتم إرسال روابط اجتماعات الزووم وفواتير الدفع إلى هذا الرقم.</small>
                </div>

                <div class="text-end"><button type="submit" name="update_profile" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" style="background:#0b1c3f;">تحديث البيانات</button></div>
            </form></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 border-left-danger"><div class="card-header bg-white py-3 border-0"><h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-shield-halved text-danger me-2"></i> تغيير كلمة المرور</h5></div>
            <div class="card-body p-4"><form method="POST"><input type="password" name="current_password" class="form-control mb-3 text-start" dir="ltr" placeholder="كلمة المرور الحالية" required><input type="password" name="new_password" class="form-control mb-3 text-start" dir="ltr" placeholder="الجديدة" required minlength="6"><input type="password" name="confirm_password" class="form-control mb-4 text-start" dir="ltr" placeholder="تأكيد الجديدة" required minlength="6"><button type="submit" name="update_password" class="btn btn-danger w-100 rounded-pill fw-bold shadow-sm">حفظ التغيير</button></form></div>
        </div>
    </div>
</div>
</div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script><script> document.getElementById("openSidebarBtn").addEventListener("click",()=>{document.getElementById("sidebar").classList.add("active");document.getElementById("sidebarOverlay").classList.add("active");}); document.getElementById("sidebarOverlay").addEventListener("click",()=>{document.getElementById("sidebar").classList.remove("active");document.getElementById("sidebarOverlay").classList.remove("active");});</script></body></html>