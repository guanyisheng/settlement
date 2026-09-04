<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/DashboardService.php';
require_once __DIR__ . '/../includes/OrderService.php';
require_once __DIR__ . '/../includes/WithdrawalService.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('dashboard');

$pdo = Database::getConnection();
$stats = DashboardService::getStats($pdo);
$pendingRegistrations = Auth::isBoss() ? UserService::countPendingRegistrations($pdo) : 0;

$recentOrders = OrderService::search($pdo, ['status' => 'PENDING']);
$recentOrders = array_slice($recentOrders, 0, 5);

$recentWithdrawals = WithdrawalService::search($pdo, ['status' => 'PENDING']);
$recentWithdrawals = array_slice($recentWithdrawals, 0, 5);

$currentPage = 'dashboard';
$pageTitle = '工作台';
require __DIR__ . '/partials/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">今日报单数量</div>
        <div class="value primary"><?= $stats['today_orders'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label">待审核订单</div>
        <div class="value warning"><?= $stats['pending_orders'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label">今日结算金额</div>
        <div class="value success"><?= formatMoney($stats['today_settled']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">待处理提现</div>
        <div class="value warning"><?= $stats['pending_withdrawals'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label">系统总收入</div>
        <div class="value success"><?= formatMoney($stats['total_income']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">当前待提现金额</div>
        <div class="value danger"><?= formatMoney($stats['pending_withdraw_amount']) ?></div>
    </div>
    <?php if (Auth::isBoss()): ?>
    <div class="stat-card">
        <div class="label">待审核注册</div>
        <div class="value warning"><?= $pendingRegistrations ?></div>
        <?php if ($pendingRegistrations > 0): ?>
        <a href="/admin/registrations.php" class="btn btn-sm btn-primary" style="margin-top:10px">去审核</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
    <div class="card">
        <div class="card-header">
            <h2>待审核订单</h2>
            <a href="/admin/orders.php?status=PENDING" class="btn btn-sm">查看全部</a>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($recentOrders)): ?>
                <p style="padding:20px;color:var(--text-muted);font-size:13px">暂无待审核订单</p>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>打手</th><th>客户</th><th>金额</th><th>时间</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td><?= e($o['staff_name']) ?></td>
                            <td><?= e($o['customer_name']) ?></td>
                            <td class="money"><?= formatMoney($o['amount']) ?></td>
                            <td><?= formatDateTimeShort($o['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>待处理提现</h2>
            <a href="/admin/withdrawals.php?status=PENDING" class="btn btn-sm">查看全部</a>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($recentWithdrawals)): ?>
                <p style="padding:20px;color:var(--text-muted);font-size:13px">暂无待处理提现</p>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>打手</th><th>金额</th><th>申请时间</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentWithdrawals as $w): ?>
                        <tr>
                            <td><?= e($w['staff_name']) ?></td>
                            <td class="money"><?= formatMoney($w['amount']) ?></td>
                            <td><?= formatDateTimeShort($w['created_at']) ?></td>
                            <td><a href="/admin/withdrawals.php?id=<?= $w['id'] ?>" class="btn btn-sm btn-primary">处理</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
