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

Auth::requireBoss();

$pdo = Database::getConnection();
$stats = DashboardService::getStats($pdo);
$pendingRegistrations = Auth::isBoss() ? UserService::countPendingRegistrations($pdo) : 0;
$boardNames = BoardService::names($pdo, true);

$recentOrders = array_slice(OrderService::search($pdo, ['status' => 'PENDING']), 0, 6);
$recentWithdrawals = array_slice(WithdrawalService::search($pdo, ['status' => 'PENDING']), 0, 6);

$orderAmount = (float) ($stats['order_amount_total'] ?? $stats['total_income'] ?? 0);
$revenue = (float) ($stats['revenue_total'] ?? 0);
$staffPay = (float) ($stats['staff_pay_total'] ?? 0);
$paidOut = (float) ($stats['withdraw_paid_total'] ?? 0);
$pendingAmt = (float) ($stats['pending_withdraw_amount'] ?? 0);
$unrequested = (float) ($stats['unrequested_withdraw'] ?? 0);
$awaiting = (float) ($stats['awaiting_withdraw_total'] ?? 0);
$staffMoney = (float) ($stats['staff_money_total'] ?? 0);
$netProfit = (float) ($stats['net_profit'] ?? 0);
$deposit = (float) ($stats['deposit_total'] ?? 0);

$currentPage = 'dashboard';
$pageTitle = '财务工作台';
$adminDashVersion = 'v2';
require __DIR__ . '/partials/header.php';
?>

