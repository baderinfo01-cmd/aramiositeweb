<?php
require_once __DIR__ . '/../includes/init.php';
$me = require_role(['trainer'], '../login.php');
$trainer_id = (int)$me['id'];
$dashRole = 'trainer';
function url_ok(string $u): bool { return (bool)filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u); }
function wa_buttons(): string {
    if (empty($_SESSION['wa_buttons'])) return '';
    $h = '<div class="dcard p-4 mb-4 text-center" style="border-color:#25d366"><div class="fw-bold mb-3"><i class="fa-brands fa-whatsapp text-success fs-4"></i> ' . e(d('أرسل رسالة جاهزة عبر واتساب', 'Send a ready-made WhatsApp message')) . '</div><div class="d-flex justify-content-center flex-wrap gap-2">';
    foreach ($_SESSION['wa_buttons'] as [$l, $u]) $h .= '<a target="_blank" rel="noopener" class="btn btn-success rounded-pill fw-bold px-4" href="' . e($u) . '"><i class="fa-brands fa-whatsapp"></i> ' . e($l) . '</a>';
    unset($_SESSION['wa_buttons']);
    return $h . '</div></div>';
}
function wa_push(string $label, string $url): void { if ($url) $_SESSION['wa_buttons'][] = [$label, $url]; }
