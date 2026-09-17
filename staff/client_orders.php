<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireStaff();

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');
$staffId = (int) Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    try {
        match ($action) {
            'accept' => ClientOrderService::accept($pdo, $id, $staffId),
            'start' => ClientOrderService::start($pdo, $id, $staffId),
            'complete' => ClientOrderService::complete($pdo, $id, $staffId),
            'transfer' => ClientOrderService::transferToPool($pdo, $id, $staffId, (string) ($_POST['note'] ?? '')),
            default => throw new InvalidArgumentException('未知操作'),
        };
        flash('success', '操作成功');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/staff/client_orders.php');
}

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
                <p class="subtitle">转单回池无结算，结单人才拿钱</p>
            </div>
            <?php require __DIR__ . '/partials/user-chip.php'; ?>
        </div>
    </header>
    <main class="page-content">
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if (!ClientOrderService::isReady($pdo)): ?>
            <div class="alert alert-error">请先执行 migrate_customer_portal.sql</div>
        <?php elseif ($orders === []): ?>
            <div class="empty-state"><p>暂无顾客单</p></div>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <?php
                $mine = (int) ($o['staff_id'] ?? 0) === $staffId;
                $st = (string) $o['status'];
                ?>
                <div class="order-card" style="padding:12px;margin-bottom:10px;background:#fff;border-radius:8px">
                    <div><strong><?= e($o['order_no']) ?></strong> · <?= e(ClientOrderService::statusLabel($st)) ?></div>
                    <div><?= e($o['business_type_name']) ?> · 顾客 <?= e($o['client_name']) ?> · ¥<?= formatMoney($o['amount']) ?></div>
                    <?php if (!empty($o['transfer_note'])): ?>
                        <div style="color:#888;font-size:12px">转单说明：<?= e($o['transfer_note']) ?></div>
                    <?php endif; ?>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">
                        <?php if ($st === 'POOL' || ($st === 'WAITING' && $mine)): ?>
                            <form method="post"><input type="hidden" name="action" value="accept"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                <button class="btn btn-sm btn-primary"><?= $st === 'POOL' ? '抢单' : '接单' ?></button></form>
                        <?php endif; ?>
                        <?php if ($mine && $st === 'ACCEPTED'): ?>
                            <form method="post"><input type="hidden" name="action" value="start"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                <button class="btn btn-sm">开始服务</button></form>
                        <?php endif; ?>
                        <?php if ($mine && in_array($st, ['ACCEPTED', 'DOING'], true)): ?>
                            <form method="post"><input type="hidden" name="action" value="complete"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                <button class="btn btn-sm btn-primary">结单</button></form>
                            <form method="post" onsubmit="return confirm('转单回池？你将拿不到这单钱')">
                                <input type="hidden" name="action" value="transfer"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                <input type="hidden" name="note" value="打手转单">
                                <button class="btn btn-sm">转单回池</button></form>
                        <?php endif; ?>
                        <?php if ($st === 'DONE' && $mine): ?>
                            <span>结算 ¥<?= formatMoney($o['staff_amount'] ?? 0) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
    <?php require __DIR__ . '/partials/nav.php'; ?>
</div>
</body>
</html>
