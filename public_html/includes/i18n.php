<?php
/** نظام اللغات المتعددة: ar, en, fr, tr, es, ms */

const SUPPORTED_LANGS = [
    'ar' => ['name' => 'العربية',  'dir' => 'rtl', 'flag' => '🇸🇦', 'locale' => 'ar_AR'],
    'en' => ['name' => 'English',  'dir' => 'ltr', 'flag' => '🇬🇧', 'locale' => 'en_US'],
    'fr' => ['name' => 'Français', 'dir' => 'ltr', 'flag' => '🇫🇷', 'locale' => 'fr_FR'],
    'tr' => ['name' => 'Türkçe',   'dir' => 'ltr', 'flag' => '🇹🇷', 'locale' => 'tr_TR'],
    'es' => ['name' => 'Español',  'dir' => 'ltr', 'flag' => '🇪🇸', 'locale' => 'es_ES'],
    'ms' => ['name' => 'Melayu',   'dir' => 'ltr', 'flag' => '🇲🇾', 'locale' => 'ms_MY'],
];

function detect_lang(): string {
    if (isset($_GET['lang']) && isset(SUPPORTED_LANGS[$_GET['lang']])) {
        $l = $_GET['lang'];
        $_SESSION['lang'] = $l;
        if (!headers_sent()) setcookie('lang', $l, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'secure' => is_https()]);
        return $l;
    }
    if (!empty($_SESSION['lang']) && isset(SUPPORTED_LANGS[$_SESSION['lang']])) return $_SESSION['lang'];
    if (!empty($_COOKIE['lang']) && isset(SUPPORTED_LANGS[$_COOKIE['lang']])) return $_SESSION['lang'] = $_COOKIE['lang'];
    $_SESSION['lang'] = 'ar'; // الافتراضي: العربية (كما في الموقع الأصلي)
    return 'ar';
}

function lang_dict(string $l): array {
    static $cache = [];
    if (!isset($cache[$l])) {
        $cache[$l] = [];
        foreach (["$l.php", "$l.dash.php"] as $fn) {
            $f = dirname(__DIR__) . "/lang/$fn";
            if (is_file($f)) $cache[$l] = array_merge($cache[$l], (array)(require $f));
        }
    }
    return $cache[$l];
}

/** ترجمة مفتاح: اللغة الحالية ← الإنجليزية ← العربية ← المفتاح نفسه */
function t(string $key, array $vars = []): string {
    global $lang;
    $s = lang_dict($lang)[$key] ?? lang_dict('en')[$key] ?? lang_dict('ar')[$key] ?? $key;
    foreach ($vars as $k => $v) $s = str_replace('{' . $k . '}', (string)$v, $s);
    return $s;
}

/** حقل محتوى ثنائي اللغة في قاعدة البيانات (title_ar/title_en) مع ترجمات إضافية من جدول translations */
function tr_field(array $row, string $base, ?string $entity = null): string {
    global $lang, $pdo;
    if ($entity !== null && $lang !== 'ar' && $lang !== 'en' && isset($row['id'])) {
        static $memo = [];
        $mk = "$entity:{$row['id']}:$lang";
        if (!isset($memo[$mk])) {
            try {
                $st = $pdo->prepare("SELECT field, value FROM translations WHERE entity = ? AND entity_id = ? AND lang = ?");
                $st->execute([$entity, $row['id'], $lang]);
                $memo[$mk] = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Throwable $e) { $memo[$mk] = []; }
        }
        if (!empty($memo[$mk][$base])) return $memo[$mk][$base];
    }
    foreach ([$lang, 'en', 'ar'] as $l) {
        if (!empty($row["{$base}_$l"])) return $row["{$base}_$l"];
    }
    return '';
}

function lang_switcher_url(string $l): string {
    $q = $_GET; $q['lang'] = $l;
    return strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($q);
}
