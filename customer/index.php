<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/BusinessTypeService.php';
require_once __DIR__ . '/../includes/MembershipService.php';
require_once __DIR__ . '/../includes/ExtraFeeService.php';

$ready = ClientOrderService::isReady($pdo);
$types = BusinessTypeService::getAll($pdo, true);
$extraFeeMap = ExtraFeeService::mapEnabledByBusinessType($pdo, $types);

$currentPage = 'home';
$pageTitle = '首页 / 业务';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="customer-hero">
    <h2><?= e(brandName()) ?> · 在线下单</h2>
    <p>选业务进入下单；也可去「打手广场」指定打手。手机点左上角 ☰ 打开菜单。</p>
</div>

<?php if (!$ready): ?>
    <div class="alert alert-error">请先执行 database/migrate_customer_portal.sql</div>
<?php elseif ($types === []): ?>
    <div class="empty-box">暂无上架业务</div>
<?php else: ?>
    <div class="card">
        <div class="card-header"><h2>全部业务（<?= count($types) ?>）</h2></div>
        <div class="card-body">
            <div class="product-grid">
                <?php foreach ($types as $t): ?>
                    <?php $feeCount = count($extraFeeMap[(int) $t['id']] ?? []); ?>
                    <a class="product-card" href="/customer/order.php?bt=<?= (int) $t['id'] ?>">
                        <div class="product-card-cover">
                            <span class="product-card-badge"><?= $feeCount > 0 ? '可加购' : '标准价' ?></span>
                        </div>
                        <div class="product-card-body">
                            <div class="product-card-name"><?= e($t['name']) ?></div>
                            <?php if (!empty($t['remark'])): ?>
                                <div class="product-card-meta"><?= e(mb_strimwidth((string) $t['remark'], 0, 40, '…')) ?></div>
                            <?php endif; ?>
                            <div class="product-card-foot">
                                <span class="price">¥<?= number_format((float) $t['unit_price'], 2) ?></span>
                                <span class="badge badge-settled">下单</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
