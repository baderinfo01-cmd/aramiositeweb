<?php
require_once __DIR__ . '/_bootstrap.php';
$in = api_guard();
$q = trim(mb_substr((string)($in['q'] ?? ''), 0, 300));
if ($q === '') json_out(['ok' => false, 'items' => []]);
if (!rate_limit('ai_reco', 8, 600)) json_out(['ok' => false, 'items' => [], 'message' => t('ai_limit')], 429);

$items = [];
if (ai_available()) {
    $ctx = ai_site_context($lang);
    $res = ai_json("Visitor goal/level (language: " . ai_lang_name($lang) . "): \"$q\"\n\nChoose up to 3 best matching courses from the list below. "
        . "Return JSON: {\"items\":[{\"id\":<course id>,\"why\":\"<one short sentence in " . ai_lang_name($lang) . ">\"}]}. Use ONLY ids that appear in the list. If nothing fits return {\"items\":[]}.\n\n" . $ctx,
        'You match learners to courses.', ['feature' => 'recommend', 'max_tokens' => 700]);
    foreach ((array)($res['items'] ?? []) as $it) {
        $st = $pdo->prepare("SELECT * FROM courses WHERE id=? AND status='published'");
        $st->execute([(int)($it['id'] ?? 0)]);
        if ($c = $st->fetch()) $items[] = ['id' => (int)$c['id'], 'title' => tr_field($c, 'title', 'course'), 'why' => mb_substr((string)($it['why'] ?? ''), 0, 200)];
    }
}
if (!$items) {
    foreach (ai_keyword_recommend($q, $lang, 3) as $c) $items[] = ['id' => (int)$c['id'], 'title' => tr_field($c, 'title', 'course'), 'why' => ''];
}
json_out(['ok' => true, 'items' => $items]);
