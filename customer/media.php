<?php

declare(strict_types=1);

/** 顾客端公开查看：仅可接单打手的毛照 / 荣誉图 */
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/StaffPhotoService.php';
require_once __DIR__ . '/../includes/StaffPhotoStorage.php';

$pdo = Database::getConnection();
$type = (string) ($_GET['type'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
$storage = new StaffPhotoStorage();
$key = null;
$staffId = 0;

try {
    if ($type === 'photo') {
        $photo = StaffPhotoService::getById($pdo, $id);
        if (!$photo) {
            http_response_code(404);
            exit('不存在');
        }
        $staffId = (int) $photo['staff_id'];
        $key = $photo['photo_key'];
    } elseif ($type === 'honor') {
        $stmt = $pdo->prepare(
            'SELECT i.image_key, h.staff_id FROM staff_honor_images i
             JOIN staff_honors h ON h.id = i.honor_id
             WHERE i.id = ? AND i.deleted_at IS NULL AND h.deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('不存在');
        }
        $staffId = (int) $row['staff_id'];
        $key = $row['image_key'];
    } else {
        http_response_code(400);
        exit('参数错误');
    }
} catch (Throwable) {
    http_response_code(500);
    exit('读取失败');
}

if (!ClientOrderService::getAcceptingStaff($pdo, $staffId)) {
    http_response_code(403);
    exit('无权查看');
}

try {
    redirect($storage->getAccessUrl($key));
} catch (Throwable) {
    http_response_code(404);
    exit('文件不存在');
}
