<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';

if (!$isClient) {
    flash('error', '请先登录顾客账号');
    redirect('/login.php?next=' . rawurlencode('/customer/orders.php'));
}

$orders = ClientOrderService::isReady($pdo) ? ClientOrderService::listForClient($pdo, $uid) : [];
$currentPage = 'orders';
$pageTitle = '我的订单';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2>我的订单（<?= count($orders) ?>）</h2></div>
    <div class="card-body">
        <?php if ($orders === []): ?>
            <div class="empty-box">暂无订单 · <a href="/customer/index.php">去选业务</a></div>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <a class="order-item" href="/customer/order_detail.php?id=<?= (int) $o['id'] ?>">
                    <div class="order-item-top">
                        <strong><?= e($o['order_no']) ?></strong>
                        <span class="badge badge-settled"><?= e(ClientOrderService::statusLabel((string) $o['status'])) ?></span>
                    </div>
                    <div><?= e($o['business_type_name']) ?> ×<?= (int) $o['quantity'] ?></div>
                    <div class="price" style="margin-top:4px">¥<?= formatMoney($o['amount']) ?></div>
                    <div class="product-card-meta">打手：<?= e($o['staff_name'] ?: '待接 / 抢单池') ?></div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
