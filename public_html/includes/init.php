<?php
/** التهيئة المشتركة لكل الصفحات */
require_once __DIR__ . '/security.php';
secure_session_start();
send_security_headers();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ai.php';

$lang = detect_lang();
$dir  = SUPPORTED_LANGS[$lang]['dir'];

/* ---------- العملات ---------- */
if (isset($_GET['currency'])) $_SESSION['currency'] = ($_GET['currency'] === 'DZD') ? 'DZD' : 'USD';
if (empty($_SESSION['currency'])) $_SESSION['currency'] = 'USD';

/* ---------- الإعدادات ---------- */
function all_settings(): array {
    global $pdo;
    static $s = null;
    if ($s === null) {
        $s = [];
        try { foreach ($pdo->query("SELECT setting_key, setting_value_ar, setting_value_en FROM settings") as $r) $s[$r['setting_key']] = $r; } catch (Throwable $e) {}
    }
    return $s;
}
function getSetting($pdo, $key, $lang = 'ar') {
    $r = all_settings()[$key] ?? null;
    if (!$r) return '';
    $val = $r["setting_value_" . ($lang === 'ar' ? 'ar' : 'en')] ?? '';
    return $val !== '' && $val !== null ? $val : ($r['setting_value_ar'] ?? '');
}
function setSetting(string $key, ?string $ar, ?string $en = null): void {
    global $pdo;
    $ex = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ?"); $ex->execute([$key]);
    if ($ex->fetchColumn()) $pdo->prepare("UPDATE settings SET setting_value_ar = ?, setting_value_en = COALESCE(?, setting_value_en) WHERE setting_key = ?")->execute([$ar, $en, $key]);
    else $pdo->prepare("INSERT INTO settings (setting_key, setting_value_ar, setting_value_en) VALUES (?,?,?)")->execute([$key, $ar, $en]);
}
function exchange_rate(): float { return (float)(getSetting(null, 'exchange_rate') ?: 135); }

function formatPrice($usd_price, $dzd_price = null) {
    $usd = (float)$usd_price; $dzd = (float)$dzd_price; $rate = exchange_rate();
    if (($_SESSION['currency'] ?? 'USD') === 'DZD') {
        $v = $dzd > 0 ? $dzd : $usd * $rate;
        return number_format($v, 0) . ' ' . t('currency_dzd');
    }
    return '$' . number_format($usd > 0 ? $usd : ($rate > 0 ? $dzd / $rate : 0), 2);
}

$siteName      = getSetting($pdo, 'site_name', $lang) ?: 'Aramio Smart Global';
$contact_email = getSetting($pdo, 'contact_email', $lang) ?: 'info@aramiosmartglobal.com';
$contact_phone = getSetting($pdo, 'phone_number', $lang) ?: '';

/* ---------- ترويسة المستخدم الحالي ---------- */
$me = current_user();
