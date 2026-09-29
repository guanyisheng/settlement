<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/ExtraFeeService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireStaff();

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');
$staffId = (int) Auth::id();

$orders = ClientOrderService::isReady($pdo) ? ClientOrderService::listForStaff($pdo, $staffId) : [];
$user = Auth::user();
$currentPage = 'client_orders';
$pageTitle = brandTitle('顾客单');
$bodyClass = 'has-nav';
require __DIR__ . '/partials/head.php';
?>
<div class="app-shell">
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="top-bar-info">
                <h1>顾客单 / 抢单池</h1>
                <p class="subtitle">点进详情操作；转单回池无结算</p>
            </div>
            <?php require __DIR__ . '/partials/user-chip.php'; ?>
        </div>
    </header>
    <main class="page-content">
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if (!ClientOrderService::isReady($pdo)): ?>
            <div class="alert alert-error">请先执行 database/一键注入_全部更新.sql</div>
        <?php elseif ($orders === []): ?>
            <div class="empty-state"><p>暂无顾客单（抢单池或指定给你的单）</p></div>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <?php
                $st = (string) $o['status'];
                $feeLabel = ExtraFeeService::formatSnapshotLabel($o['extra_fees_json'] ?? null, isset($o['extra_fees_rate']) ? (float) $o['extra_fees_rate'] : null);
                ?>
                <a href="/staff/client_order_detail.php?id=<?= (int) $o['id'] ?>" class="order-card" style="padding:12px;margin-bottom:10px;display:block;text-decoration:none;color:inherit">
                    <div><strong><?= e($o['order_no']) ?></strong> · <?= e(ClientOrderService::statusLabel($st)) ?></div>
                    <div><?= e($o['business_type_name']) ?> · 顾客 <?= e($o['client_name']) ?> · ¥<?= formatMoney($o['amount']) ?></div>
                    <?php if ($feeLabel !== ''): ?>
                        <div style="color:var(--text-muted);font-size:12px">加价：<?= e($feeLabel) ?></div>
                    <?php endif; ?>
                    <div style="margin-top:6px;font-size:12px;color:var(--primary-light)">查看详情 / 接单操作 →</div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
    <?php require __DIR__ . '/partials/nav.php'; ?>
</div>
</body>
</html>
