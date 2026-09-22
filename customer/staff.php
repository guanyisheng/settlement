<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/StaffPhotoService.php';

$q = trim((string) ($_GET['q'] ?? ''));
$ready = ClientOrderService::isReady($pdo);
$staffList = $ready ? ClientOrderService::listAcceptingStaff($pdo) : [];
if ($q !== '') {
    $staffList = array_values(array_filter($staffList, static function ($s) use ($q) {
        $hay = ($s['nickname'] ?? '') . ' ' . ($s['username'] ?? '') . ' ' . ($s['contact_wechat'] ?? '');
        return mb_stripos($hay, $q) !== false;
    }));
}

$currentPage = 'staff';
$pageTitle = '打手广场';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<div class="card">
    <div class="card-header"><h2>可接单打手（<?= count($staffList) ?>）</h2></div>
    <div class="card-body">
        <form method="get" class="filter-bar">
            <div class="form-group" style="flex:1;min-width:180px">
                <label>搜索</label>
                <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="昵称 / 用户名">
            </div>
            <button class="btn btn-primary" type="submit">搜索</button>
        </form>
        <?php if ($staffList === []): ?>
            <div class="empty-box">暂无打手</div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($staffList as $s): ?>
                    <?php
                    $sid = (int) $s['id'];
                    $stats = ClientOrderService::staffReviewStats($pdo, $sid);
                    $photoUrl = '';
                    try {
                        $photos = StaffPhotoService::listByStaff($pdo, $sid);
                        if ($photos !== []) {
                            $photoUrl = '/customer/media.php?type=photo&id=' . (int) $photos[0]['id'];
                        }
                    } catch (Throwable) {
                    }
                    ?>
                    <a class="product-card" href="/customer/staff_detail.php?id=<?= $sid ?>">
                        <div class="product-card-cover" style="<?= $photoUrl !== '' ? 'background-image:url(' . e($photoUrl) . ')' : '' ?>">
                            <span class="product-card-badge"><?= $stats['count'] > 0 ? $stats['avg'] . '分' : '新打手' ?></span>
                        </div>
                        <div class="product-card-body">
                            <div class="product-card-name"><?= e($s['nickname'] ?: $s['username']) ?></div>
                            <div class="product-card-meta"><?= !empty($s['contact_wechat']) ? '微信 ' . e($s['contact_wechat']) : '可接单' ?></div>
                            <div class="product-card-foot">
                                <span class="badge badge-settled">主页</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
