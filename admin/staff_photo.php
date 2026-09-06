<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/StaffPhotoService.php';
require_once __DIR__ . '/../includes/StaffPhotoStorage.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('staff');

$pdo = Database::getConnection();
$storage = new StaffPhotoStorage();
$key = null;

$photoId = (int) ($_GET['photo_id'] ?? 0);
$honorImageId = (int) ($_GET['honor_image_id'] ?? 0);
$staffId = (int) ($_GET['id'] ?? 0);

try {
    if ($photoId > 0) {
        $photo = StaffPhotoService::getById($pdo, $photoId);
        if (!$photo) {
            http_response_code(404);
            exit('毛照不存在');
        }
        $key = $photo['photo_key'];
    } elseif ($honorImageId > 0) {
        $stmt = $pdo->prepare(
            'SELECT image_key FROM staff_honor_images
             WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$honorImageId]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('荣誉图片不存在');
        }
        $key = $row['image_key'];
    } elseif ($staffId > 0) {
        $staff = UserService::getStaffById($pdo, $staffId);
        if (!$staff || empty($staff['photo_key'])) {
            http_response_code(404);
            exit('毛照不存在');
        }
        $key = $staff['photo_key'];
    } else {
        http_response_code(400);
        exit('参数错误');
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('读取失败');
}

redirect($storage->getAccessUrl($key));
