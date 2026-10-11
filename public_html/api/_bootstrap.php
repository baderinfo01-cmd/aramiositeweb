<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function api_input(): array {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    return is_array($j) ? $j : $_POST;
}
function api_guard(): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'POST only'], 405);
    $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$t)) json_out(['ok' => false, 'message' => 'csrf'], 419);
    return api_input();
}
