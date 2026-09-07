<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/BalanceService.php';
require_once __DIR__ . '/../includes/OrderService.php';
require_once __DIR__ . '/../includes/SettlementService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireStaff();

$pdo = Database::getConnection();
$staffId = Auth::id();
$balance = BalanceService::getBalanceSummary($pdo, $staffId);
$orders = OrderService::getByStaff($pdo, $staffId);

$currentPage = 'orders';
$user = Auth::user();
$pageTitle = brandTitle('我的订单');
$bodyClass = 'has-nav';

require __DIR__ . '/partials/head.php';
?>
<div class="app-shell">
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="top-bar-info">
                <h1>我的订单</h1>
                <p class="subtitle">你好，<?= e($user['nickname']) ?></p>
            </div>
            <?php require __DIR__ . '/partials/user-chip.php'; ?>
        </div>
    </header>

    <div class="balance-hero">
        <div class="balance-hero-label">可提现余额</div>
        <div class="balance-hero-main">
            <span class="currency">¥</span><?= number_format($balance['available_balance'], 2) ?>
        </div>
        <div class="balance-stats balance-stats-3">
            <div class="balance-stat">
                <label>当前余额</label>
                <div class="val money"><?= formatMoney($balance['current_balance']) ?></div>
            </div>
            <div class="balance-stat">
                <label>累计收入</label>
                <div class="val income"><?= formatMoney($balance['total_income']) ?></div>
            </div>
            <div class="balance-stat">
                <label>已提现</label>
                <div class="val"><?= formatMoney($balance['paid_withdrawals']) ?></div>
            </div>
        </div>
    </div>

    <main class="page-content">
        <div class="section-title">订单列表</div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <?= svgIcon('empty-orders', 'empty-icon-svg') ?>
                </div>
                <p>暂无订单记录</p>
                <a href="/staff/report.php" class="btn btn-primary" style="width:auto;display:inline-flex;padding:10px 24px;font-size:14px">
                    去报单
                </a>
            </div>
        <?php else: ?>
            <div class="order-list">
                <?php foreach ($orders as $order): ?>
                <?php
                    $myShare = OrderService::shareForStaff($order, (int) $staffId);
                    $totalPay = (float) ($order['staff_amount'] ?? SettlementService::calcStaffAmount((float) $order['amount']));
                    $isCo = (int) ($order['co_staff_id'] ?? 0) === (int) $staffId
                        && (int) ($order['staff_id'] ?? 0) !== (int) $staffId;
                    $hasCo = (int) ($order['co_staff_id'] ?? 0) > 0;
                ?>
                <article class="order-card <?= orderStatusClass($order['status']) ?>">
                    <div class="order-card-no" title="微信订单编号"><?= e($order['wechat_order_no'] ?? $order['order_no']) ?></div>
                    <div class="order-card-top">
                        <div class="order-card-title"><?= e($order['customer_name']) ?> · <?= e($order['business_type_name']) ?></div>
                        <span class="status-badge <?= orderStatusClass($order['status']) ?>"><?= orderStatusLabel($order['status']) ?></span>
                    </div>
                    <?php if ($hasCo): ?>
                        <div class="order-role-tag <?= $isCo ? '' : 'is-primary' ?>">
                            <?= $isCo
                                ? ('附加打手 · 主打手 ' . e($order['staff_name'] ?? ''))
                                : ('主报单 · 附加 ' . e($order['co_staff_name'] ?? '')) ?>
                        </div>
                    <?php endif; ?>
                    <div class="order-card-amount"><?= formatMoney($myShare) ?></div>
                    <?php if ($hasCo): ?>
                        <div class="order-share-hint">本单合计 <?= formatMoney($totalPay) ?> · 两人平分后你的份额</div>
                    <?php endif; ?>
                    <div class="order-card-meta">
                        <div class="meta-item">
                            <span class="meta-label">报单时间</span>
                            <span class="meta-value"><?= formatDateTime($order['created_at']) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">数量</span>
                            <span class="meta-value"><?= e((string) $order['quantity']) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">开始</span>
                            <span class="meta-value"><?= formatDateTime($order['start_time']) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">结束</span>
                            <span class="meta-value"><?= formatDateTime($order['end_time']) ?></span>
                        </div>
                    </div>
                    <?php $screenshots = parseScreenshotKeys($order['screenshot_key'] ?? null); ?>
                    <?php if ($screenshots): ?>
                        <div class="order-screenshot-links">
                            <?php foreach ($screenshots as $i => $key): ?>
                                <a href="/staff/screenshot.php?id=<?= $order['id'] ?>&i=<?= $i ?>" class="order-screenshot-link" target="_blank">
                                    查看截图<?= count($screenshots) > 1 ? (' ' . ($i + 1)) : '' ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($order['remark']): ?>
                        <div class="order-note">备注：<?= e($order['remark']) ?></div>
                    <?php endif; ?>
                    <?php if ($order['status'] === 'REJECTED' && $order['reject_reason']): ?>
                        <div class="order-reject">拒绝原因：<?= e($order['reject_reason']) ?></div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/partials/nav.php'; ?>
</div>
<?php require __DIR__ . '/partials/foot.php'; ?>
