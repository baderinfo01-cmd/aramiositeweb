<?php
require_once 'admin_init.php';

$success_msg = '';

// إضافة إحصائية جديدة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_stat'])) {
    $title = $_POST['title'];
    $count = $_POST['count'];
    $icon = $_POST['icon']; // مثل fa-users
    
    $pdo->prepare("INSERT INTO statistics (title_ar, count_number, icon) VALUES (?, ?, ?)")->execute([$title, $count, $icon]);
    $success_msg = "تم إضافة الإحصائية بنجاح!";
}

// حذف إحصائية
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM statistics WHERE id = ?")->execute([$_GET['delete']]);
    $success_msg = "تم حذف الإحصائية.";
}

// جلب الإحصائيات
$stats = $pdo->query("SELECT * FROM statistics ORDER BY id ASC")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-gray-800"><i class="fa-solid fa-chart-pie text-primary"></i> إدارة الإحصائيات</h3>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStatModal"><i class="fa-solid fa-plus"></i> إضافة إحصائية</button>
</div>

<?php if($success_msg): ?> <div class="alert alert-success"><?php echo $success_msg; ?></div> <?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-body">
        <table class="table table-bordered text-center">
            <thead class="table-dark">
                <tr>
                    <th>الأيقونة</th>
                    <th>العنوان (مثال: طلاب مسجلين)</th>
                    <th>الرقم</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($stats as $stat): ?>
                <tr>
                    <td><i class="fa-solid <?php echo $stat['icon']; ?> fs-3 text-primary"></i> <br><small class="text-muted"><?php echo $stat['icon']; ?></small></td>
                    <td class="align-middle fw-bold"><?php echo htmlspecialchars($stat['title_ar']); ?></td>
                    <td class="align-middle fs-4 text-success fw-bold"><?php echo number_format($stat['count_number']); ?></td>
                    <td class="align-middle">
                        <a href="?delete=<?php echo $stat['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من الحذف؟')"><i class="fa-solid fa-trash"></i> حذف</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal الإضافة -->
<div class="modal fade" id="addStatModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">إضافة إحصائية جديدة</h5></div>
      <form method="POST">
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label">عنوان الإحصائية</label>
                  <input type="text" name="title" class="form-control" required placeholder="مثال: فصول دراسية مكتملة">
              </div>
              <div class="mb-3">
                  <label class="form-label">الرقم (بدون فواصل)</label>
                  <input type="number" name="count" class="form-control" required placeholder="مثال: 1500">
              </div>
              <div class="mb-3">
                  <label class="form-label">كود الأيقونة (من FontAwesome)</label>
                  <input type="text" name="icon" class="form-control" required dir="ltr" placeholder="مثال: fa-bell">
                  <small><a href="https://fontawesome.com/search?o=r&m=free" target="_blank">ابحث عن الأيقونات هنا</a> وابدأ الكود بـ fa-</small>
              </div>
          </div>
          <div class="modal-footer">
              <button type="submit" name="add_stat" class="btn btn-primary">حفظ الإحصائية</button>
          </div>
      </form>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>