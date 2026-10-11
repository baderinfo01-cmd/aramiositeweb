<?php
require_once 'admin_init.php';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_test'])) {
    $pdo->prepare("INSERT INTO testimonials (student_name, course_name, feedback_text, rating) VALUES (?, ?, ?, ?)")->execute([$_POST['name'], $_POST['course'], $_POST['feedback'], $_POST['rating']]);
    $success_msg = "تم إضافة الرأي بنجاح!";
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$_GET['delete']]);
    $success_msg = "تم الحذف.";
}

$tests = $pdo->query("SELECT * FROM testimonials ORDER BY id DESC")->fetchAll();
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-gray-800"><i class="fa-solid fa-comments text-primary"></i> إدارة آراء المتدربين</h3>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTestModal"><i class="fa-solid fa-plus"></i> إضافة رأي</button>
</div>

<?php if($success_msg): ?> <div class="alert alert-success"><?php echo $success_msg; ?></div> <?php endif; ?>

<div class="row">
    <?php foreach($tests as $test): ?>
    <div class="col-md-4 mb-4">
        <div class="card shadow h-100 border-left-primary">
            <div class="card-body">
                <div class="text-warning mb-2"><?php for($i=1; $i<=$test['rating']; $i++) echo '<i class="fa-solid fa-star"></i>'; ?></div>
                <p class="fst-italic text-muted">"<?php echo htmlspecialchars($test['feedback_text']); ?>"</p>
                <hr>
                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($test['student_name']); ?></h6>
                <small class="text-muted"><?php echo htmlspecialchars($test['course_name']); ?></small>
            </div>
            <div class="card-footer bg-white border-top-0 text-center">
                <a href="?delete=<?php echo $test['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف؟')"><i class="fa-solid fa-trash"></i> حذف</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal الإضافة (نفس الفكرة السابقة، يحتوي على حقول الاسم، الدورة، النص، والتقييم من 1-5) -->
<!-- سأختصره هنا لتوفير المساحة، يمكنك بناءه بسهولة بناء على الـ modal السابق -->
<div class="modal fade" id="addTestModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-body"><input type="text" name="name" class="form-control mb-2" placeholder="اسم المتدرب" required><input type="text" name="course" class="form-control mb-2" placeholder="اسم الدورة" required><textarea name="feedback" class="form-control mb-2" placeholder="نص الرأي" required></textarea><input type="number" name="rating" class="form-control mb-2" min="1" max="5" placeholder="التقييم (1-5)" required></div><div class="modal-footer"><button type="submit" name="add_test" class="btn btn-primary">حفظ</button></div></form></div></div></div>

<?php require_once 'includes/footer.php'; ?>