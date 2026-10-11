<?php
require_once 'admin_init.php';
$success_msg = ''; $error_msg = '';

// 1. إضافة شهادة قديمة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_legacy_cert'])) {
    $code = trim($_POST['certificate_code']);
    $name = trim($_POST['student_name']);
    $nat = trim($_POST['nationality']);
    $spec = trim($_POST['specialty']);
    $grade = trim($_POST['grade']);
    $date = trim($_POST['issue_date']);

    // التحقق من عدم تكرار الكود
    $check = $pdo->prepare("SELECT id FROM legacy_certificates WHERE certificate_code = ?");
    $check->execute([$code]);
    if ($check->rowCount() > 0) {
        $error_msg = "رقم الشهادة هذا موجود مسبقاً!";
    } else {
        $insert = $pdo->prepare("INSERT INTO legacy_certificates (certificate_code, student_name, nationality, specialty, grade, issue_date) VALUES (?, ?, ?, ?, ?, ?)");
        if ($insert->execute([$code, $name, $nat, $spec, $grade, $date])) {
            $success_msg = "تم إدراج الشهادة بنجاح! يمكن التحقق منها الآن في الواجهة.";
        }
    }
}

// 2. تعديل شهادة قديمة (الكود الجديد)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_legacy_cert'])) {
    $id = $_POST['cert_id']; // الـ ID المخفي
    $code = trim($_POST['certificate_code']);
    $name = trim($_POST['student_name']);
    $nat = trim($_POST['nationality']);
    $spec = trim($_POST['specialty']);
    $grade = trim($_POST['grade']);
    $date = trim($_POST['issue_date']);

    // التحقق من أن رقم الشهادة الجديد غير مستخدم من طرف شخص آخر
    $check = $pdo->prepare("SELECT id FROM legacy_certificates WHERE certificate_code = ? AND id != ?");
    $check->execute([$code, $id]);
    if ($check->rowCount() > 0) {
        $error_msg = "لا يمكن التعديل: رقم الشهادة هذا مستخدم لمتدرب آخر!";
    } else {
        $update = $pdo->prepare("UPDATE legacy_certificates SET certificate_code=?, student_name=?, nationality=?, specialty=?, grade=?, issue_date=? WHERE id=?");
        if ($update->execute([$code, $name, $nat, $spec, $grade, $date, $id])) {
            $success_msg = "تم تحديث بيانات الشهادة بنجاح!";
        }
    }
}

// 3. حذف شهادة
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM legacy_certificates WHERE id = ?")->execute([$_GET['delete']]);
    $success_msg = "تم حذف الشهادة.";
}

// جلب الشهادات القديمة
$legacy_certs = $pdo->query("SELECT * FROM legacy_certificates ORDER BY issue_date DESC")->fetchAll();
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark"><i class="fa-solid fa-file-signature text-warning me-2"></i> أرشيف الشهادات القديمة (الورقية)</h3>
    <button class="btn btn-warning text-dark rounded-pill fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCertModal">
        <i class="fa-solid fa-plus me-2"></i> إدراج شهادة قديمة
    </button>
</div>

<?php if($success_msg): ?> <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> <?php endif; ?>
<?php if($error_msg): ?> <div class="alert alert-danger rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div> <?php endif; ?>

<div class="alert alert-info fw-bold shadow-sm rounded-4 border-0 p-4 mb-4 lh-lg">
    <i class="fa-solid fa-circle-info fa-2x text-info mb-2"></i><br>
    هنا يتم إدخال بيانات الطلاب الذين تخرجوا وحصلوا على شهادات ورقية <strong>قبل إنشاء هذه المنصة</strong>، لكي يتمكنوا من التحقق من صحة شهاداتهم عبر الموقع برقم الشهادة أو الـ QR Code.
</div>

