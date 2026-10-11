<?php
/** أدوات الذكاء الاصطناعي للإدارة والمدربين (توليد وصف، ترجمة، اختبارات، شرح، ردود، تحليلات) */
require_once __DIR__ . '/_bootstrap.php';
$in = api_guard();
if (!$me || !in_array($me['role'], ['admin', 'staff', 'trainer'], true)) json_out(['ok' => false, 'message' => 'forbidden'], 403);
if (!ai_available()) json_out(['ok' => false, 'message' => t('ai_unavailable')]);
if (!rate_limit('ai_tools_' . $me['id'], 40, 3600)) json_out(['ok' => false, 'message' => t('ai_limit')], 429);

$action = (string)($in['action'] ?? '');
$isAdmin = in_array($me['role'], ['admin', 'staff'], true);
$txt = fn($k, $n = 1500) => trim(mb_substr((string)($in[$k] ?? ''), 0, $n));
$sys = "You are an expert instructional designer and copywriter for the academy \"$siteName\". Be accurate, professional and culturally appropriate.";
$ownsCourse = function (int $cid) use ($pdo, $me, $isAdmin) {
    $s = $pdo->prepare("SELECT * FROM courses WHERE id=?"); $s->execute([$cid]); $c = $s->fetch();
    return $c && ($isAdmin || (int)$c['trainer_id'] === (int)$me['id']) ? $c : null;
};

