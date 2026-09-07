<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/OrderService.php';
require_once __DIR__ . '/../includes/OrderScreenshotStorage.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireStaff();

$orderId = (int) ($_GET['id'] ?? 0);
$index = max(0, (int) ($_GET['i'] ?? 0));
$pdo = Database::getConnection();
$order = OrderService::getById($pdo, $orderId);

if (!$order || !OrderService::isParticipant($order, (int) Auth::id())) {
    http_response_code(404);
    exit('截图不存在');
}

$keys = parseScreenshotKeys($order['screenshot_key'] ?? null);
if (!isset($keys[$index])) {
    http_response_code(404);
    exit('截图不存在');
}

$storage = new OrderScreenshotStorage();
redirect($storage->getAccessUrl($keys[$index]));
