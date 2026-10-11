<?php
require_once 'admin_init.php';
$success_msg = '';

// 1. تحديث حالة الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $id = $_POST['lead_id'];
    $status = $_POST['status'];
    $pdo->prepare("UPDATE live_events_leads SET status = ? WHERE id = ?")->execute([$status, $id]);
    $success_msg = "تم تحديث حالة المسجل بنجاح.";
}

// 2. حذف الطلب
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM live_events_leads WHERE id = ?")->execute([$_GET['delete']]);
    $success_msg = "تم حذف الطلب نهائياً.";
}

// 3. نظام الفلترة الذكي (جلب البيانات حسب المسوق)
$current_filter = isset($_GET['marketer']) ? $_GET['marketer'] : 'all';

if ($current_filter === 'yassine') {
    $stmt = $pdo->prepare("SELECT * FROM live_events_leads WHERE marketer_code = 'yassine' ORDER BY created_at DESC");
} elseif ($current_filter === 'sabira') {
    $stmt = $pdo->prepare("SELECT * FROM live_events_leads WHERE marketer_code = 'sabira' ORDER BY created_at DESC");
} else {
    // جلب الكل
    $stmt = $pdo->prepare("SELECT * FROM live_events_leads ORDER BY created_at DESC");
}
$stmt->execute();
$leads = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<style>
/* --- تنسيقات خاصة لتحويل الجدول إلى بطاقات على الهاتف --- */
@media (max-width: 768px) {
    .responsive-table, .responsive-table tbody, .responsive-table tr, .responsive-table td {
        display: block;
        width: 100%;
    }
    .responsive-table thead {
        display: none; /* إخفاء رأس الجدول في الهاتف */
    }
    .responsive-table tr {
        margin-bottom: 1rem;
        background-color: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        padding: 10px;
    }
    .responsive-table td {
        text-align: right !important;
        border-bottom: 1px solid #f1f1f1 !important;
        padding: 12px 10px !important;
        position: relative;
        display: flex;
        flex-direction: column; /* ترتيب العناصر عمودياً */
        align-items: flex-start;
        gap: 5px;
    }
    .responsive-table td:last-child {
        border-bottom: none !important;
        align-items: stretch; /* جعل أزرار الإجراءات تمتد لكامل العرض */
    }
    /* إنشاء عناوين للخلايا في الهاتف عبر السمة data-label */
    .responsive-table td::before {
        content: attr(data-label);
        font-weight: bold;
        color: #0b1c3f;
        font-size: 0.85rem;
        margin-bottom: 3px;
        opacity: 0.7;
    }
    
    /* تعديلات على الأزرار والنصوص في الهاتف */
    .action-form { width: 100%; }
    .action-form select, .action-form button { width: 100% !important; margin-bottom: 5px; }
    .filter-section { flex-direction: column !important; align-items: stretch !important; }
    .filter-section a { width: 100%; text-align: center; justify-content: center; display: flex; }
}
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
    <h3 class="fw-bold text-dark text-center text-md-start m-0"><i class="fa-solid fa-plane-departure text-warning me-2"></i> مسجلو ملتقى ماليزيا 🇲🇾</h3>
</div>

<?php if($success_msg): ?> 
    <div class="alert alert-success rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?></div> 
<?php endif; ?>

<!-- 💡 أزرار الفلترة (تم جعلها متجاوبة) -->
<div class="bg-white p-3 rounded-4 shadow-sm mb-4 filter-section d-flex align-items-center gap-3 border-start border-4 border-primary">
    <span class="fw-bold text-muted text-center text-md-start"><i class="fa-solid fa-filter me-1"></i> تصفية:</span>
    
    <a href="?marketer=all" class="btn rounded-pill px-4 fw-bold <?php echo ($current_filter == 'all') ? 'btn-primary' : 'btn-outline-primary'; ?>">
        الجميع <span class="badge bg-light text-primary ms-1"><?php echo ($current_filter == 'all') ? count($leads) : ''; ?></span>
    </a>
    
    <a href="?marketer=yassine" class="btn rounded-pill px-4 fw-bold <?php echo ($current_filter == 'yassine') ? 'btn-primary' : 'btn-outline-primary'; ?>">
        👨‍💼 ياسين <span class="badge bg-light text-primary ms-1"><?php echo ($current_filter == 'yassine') ? count($leads) : ''; ?></span>
    </a>
    
    <a href="?marketer=sabira" class="btn rounded-pill px-4 fw-bold <?php echo ($current_filter == 'sabira') ? 'btn-primary' : 'btn-outline-primary'; ?>">
        👩‍💼 صبيرة <span class="badge bg-light text-primary ms-1"><?php echo ($current_filter == 'sabira') ? count($leads) : ''; ?></span>
    </a>
</div>

