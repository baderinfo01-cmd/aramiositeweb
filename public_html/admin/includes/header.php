<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>الإدارة المركزية | <?php echo $siteName; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #f0f2f5; overflow-x: hidden; color: #333; }
        
        /* 💡 القائمة الجانبية (تم إصلاح السكرول والتمرير 100% للهاتف) */
        .sidebar {
            height: 100vh; /* تأخذ طول الشاشة */
            background-color: #051024; color: white;
            position: fixed; right: 0; top: 0; width: 260px; z-index: 1050; 
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            box-shadow: -4px 0 20px rgba(0,0,0,0.15);
            display: flex; flex-direction: column; /* لجعل السكرول يعمل بمرونة */
        }
        
        /* 💡 الجزء العلوي الثابت للقائمة */
        .sidebar-brand { padding: 25px 20px; text-align: center; background: rgba(0,0,0,0.2); flex-shrink: 0; }
        .sidebar-brand i { font-size: 2.5rem; color: #1cc88a; filter: drop-shadow(0 0 10px rgba(28,200,138,0.5)); }
        
        /* 💡 الجزء القابل للتمرير (السحر هنا) */
        .sidebar-menu-container {
            flex-grow: 1; /* يأخذ المساحة المتبقية */
            overflow-y: auto; /* يسمح بالتمرير العمودي */
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch; /* تمرير ناعم جداً للآيفون والآيباد */
            padding-bottom: 80px; /* مساحة فارغة في الأسفل لكي لا يختفي زر الخروج */
        }
        
        /* 💡 إخفاء شريط التمرير البشع لجمالية التصميم */
        .sidebar-menu-container::-webkit-scrollbar { width: 4px; }
        .sidebar-menu-container::-webkit-scrollbar-track { background: transparent; }
        .sidebar-menu-container::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        .sidebar-menu-container:hover::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); }
        
        .sidebar-menu { padding: 0; list-style: none; margin: 0; }
        .sidebar-menu li { margin-bottom: 5px; px-3; }
        .sidebar-menu li a {
            display: flex; align-items: center; color: #a3aab5; text-decoration: none; 
            padding: 12px 20px; font-size: 1.05rem; transition: 0.3s; font-weight: 600;
            border-radius: 0 30px 30px 0; margin-left: 15px;
        }
        .sidebar-menu li a i { width: 35px; text-align: center; font-size: 1.2rem; }
        
        .sidebar-menu li a:hover { color: #fff; background: rgba(255,255,255,0.05); transform: translateX(-5px); }
        .sidebar-menu li a.active {
            color: #fff; background: linear-gradient(90deg, #1cc88a 0%, #159f6d 100%);
            box-shadow: 0 4px 15px rgba(28,200,138,0.4);
        }
        .sidebar-menu li a.active i { color: #fff; }

        /* منطقة المحتوى */
        .main-content { margin-right: 260px; padding: 0; transition: margin 0.3s cubic-bezier(0.4, 0, 0.2, 1); min-height: 100vh; }
        
        /* الترويسة العلوية */
        .topbar { 
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            height: 75px; display: flex; align-items: center; justify-content: space-between; 
            padding: 0 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); 
            position: sticky; top: 0; z-index: 1000; margin-bottom: 30px;
        }
        .admin-badge { background: #fee2e2; color: #dc2626; padding: 5px 15px; border-radius: 20px; font-weight: 800; font-size: 0.85rem; border: 1px solid #fca5a5; }
        .user-profile img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb; padding: 2px; }
        
        .admin-card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); transition: 0.3s; background: #fff; }
        .admin-card:hover { box-shadow: 0 10px 30px rgba(0,0,0,0.08); transform: translateY(-3px); }
        
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(5, 16, 36, 0.6); backdrop-filter: blur(3px); z-index: 1040; }

        @media (max-width: 992px) {
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
        <!-- الرأس الثابت -->
        <div class="sidebar-brand">
            <i class="fa-solid fa-crown mb-2"></i>
            <h4 class="fw-bold m-0 text-white mt-2" style="letter-spacing: 1px;">Aramio Admin</h4>
            <small class="text-muted d-block mt-1">الإدارة المركزية</small>
        </div>
        
        <!-- 💡 الحاوية القابلة للتمرير (السكرول) -->
        <div class="sidebar-menu-container">
            <ul class="sidebar-menu mt-3">
                <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house-chimney"></i> الرئيسية</a></li>
                
                <li class="pt-3 pb-1 px-4 text-uppercase small fw-bold" style="color: #6c7293; letter-spacing: 1px;">الأكاديمية</li>
                <li><a href="categories.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>"><i class="fa-solid fa-tags"></i> تصنيفات الدورات</a></li>
                <li><a href="courses.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : ''; ?>"><i class="fa-solid fa-photo-film"></i> إدارة الدورات</a></li>
                <li><a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>"><i class="fa-solid fa-users-gear"></i> إدارة المستخدمين</a></li>
                
                       
              
                
                <li><a href="live_events.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'live_events.php' ? 'active' : ''; ?>"><i class="fa-solid fa-users-gear"></i>  مسجلو الملتقيات الدولية</a></li>
                
                <li><a href="legacy_certs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'legacy_certs.php' ? 'active' : ''; ?>"><i class="fa-solid fa-users-gear"></i>  إضافة ارشيف الشهادات</a></li>


                
                
                               
                <li class="pt-3 pb-1 px-4 text-uppercase small fw-bold" style="color: #6c7293; letter-spacing: 1px;">المالية</li>
                <li><a href="financial.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'financial.php' ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice-dollar"></i> طلبات الدورات</a></li>
                <li><a href="consultations.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'consultations.php' ? 'active' : ''; ?>"><i class="fa-solid fa-handshake"></i> مدفوعات الاستشارات</a></li>
                <li><a href="expenses.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'active' : ''; ?>"><i class="fa-solid fa-money-bill-trend-up"></i> المصاريف والأرباح</a></li>
                
                <li class="pt-3 pb-1 px-4 text-uppercase small fw-bold" style="color: #6c7293; letter-spacing: 1px;">الواجهة والإعدادات</li>
                <li><a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>"><i class="fa-solid fa-laptop-code"></i> الإعدادات العامة</a></li>

                
                
                
                

                

                
                
                
                
                <li><a href="notifications.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>"><i class="fa-solid fa-bell"></i> الإشعارات المنبثقة</a></li>
                <li><a href="messages.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>"><i class="fa-solid fa-envelope"></i> رسائل الزوار</a></li>
                
                <!-- زر الخروج مفصول في الأسفل -->
                <li class="mt-4 border-top pt-3 mx-3" style="border-color: rgba(255,255,255,0.1) !important;">
                    <a href="../logout.php" class="text-danger" style="margin-left: 0; padding-left: 10px;"><i class="fa-solid fa-power-off text-danger"></i> تسجيل الخروج</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="main-content">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none shadow-sm rounded-circle p-2" id="openSidebarBtn" style="width: 40px; height: 40px;"><i class="fa-solid fa-bars text-primary"></i></button>
                <h5 class="m-0 fw-bold text-dark d-none d-md-block">لوحة التحكم العليا 👑</h5>
            </div>
            <div class="d-flex align-items-center gap-4">
                <span class="admin-badge d-none d-sm-inline-block"><i class="fa-solid fa-shield-halved me-1"></i> مدير عام</span>
                <div class="user-profile d-flex align-items-center gap-2" style="cursor: pointer;">
                    <div class="text-end d-none d-md-block">
                        <span class="fw-bold text-dark d-block" style="line-height: 1;">الأستاذ هلال</span>
                        <small class="text-muted fw-bold">Admin</small>
                    </div>
                    <img src="https://ui-avatars.com/api/?name=Hilal&background=051024&color=fff&bold=true" alt="Admin">
                </div>
            </div>
        </header>

        <div class="container-fluid px-4 pb-5">