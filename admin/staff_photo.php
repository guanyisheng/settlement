<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/StaffPhotoStorage.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('staff');

$staffId = (int) ($_GET['id'] ?? 0);
$pdo = Database::getConnection();
$staff = UserService::getStaffById($pdo, $staffId);

if (!$staff || empty($staff['photo_key'])) {
    http_response_code(404);
    exit('毛照不存在');
}

$storage = new StaffPhotoStorage();
redirect($storage->getAccessUrl($staff['photo_key']));