switch ($action) {
  case 'describe': {
    $title = $txt('title', 200); $kw = $txt('keywords', 500);
    if ($title === '') json_out(['ok' => false, 'message' => 'title required']);
    $j = ai_json("Write a compelling course description (110-160 words, plain text, with a short list of learning outcomes using '-' bullets) for a course titled \"$title\". Extra notes: \"$kw\".\n"
        . "Return JSON {\"ar\":\"<Arabic version>\",\"en\":\"<English version>\",\"title_ar\":\"<Arabic title>\",\"title_en\":\"<English title>\"}.", $sys, ['feature' => 'describe', 'max_tokens' => 1600]);
    json_out($j ? ['ok' => true, 'data' => $j] : ['ok' => false, 'message' => t('ai_error')]);
  }
  case 'translate': { // يترجم نصوص ويعيدها (بدون حفظ)
    $fields = array_slice((array)($in['fields'] ?? []), 0, 6, true);
    $langs = array_values(array_intersect((array)($in['langs'] ?? []), array_keys(SUPPORTED_LANGS)));
    if (!$fields || !$langs) json_out(['ok' => false, 'message' => 'bad input']);
    $src = []; foreach ($fields as $k => $v) $src[preg_replace('/\W/', '', (string)$k)] = mb_substr(strip_tags((string)$v), 0, 3000);
    $names = implode(', ', array_map('ai_lang_name', $langs));
    $j = ai_json("Translate each field value into these languages: $names (codes: " . implode(',', $langs) . "). Keep meaning and tone; keep proper nouns.\nFields: " . json_encode($src, JSON_UNESCAPED_UNICODE)
        . "\nReturn JSON shaped {\"<lang code>\":{\"<field>\":\"<translation>\"}}.", $sys, ['feature' => 'translate', 'max_tokens' => 3500]);
    json_out($j ? ['ok' => true, 'data' => $j] : ['ok' => false, 'message' => t('ai_error')]);
  }
  case 'translate_course': { // ترجمة دورة وحفظها في جدول translations
    $c = $ownsCourse((int)($in['course_id'] ?? 0));
    if (!$c) json_out(['ok' => false, 'message' => 'forbidden'], 403);
    $langs = ['fr', 'tr', 'es', 'ms'];
    $src = ['title' => $c['title_en'] ?: $c['title_ar'], 'description' => strip_tags((string)($c['description_en'] ?: $c['description_ar']))];
    $j = ai_json("Translate the course title and description into French (fr), Turkish (tr), Spanish (es) and Malay (ms).\nSource: " . json_encode($src, JSON_UNESCAPED_UNICODE)
        . "\nReturn JSON {\"fr\":{\"title\":\"\",\"description\":\"\"},\"tr\":{...},\"es\":{...},\"ms\":{...}}.", $sys, ['feature' => 'translate_course', 'max_tokens' => 3500]);
    if (!$j) json_out(['ok' => false, 'message' => t('ai_error')]);
    $up = $pdo->prepare("INSERT INTO translations (entity, entity_id, field, lang, value) VALUES ('course',?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
    $n = 0;
    foreach ($langs as $l) foreach (['title', 'description'] as $f) if (!empty($j[$l][$f])) { $up->execute([$c['id'], $f, $l, (string)$j[$l][$f]]); $n++; }
    audit('ai_translate_course', (string)$c['id']);
    json_out(['ok' => true, 'saved' => $n]);
  }
  case 'promo': {
    $c = $ownsCourse((int)($in['course_id'] ?? 0));
    if (!$c) json_out(['ok' => false, 'message' => 'forbidden'], 403);
    $j = ai_json("Write promotional social media copy for this course: " . json_encode(['ar' => $c['title_ar'], 'en' => $c['title_en'], 'desc' => mb_substr(strip_tags((string)$c['description_ar'] . ' ' . $c['description_en']), 0, 700), 'price_usd' => $c['price_usd'], 'start' => $c['start_date'], 'link' => base_url('course.php?id=' . $c['id'])], JSON_UNESCAPED_UNICODE)
        . "\nReturn JSON {\"facebook_ar\":\"\",\"instagram_ar\":\"(with hashtags)\",\"whatsapp_ar\":\"\",\"facebook_en\":\"\",\"email_subject_ar\":\"\"}. Include the link in each post. Do not invent discounts.", $sys, ['feature' => 'promo', 'max_tokens' => 1500, 'temperature' => 0.7]);
    json_out($j ? ['ok' => true, 'data' => $j] : ['ok' => false, 'message' => t('ai_error')]);
  }
  case 'bio': {
    $b = $txt('text', 2000);
    $r = ai_chat([['role' => 'user', 'content' => "Rewrite this trainer's professional bio to be polished, credible and engaging (90-140 words), keep ONLY facts present in the text, same language as the original. If the text is empty, write nothing but a short generic placeholder asking to fill details.\n\n\"$b\""]], $sys, ['feature' => 'bio', 'max_tokens' => 500]);
    json_out($r['ok'] ? ['ok' => true, 'text' => $r['text']] : ['ok' => false, 'message' => t('ai_error')]);
  }
  case 'outline': {
    $title = $txt('title', 200); $n = max(4, min(15, (int)($in['count'] ?? 8)));
    if ($title === '') json_out(['ok' => false, 'message' => 'title required']);
    $j = ai_json("Propose a lesson outline of $n lessons for a course titled \"$title\". Return JSON {\"lessons\":[{\"title_ar\":\"\",\"title_en\":\"\"}]}.", $sys, ['feature' => 'outline', 'max_tokens' => 1500]);
    json_out($j ? ['ok' => true, 'data' => $j] : ['ok' => false, 'message' => t('ai_error')]);
  }
  case 'quiz': {
    $c = $ownsCourse((int)($in['course_id'] ?? 0));
    if (!$c) json_out(['ok' => false, 'message' => 'forbidden'], 403);
    $n = max(3, min(15, (int)($in['count'] ?? 8))); $topic = $txt('topic', 400);
    $lessons = $pdo->prepare("SELECT title_ar, title_en FROM lessons WHERE course_id=? ORDER BY lesson_order LIMIT 30"); $lessons->execute([$c['id']]);
    $j = ai_json("Create a multiple-choice quiz of $n questions for the course \"{$c['title_en']}\" / \"{$c['title_ar']}\". Focus: \"$topic\". Lessons: " . json_encode($lessons->fetchAll(), JSON_UNESCAPED_UNICODE)
        . "\nDescription: " . mb_substr(strip_tags((string)$c['description_ar'] . ' ' . $c['description_en']), 0, 1200)
        . "\nWrite questions in " . ai_lang_name($txt('qlang', 5) ?: $lang) . ". Each has exactly 4 options and one correct answer. Return JSON {\"questions\":[{\"question\":\"\",\"options\":[\"\",\"\",\"\",\"\"],\"correct_index\":0,\"explanation\":\"\"}]}.",
        $sys, ['feature' => 'quiz', 'max_tokens' => 3500]);
    json_out($j ? ['ok' => true, 'data' => $j] : ['ok' => false, 'message' => t('ai_error')]);
  }
  case 'reply': {
    if (!$isAdmin) json_out(['ok' => false, 'message' => 'forbidden'], 403);
    $s = $pdo->prepare("SELECT * FROM contact_messages WHERE id=?"); $s->execute([(int)($in['id'] ?? 0)]); $m = $s->fetch();
    if (!$m) json_out(['ok' => false, 'message' => 'not found']);
    $r = ai_chat([['role' => 'user', 'content' => "Visitor message:\n\"{$m['message']}\"\n\nDraft a polite, helpful reply from the academy (max 120 words), in the same language as the visitor's message. Do not invent prices or dates. Sign as \"$siteName team\"."]],
        $sys . "\nFacts:\n" . ai_site_context($lang), ['feature' => 'reply', 'max_tokens' => 500]);
    json_out($r['ok'] ? ['ok' => true, 'text' => $r['text']] : ['ok' => false, 'message' => t('ai_error')]);
  }
  case 'popups': {
    if (!$isAdmin) json_out(['ok' => false, 'message' => 'forbidden'], 403);
    $j = ai_json("Write 5 short social-proof style popup notifications (max 90 chars) for the academy website, in Arabic and English, about people enrolling or certificates being issued. Use generic names (first name + initial) and realistic course topics from this list:\n" . ai_site_context('ar')
        . "\nReturn JSON {\"items\":[{\"ar\":\"\",\"en\":\"\"}]}.", $sys, ['feature' => 'popups', 'max_tokens' => 900, 'temperature' => 0.8]);
    json_out($j ? ['ok' => true, 'data' => $j] : ['ok' => false, 'message' => t('ai_error')]);
  }
  case 'seo': {
    $c = $ownsCourse((int)($in['course_id'] ?? 0));
    if (!$c) json_out(['ok' => false, 'message' => 'forbidden'], 403);
    $r = ai_json("Write an SEO meta description (<=155 chars) and 8 keywords for this course in Arabic and English. Course: " . json_encode(['t' => $c['title_ar'], 'e' => $c['title_en'], 'd' => mb_substr(strip_tags((string)$c['description_ar']), 0, 600)], JSON_UNESCAPED_UNICODE)
        . "\nReturn JSON {\"meta_ar\":\"\",\"meta_en\":\"\",\"keywords\":[\"\"]}.", $sys, ['feature' => 'seo', 'max_tokens' => 600]);
    json_out($r ? ['ok' => true, 'data' => $r] : ['ok' => false, 'message' => t('ai_error')]);
  }
  case 'insights': {
    if (!$isAdmin) json_out(['ok' => false, 'message' => 'forbidden'], 403);
    $q = fn($sql) => $pdo->query($sql)->fetchAll();
    $data = [
      'orders_by_month' => $q("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) n, ROUND(SUM(amount_usd),2) usd FROM course_orders WHERE status='approved' GROUP BY m ORDER BY m DESC LIMIT 12"),
      'top_courses' => $q("SELECT c.title_en t, COUNT(*) n FROM course_orders o JOIN courses c ON c.id=o.course_id WHERE o.status='approved' GROUP BY c.id ORDER BY n DESC LIMIT 5"),
      'order_status' => $q("SELECT status, COUNT(*) n FROM course_orders GROUP BY status"),
      'users_by_country' => $q("SELECT country, COUNT(*) n FROM users WHERE role='trainee' GROUP BY country ORDER BY n DESC LIMIT 6"),
      'pending_courses' => $q("SELECT COUNT(*) n FROM courses WHERE status='pending'"),
    ];
    $r = ai_chat([['role' => 'user', 'content' => "Analyse these academy metrics and give: 1) key insights (3 bullets) 2) risks (2 bullets) 3) five concrete actions to grow revenue next month. Be specific, use numbers. Reply in " . ai_lang_name($lang) . ".\n" . json_encode($data, JSON_UNESCAPED_UNICODE)]],
        $sys, ['feature' => 'insights', 'max_tokens' => 900, 'temperature' => 0.3]);
    json_out($r['ok'] ? ['ok' => true, 'text' => $r['text']] : ['ok' => false, 'message' => t('ai_error')]);
  }
}
json_out(['ok' => false, 'message' => 'unknown action'], 400);