<div class="admin-card card p-0 overflow-hidden mb-5">
    <div class="table-responsive">
        <table class="table table-hover align-middle text-center mb-0">
            <thead class="table-light text-muted small fw-bold">
                <tr>
                    <th class="py-3 text-start ps-4">اسم المتدرب / الجنسية</th>
                    <th class="py-3">التخصص (الدورة)</th>
                    <th class="py-3">رقم الشهادة (ID)</th>
                    <th class="py-3">تاريخ الإصدار</th>
                    <th class="py-3">الدرجة</th>
                    <th class="py-3">إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($legacy_certs)): ?>
                    <tr><td colspan="6" class="py-5 text-muted fw-bold">لا يوجد أرشيف شهادات حتى الآن.</td></tr>
                <?php else: ?>
                    <?php foreach($legacy_certs as $cert): ?>
                    <tr>
                        <td class="text-start ps-4 fw-bold text-dark">
                            <i class="fa-solid fa-user-graduate text-primary opacity-50 me-1"></i> <?php echo htmlspecialchars($cert['student_name']); ?><br>
                            <small class="text-muted"><i class="fa-solid fa-earth-americas"></i> <?php echo htmlspecialchars($cert['nationality']); ?></small>
                        </td>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($cert['specialty']); ?></td>
                        <td><span class="badge bg-dark fw-bold font-monospace fs-6 px-3 py-2 rounded-pill shadow-sm"><i class="fa-solid fa-qrcode text-warning me-1"></i> <?php echo htmlspecialchars($cert['certificate_code']); ?></span></td>
                        <td class="text-muted fw-bold"><?php echo date('Y-m-d', strtotime($cert['issue_date'])); ?></td>
                        <td class="text-success fw-bold"><?php echo htmlspecialchars($cert['grade']); ?></td>
                        <td>
                            <!-- زر التعديل الجديد (يحتوي على البيانات مخفية) -->
                            <button type="button" class="btn btn-sm btn-primary rounded-circle me-1 btn-edit"
                                data-id="<?php echo $cert['id']; ?>"
                                data-code="<?php echo htmlspecialchars($cert['certificate_code']); ?>"
                                data-name="<?php echo htmlspecialchars($cert['student_name']); ?>"
                                data-nat="<?php echo htmlspecialchars($cert['nationality']); ?>"
                                data-spec="<?php echo htmlspecialchars($cert['specialty']); ?>"
                                data-grade="<?php echo htmlspecialchars($cert['grade']); ?>"
                                data-date="<?php echo $cert['issue_date']; ?>">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <!-- زر الحذف -->
                            <a href="?delete=<?php echo $cert['id']; ?>" class="btn btn-sm btn-danger rounded-circle" onclick="return confirm('حذف نهائي؟')"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: إدراج شهادة (إضافة) -->
<div class="modal fade" id="addCertModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4">
    <div class="modal-header bg-warning text-dark border-0 rounded-top-4"><h5 class="modal-title fw-bold"><i class="fa-solid fa-file-signature"></i> إدراج بيانات شهادة قديمة</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body p-4">
        <div class="row mb-3">
            <div class="col-12 mb-3"><label class="form-label text-muted small fw-bold">رقم الشهادة (Certificate ID) <span class="text-danger">*</span></label><input type="text" name="certificate_code" class="form-control fw-bold border-warning text-center fs-5" dir="ltr" placeholder="مثال: IPD.310126" required></div>
            <div class="col-md-6 mb-3"><label class="form-label text-muted small fw-bold">اسم المتدرب <span class="text-danger">*</span></label><input type="text" name="student_name" class="form-control fw-bold" required></div>
            <div class="col-md-6 mb-3"><label class="form-label text-muted small fw-bold">الجنسية</label><input type="text" name="nationality" class="form-control fw-bold"></div>
            <div class="col-md-12 mb-3"><label class="form-label text-muted small fw-bold">التخصص (اسم الدورة أو الدبلوم) <span class="text-danger">*</span></label><input type="text" name="specialty" class="form-control fw-bold" placeholder="مثال: الطب البديل" required></div>
            <div class="col-md-6 mb-3"><label class="form-label text-muted small fw-bold">الدرجة (التقدير) <span class="text-danger">*</span></label><input type="text" name="grade" class="form-control fw-bold text-success" placeholder="مثال: ممتاز" required></div>
            <div class="col-md-6 mb-3"><label class="form-label text-muted small fw-bold">تاريخ الإصدار <span class="text-danger">*</span></label><input type="date" name="issue_date" class="form-control fw-bold" required></div>
        </div>
    </div><div class="modal-footer border-0 bg-light rounded-bottom-4"><button type="submit" name="add_legacy_cert" class="btn btn-warning w-100 rounded-pill fw-bold shadow-sm text-dark">حفظ وإدراج في قاعدة البيانات</button></div></form>
