<?php
require_once 'includes/init.php';
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'ar'; 

// 1. التقاط كود المسوق من الرابط (Affiliate System)
$ref = isset($_GET['ref']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['ref']) : 'direct';

// 2. معالجة الإرسال المخفي (AJAX) — مع CSRF وتحديد المعدل وتحقق من المدخلات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_register'])) {
    header('Content-Type: application/json');
    if (!csrf_ok()) { http_response_code(419); echo json_encode(['status' => 'error', 'message' => 'Session expired, refresh the page.']); exit(); }
    if (!rate_limit('event_lead', 5, 3600)) { http_response_code(429); echo json_encode(['status' => 'error', 'message' => t('err_too_many')]); exit(); }
    try {
        $full_name = mb_substr(trim($_POST['full_name'] ?? ''), 0, 150);
        $dob = trim($_POST['dob'] ?? '');
        $dob = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) ? $dob : null;
        $phone = mb_substr(trim($_POST['phone'] ?? ''), 0, 50);
        $whatsapp = mb_substr(trim($_POST['whatsapp'] ?? ''), 0, 50);
        $country = mb_substr(trim($_POST['country'] ?? ''), 0, 100);
        $research_title = mb_substr(trim($_POST['research_title'] ?? ''), 0, 2000);
        $marketer_code = mb_substr(preg_replace('/[^A-Za-z0-9_\-]/', '', trim($_POST['marketer_code'] ?? 'direct')), 0, 50) ?: 'direct';
        $event_name = 'ملتقى ماليزيا الدولي 2026';
        if ($full_name === '' || $phone === '' || $whatsapp === '' || $country === '' || $research_title === '') {
            echo json_encode(['status' => 'error', 'message' => t('err_required')]); exit();
        }
        $insert = $pdo->prepare("INSERT INTO live_events_leads (event_name, full_name_ar, dob, phone, whatsapp, country, research_title, marketer_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new')");
        $insert->execute([$event_name, $full_name, $dob, $phone, $whatsapp, $country, $research_title, $marketer_code]);
        echo json_encode(['status' => 'success']);
    } catch (Throwable $e) {
        error_log('event lead: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Server error']);
    }
    exit();
}

