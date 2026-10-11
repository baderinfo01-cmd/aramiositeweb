<?php
/** طبقة الأمان: جلسات، CSRF، ترويسات، تحديد معدل الطلبات، رفع الملفات، أدوات مساعدة */

/* توافق مع PHP 7.4 */
if (!function_exists('str_contains')) { function str_contains(string $h, string $n): bool { return $n === '' || strpos($h, $n) !== false; } }
if (!function_exists('str_starts_with')) { function str_starts_with(string $h, string $n): bool { return strncmp($h, $n, strlen($n)) === 0; } }

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function secure_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('ARAMIOSESS');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => is_https(),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function send_security_headers(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_ok(): bool {
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
/** يوقف التنفيذ إن لم يكن الطلب POST صالحًا */
function require_post_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
        http_response_code(419);
        if (is_ajax()) { header('Content-Type: application/json'); die(json_encode(['ok' => false, 'error' => 'csrf'])); }
        die('Invalid or expired request. Please go back and refresh the page.');
    }
}
function is_ajax(): bool {
    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/* ---------- Rate limit (DB based) ---------- */
function rate_limit(string $bucket, int $max, int $windowSec): bool {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $key = hash('sha256', $bucket . '|' . $ip);
    try {
        $pdo->prepare("DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL ? SECOND)")->execute([$windowSec]);
        $st = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE k = ? AND created_at >= (NOW() - INTERVAL ? SECOND)");
        $st->execute([$key, $windowSec]);
        if ((int)$st->fetchColumn() >= $max) return false;
        $pdo->prepare("INSERT INTO rate_limits (k) VALUES (?)")->execute([$key]);
    } catch (Throwable $e) { /* الجدول غير موجود بعد: لا نمنع */ }
    return true;
}

/* ---------- Flash ---------- */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flash_render(): string {
    if (empty($_SESSION['flash'])) return '';
    $out = '';
    foreach ($_SESSION['flash'] as [$type, $msg]) {
        $cls = ['ok' => 'success', 'err' => 'danger', 'warn' => 'warning', 'info' => 'info'][$type] ?? 'info';
        $out .= '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">' . e($msg) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/* ---------- Auth helpers ---------- */
function current_user(): ?array {
    global $pdo;
    static $u = false;
    if ($u !== false) return $u;
    if (empty($_SESSION['user_id'])) return $u = null;
    $st = $pdo->prepare("SELECT id, full_name, last_name, email, role, status, avatar, country, phone FROM users WHERE id = ? LIMIT 1");
    $st->execute([$_SESSION['user_id']]);
    $row = $st->fetch();
    if (!$row || (int)$row['status'] === 0) { $_SESSION = []; return $u = null; }
    $_SESSION['user_role'] = $row['role'];
    return $u = $row;
}
function require_role(array $roles, string $loginPath = '../login.php'): array {
    $u = current_user();
    if (!$u || !in_array($u['role'], $roles, true)) { header('Location: ' . $loginPath); exit; }
    return $u;
}

/* ---------- Uploads ---------- */
/**
 * رفع آمن للصور (والاختياري PDF). يتحقق من النوع الحقيقي عبر finfo ويولّد اسمًا عشوائيًا.
 * @return string|null اسم الملف المحفوظ أو null عند الفشل
 */
function save_upload(string $field, string $subdir, array $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], int $maxBytes = 5242880): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$field];
    if ($f['size'] > $maxBytes || !is_uploaded_file($f['tmp_name'])) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowed[$mime])) return null;
    $dir = dirname(__DIR__) . '/uploads/' . trim($subdir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return null;
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    return move_uploaded_file($f['tmp_name'], $dir . '/' . $name) ? $name : null;
}

/* ---------- Misc ---------- */
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function base_url(string $path = ''): string {
    $b = rtrim((string)app_config('base_url', ''), '/');
    if ($b === '') $b = (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $b . '/' . ltrim($path, '/');
}
function send_mail(string $to, string $subject, string $htmlBody): bool {
    $from = app_config('mail_from', 'no-reply@localhost');
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Aramio <$from>\r\n";
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
    if (!$ok && app_config('debug')) error_log("MAIL (not sent) to $to: $subject");
    return $ok;
}
