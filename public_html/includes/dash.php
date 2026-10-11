<?php
/** دوال مساعدة للوحات التحكم */
function d(string $ar, string $en): string { global $lang; return $lang === 'ar' ? $ar : $en; }

function dash_menu(string $role): array {
    $m = [
      'admin' => [
        ['index.php', 'fa-gauge-high', d('الرئيسية', 'Overview')],
        ['ai_studio.php', 'fa-wand-magic-sparkles', d('استوديو الذكاء الاصطناعي', 'AI Studio'), 'ai'],
        ['#', '', d('الأكاديمية', 'Academy')],
        ['categories.php', 'fa-tags', d('تصنيفات الدورات', 'Categories')],
        ['courses.php', 'fa-photo-film', d('إدارة الدورات', 'Courses')],
        ['users.php', 'fa-users-gear', d('المستخدمون', 'Users')],
        ['live_events.php', 'fa-earth-asia', d('مسجلو الملتقيات', 'Event leads')],
        ['legacy_certs.php', 'fa-certificate', d('أرشيف الشهادات', 'Certificates archive')],
        ['#', '', d('المالية', 'Finance')],
        ['financial.php', 'fa-file-invoice-dollar', d('طلبات الدورات', 'Course orders')],
        ['consultations.php', 'fa-handshake', d('مدفوعات الاستشارات', 'Consultations')],
        ['expenses.php', 'fa-money-bill-trend-up', d('المصاريف والأرباح', 'Expenses & profit')],
        ['#', '', d('الواجهة والمحتوى', 'Website & content')],
        ['settings.php', 'fa-sliders', d('الإعدادات العامة', 'Settings')],
        ['statistics.php', 'fa-chart-simple', d('الإحصائيات المعروضة', 'Public statistics')],
        ['testimonials.php', 'fa-comments', d('آراء المتدربين', 'Testimonials')],
        ['faqs.php', 'fa-circle-question', d('الأسئلة الشائعة', 'FAQ')],
        ['notifications.php', 'fa-bell', d('الإشعارات المنبثقة', 'Popup notices')],
        ['messages.php', 'fa-envelope', d('رسائل الزوار', 'Messages'), 'msg'],
        ['newsletter.php', 'fa-paper-plane', d('النشرة البريدية', 'Newsletter')],
        ['audit.php', 'fa-shield-halved', d('سجل الأمان', 'Security log')],
      ],
      'trainer' => [
        ['index.php', 'fa-gauge-high', d('الرئيسية', 'Overview')],
        ['ai_assistant.php', 'fa-wand-magic-sparkles', d('مساعد المدرب الذكي', 'AI Teaching Assistant'), 'ai'],
        ['my_courses.php', 'fa-photo-film', d('دوراتي', 'My courses')],
        ['quizzes.php', 'fa-list-check', d('الاختبارات', 'Quizzes')],
        ['students.php', 'fa-user-graduate', d('طلابي', 'My students')],
        ['certificates.php', 'fa-award', d('الشهادات والامتحانات', 'Certificates & exams')],
        ['schedule.php', 'fa-calendar-days', d('جدول الاستشارات', 'Consultation schedule')],
        ['earnings.php', 'fa-wallet', d('الأرباح', 'Earnings')],
        ['settings.php', 'fa-user-gear', d('إعداداتي', 'Settings')],
      ],
      'trainee' => [
        ['index.php', 'fa-gauge-high', d('الرئيسية', 'Overview')],
        ['ai_tutor.php', 'fa-wand-magic-sparkles', d('المعلّم الذكي', 'AI Tutor'), 'ai'],
        ['store.php', 'fa-cart-shopping', d('متجر الدورات', 'Course store')],
        ['my_courses.php', 'fa-book-open-reader', d('دوراتي', 'My courses')],
        ['my_certificates.php', 'fa-award', d('شهاداتي', 'My certificates')],
        ['consultations.php', 'fa-handshake', d('الاستشارات', 'Consultations')],
        ['payments.php', 'fa-receipt', d('مدفوعاتي', 'Payments')],
        ['settings.php', 'fa-user-gear', d('إعداداتي', 'Settings')],
      ],
    ];
    return $m[$role] ?? [];
}

function status_badge(string $status): string {
    $map = [
      'published' => ['st-ok', d('منشورة', 'Published')], 'approved' => ['st-ok', d('مقبول', 'Approved')], 'passed' => ['st-ok', d('ناجح', 'Passed')], 'completed' => ['st-ok', d('مكتمل', 'Completed')], 'accepted' => ['st-ok', d('مقبول', 'Accepted')],
      'pending' => ['st-warn', d('قيد المراجعة', 'Pending')], 'reserved' => ['st-info', d('محجوز', 'Reserved')], 'draft' => ['st-mute', d('مسودة', 'Draft')], 'pending_trainer' => ['st-warn', d('بانتظار المدرب', 'Awaiting trainer')],
      'rejected' => ['st-bad', d('مرفوض', 'Rejected')], 'canceled' => ['st-mute', d('ملغى', 'Canceled')], 'new' => ['st-info', d('جديد', 'New')], 'contacted' => ['st-warn', d('تم التواصل', 'Contacted')], 'paid' => ['st-ok', d('مدفوع', 'Paid')],
    ];
    [$cls, $label] = $map[$status] ?? ['st-mute', $status];
    return '<span class="st ' . $cls . '">' . e($label) . '</span>';
}

/** زر إجراء عبر POST + CSRF (بديل روابط GET الخطرة). $fields حقول مخفية إضافية */
function action_btn(string $label, array $fields, string $cls = 'btn-soft-info', string $confirm = '', string $icon = ''): string {
    $h = '<form method="post" class="d-inline"' . ($confirm ? ' onsubmit="return confirm(' . e(json_encode($confirm, JSON_UNESCAPED_UNICODE)) . ')"' : '') . '>' . csrf_field();
    foreach ($fields as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return $h . '<button class="btn-soft ' . $cls . '" type="submit">' . ($icon ? '<i class="fa-solid ' . $icon . '"></i> ' : '') . e($label) . '</button></form>';
}

function money_usd($v): string { return '$' . number_format((float)$v, 2); }
function money_dzd($v): string { return number_format((float)$v, 0) . ' ' . d('د.ج', 'DZD'); }
function wa_link(?string $num, string $text = ''): string {
    $n = preg_replace('/\D/', '', (string)$num);
    return $n ? 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '') : '';
}
function unread_count(): int {
    global $pdo, $me;
    if (!$me) return 0;
    try { $s = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id=? AND is_read=0"); $s->execute([$me['id']]); return (int)$s->fetchColumn(); } catch (Throwable $e) { return 0; }
}
