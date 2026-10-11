<?php
/** بوابة لوحة الإدارة: تسجيل دخول + صلاحية admin/staff + تحميل أدوات اللوحة */
require_once __DIR__ . '/../includes/init.php';
$me = require_role(['admin', 'staff'], '../login.php');
$isSuperAdmin = ($me['role'] === 'admin');
$dashRole = 'admin';
function admin_only(): void {
    global $isSuperAdmin;
    if (!$isSuperAdmin) { http_response_code(403); die('Forbidden: administrators only.'); }
}

/** أزرار واتساب تُعرض بعد إعادة التوجيه (PRG) */
function wa_queue(string $label, string $url, string $cls = 'btn-success'): void { if ($url) $_SESSION['wa_buttons'][] = [$label, $url, $cls]; }
function wa_render(): string {
    if (empty($_SESSION['wa_buttons'])) return '';
    $h = '<div class="dcard p-4 mb-4 text-center" style="border-color:#25d366"><div class="fw-bold mb-3"><i class="fa-brands fa-whatsapp text-success fs-4"></i> ' . e(d('أرسل رسالة جاهزة عبر واتساب', 'Send a ready-made WhatsApp message')) . '</div><div class="d-flex justify-content-center flex-wrap gap-2">';
    foreach ($_SESSION['wa_buttons'] as [$l, $u, $c]) $h .= '<a target="_blank" rel="noopener" class="btn ' . e($c) . ' rounded-pill fw-bold px-4" href="' . e($u) . '"><i class="fa-brands fa-whatsapp"></i> ' . e($l) . '</a>';
    unset($_SESSION['wa_buttons']);
    return $h . '</div></div>';
}
