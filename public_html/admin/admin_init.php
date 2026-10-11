<?php
// استدعاء ملف التهيئة الرئيسي
require_once '../includes/init.php';

// حماية لوحة التحكم: إذا لم يكن مسجلاً، أو لم يكن (أدمن/موظف)، اطرده لصفحة الدخول
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'staff')) {
    header('Location: ../login.php');
    exit();
}

// تحديد مسارات مجلد الإدارة لتسهيل استدعاء الملفات
$admin_css = '../assets/css/';
$admin_js = '../assets/js/';
?>