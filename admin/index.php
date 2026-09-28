<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/DashboardService.php';
require_once __DIR__ . '/../includes/OrderService.php';
require_once __DIR__ . '/../includes/WithdrawalService.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/BoardService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('dashboard');

$pdo = Database::getConnection();
$stats = DashboardService::getStats($pdo);
$pendingRegistrations = Auth::isBoss() ? UserService::countPendingRegistrations($pdo) : 0;
$boardNames = BoardService::names($pdo, true);

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
        <div class="label">待处理提现（笔数）</div>
        <div class="value warning"><?= $stats['pending_withdrawals'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label">系统总收入（订单原价）</div>
        <div class="value success"><?= formatMoney($stats['order_amount_total'] ?? $stats['total_income']) ?></div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px">按单钱/原价合计；优惠前</p>
    </div>
    <div class="stat-card">
        <div class="label">总流水（实付）</div>
        <div class="value success"><?= formatMoney($stats['revenue_total'] ?? 0) ?></div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px">客服点「通过」后按实付计入；未改实付=原价</p>
    </div>
    <div class="stat-card">
        <div class="label">打手结算合计</div>
        <div class="value danger"><?= formatMoney($stats['staff_pay_total'] ?? 0) ?></div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px">已通过报单的打手应得（含已提/未提）</p>
    </div>
    <div class="stat-card">
        <div class="label">已提现合计（已放款）</div>
        <div class="value danger"><?= formatMoney($stats['withdraw_paid_total'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">处理中提现</div>
        <div class="value warning"><?= formatMoney($stats['pending_withdraw_amount'] ?? 0) ?></div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px">已申请、尚未放款</p>
        <a href="/admin/withdrawals.php?status=PENDING" class="btn btn-sm btn-danger" style="margin-top:8px">去处理提现</a>
    </div>
    <div class="stat-card">
        <div class="label">未发起提现</div>
        <div class="value warning"><?= formatMoney($stats['unrequested_withdraw'] ?? 0) ?></div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px">结算 − 已放款 − 处理中 − 罚款</p>
    </div>
    <div class="stat-card">
        <div class="label">待提现总金额</div>
        <div class="value danger"><?= formatMoney($stats['awaiting_withdraw_total'] ?? 0) ?></div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px">处理中 + 未发起（还在系统里的打手款）</p>
    </div>
    <div class="stat-card">
        <div class="label">打手款总量</div>
        <div class="value primary"><?= formatMoney($stats['staff_money_total'] ?? 0) ?></div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px">已放款 + 处理中 + 未发起</p>
    </div>
    <div class="stat-card">
        <div class="label">押金总和</div>
        <div class="value primary"><?= formatMoney($stats['deposit_total'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">净利润（流水−打手结算）</div>
        <div class="value warning"><?= formatMoney($stats['net_profit'] ?? 0) ?></div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px">实付流水 − 打手结算</p>
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

<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
        <h2 style="margin:0">板块结算工作台</h2>
        <a class="btn btn-sm" href="/admin/board_settlement.php">管理/自定义板块</a>
    </div>
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px">
        <?php if ($boardNames === []): ?>
            <span style="color:var(--text-muted);font-size:13px">暂无板块，请执行 migrate_settlement_boards.sql 或去自定义</span>
        <?php else: ?>
            <?php foreach ($boardNames as $board): ?>
                <a class="btn btn-primary" href="/admin/board_settlement.php?board=<?= rawurlencode($board) ?>"><?= e($board) ?>结算</a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
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
