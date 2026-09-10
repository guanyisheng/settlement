<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/StaffPhotoService.php';
require_once __DIR__ . '/../includes/StaffPhotoStorage.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireAdminAccess();
// 提现放款 / 用户中心 / 打手资料 均可看收款码与毛照（勿仅限旧 staff 菜单）
$canViewMedia = Auth::canAccessPage('staff')
    || Auth::canAccessPage('users')
    || Auth::canAccessPage('withdrawals')
    || Auth::canAccessPage('registrations')
    || Auth::can('photo.view')
    || Auth::can('withdrawal.process')
    || Auth::can('staff.view')
    || Auth::can('user.view')
    || Auth::isBoss();
if (!$canViewMedia) {
    flash('error', '无权限查看该图片');
    redirect(Auth::adminHomeUrl());
}

$pdo = Database::getConnection();
$storage = new StaffPhotoStorage();
$key = null;

$photoId = (int) ($_GET['photo_id'] ?? 0);
$honorImageId = (int) ($_GET['honor_image_id'] ?? 0);
$staffId = (int) ($_GET['id'] ?? 0);
$wantPayQr = !empty($_GET['pay_qr']);

try {
    if ($wantPayQr && $staffId > 0) {
        // 必须用 getById：多角色用户 legacy role 可能不是 STAFF，getStaffById 会误判 404
        $staff = UserService::getById($pdo, $staffId);
        if (!$staff || !empty($staff['deleted_at'])) {
            http_response_code(404);
            exit('用户不存在');
        }
        if (empty($staff['pay_qr_key'])) {
            http_response_code(404);
            exit('收款码不存在');
        }
        $key = (string) $staff['pay_qr_key'];
    } elseif ($photoId > 0) {
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
        $staff = UserService::getById($pdo, $staffId);
        if (!$staff || !empty($staff['deleted_at']) || empty($staff['photo_key'])) {
            http_response_code(404);
            exit('毛照不存在');
        }
        $key = (string) $staff['photo_key'];
    } else {
        http_response_code(400);
        exit('参数错误');
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('读取失败');
}

if ($key === null || $key === '') {
    http_response_code(404);
    exit('文件不存在');
}

try {
    redirect($storage->getAccessUrl($key));
} catch (Throwable $e) {
    http_response_code(502);
    exit('存储访问失败：请检查 COS 配置是否与上传时一致');
}