<div class="admin-card card p-0 overflow-hidden mb-5 border-0 shadow-sm bg-transparent bg-md-white">
    <div class="table-responsive border-0">
        <table class="table table-hover align-middle text-center mb-0 responsive-table">
            <thead class="text-white small fw-bold d-none d-md-table-header-group" style="background-color: #0b1c3f;">
                <tr>
                    <th class="py-3 ps-4 text-start">الاسم ومصدر التسجيل</th>
                    <th class="py-3">الدولة</th>
                    <th class="py-3">تاريخ الميلاد</th>
                    <th class="py-3">الهاتف / الواتساب</th>
                    <th class="py-3">التخصص / نوع المشاركة</th>
                    <th class="py-3">الحالة والإجراء</th>
                </tr>
            </thead>
            <tbody style="background-color: white;">
                <?php if(empty($leads)): ?>
                    <tr><td colspan="6" class="py-5 text-muted fw-bold fs-5 text-center">لا يوجد مسجلين في هذه القائمة حتى الآن.</td></tr>
                <?php else: ?>
                    <?php foreach($leads as $lead): 
                        $clean_whatsapp = preg_replace('/[^0-9]/', '', $lead['whatsapp']);
                        $country_color = (strpos($lead['country'], 'الجزائر') !== false) ? 'text-success' : 'text-danger';
                        
                        // تحديد شكل بادج المسوق
                        if($lead['marketer_code'] == 'yassine') {
                            $source_badge = '<span class="badge bg-info text-dark mt-2"><i class="fa-solid fa-link"></i> رابط ياسين</span>';
                        } elseif ($lead['marketer_code'] == 'sabira') {
                            $source_badge = '<span class="badge bg-warning text-dark mt-2"><i class="fa-solid fa-link"></i> رابط صبيرة</span>';
                        } else {
                            $source_badge = '<span class="badge bg-secondary mt-2"><i class="fa-solid fa-globe"></i> زيارة مباشرة</span>';
                        }
                    ?>
                    <tr class="<?php echo ($lead['status'] == 'new') ? 'table-warning bg-opacity-25' : ''; ?>">
                        
                        <td data-label="الاسم ومصدر التسجيل" class="text-start ps-md-4 fw-bold w-100 w-md-auto">
                            <div><i class="fa-solid fa-user-tie text-primary opacity-50 me-1"></i> <span class="fs-6"><?php echo htmlspecialchars($lead['full_name_ar']); ?></span></div>
                            <?php echo $source_badge; ?>
                        </td>

                        <td data-label="الدولة">
                            <span class="badge bg-light border fw-bold <?php echo $country_color; ?> px-3 py-2 fs-6">
                                <i class="fa-solid fa-earth-africa"></i> <?php echo htmlspecialchars($lead['country'] ?? '-'); ?>
                            </span>
                        </td>
                        
                        <td data-label="تاريخ الميلاد" class="text-muted fw-bold">
                            <?php echo htmlspecialchars($lead['dob'] ?? '-'); ?>
                        </td>

                        <td data-label="الهاتف / الواتساب" class="fw-bold" dir="ltr">
                            <span class="text-secondary d-inline-block d-md-block mb-2 fs-6"><i class="fa-solid fa-phone ms-1"></i> <?php echo htmlspecialchars($lead['phone']); ?></span>
                            <a href="https://wa.me/<?php echo $clean_whatsapp; ?>" target="_blank" class="badge bg-success text-decoration-none px-3 py-2 rounded-pill shadow-sm" style="transition: 0.2s;">
                                <i class="fa-brands fa-whatsapp fs-5 align-middle"></i> مراسلة
                            </a>
                        </td>
                        
                        <td data-label="التخصص / نوع المشاركة" class="text-muted small fw-bold lh-base" style="max-width: 100%; width: max-content;">
                            <?php echo htmlspecialchars($lead['research_title']); ?>
                        </td>
                        
                        <td data-label="الحالة والإجراء">
                            <form method="POST" class="d-flex flex-column gap-2 align-items-center mb-1 action-form">
                                <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                                <select name="status" class="form-select form-select-sm fw-bold <?php echo ($lead['status'] == 'new') ? 'border-warning text-dark' : (($lead['status'] == 'paid') ? 'border-success text-success bg-success bg-opacity-10' : ''); ?>" style="width: 140px; cursor:pointer;">
                                    <option value="new" <?php echo ($lead['status'] == 'new') ? 'selected' : ''; ?>>🆕 طلب جديد</option>
                                    <option value="contacted" <?php echo ($lead['status'] == 'contacted') ? 'selected' : ''; ?>>📞 تم الاتصال</option>
                                    <option value="paid" <?php echo ($lead['status'] == 'paid') ? 'selected' : ''; ?>>💸 دفع مؤكد</option>
                                    <option value="canceled" <?php echo ($lead['status'] == 'canceled') ? 'selected' : ''; ?>>❌ ملغي</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm" style="background-color: #0b1c3f; border:none;">تحديث</button>
                            </form>
                            <a href="?delete=<?php echo $lead['id']; ?>" class="btn btn-sm btn-outline-danger fw-bold w-100 w-md-auto mt-1 text-decoration-none" onclick="return confirm('هل أنت متأكد من الحذف؟')">
                                <i class="fa-solid fa-trash ms-1"></i> حذف
                            </a>
                        </td>
                        
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div> 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById("openSidebarBtn").addEventListener("click", () => { document.getElementById("sidebar").classList.add("active"); document.getElementById("sidebarOverlay").classList.add("active"); }); 
    document.getElementById("sidebarOverlay").addEventListener("click", () => { document.getElementById("sidebar").classList.remove("active"); document.getElementById("sidebarOverlay").classList.remove("active"); });
</script>
</body>
</html>