<div class="finance-dash">
    <div class="finance-hero">
        <div class="finance-hero-card is-main">
            <div class="finance-kicker">卡里还要备的钱</div>
            <div class="finance-hero-value"><?= formatMoney($awaiting) ?></div>
            <p class="finance-hint">待结算 = 处理中 + 未发起（仅启用账号；停用不计）</p>
            <a class="btn btn-sm btn-danger" href="/admin/withdrawals.php?status=PENDING">去处理提现</a>
        </div>
        <div class="finance-hero-card">
            <div class="finance-kicker">总流水（实付）</div>
            <div class="finance-hero-value is-success"><?= formatMoney($revenue) ?></div>
            <p class="finance-hint">客服通过后计入；未改实付按原价</p>
        </div>
        <div class="finance-hero-card">
            <div class="finance-kicker">净利润</div>
            <div class="finance-hero-value is-warning"><?= formatMoney($netProfit) ?></div>
            <p class="finance-hint">实付流水 − 打手结算</p>
        </div>
    </div>

    <section class="finance-section">
        <div class="finance-section-head">
            <h2>收入</h2>
            <span>原价 vs 实付</span>
        </div>
        <div class="finance-grid cols-2">
            <div class="finance-metric">
                <div class="label">系统总收入（订单原价）</div>
                <div class="value"><?= formatMoney($orderAmount) ?></div>
                <p class="finance-hint">单钱合计，优惠前</p>
            </div>
            <div class="finance-metric">
                <div class="label">总流水（实付）</div>
                <div class="value is-success"><?= formatMoney($revenue) ?></div>
                <p class="finance-hint">有优惠时会低于原价</p>
            </div>
        </div>
    </section>

    <section class="finance-section">
        <div class="finance-section-head">
            <h2>打手款</h2>
            <span>已放款 + 处理中 + 未发起 = 打手款总量</span>
        </div>
        <div class="finance-eq">
            <div class="finance-metric">
                <div class="label">已提现（已放款）</div>
                <div class="value is-danger"><?= formatMoney($paidOut) ?></div>
            </div>
            <div class="finance-plus">+</div>
            <div class="finance-metric">
                <div class="label">处理中提现</div>
                <div class="value is-warning"><?= formatMoney($pendingAmt) ?></div>
                <p class="finance-hint">已申请未放款 · 仅启用账号</p>
            </div>
            <div class="finance-plus">+</div>
            <div class="finance-metric">
                <div class="label">未发起提现</div>
                <div class="value is-warning"><?= formatMoney($unrequested) ?></div>
                <p class="finance-hint">启用账号可提余额；停用账号不计</p>
            </div>
            <div class="finance-plus">=</div>
            <div class="finance-metric is-highlight">
                <div class="label">打手款总量</div>
                <div class="value is-primary"><?= formatMoney($staffMoney) ?></div>
            </div>
        </div>
        <div class="finance-grid cols-3" style="margin-top:12px">
            <div class="finance-metric">
                <div class="label">打手结算合计</div>
                <div class="value"><?= formatMoney($staffPay) ?></div>
                <p class="finance-hint">已通过报单应得</p>
            </div>
            <div class="finance-metric is-highlight">
                <div class="label">待结算 / 待提现</div>
                <div class="value is-danger"><?= formatMoney($awaiting) ?></div>
                <p class="finance-hint">处理中 + 未发起 · 停用账号不参加</p>
            </div>
            <div class="finance-metric">
                <div class="label">押金总和</div>
                <div class="value is-primary"><?= formatMoney($deposit) ?></div>
            </div>
        </div>
    </section>

    <section class="finance-section">
        <div class="finance-section-head">
            <h2>今日待办</h2>
            <span>先处理这些</span>
        </div>
        <div class="finance-todo">
            <a class="finance-todo-item" href="/admin/orders.php?status=PENDING">
                <strong><?= (int) $stats['pending_orders'] ?></strong>
                <span>待审核订单</span>
            </a>
            <a class="finance-todo-item" href="/admin/withdrawals.php?status=PENDING">
                <strong><?= (int) $stats['pending_withdrawals'] ?></strong>
                <span>待处理提现（笔）</span>
            </a>
            <div class="finance-todo-item is-static">
                <strong><?= (int) $stats['today_orders'] ?></strong>
                <span>今日报单</span>
            </div>
            <div class="finance-todo-item is-static">
                <strong><?= formatMoney($stats['today_settled']) ?></strong>
                <span>今日结算金额</span>
            </div>
            <?php if (Auth::isBoss()): ?>
            <a class="finance-todo-item" href="/admin/registrations.php">
                <strong><?= (int) $pendingRegistrations ?></strong>
                <span>待审核注册</span>
            </a>
            <?php endif; ?>
        </div>
    </section>

    <section class="finance-section">
        <div class="finance-section-head">
            <h2>板块结算</h2>
            <a class="btn btn-sm" href="/admin/board_settlement.php">管理板块</a>
        </div>
        <div class="finance-boards">
            <?php if ($boardNames === []): ?>
                <span class="finance-hint">暂无板块，请先执行 一键注入_全部更新.sql</span>
            <?php else: ?>
                <?php foreach ($boardNames as $board): ?>
                    <a class="btn btn-primary" href="/admin/board_settlement.php?board=<?= rawurlencode($board) ?>"><?= e($board) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="finance-split">
        <section class="finance-section" style="margin:0">
            <div class="finance-section-head">
                <h2>待审核订单</h2>
                <a class="btn btn-sm" href="/admin/orders.php?status=PENDING">全部</a>
            </div>
            <?php if ($recentOrders === []): ?>
                <p class="finance-empty">暂无待审核订单</p>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>打手</th><th>客户</th><th>金额</th><th>时间</th></tr></thead>
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
        </section>
        <section class="finance-section" style="margin:0">
            <div class="finance-section-head">
                <h2>待处理提现</h2>
                <a class="btn btn-sm" href="/admin/withdrawals.php?status=PENDING">全部</a>
            </div>
            <?php if ($recentWithdrawals === []): ?>
                <p class="finance-empty">暂无待处理提现</p>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>打手</th><th>金额</th><th>时间</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recentWithdrawals as $w): ?>
                        <tr>
                            <td><?= e($w['staff_name']) ?></td>
                            <td class="money"><?= formatMoney($w['amount']) ?></td>
                            <td><?= formatDateTimeShort($w['created_at']) ?></td>
                            <td><a class="btn btn-sm btn-primary" href="/admin/withdrawals.php?id=<?= (int) $w['id'] ?>">处理</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
