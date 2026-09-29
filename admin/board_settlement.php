<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/DashboardService.php';
require_once __DIR__ . '/../includes/BoardService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('dashboard');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_board') {
            $id = (int) ($_POST['id'] ?? 0);
            BoardService::save($pdo, $_POST, $id > 0 ? $id : null);
            flash('success', $id > 0 ? '板块已更新' : '板块已新增');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    $q = trim((string) ($_POST['board'] ?? $_GET['board'] ?? ''));
    redirect('/admin/board_settlement.php' . ($q !== '' ? ('?board=' . rawurlencode($q)) : ''));
}

$boards = BoardService::names($pdo, true);
$allBoards = BoardService::all($pdo, false);
$board = trim((string) ($_GET['board'] ?? ($boards[0] ?? '')));
if ($board !== '' && !in_array($board, $boards, true) && $boards !== []) {
    $board = $boards[0];
}
$stats = $board !== '' ? DashboardService::boardStats($pdo, $board) : [
    'order_count' => 0, 'revenue' => 0, 'staff_pay' => 0, 'profit' => 0,
];

$currentPage = 'dashboard';
$pageTitle = ($board !== '' ? $board . ' · ' : '') . '结算工作台';
require __DIR__ . '/partials/header.php';
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!BoardService::isReady($pdo)): ?>
<div class="alert alert-error">请先执行 <code>database/一键注入_全部更新.sql</code></div>
<?php endif; ?>

<p style="margin-bottom:12px">
    <?php foreach ($boards as $b): ?>
        <a class="btn <?= $b === $board ? 'btn-primary' : '' ?>" href="?board=<?= rawurlencode($b) ?>"><?= e($b) ?></a>
    <?php endforeach; ?>
    <a class="btn" href="/admin/index.php">← 工作台</a>
</p>

<div class="alert alert-success" style="margin-bottom:16px">
    在「业务类型」里给业务选所属板块。本页按实付流水 − 打手结算算利润。板块名可自定义。
</div>

<?php if ($board !== ''): ?>
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
<?php endif; ?>

<div class="card" style="margin-top:24px">
    <div class="card-header"><h2>自定义板块</h2></div>
    <div class="card-body">
        <form method="post" class="form-row" style="margin-bottom:16px">
            <input type="hidden" name="action" value="save_board">
            <input type="hidden" name="board" value="<?= e($board) ?>">
            <div class="form-group"><label>名称</label><input name="name" class="form-control" required placeholder="如：三角洲行动"></div>
            <div class="form-group"><label>排序</label><input type="number" name="sort_order" class="form-control" value="0"></div>
            <div class="form-group" style="display:flex;align-items:flex-end"><button class="btn btn-primary">新增板块</button></div>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>名称</th><th>排序</th><th>状态</th><th>保存</th></tr></thead>
                <tbody>
                <?php foreach ($allBoards as $row): ?>
                    <?php $fid = 'board-form-' . (int) $row['id']; ?>
                    <form method="post" id="<?= $fid ?>"></form>
                    <tr>
                        <td>
                            <input type="hidden" form="<?= $fid ?>" name="action" value="save_board">
                            <input type="hidden" form="<?= $fid ?>" name="id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" form="<?= $fid ?>" name="board" value="<?= e($board) ?>">
                            <input form="<?= $fid ?>" name="name" class="form-control" value="<?= e($row['name']) ?>" required>
                        </td>
                        <td><input form="<?= $fid ?>" type="number" name="sort_order" class="form-control" value="<?= (int) $row['sort_order'] ?>"></td>
                        <td>
                            <select form="<?= $fid ?>" name="status" class="form-control">
                                <option value="1" <?= (int) $row['status'] === 1 ? 'selected' : '' ?>>启用</option>
                                <option value="0" <?= (int) $row['status'] === 0 ? 'selected' : '' ?>>停用</option>
                            </select>
                        </td>
                        <td><button form="<?= $fid ?>" class="btn btn-sm btn-primary">保存</button></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($allBoards === []): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted)">暂无板块，请先执行 SQL 或上方新增</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