</div></div></div>

<!-- Modal 2: تعديل شهادة (النافذة الجديدة) -->
<div class="modal fade" id="editCertModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4">
    <div class="modal-header bg-primary text-white border-0 rounded-top-4"><h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square"></i> تعديل بيانات الشهادة</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body p-4">
        <!-- حقل مخفي لتخزين الـ ID الخاص بالشهادة التي نعدلها -->
        <input type="hidden" name="cert_id" id="edit_cert_id">
        <div class="row mb-3">
            <div class="col-12 mb-3"><label class="form-label text-muted small fw-bold">رقم الشهادة (Certificate ID) <span class="text-danger">*</span></label><input type="text" name="certificate_code" id="edit_code" class="form-control fw-bold border-primary text-center fs-5" dir="ltr" required></div>
            <div class="col-md-6 mb-3"><label class="form-label text-muted small fw-bold">اسم المتدرب <span class="text-danger">*</span></label><input type="text" name="student_name" id="edit_name" class="form-control fw-bold" required></div>
            <div class="col-md-6 mb-3"><label class="form-label text-muted small fw-bold">الجنسية</label><input type="text" name="nationality" id="edit_nat" class="form-control fw-bold"></div>
            <div class="col-md-12 mb-3"><label class="form-label text-muted small fw-bold">التخصص (اسم الدورة أو الدبلوم) <span class="text-danger">*</span></label><input type="text" name="specialty" id="edit_spec" class="form-control fw-bold" required></div>
            <div class="col-md-6 mb-3"><label class="form-label text-muted small fw-bold">الدرجة (التقدير) <span class="text-danger">*</span></label><input type="text" name="grade" id="edit_grade" class="form-control fw-bold text-success" required></div>
            <div class="col-md-6 mb-3"><label class="form-label text-muted small fw-bold">تاريخ الإصدار <span class="text-danger">*</span></label><input type="date" name="issue_date" id="edit_date" class="form-control fw-bold" required></div>
        </div>
    </div><div class="modal-footer border-0 bg-light rounded-bottom-4"><button type="submit" name="edit_legacy_cert" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">حفظ التعديلات</button></div></form>
</div></div></div>

</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // كود القائمة الجانبية (موجود مسبقاً)
    document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); }); 
    document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });

    // 💡 السكربت الخاص بجلب البيانات وفتح نافذة التعديل
    document.addEventListener("DOMContentLoaded", function() {
        const editButtons = document.querySelectorAll('.btn-edit');
        
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                // جلب البيانات من الزر الذي تم الضغط عليه
                const id = this.getAttribute('data-id');
                const code = this.getAttribute('data-code');
                const name = this.getAttribute('data-name');
                const nat = this.getAttribute('data-nat');
                const spec = this.getAttribute('data-spec');
                const grade = this.getAttribute('data-grade');
                const date = this.getAttribute('data-date');

                // وضع البيانات داخل حقول الاستمارة في نافذة التعديل
                document.getElementById('edit_cert_id').value = id;
                document.getElementById('edit_code').value = code;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_nat').value = nat;
                document.getElementById('edit_spec').value = spec;
                document.getElementById('edit_grade').value = grade;
                document.getElementById('edit_date').value = date;

                // إظهار نافذة التعديل (Modal)
                var editModal = new bootstrap.Modal(document.getElementById('editCertModal'));
                editModal.show();
            });
        });
    });
</script>
</body></html>