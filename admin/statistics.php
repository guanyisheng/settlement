<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/DashboardService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('statistics');

$pdo = Database::getConnection();
$rangeKey = (string) ($_GET['range'] ?? 'month');
$allowedRanges = ['today', 'yesterday', 'week', 'last_week', 'month', 'last_month', 'all'];
if (!in_array($rangeKey, $allowedRanges, true)) {
    $rangeKey = 'month';
}
$data = DashboardService::getBossStatistics($pdo, $rangeKey);
$overview = $data['overview'];
$period = $data['period'];
$byStatus = $data['by_status'];
$range = $data['range'] ?? DashboardService::resolveDateRange($rangeKey);
$rangeMetrics = $data['range_metrics'] ?? ['orders' => 0, 'flow' => 0.0, 'withdraw' => 0.0, 'commission' => 0.0];
$statusLabels = [
    'PENDING'  => '待审核',
    'APPROVED' => '已通过',
    'REJECTED' => '已拒绝',
    'SETTLED'  => '已结算',
];

$currentPage = 'statistics';
$pageTitle = '数据统计';
require __DIR__ . '/partials/header.php';

$rangeLinks = [
    'today' => '今天',
    'yesterday' => '昨天',
    'week' => '本周',
    'last_week' => '上周',
    'month' => '本月',
    'last_month' => '上月',
    'all' => '总共',
];
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">打手总数</div>
        <div class="value primary"><?= $overview['staff_total'] ?></div>
        <div class="stat-sub">启用 <?= $overview['staff_active'] ?> · 待审核 <?= $overview['pending_staff'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label">客户总数</div>
        <div class="value primary"><?= $overview['customer_total'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label">预存总余额</div>
        <div class="value success"><?= formatMoney($overview['prepaid_balance'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">累计订单</div>
        <div class="value"><?= $overview['order_total'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label">累计结算金额</div>
        <div class="value success"><?= formatMoney($overview['order_amount']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">累计已放款</div>
        <div class="value"><?= formatMoney($overview['withdraw_paid']) ?></div>
    </div>
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="flex-wrap:wrap;gap:12px">
        <h2 style="margin:0">订单结算区 · <?= e($range['label']) ?></h2>
        <div class="range-chips">
            <?php foreach ($rangeLinks as $key => $label): ?>
                <a class="range-chip <?= $rangeKey === $key ? 'active' : '' ?>"
                   href="?range=<?= e($key) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="stats-grid" style="margin:0">
            <div class="stat-card">
                <div class="label">订单数（已通过）</div>
                <div class="value primary"><?= (int) $rangeMetrics['orders'] ?></div>
                <div class="stat-sub">按审核日统计</div>
            </div>
            <div class="stat-card">
                <div class="label">总流水</div>
                <div class="value success"><?= formatMoney($rangeMetrics['flow']) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">提现金额</div>
                <div class="value"><?= formatMoney($rangeMetrics['withdraw']) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">抽成</div>
                <div class="value warning"><?= formatMoney($rangeMetrics['commission']) ?></div>
                <div class="stat-sub">流水 − 打手结算</div>
            </div>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">今日报单</div>
        <div class="value primary"><?= $period['today_orders'] ?></div>
        <div class="stat-sub">结算 <?= formatMoney($period['today_amount']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">本周报单</div>
        <div class="value primary"><?= $period['week_orders'] ?></div>
        <div class="stat-sub">结算 <?= formatMoney($period['week_amount']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">本月报单</div>
        <div class="value primary"><?= $period['month_orders'] ?></div>
        <div class="stat-sub">结算 <?= formatMoney($period['month_amount']) ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
    <div class="card">
        <div class="card-header"><h2>近14日报单趋势</h2></div>
        <div class="card-body">
            <div class="trend-chart">
                <?php foreach ($data['daily_trend'] as $day): ?>
                    <?php $height = round($day['count'] / $data['max_daily_count'] * 100); ?>
                    <div class="trend-bar-wrap" title="<?= e($day['date']) ?>：<?= $day['count'] ?> 单">
                        <div class="trend-bar" style="height:<?= max(4, $height) ?>%"></div>
                        <span class="trend-label"><?= e($day['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>近14日结算金额</h2></div>
        <div class="card-body">
            <div class="trend-chart trend-chart-amount">
                <?php foreach ($data['daily_trend'] as $day): ?>
                    <?php $height = round($day['amount'] / $data['max_daily_amount'] * 100); ?>
                    <div class="trend-bar-wrap" title="<?= e($day['date']) ?>：<?= formatMoney($day['amount']) ?>">
                        <div class="trend-bar trend-bar-success" style="height:<?= max(4, $height) ?>%"></div>
                        <span class="trend-label"><?= e($day['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
    <div class="card">
        <div class="card-header"><h2>订单状态分布</h2></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>状态</th><th>数量</th><th>金额</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <?php $row = $byStatus[$key] ?? ['count' => 0, 'amount' => 0.0]; ?>
                        <tr>
                            <td><?= $label ?></td>
                            <td><?= $row['count'] ?></td>
                            <td class="money"><?= formatMoney($row['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>业务类型收入</h2></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>业务类型</th><th>订单数</th><th>结算金额</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($data['business_types'])): ?>
                        <tr><td colspan="3" style="text-align:center;color:var(--text-muted)">暂无数据</td></tr>
                    <?php else: ?>
                        <?php foreach ($data['business_types'] as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td><?= (int) $row['order_count'] ?></td>
                            <td class="money"><?= formatMoney($row['total_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
    <div class="card">
        <div class="card-header"><h2>打手收入 TOP10</h2></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>打手</th><th>订单数</th><th>结算金额</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($data['top_staff'])): ?>
                        <tr><td colspan="3" style="text-align:center;color:var(--text-muted)">暂无数据</td></tr>
                    <?php else: ?>
                        <?php foreach ($data['top_staff'] as $row): ?>
                        <tr>
                            <td><?= e($row['nickname']) ?></td>
                            <td><?= (int) $row['order_count'] ?></td>
                            <td class="money"><?= formatMoney($row['total_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>客户收入 TOP10</h2></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>客户</th><th>订单数</th><th>结算金额</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($data['top_customers'])): ?>
                        <tr><td colspan="3" style="text-align:center;color:var(--text-muted)">暂无数据</td></tr>
                    <?php else: ?>
                        <?php foreach ($data['top_customers'] as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td><?= (int) $row['order_count'] ?></td>
                            <td class="money"><?= formatMoney($row['total_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
