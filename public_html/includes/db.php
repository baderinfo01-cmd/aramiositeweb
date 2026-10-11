<?php
/**
 * الاتصال بقاعدة البيانات. الإعدادات تُقرأ من config.local.php (خارج Git).
 * يبحث أولًا خارج public_html (الأكثر أمانًا) ثم داخل includes/.
 */
if (!function_exists('app_config')) {
    function app_config(?string $key = null, $default = null) {
        static $cfg = null;
        if ($cfg === null) {
            $paths = [dirname(__DIR__, 2) . '/aramio_config.php', __DIR__ . '/config.local.php'];
            $cfg = [];
            foreach ($paths as $p) {
                if (is_file($p)) { $cfg = (array)(require $p); break; }
            }
        }
        if ($key === null) return $cfg;
        return $cfg[$key] ?? $default;
    }
}

if (!app_config('db_name')) {
    http_response_code(503);
    die('Configuration missing: copy includes/config.sample.php to includes/config.local.php and fill it in.');
}

$dsn = 'mysql:host=' . app_config('db_host', 'localhost') . ';dbname=' . app_config('db_name') . ';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, app_config('db_user'), app_config('db_pass'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    die(app_config('debug') ? 'DB error: ' . $e->getMessage() : 'Service temporarily unavailable.');
}
