<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard();
if (!$me || $me['role'] !== 'admin') json_out(['ok' => false, 'message' => 'forbidden'], 403);
if (!ai_available()) json_out(['ok' => false, 'message' => 'AI not configured']);
$r = ai_chat([['role' => 'user', 'content' => 'Reply with exactly: OK']], 'You are a connectivity test.', ['feature' => 'test', 'max_tokens' => 20]);
json_out(['ok' => $r['ok'], 'message' => $r['ok'] ? ('✔ ' . mb_substr($r['text'], 0, 80)) : ('✖ ' . $r['error'])]);
