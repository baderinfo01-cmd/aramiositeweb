<?php
require_once '../includes/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'trainer') { header('Location: ../login.php'); exit(); }
$trainer_id = $_SESSION['user_id'];
$success_msg = '';
$whatsapp_btn = ''; // 💡 المتغير السحري لزر الواتساب

// 1. ✅ إصدار الشهادة المعتمدة (Certificate Issued) + إشعار الواتساب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['issue_certificate'])) {
    $exam_id = $_POST['exam_id'];
    $grade = $_POST['grade']; 
    
    $check = $pdo->prepare("
        SELECT e.trainee_id, u.full_name AS trainee_name, u.whatsapp_number, c.title_ar AS course_title 
        FROM certificate_exams e 
        JOIN users u ON e.trainee_id = u.id 
        JOIN courses c ON e.course_id = c.id 
        WHERE e.id = ? AND e.trainer_id = ? AND e.status = 'pending'
    ");
    $check->execute([$exam_id, $trainer_id]);
    $exam_data = $check->fetch();
    
    if ($exam_data) {
        $certificate_code = 'AR-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8)) . '-' . date('y');
        $issue_date = date('Y-m-d');
        
        $pdo->prepare("UPDATE certificate_exams SET status = 'passed', certificate_code = ?, issue_date = ?, grade = ? WHERE id = ?")->execute([$certificate_code, $issue_date, $grade, $exam_id]);
        $success_msg = "تم اعتماد المتدرب بتقدير ($grade)! كود الشهادة: <strong dir='ltr'>$certificate_code</strong>";

        // 💡 سحر الواتساب: رسالة التهنئة بالنجاح والشهادة
        if (!empty($exam_data['whatsapp_number'])) {
            $wa_num = preg_replace('/[^0-9]/', '', $exam_data['whatsapp_number']);
            $wa_msg = "ألف مبروك *" . $exam_data['trainee_name'] . "*! 🎓🎉\n\n";
            $wa_msg .= "لقد اجتزت دورة *" . $exam_data['course_title'] . "* بتقدير (*" . $grade . "*).\n\n";
            $wa_msg .= "شهادتك المعتمدة دولياً جاهزة الآن للتحميل والطباعة من لوحتك الشخصية في منصة أراميو.\n";
            $wa_msg .= "🔑 كود شهادتك المرجعي هو: *" . $certificate_code . "*\n\nفخورون بك وبإنجازك! 🏆";

            $wa_link = "https://wa.me/" . $wa_num . "?text=" . urlencode($wa_msg);
            $whatsapp_btn = "<a href='$wa_link' target='_blank' class='btn btn-success fw-bold rounded-pill px-4 mt-3 shadow-sm pulse-button'><i class='fa-brands fa-whatsapp fs-5 me-2'></i> أرسل التهنئة للمتدرب (واتساب)</a>";
        }
    }
}

if (isset($_GET['reject'])) {
    $pdo->prepare("UPDATE certificate_exams SET status = 'rejected' WHERE id = ? AND trainer_id = ?")->execute([$_GET['reject'], $trainer_id]);
    $success_msg = "تم رفض طلب الشهادة. يمكن للمتدرب إعادة المحاولة لاحقاً.";
}

$exams = $pdo->prepare("SELECT e.*, u.full_name AS trainee_name, c.title_ar AS course_title FROM certificate_exams e JOIN users u ON e.trainee_id = u.id JOIN courses c ON e.course_id = c.id WHERE e.trainer_id = ? ORDER BY e.created_at DESC");
$exams->execute([$trainer_id]);
$exams = $exams->fetchAll();

require_once 'includes/header.php';
?>

