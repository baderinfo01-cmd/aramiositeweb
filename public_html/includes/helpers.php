<?php
/** دوال مساعدة عامة للعرض */

function course_thumb(array $c): string {
    $f = $c['thumbnail'] ?? '';
    if ($f && $f !== 'default_course.jpg' && is_file(dirname(__DIR__) . '/uploads/courses/' . basename($f))) return 'uploads/courses/' . rawurlencode(basename($f));
    return '';
}
function avatar_url(?string $avatar, string $name = 'U', string $prefix = ''): string {
    if ($avatar && $avatar !== 'default_avatar.png' && is_file(dirname(__DIR__) . '/uploads/avatars/' . basename($avatar))) return $prefix . 'uploads/avatars/' . rawurlencode(basename($avatar));
    return 'https://ui-avatars.com/api/?background=0b1c3f&color=fff&bold=true&name=' . urlencode($name);
}
function type_label(string $type): string {
    return t(['online' => 'course_online', 'offline' => 'course_offline', 'online_live' => 'course_online_live'][$type] ?? 'course_online');
}
function type_icon(string $type): string {
    return ['online' => 'fa-play-circle', 'offline' => 'fa-building', 'online_live' => 'fa-satellite-dish'][$type] ?? 'fa-play-circle';
}
function stars(float $r): string {
    $o = '';
    for ($i = 1; $i <= 5; $i++) $o .= '<i class="fa-' . ($r >= $i - 0.25 ? 'solid' : 'regular') . ' fa-star"></i>';
    return $o;
}
/** يبحث عن شهادة في النظام الجديد ثم في الأرشيف القديم */
function find_certificate(string $code): ?array {
    global $pdo;
    $code = trim($code);
    if ($code === '' || strlen($code) > 50) return null;
    $st = $pdo->prepare("SELECT e.certificate_code, e.issue_date, e.grade, u.full_name, u.last_name, u.country AS nationality, c.title_ar, c.title_en
        FROM certificate_exams e JOIN users u ON e.trainee_id=u.id JOIN courses c ON e.course_id=c.id
        WHERE e.certificate_code = ? AND e.status='passed' LIMIT 1");
    $st->execute([$code]);
    if ($r = $st->fetch()) {
        return ['code' => $r['certificate_code'], 'date' => $r['issue_date'], 'grade' => $r['grade'], 'name' => trim($r['full_name'] . ' ' . $r['last_name']),
                'nationality' => $r['nationality'], 'course' => $r['title_ar'], 'course_en' => $r['title_en'], 'legacy' => false];
    }
    $st = $pdo->prepare("SELECT certificate_code, issue_date, grade, student_name, nationality, specialty FROM legacy_certificates WHERE certificate_code = ? LIMIT 1");
    $st->execute([$code]);
    if ($r = $st->fetch()) {
        return ['code' => $r['certificate_code'], 'date' => $r['issue_date'], 'grade' => $r['grade'], 'name' => $r['student_name'],
                'nationality' => $r['nationality'], 'course' => $r['specialty'], 'course_en' => $r['specialty'], 'legacy' => true];
    }
    return null;
}
function countries_list(): array {
    return ['الجزائر' => 'Algeria', 'تونس' => 'Tunisia', 'المغرب' => 'Morocco', 'مصر' => 'Egypt', 'السعودية' => 'Saudi Arabia', 'الإمارات' => 'UAE', 'قطر' => 'Qatar',
            'الكويت' => 'Kuwait', 'ماليزيا' => 'Malaysia', 'تركيا' => 'Türkiye', 'فرنسا' => 'France', 'إسبانيا' => 'Spain', 'أخرى' => 'Other'];
}
function country_label(string $ar): string {
    global $lang;
    return $lang === 'ar' ? $ar : (countries_list()[$ar] ?? $ar);
}
function country_flag(string $ar): string {
    return ['الجزائر'=>'🇩🇿','السعودية'=>'🇸🇦','مصر'=>'🇪🇬','الإمارات'=>'🇦🇪','المغرب'=>'🇲🇦','تونس'=>'🇹🇳','ماليزيا'=>'🇲🇾','قطر'=>'🇶🇦','تركيا'=>'🇹🇷','فرنسا'=>'🇫🇷','إسبانيا'=>'🇪🇸','الكويت'=>'🇰🇼'][$ar] ?? '🌍';
}
function audit(string $action, string $detail = ''): void {
    global $pdo;
    try { $pdo->prepare("INSERT INTO audit_log (user_id, action, detail, ip) VALUES (?,?,?,?)")->execute([$_SESSION['user_id'] ?? null, $action, mb_substr($detail, 0, 250), client_ip()]); } catch (Throwable $e) {}
}
function notify_user(int $userId, string $title, string $body = '', string $link = ''): void {
    global $pdo;
    try { $pdo->prepare("INSERT INTO user_notifications (user_id, title, body, link) VALUES (?,?,?,?)")->execute([$userId, $title, mb_substr($body, 0, 480), $link]); } catch (Throwable $e) {}
}
function strong_password(string $p): bool { return strlen($p) >= 8 && preg_match('/[A-Za-z\p{L}]/u', $p) && preg_match('/\d/', $p); }
function url_ok_pub(?string $u): bool { return (bool)$u && (bool)filter_var($u, FILTER_VALIDATE_URL) && (bool)preg_match('#^https?://#i', $u); }
