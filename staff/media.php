<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/StaffPhotoService.php';
require_once __DIR__ . '/../includes/StaffPhotoStorage.php';
require_once __DIR__ . '/../includes/HonorService.php';

Auth::requireStaff();

$pdo = Database::getConnection();
$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);
$uid = (int) Auth::id();
$storage = new StaffPhotoStorage();
$key = null;

try {
    if ($type === 'photo') {
        $photo = StaffPhotoService::getById($pdo, $id);
        if (!$photo || (int) $photo['staff_id'] !== $uid) {
            http_response_code(403);
            exit('无权查看');
        }
        $key = $photo['photo_key'];
    } elseif ($type === 'honor') {
        $stmt = $pdo->prepare(
            'SELECT i.*, h.staff_id FROM staff_honor_images i
             JOIN staff_honors h ON h.id = i.honor_id
             WHERE i.id = ? AND i.deleted_at IS NULL AND h.deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['staff_id'] !== $uid) {
            http_response_code(403);
            exit('无权查看');
        }
        $key = $row['image_key'];
    } else {
        http_response_code(400);
        exit('参数错误');
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('读取失败');
}

redirect($storage->getAccessUrl($key));