<style>
    @keyframes pulse { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); } 70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(37, 211, 102, 0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); } }
    .pulse-button { animation: pulse 2s infinite; background-color: #25d366 !important; border-color: #25d366 !important; color: white !important; }
</style>

<div class="d-flex justify-content-between mb-4"><h3 class="fw-bold"><i class="fa-solid fa-certificate text-warning"></i> طلبات اعتماد الشهادات</h3></div>

<?php if($success_msg): ?> 
    <div class="alert alert-success rounded-4 fw-bold shadow-sm border-0 text-center p-4 mb-4">
        <i class="fa-solid fa-check-circle fa-3x mb-3 text-success"></i><br>
        <span class="fs-5 text-dark"><?php echo $success_msg; ?></span><br>
        <?php echo $whatsapp_btn; ?> <!-- 💡 زر الواتساب יظهر هنا -->
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4"><div class="table-responsive"><table class="table text-center align-middle mb-0"><thead class="table-light small"><tr><th class="ps-4 text-start">المتدرب / الدورة</th><th>موعد الاختبار</th><th>الحالة / الكود</th><th>الإجراء</th></tr></thead><tbody>
    <?php foreach($exams as $ex): ?><tr>
        <td class="fw-bold text-start ps-4"><i class="fa-solid fa-user-graduate text-primary me-1"></i> <?php echo htmlspecialchars($ex['trainee_name']); ?><br><span class="badge bg-light text-secondary border mt-1"><?php echo htmlspecialchars($ex['course_title']); ?></span></td>
        <td class="fw-bold text-primary"><?php echo date('Y-m-d', strtotime($ex['exam_date'])); ?></td>
        <td>
            <?php if($ex['status'] == 'pending') echo '<span class="badge bg-warning text-dark">بانتظار الاختبار</span>';
            elseif($ex['status'] == 'rejected') echo '<span class="badge bg-danger">رسب</span>';
            elseif($ex['status'] == 'passed') echo '<span class="badge bg-success mb-1">تم الاعتماد ('.$ex['grade'].')</span><br><span class="badge bg-dark fw-normal border" dir="ltr">'.$ex['certificate_code'].'</span>'; ?>
        </td>
        <td>
            <?php if($ex['status'] == 'pending'): ?>
                <button class="btn btn-sm btn-success fw-bold rounded-pill px-3 shadow-sm mb-1" onclick="openCertModal(<?php echo $ex['id']; ?>, '<?php echo addslashes($ex['trainee_name']); ?>')">اعتماد ونجاح</button>
                <a href="?reject=<?php echo $ex['id']; ?>" class="btn btn-sm btn-outline-danger rounded-circle" onclick="return confirm('تأكيد الرسوب؟')"><i class="fa-solid fa-xmark"></i></a>
            <?php else: ?><span class="text-muted small opacity-25">مقفل</span><?php endif; ?>
        </td>
    </tr><?php endforeach; ?>
</tbody></table></div></div>

<!-- Modal إصدار الشهادة -->
<div class="modal fade" id="certModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4">
    <div class="modal-header bg-success text-white border-0"><h5 class="fw-bold m-0">اعتماد المتدرب وإصدار الشهادة</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body p-4 text-center">
        <h5 class="fw-bold text-dark mb-4">المتدرب: <span id="modalTraineeName" class="text-primary"></span></h5>
        <div class="bg-light p-3 rounded-3 border mb-3 text-start">
            <label class="fw-bold text-muted small mb-2"><i class="fa-solid fa-star text-warning"></i> حدد الدرجة (التقدير) التي حصل عليها:</label>
            <select name="grade" class="form-select fw-bold text-success" required>
                <option value="ممتاز">ممتاز (Excellent)</option>
                <option value="جيد جداً">جيد جداً (Very Good)</option>
                <option value="جيد">جيد (Good)</option>
                <option value="مقبول">مقبول (Acceptable)</option>
            </select>
        </div>
        <input type="hidden" name="exam_id" id="modalExamId">
    </div><div class="modal-footer border-0"><button type="submit" name="issue_certificate" class="btn btn-success w-100 rounded-pill fw-bold">إصدار الشهادة النهائية</button></div></form>
</div></div></div>

</div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); });
    function openCertModal(id, name) { document.getElementById('modalExamId').value = id; document.getElementById('modalTraineeName').innerText = name; new bootstrap.Modal(document.getElementById('certModal')).show(); }
</script></body></html>