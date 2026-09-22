<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/StaffPhotoService.php';
require_once __DIR__ . '/../includes/HonorService.php';

$id = (int) ($_GET['id'] ?? 0);
$staff = ClientOrderService::getStaffPublic($pdo, $id);
if (!$staff) {
    flash('error', '打手不存在或暂不接单');
    redirect('/customer/staff.php');
}
$stats = ClientOrderService::staffReviewStats($pdo, $id);
$photos = [];
$honors = [];
try { $photos = StaffPhotoService::listByStaff($pdo, $id); } catch (Throwable) {}
try { $honors = HonorService::listByStaff($pdo, $id); } catch (Throwable) {}
$reviews = [];
try {
    $stmt = $pdo->prepare(
        "SELECT r.*, c.nickname AS client_name FROM client_order_reviews r
         JOIN users c ON c.id = r.client_id WHERE r.staff_id = ? ORDER BY r.id DESC LIMIT 20"
    );
    $stmt->execute([$id]);
    $reviews = $stmt->fetchAll();
} catch (PDOException) {
}

$currentPage = 'staff';
$pageTitle = ($staff['nickname'] ?: $staff['username']) . ' · 打手主页';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<p style="margin-bottom:12px"><a class="btn btn-sm btn-back" href="/customer/staff.php">← 返回广场</a></p>

<div class="card">
    <div class="card-header">
        <h2><?= e($staff['nickname'] ?: $staff['username']) ?></h2>
        <a class="btn btn-primary" href="/customer/order.php?staff=<?= $id ?>">指定 Ta 下单</a>
    </div>
    <div class="card-body">
        <p style="color:var(--text-muted);font-size:13px;margin-bottom:8px">
            评分 <?= $stats['avg'] ?>（<?= $stats['count'] ?> 条）
            <?php if (!empty($staff['contact_wechat'])): ?> · 微信 <?= e($staff['contact_wechat']) ?><?php endif; ?>
        </p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>毛照</h2></div>
    <div class="card-body">
        <?php if ($photos === []): ?>
            <div class="empty-box">暂无毛照</div>
        <?php else: ?>
            <div class="photo-grid">
                <?php foreach ($photos as $p): ?>
                    <img src="/customer/media.php?type=photo&id=<?= (int) $p['id'] ?>" alt="">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>荣誉墙</h2></div>
    <div class="card-body">
        <?php if ($honors === []): ?>
            <div class="empty-box">暂无荣誉</div>
        <?php else: ?>
            <?php foreach ($honors as $h): ?>
                <div class="order-item" style="cursor:default">
                    <strong><?= e($h['title']) ?></strong>
                    <?php if (!empty($h['remark'])): ?><div class="product-card-meta"><?= e($h['remark']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>评价</h2></div>
    <div class="card-body">
        <?php if ($reviews === []): ?>
            <div class="empty-box">暂无评价</div>
        <?php else: ?>
            <?php foreach ($reviews as $r): ?>
                <div class="order-item" style="cursor:default">
                    <div class="order-item-top">
                        <strong><?= (int) $r['score'] ?> 星</strong>
                        <span style="color:var(--text-muted)"><?= e($r['client_name'] ?: '顾客') ?></span>
                    </div>
                    <div><?= e($r['content'] ?: '（无文字）') ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