// 3. إعدادات الصفحة العادية
$contact_phone = getSetting($pdo, 'phone_number', $lang) ?: '+21350515253';
$algerian_names = ['محمد الأمين', 'ياسين ب.', 'عبد الرؤوف م.', 'فاطمة الزهراء', 'سمية ك.', 'عبد القادر', 'هشام ر.', 'أمينة ب.', 'يوسف ق.', 'زينب ل.'];
$tunisian_names = ['أحمد الطرابلسي', 'مريم ب.', 'ياسين العياري', 'سناء م.', 'خليل ق.', 'فرح ب.', 'محمد علي', 'أميرة س.'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>الملتقى الدولي بماليزيا | أراميو سمارت العالمية</title>
    <link rel="stylesheet" href="assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    
    <style>
        :root { --primary: #07132b; --accent: #1cc88a; --gold: #D4AF37; --light-gold: #f9f5e8; --dark-red: #e74a3b; } 
        body { font-family: 'Cairo', sans-serif; background-color: #f8f9fa; color: #333; overflow-x: hidden; }
        
        /* Hero Section */
        .sales-hero { background: linear-gradient(rgba(7, 19, 43, 0.92), rgba(7, 19, 43, 0.88)), url('https://images.unsplash.com/photo-1596422846543-74c6eb24f56b?ixlib=rb-4.0.3') center/cover fixed; padding: 100px 0 60px; color: white; text-align: center; border-bottom: 5px solid var(--gold); }
        .urgency-badge { display: inline-block; background: rgba(212, 175, 55, 0.15); border: 1px solid var(--gold); color: var(--gold); padding: 8px 25px; border-radius: 30px; font-weight: 800; margin-bottom: 20px; letter-spacing: 1px; animation: pulseGold 2s infinite; }
        @keyframes pulseGold { 0% { box-shadow: 0 0 0 0 rgba(212, 175, 55, 0.4); } 70% { box-shadow: 0 0 0 15px rgba(212, 175, 55, 0); } 100% { box-shadow: 0 0 0 0 rgba(212, 175, 55, 0); } }
        .hero-title { font-size: 3.2rem; font-weight: 900; line-height: 1.4; margin-bottom: 15px; }
        .hero-title span { color: var(--gold); text-shadow: 0 0 15px rgba(212,175,55,0.4); }
        
        /* Country Selection */
        .selection-wrapper { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 40px; border-radius: 20px; backdrop-filter: blur(10px); margin-top: 30px; }
        .country-selector { display: flex; justify-content: center; gap: 20px; margin-top: 25px; flex-wrap: wrap; }
        .btn-country { background: white; border: 3px solid transparent; color: var(--primary); font-size: 1.5rem; font-weight: 800; padding: 15px 40px; border-radius: 15px; cursor: pointer; transition: 0.3s; width: 220px; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .btn-country:hover { background: var(--gold); border-color: white; color: var(--primary); transform: translateY(-5px); }

        /* Hidden Content Transition */
        #main-content { display: none; opacity: 0; transition: opacity 0.8s ease-in-out; }

        /* Info Sections */
        .section-title { font-weight: 900; color: var(--primary); position: relative; display: inline-block; padding-bottom: 15px; margin-bottom: 40px; }
        .section-title::after { content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 80px; height: 4px; background: var(--gold); border-radius: 2px; }
        .info-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); height: 100%; border-right: 4px solid var(--gold); transition: 0.3s; }
        .info-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.08); }
        .info-icon { font-size: 2.5rem; color: var(--gold); margin-bottom: 20px; }
        .list-styled li { margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px; font-weight: 600; color: #555; }
        .list-styled i { color: var(--accent); margin-top: 5px; font-size: 1.1rem; }

        /* Pricing Box */
        .pricing-section { background: var(--light-gold); padding: 80px 0; }
        .pricing-box { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 15px 35px rgba(0,0,0,0.06); border: 2px solid #eee; height: 100%; position: relative; overflow: hidden; transition: 0.3s; }
        .pricing-box:hover { border-color: var(--gold); }
        .pricing-box.premium { border: 4px solid var(--gold); transform: scale(1.03); z-index: 2; box-shadow: 0 25px 50px rgba(212,175,55,0.15); }
        .new-price { font-size: 3rem; font-weight: 900; color: var(--primary); margin: 20px 0; }
        .date-badge { background: rgba(7, 19, 43, 0.05); color: var(--primary); padding: 10px 15px; border-radius: 10px; font-weight: bold; margin-bottom: 20px; display: inline-block; border: 1px dashed var(--primary); }

        /* Form */
        .form-section { padding: 80px 0; background-color: white; }
        .register-form-box { background: white; border-radius: 20px; padding: 50px; box-shadow: 0 20px 60px rgba(0,0,0,0.08); border-top: 6px solid var(--primary); position: relative; }
        .form-control { border-radius: 10px; padding: 14px; background-color: #f8f9fa; border: 1px solid #ddd; font-weight: 600; }
        .form-control:focus { border-color: var(--gold); box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25); background: white; }
        .btn-submit { background: var(--primary); color: var(--gold); border-radius: 15px; padding: 18px; font-weight: 900; font-size: 1.4rem; border: none; width: 100%; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-submit:hover { background: #0a1b3d; transform: translateY(-3px); box-shadow: 0 15px 30px rgba(7,19,43,0.3); color: white;}

        /* Success Overlay */
        #success-overlay { display: none; text-align: center; padding: 30px; animation: fadeIn 0.5s; }
        .success-icon { font-size: 6rem; color: var(--accent); margin-bottom: 20px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Floating elements */
        .landing-whatsapp { position: fixed; bottom: 30px; left: 30px; background-color: #25d366; color: white; border-radius: 50px; padding: 12px 25px; font-size: 1.2rem; font-weight: bold; box-shadow: 0px 10px 30px rgba(37, 211, 102, 0.4); z-index: 1000; text-decoration: none; animation: pulse 2s infinite; }
        .toast-container { position: fixed; bottom: 30px; right: 30px; z-index: 1050; }
        .custom-toast { background: white; border-right: 4px solid var(--accent); box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 10px; padding: 15px; display: flex; align-items: center; gap: 15px; transform: translateY(100px); opacity: 0; transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55); width: 300px; }
        .custom-toast.show { transform: translateY(0); opacity: 1; }

        @media (max-width: 992px) {
            .sales-hero { padding: 80px 15px; } .hero-title { font-size: 2.2rem; }
            .pricing-box.premium { transform: scale(1); }
            .register-form-box { padding: 30px 20px; }
        }
    </style>
</head>
<body>

    <div class="toast-container" id="toastContainer"></div>

    <!-- 1. HERO & SELECTION -->
    <div class="sales-hero" id="hero-section">
        <div class="container">
            <span class="urgency-badge"><i class="fa-solid fa-star me-2"></i> التسجيل المبكر متاح الآن للمختصين فقط</span>
            <p class="text-uppercase tracking-widest text-warning fw-bold mb-2" style="letter-spacing: 2px;">Aramio Smart Global Academy</p>
            <h1 class="hero-title">لقاء الكبار 🌍<br>ملتقى <span>ماليزيا</span> الدولي 🇲🇾</h1>
            
            <div id="selection-box" class="selection-wrapper">
                <p class="fs-4 text-white fw-bold mb-2">لضمان دقة المعلومات والأسعار...</p>
                <p class="text-warning mb-4">الرجاء تحديد بلد إقامتك لعرض التفاصيل المخصصة لك:</p>
                <div class="country-selector">
                    <button class="btn-country" onclick="startExperience('الجزائر', 'dz')">الجزائر 🇩🇿</button>
                    <button class="btn-country" onclick="startExperience('تونس', 'tn')">تونس 🇹🇳</button>
                </div>
            </div>

            <a href="#registerForm" id="btn-scroll-form" class="btn btn-warning btn-lg rounded-pill px-5 py-3 fw-black shadow-lg fs-5 mt-5 pulse-button" style="display:none; color: var(--primary);">حجز مقعد مبدئي <i class="fa-solid fa-arrow-down ms-2"></i></a>
        </div>
    </div>

    <!-- MAIN CONTENT (HIDDEN INITIALLY) -->
    <div id="main-content">
        
        <!-- 2. ABOUT THE SUMMIT (Detailed Info) -->
        <section class="py-5">
            <div class="container">
                <div class="text-center mb-5">
                    <h2 class="section-title">التفاصيل الشاملة للملتقى</h2>
                </div>
                
                <div class="row g-4 mb-5">
                    <div class="col-lg-12">
                        <div class="info-card bg-primary text-white border-0 text-center p-4">
                            <h3 class="fw-bold text-warning mb-3">عنوان وموضوع الملتقى الرئيسي</h3>
                            <h4 class="lh-base">"خبراء التدريب والتعليم والمختصين في مواجهة تحديات المستقبل<br>بين الابتكار والتمكين والاستدامة"</h4>
                            <p class="mt-3 opacity-75 fs-5">من التدريب والممارسة إلى التمكين داخل القاعات والمؤسسات التدريبية والتطبيقية.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-card">
                            <i class="fa-solid fa-bullseye info-icon"></i>
                            <h4 class="fw-bold text-dark mb-3">محاور الملتقى الأساسية</h4>
                            <ul class="list-styled list-unstyled">
                                <li><i class="fa-solid fa-check"></i> الابتكار في أساليب التدريب أو الممارسة كمتخصص.</li>
                                <li><i class="fa-solid fa-check"></i> التمكين المهني داخل المؤسسات التدريبية أو الممارستية.</li>
                                <li><i class="fa-solid fa-check"></i> الاستدامة في بناء القدرات وتحقيق الأثر.</li>
                                <li><i class="fa-solid fa-check"></i> تجارب دولية ناجحة وتبادل الخبرات الميدانية.</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-card border-danger">
                            <i class="fa-solid fa-user-shield info-icon text-danger"></i>
                            <h4 class="fw-bold text-dark mb-3">شروط المشاركة (للنخبة فقط)</h4>
                            <ul class="list-styled list-unstyled">
                                <li><i class="fa-solid fa-triangle-exclamation text-danger"></i> أن تكون مؤهلاً بشهادة رسمية في مجال تخصصك.</li>
                                <li><i class="fa-solid fa-triangle-exclamation text-danger"></i> أن تمتلك خبرة ميدانية أو مهنية لا تقل عن (سنتين).</li>
                            </ul>
                            <div class="alert bg-light border mt-4 small fw-bold">
                                <strong>طرق المشاركة:</strong> إما بورقة بحثية كمحاضر دولي، أو عرض قصة نجاح ملهمة، أو الحضور للإستفادة والنقاش فقط.
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="info-card border-success bg-success bg-opacity-10 text-center">
                            <h4 class="fw-bold text-success mb-3"><i class="fa-solid fa-certificate me-2"></i> قوة الاعتماد والتوثيق الدولي</h4>
                            <p class="fw-bold text-dark lh-lg m-0">الحصول على دبلوم دولي معتمد كخبير + رقم اعتماد موثق رسمياً في الموقع الدولي لأراميو (يمكن التحقق منه عالمياً). يمنحك هذا صفة مهنية، ويسهل تنظيمك للدورات محلياً ودولياً، كما يتيح لك منح شهادات لطلابك (برسوم رمزية) معززاً سيرتك الذاتية في القطاع الخاص.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. DYNAMIC PRICING -->
        <section class="pricing-section">
            <div class="container">
                <div class="text-center mb-5">
                    <h2 class="section-title">الباقات والتواريخ الخاصة بـ <span id="display-country-name"></span></h2>
                </div>
                
                <div id="dynamic-pricing-container" class="row justify-content-center g-4">
                    <!-- سيتم حقن الأسعار والتواريخ الدقيقة هنا عبر الجافاسكربت -->
                </div>
            </div>
        </section>

        <!-- 4. LEAD GEN FORM (AJAX) -->
        <section id="registerForm" class="form-section">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="register-form-box">
                            
                            <div id="form-content">
                                <div class="text-center mb-5">
                                    <h2 class="fw-black text-primary">استمارة التسجيل المبدئي 📝</h2>
                                    <p class="text-muted fw-bold">احجز مقعدك الآن، وسيقوم فريق المبيعات بالتواصل معك فوراً لتأكيد الباقة وإتمام الدفع.</p>
                                </div>
                                <form id="summitForm" onsubmit="submitForm(event)">
                                    <input type="hidden" name="ajax_register" value="1"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="marketer_code" value="<?php echo $ref; ?>"> <!-- كود المسوق -->
                                    
                                    <div class="row g-4 mb-4">
                                        <div class="col-md-12">
                                            <label class="form-label fw-bold">الاسم واللقب كامل <span class="text-danger">*</span></label>
                                            <input type="text" name="full_name" class="form-control" required placeholder="أدخل اسمك ولقبك">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">تاريخ الميلاد <span class="text-danger">*</span></label>
                                            <input type="date" name="dob" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">الدولة <span class="text-danger">*</span></label>
                                            <input type="text" name="country" id="input-country" class="form-control" readonly style="background-color: #e9ecef;">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label fw-bold">التخصص ونوع المشاركة <span class="text-danger">*</span></label>
                                            <input type="text" name="research_title" class="form-control" required placeholder="(مثال: مدرب تنمية بشرية - أشارك بورقة بحثية)">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">رقم الهاتف <span class="text-danger">*</span></label>
                                            <input type="tel" name="phone" class="form-control text-start" dir="ltr" required placeholder="+213...">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-success"><i class="fa-brands fa-whatsapp"></i> رقم الواتساب <small>(إجباري للتواصل)</small> *</label>
                                            <input type="tel" name="whatsapp" class="form-control text-start border-success" dir="ltr" required placeholder="+213...">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" id="submitBtn" class="btn-submit">
                                        <span id="btnText">تأكيد حجز المقعد 🚀</span>
                                        <span id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                                    </button>
                                </form>
                            </div>

                            <!-- Success Message -->
                            <div id="success-overlay">
                                <i class="fa-solid fa-circle-check success-icon"></i>
                                <h2 class="text-success fw-black mb-3">تم إرسال طلبك بنجاح!</h2>
                                <p class="fs-4 text-dark fw-bold mb-4">وصلت معلوماتك إلى الإدارة بأمان.</p>
                                <div class="bg-light border-start border-4 border-primary p-3 rounded text-start text-muted fw-bold mb-4">
                                    <i class="fa-solid fa-headset text-primary me-2"></i> سوف يتواصل معك فريقنا في أقرب وقت عبر <strong>الواتساب</strong> لتأكيد الباقة التي تود اختيارها وإرشادك لطريقة إرسال الدفعة الأولى وملفك الشخصي.
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div> <!-- End Main Content -->

    <footer class="py-4 bg-dark text-white text-center">
        <div class="container fw-bold opacity-75 small">
            &copy; <?php echo date('Y'); ?> ARAMIO SMART GLOBAL ACADEMY - All Rights Reserved.
        </div>
    </footer>

    <script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        // 1. Data Definitions with Precise Dates




             // 1. Data Definitions with Precise Dates & New Packages
        const pricingData = {
            'dz': `
                <!-- الباقة الأولى: 11 مليون -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="pricing-box text-center">
                        <h4 class="text-primary fw-bold mb-3">باقة الملتقى فقط</h4>
                        <div class="date-badge"><i class="fa-regular fa-clock me-1"></i> المدة: 04 أيام</div>
                        <div class="new-price" style="font-size: 2.3rem;">110,000 د.ج</div>
                        <ul class="list-styled list-unstyled text-start mt-4 small">
                            <li><i class="fa-solid fa-check"></i> ديبلوم دولي + رقم اعتماد دولي.</li>
                            <li><i class="fa-solid fa-check"></i> حضور المؤتمر ومناقشاته.</li>
                            <li><i class="fa-solid fa-check"></i> استراحة قهوة ومرشد سياحي.</li>
                            <li><i class="fa-solid fa-check"></i> وسام أراميو + كرنيه العضوية.</li>
                            <li class="text-danger"><i class="fa-solid fa-times text-danger"></i> لا تشمل الطيران والإقامة والنقل.</li>
                        </ul>
                        <div class="bg-light p-2 rounded mt-4 text-center border-start border-warning border-4 small fw-bold text-muted">
                            تدفع على دفعتين (50,000 د.ج كدفعة أولى)
                        </div>
                    </div>
                </div>

                <!-- الباقة الثانية: 17 مليون (الجديدة) -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="pricing-box text-center border-info" style="background:#f4fbff">
                        <span class="badge bg-info text-dark position-absolute top-0 start-50 translate-middle-x mt-3 fs-6 rounded-pill px-3">إقامة بدون طيران 🏨</span>
                        <h4 class="text-dark fw-bold mt-4 mb-3">الباقة المتوسطة</h4>
                        <div class="date-badge bg-white"><i class="fa-regular fa-clock me-1"></i> المدة: 05 أيام</div>
                        <div class="new-price text-info" style="font-size: 2.3rem;">170,000 د.ج</div>
                        <ul class="list-styled list-unstyled text-start mt-4 small">
                            <li><i class="fa-solid fa-star text-info"></i> <strong>جميع مميزات الملتقى والاعتمادات.</strong></li>
                            <li><i class="fa-solid fa-hotel text-primary"></i> إقامة بفندق 4 نجوم فخم مع فطور.</li>
                            <li><i class="fa-solid fa-map-location-dot text-primary"></i> مرشد سياحي مؤهل للجولات.</li>
                            <li class="text-danger"><i class="fa-solid fa-plane-slash text-danger"></i> لا تشمل تذكرة الطيران.</li>
                        </ul>
                        <div class="bg-white p-2 rounded mt-4 text-center border-start border-info border-4 small fw-bold text-muted">
                            تدفع على دفعتين بالتنسيق مع الإدارة
                        </div>
                    </div>
                </div>

                <!-- الباقة الثالثة: 29.9 مليون (التخفيض) -->
                <div class="col-lg-4 col-md-12 mb-4">
                    <div class="pricing-box premium text-center">
                        <span class="badge bg-danger position-absolute top-0 start-50 translate-middle-x mt-3 fs-6 rounded-pill px-3">التكفل الشامل VIP ✈️</span>
                        <h4 class="text-dark fw-bold mt-4 mb-3">الباقة الشاملة المتقدمة</h4>
                        <div class="date-badge"><i class="fa-regular fa-clock me-1"></i> المدة: 09 أيام كاملة</div>
                        <div class="new-price text-warning" style="font-size: 2.3rem;">299,000 د.ج</div>
                        <ul class="list-styled list-unstyled text-start mt-4 small">
                            <li><i class="fa-solid fa-star text-warning"></i> <strong>التمتع بكل الخدمات المذكورة.</strong></li>
                            <li><i class="fa-solid fa-plane"></i> تذكرة الطيران ذهاب وإياب.</li>
                            <li><i class="fa-solid fa-van-shuttle"></i> النقل الكامل من وإلى المطار.</li>
                            <li><i class="fa-solid fa-hotel"></i> إقامة بفندق 4 نجوم مع فطور الصباح.</li>
                        </ul>
                        <div class="bg-light p-2 rounded mt-4 text-center border-start border-success border-4 small fw-bold text-muted">
                            تدفع على دفعتين (150,000 د.ج كدفعة أولى)
                        </div>
                    </div>
                </div>
            `,
            'tn': `
                <!-- أسعار تونس بقيت كما هي لم تتغير -->
                <div class="col-md-6 mb-4">
                    <div class="pricing-box text-center">
                        <h4 class="text-primary fw-bold mb-3">باقة الخدمات (الملتقى فقط)</h4>
                        <div class="date-badge"><i class="fa-regular fa-calendar-days me-1"></i> تواريخ الملتقى: 02، 03، 04 جويلية 2026</div>
                        <div class="new-price">1,200 د.ت</div>
                        <ul class="list-styled list-unstyled text-start mt-4">
                            <li><i class="fa-solid fa-check"></i> استراحة قهوة ومرشد سياحي.</li>
                            <li><i class="fa-solid fa-check"></i> حضور المؤتمر ومناقشاته.</li>
                            <li><i class="fa-solid fa-check"></i> ديبلوم دولي + رقم اعتماد دولي.</li>
                            <li><i class="fa-solid fa-check"></i> وسام أراميو + كرنيه العضوية.</li>
                            <li class="text-danger"><i class="fa-solid fa-times text-danger"></i> لا تشمل الطيران ولا الإقامة.</li>
                        </ul>
                        <div class="bg-light p-3 rounded mt-4 text-center border-start border-warning border-4 small fw-bold text-muted">
                            الدفعة الأولى: 500 د.ت (لإرسال الدعوة)<br>الدفعة الثانية: 210 € (تسلم في ماليزيا يوم 28 جوان)
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="pricing-box premium text-center">
                        <span class="badge bg-danger position-absolute top-0 start-50 translate-middle-x mt-3 fs-6 rounded-pill px-4">إقامة بدون طيران 🏨</span>
                        <h4 class="text-dark fw-bold mt-4 mb-3">الباقة المتقدمة</h4>
                        <div class="date-badge"><i class="fa-regular fa-calendar-days me-1"></i> من 26 جوان إلى 04 جويلية (09 أيام)</div>
                        <div class="new-price text-warning">2,900 د.ت</div>
                        <ul class="list-styled list-unstyled text-start mt-4">
                            <li><i class="fa-solid fa-star text-warning"></i> <strong>جميع مميزات باقة الخدمات.</strong></li>
                            <li><i class="fa-solid fa-hotel"></i> إقامة بفندق 4 نجوم فخم مع الفطور.</li>
                            <li><i class="fa-solid fa-clock"></i> مدة الرحلة 09 أيام كاملة.</li>
                            <li class="text-danger"><i class="fa-solid fa-plane-slash text-danger"></i> تتكفل بالإقامة فقط ولا تشمل الطيران.</li>
                        </ul>
                        <div class="bg-light p-3 rounded mt-4 text-center border-start border-success border-4 small fw-bold text-muted">
                            تدفع على دفعتين (يتم تحديد التواريخ بالتواصل مع الإدارة)
                        </div>
                    </div>
                </div>
            `
        };



















        let selectedNamesArray = <?php echo json_encode($algerian_names); ?>;

        // 2. Dynamic Selection
        function startExperience(countryName, countryCode) {
            document.getElementById('display-country-name').innerText = countryName;
            document.getElementById('input-country').value = countryName;
            document.getElementById('dynamic-pricing-container').innerHTML = pricingData[countryCode];
            
            selectedNamesArray = (countryCode === 'dz') ? <?php echo json_encode($algerian_names); ?> : <?php echo json_encode($tunisian_names); ?>;

            document.getElementById('selection-box').style.display = 'none';
            document.getElementById('btn-scroll-form').style.display = 'inline-block';
            
            const mainContent = document.getElementById('main-content');
            mainContent.style.display = 'block';
            setTimeout(() => { mainContent.style.opacity = '1'; }, 100);
        }

        // 3. AJAX Submission
        function submitForm(e) {
            e.preventDefault();
            const form = document.getElementById('summitForm');
            const btn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            
            btn.disabled = true; btnText.innerText = 'جاري الإرسال... '; btnSpinner.classList.remove('d-none');

            const formData = new FormData(form);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('form-content').style.display = 'none';
                    document.getElementById('success-overlay').style.display = 'block';
                } else {
                    alert('خطأ: ' + data.message);
                    resetBtn();
                }
            })
            .catch(error => { alert('خطأ في الاتصال. حاول مجدداً.'); resetBtn(); });

            function resetBtn() { btn.disabled = false; btnText.innerText = 'تأكيد حجز المقعد 🚀'; btnSpinner.classList.add('d-none'); }
        }

        // 4. Fake Social Proof Toasts
        function showRegistrationPopup() {
            if(document.getElementById('selection-box').style.display !== 'none') return;
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'custom-toast';
            const randomName = selectedNamesArray[Math.floor(Math.random() * selectedNamesArray.length)];
            
            toast.innerHTML = `
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px;">
                    <i class="fa-solid fa-check"></i>
                </div>
                <div class="w-100">
                    <p class="mb-0 fw-bold text-dark small lh-sm">سجل <span class="text-primary">${randomName}</span> مقعده للتو!</p>
                    <small class="text-success fw-bold" style="font-size: 0.7rem;">قبل قليل</small>
                </div>
            `;
            container.appendChild(toast);
            setTimeout(() => { toast.classList.add('show'); }, 100);
            setTimeout(() => { toast.classList.remove('show'); setTimeout(() => { toast.remove(); }, 500); }, 4000);
        }
        setTimeout(() => { setInterval(showRegistrationPopup, 35000); }, 5000);
    </script>
</body>
</html>