<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/UserService.php';
require_once __DIR__ . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => '请求方式错误'], 405);
}

$username = trim((string) ($_GET['username'] ?? $_POST['username'] ?? ''));
$result = UserService::checkUsername($username);

jsonResponse([
    'ok'        => $result['available'],
    'available' => $result['available'],
    'message'   => $result['message'],
]);
