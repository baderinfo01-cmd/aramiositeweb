<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة المدربين | <?php echo $siteName; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #f4f7f6; overflow-x: hidden; }
        
        /* القائمة الجانبية للمدرب */
        .sidebar {
            height: 100vh; background-color: #0b1c3f; color: white;
            position: fixed; right: 0; top: 0; width: 250px; z-index: 1050; 
            transition: transform 0.3s ease-in-out; box-shadow: -5px 0 15px rgba(0,0,0,0.1);
        }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-menu { padding: 20px 0; list-style: none; margin: 0; }
        .sidebar-menu li a {
            display: block; color: #d1d3e2; text-decoration: none; padding: 12px 25px;
            font-size: 1.1rem; transition: 0.3s; font-weight: 600;
        }
        .sidebar-menu li a:hover, .sidebar-menu li a.active {
            color: #fff; background-color: rgba(255,255,255,0.1); border-right: 4px solid #f6c23e; /* لون أصفر لتمييز المدرب */
        }
        .sidebar-menu li a i { width: 25px; text-align: center; margin-left: 10px; color: #f6c23e; }

        /* منطقة المحتوى */
        .main-content { margin-right: 250px; padding: 0; transition: margin 0.3s ease-in-out; }
        
        /* الترويسة العلوية */
        .topbar { background: white; height: 70px; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .user-profile img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #f6c23e; }
        
        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.5); z-index: 1040;
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(100%); } 
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-right: 0; }
            .sidebar-overlay.active { display: block; }
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header d-flex justify-content-between align-items-center px-4">
            <div class="text-center w-100">
                <i class="fa-solid fa-chalkboard-user fa-2x text-warning mb-2"></i>
                <h4 class="fw-bold m-0 text-white">Aramio Trainer</h4>
            </div>
            <button class="btn btn-sm btn-outline-light d-md-none border-0" id="closeSidebarBtn"><i class="fa-solid fa-xmark fa-lg"></i></button>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php" class="active"><i class="fa-solid fa-chart-pie"></i> نظرة عامة</a></li>
            <li><a href="my_courses.php"><i class="fa-solid fa-book-open"></i> إدارة دوراتي</a></li>
            <li><a href="schedule.php"><i class="fa-solid fa-calendar-days"></i> جدول الاستشارات</a></li>
            <li><a href="students.php"><i class="fa-solid fa-users"></i> متدربيني</a></li>
            <li><a href="earnings.php"><i class="fa-solid fa-wallet"></i> الأرباح والمستحقات</a></li>
            <li><a href="certificates.php"><i class="fa-solid fa-wallet"></i> طلبات الشهادات</a></li>
            <li><a href="settings.php"><i class="fa-solid fa-user-gear"></i> إعدادات الحساب</a></li>
            <li class="mt-4"><a href="../logout.php" class="text-danger"><i class="fa-solid fa-right-from-bracket text-danger"></i> تسجيل الخروج</a></li>
        </ul>
    </nav>

    <div class="main-content">
        <header class="topbar mb-4">
            <div>
                <h5 class="m-0 fw-bold text-primary d-none d-md-block">مرحباً بك يا كوتش <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'مدرب'); ?> 🏆</h5>
                <button class="btn btn-light d-md-none shadow-sm" id="openSidebarBtn"><i class="fa-solid fa-bars fs-5 text-primary"></i></button>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="user-profile d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'مدرب'); ?></span>
                    <?php 
                        $header_stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                        $header_stmt->execute([$_SESSION['user_id']]);
                        $user_avatar = $header_stmt->fetchColumn();
                        $topbar_img = ($user_avatar && $user_avatar != 'default_avatar.png') ? '../uploads/avatars/' . $user_avatar : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name'] ?? 'User') . '&background=f6c23e&color=fff';
                    ?>
                    <img src="<?php echo $topbar_img; ?>" alt="Profile">
                </div>
            </div>
        </header>

        <div class="container-fluid px-4 pb-5">