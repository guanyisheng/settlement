<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/DashboardService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('dashboard');

$pdo = Database::getConnection();
$boards = ['三角洲', '暗区', '微契约'];
$board = trim((string) ($_GET['board'] ?? '三角洲'));
if (!in_array($board, $boards, true)) {
    $board = '三角洲';
}
$stats = DashboardService::boardStats($pdo, $board);

$currentPage = 'dashboard';
$pageTitle = $board . ' · 结算工作台';
require __DIR__ . '/partials/header.php';
?>
<p style="margin-bottom:12px">
    <?php foreach ($boards as $b): ?>
        <a class="btn <?= $b === $board ? 'btn-primary' : '' ?>" href="?board=<?= rawurlencode($b) ?>"><?= e($b) ?></a>
    <?php endforeach; ?>
    <a class="btn" href="/admin/index.php">← 工作台</a>
</p>

<div class="alert alert-success" style="margin-bottom:16px">
    请在「业务类型」里给业务勾选所属板块（三角洲 / 暗区 / 微契约），本页按实付流水与打手结算算利润。
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label"><?= e($board) ?> · 已审订单数</div>
        <div class="value primary"><?= (int) $stats['order_count'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label">板块流水（实付）</div>
        <div class="value success"><?= formatMoney($stats['revenue']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">打手结算合计</div>
        <div class="value warning"><?= formatMoney($stats['staff_pay']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">板块利润（流水−结算）</div>
        <div class="value success"><?= formatMoney($stats['profit']) ?></div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
