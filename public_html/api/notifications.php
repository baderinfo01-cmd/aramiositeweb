<?php
require_once __DIR__ . '/_bootstrap.php';
$in = api_guard();
if (!$me) json_out(['ok' => false], 401);
if (($in['action'] ?? '') === 'read_all') $pdo->prepare("UPDATE user_notifications SET is_read=1 WHERE user_id=?")->execute([$me['id']]);
json_out(['ok' => true]);
