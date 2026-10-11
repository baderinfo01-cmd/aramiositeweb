<?php
/**
 * طبقة الذكاء الاصطناعي — مستقلة عن المزوّد (Anthropic / OpenAI / Gemini / أي خدمة متوافقة مع OpenAI).
 * المفتاح يُقرأ من config.local.php أو من لوحة التحكم (مخزّن مشفّرًا في قاعدة البيانات).
 */

function ai_secret(): string { return hash('sha256', (string)app_config('app_secret', 'change-me'), true); }
function ai_encrypt(string $plain): string {
    if ($plain === '') return '';
    $iv = random_bytes(16);
    return base64_encode($iv . openssl_encrypt($plain, 'aes-256-cbc', ai_secret(), OPENSSL_RAW_DATA, $iv));
}
function ai_decrypt(string $enc): string {
    $raw = base64_decode($enc, true);
    if ($raw === false || strlen($raw) < 17) return '';
    return (string)openssl_decrypt(substr($raw, 16), 'aes-256-cbc', ai_secret(), OPENSSL_RAW_DATA, substr($raw, 0, 16));
}

function ai_config(): array {
    $p = app_config('ai_provider') ?: getSetting(null, 'ai_provider');
    $key = app_config('ai_api_key') ?: ai_decrypt((string)getSetting(null, 'ai_api_key_enc'));
    $model = app_config('ai_model') ?: getSetting(null, 'ai_model');
    $base = app_config('ai_base_url') ?: getSetting(null, 'ai_base_url');
    $defaults = ['anthropic' => 'claude-sonnet-5-5', 'openai' => 'gpt-4o-mini', 'gemini' => 'gemini-2.0-flash', 'compatible' => ''];
    return [
        'provider' => $p, 'key' => $key, 'base' => $base,
        'model' => $model ?: ($defaults[$p] ?? ''),
        'enabled' => getSetting(null, 'ai_enabled') !== '0',
    ];
}
function ai_available(): bool {
    $c = ai_config();
    return $c['enabled'] && $c['provider'] && ($c['key'] || $c['provider'] === 'compatible' && $c['base']);
}

function ai_http(string $url, array $headers, array $body): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'error' => 'network: ' . $err];
    $j = json_decode($res, true);
    if ($code >= 400 || !is_array($j)) return ['ok' => false, 'error' => 'HTTP ' . $code . ': ' . substr(is_array($j) ? ($j['error']['message'] ?? json_encode($j)) : $res, 0, 200)];
    return ['ok' => true, 'json' => $j];
}

/**
 * @param array $messages [['role'=>'user|assistant','content'=>'...'], ...]
 * @return array ['ok'=>bool,'text'=>string,'error'=>string]
 */
function ai_chat(array $messages, string $system = '', array $opt = []): array {
    global $pdo;
    $c = ai_config();
    $feature = $opt['feature'] ?? 'chat';
    if (!ai_available()) return ['ok' => false, 'text' => '', 'error' => 'not_configured'];
    $max = (int)($opt['max_tokens'] ?? 1200);
    $temp = $opt['temperature'] ?? 0.5;
    $r = ['ok' => false, 'error' => 'unknown'];

    switch ($c['provider']) {
        case 'anthropic':
            $r = ai_http('https://api.anthropic.com/v1/messages', ['x-api-key: ' . $c['key'], 'anthropic-version: 2023-06-01'],
                ['model' => $c['model'], 'max_tokens' => $max, 'temperature' => $temp, 'system' => $system, 'messages' => $messages]);
            if ($r['ok']) { $t = ''; foreach ($r['json']['content'] ?? [] as $b) if (($b['type'] ?? '') === 'text') $t .= $b['text']; $r['text'] = $t; }
            break;
        case 'gemini':
            $contents = [];
            foreach ($messages as $m) $contents[] = ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $m['content']]]];
            $r = ai_http('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($c['model']) . ':generateContent', ['x-goog-api-key: ' . $c['key']],
                ['systemInstruction' => ['parts' => [['text' => $system]]], 'contents' => $contents, 'generationConfig' => ['maxOutputTokens' => $max, 'temperature' => $temp]]);
            if ($r['ok']) $r['text'] = $r['json']['candidates'][0]['content']['parts'][0]['text'] ?? '';
            break;
        case 'openai':
        case 'compatible':
            $base = $c['provider'] === 'openai' ? 'https://api.openai.com/v1' : rtrim($c['base'], '/');
            $msgs = array_merge($system !== '' ? [['role' => 'system', 'content' => $system]] : [], $messages);
            $r = ai_http($base . '/chat/completions', $c['key'] ? ['Authorization: Bearer ' . $c['key']] : [],
                ['model' => $c['model'], 'messages' => $msgs, 'max_tokens' => $max, 'temperature' => $temp]);
            if ($r['ok']) $r['text'] = $r['json']['choices'][0]['message']['content'] ?? '';
            break;
    }
    $ok = !empty($r['ok']) && trim($r['text'] ?? '') !== '';
    try {
        $pdo->prepare("INSERT INTO ai_logs (user_id, feature, provider, prompt_chars, reply_chars, ok, error) VALUES (?,?,?,?,?,?,?)")
            ->execute([$_SESSION['user_id'] ?? null, $feature, $c['provider'], strlen($system . json_encode($messages)), strlen($r['text'] ?? ''), $ok ? 1 : 0, $ok ? null : substr($r['error'] ?? 'empty', 0, 250)]);
    } catch (Throwable $e) {}
    return ['ok' => $ok, 'text' => trim($r['text'] ?? ''), 'error' => $ok ? '' : ($r['error'] ?? 'empty')];
}

