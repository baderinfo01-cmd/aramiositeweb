<?php
require_once __DIR__ . '/_bootstrap.php';
$in = api_guard();
$email = trim((string)($in['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) json_out(['ok' => false, 'message' => t('err_email')], 422);
if (!rate_limit('newsletter', 5, 3600)) json_out(['ok' => false, 'message' => t('err_too_many')], 429);
$pdo->prepare("INSERT IGNORE INTO newsletter_subscribers (email, lang) VALUES (?, ?)")->execute([$email, $lang]);
json_out(['ok' => true]);