/** يطلب ردًا بصيغة JSON ويحلله (يتسامح مع أسوار ```json) */
function ai_json(string $prompt, string $system = '', array $opt = []): ?array {
    $r = ai_chat([['role' => 'user', 'content' => $prompt]], $system . "\nReturn ONLY valid JSON, no markdown fences, no commentary.", $opt + ['temperature' => 0.3, 'max_tokens' => 2500]);
    if (!$r['ok']) return null;
    $t = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $r['text']);
    $s = strpos($t, '{'); $a = strpos($t, '[');
    if ($s === false || ($a !== false && $a < $s)) $s = $a;
    if ($s === false) return null;
    $e = max((int)strrpos($t, '}'), (int)strrpos($t, ']'));
    $j = json_decode(substr($t, $s, $e - $s + 1), true);
    return is_array($j) ? $j : null;
}

function ai_lang_name(string $l): string {
    return ['ar' => 'Arabic', 'en' => 'English', 'fr' => 'French', 'tr' => 'Turkish', 'es' => 'Spanish', 'ms' => 'Malay'][$l] ?? 'English';
}

/** سياق الموقع الذي يعرفه المساعد (الدورات المنشورة + معلومات التواصل + الأسئلة الشائعة) */
function ai_site_context(string $lang): string {
    global $pdo, $siteName;
    $ctx = "Academy: $siteName.\nContact: " . getSetting(null, 'contact_email') . ' / ' . getSetting(null, 'phone_number') . "\n";
    $about = trim(strip_tags((string)getSetting(null, 'about_us', $lang)));
    if ($about) $ctx .= "About: " . mb_substr($about, 0, 700) . "\n";
    $rows = $pdo->query("SELECT c.id, c.title_ar, c.title_en, c.training_type, c.price_usd, c.start_date, c.city, cat.name_ar, cat.name_en, u.full_name trainer
        FROM courses c JOIN categories cat ON cat.id=c.category_id JOIN users u ON u.id=c.trainer_id WHERE c.status='published' ORDER BY c.id DESC LIMIT 40")->fetchAll();
    $ctx .= "Published courses (id | title | category | type | price USD | trainer | start):\n";
    foreach ($rows as $c) {
        $title = $c['title_' . ($lang === 'ar' ? 'ar' : 'en')] ?: $c['title_ar'];
        $cat = $c['name_' . ($lang === 'ar' ? 'ar' : 'en')] ?: $c['name_ar'];
        $ctx .= "{$c['id']} | $title | $cat | {$c['training_type']} | {$c['price_usd']} | {$c['trainer']} | {$c['start_date']}\n";
    }
    try {
        $f = $pdo->query("SELECT question_ar, answer_ar, question_en, answer_en FROM faqs WHERE status=1 ORDER BY sort_order LIMIT 12")->fetchAll();
        $ctx .= "FAQ:\n";
        foreach ($f as $q) $ctx .= '- ' . ($lang === 'ar' ? $q['question_ar'] . ' → ' . $q['answer_ar'] : $q['question_en'] . ' → ' . $q['answer_en']) . "\n";
    } catch (Throwable $e) {}
    $ctx .= "Course page URL pattern: course.php?id=<id>. Certificate verification: verify_certificate.php. Registration: register.php. Consultations are available inside the trainee dashboard.\n";
    return $ctx;
}

/** محرك توصية بسيط يعمل بدون أي مزوّد (احتياطي): تطابق كلمات */
function ai_keyword_recommend(string $query, string $lang, int $limit = 4): array {
    global $pdo;
    $words = array_filter(preg_split('/[\s,،.؟?!]+/u', mb_strtolower($query)), fn($w) => mb_strlen($w) >= 3);
    $rows = $pdo->query("SELECT c.*, cat.name_ar, cat.name_en FROM courses c JOIN categories cat ON cat.id=c.category_id WHERE c.status='published'")->fetchAll();
    $scored = [];
    foreach ($rows as $r) {
        $hay = mb_strtolower(implode(' ', [$r['title_ar'], $r['title_en'], strip_tags((string)$r['description_ar']), strip_tags((string)$r['description_en']), $r['name_ar'], $r['name_en']]));
        $s = 0; foreach ($words as $w) if (mb_strpos($hay, $w) !== false) $s++;
        $scored[] = [$s, $r];
    }
    usort($scored, fn($a, $b) => $b[0] <=> $a[0] ?: $b[1]['id'] <=> $a[1]['id']);
    return array_map(fn($x) => $x[1], array_slice($scored, 0, $limit));
}
