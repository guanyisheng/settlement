<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/FineService.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('fines');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        FineService::create(
            $pdo,
            (int) ($_POST['staff_id'] ?? 0),
            (float) ($_POST['amount'] ?? 0),
            (string) ($_POST['reason'] ?? ''),
            (int) Auth::id()
        );
        flash('success', '罚款已登记，将从可提现余额扣除');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin/fines.php');
}

$staffList = UserService::getStaffList($pdo);
$fines = FineService::listRecent($pdo);
$currentPage = 'fines';
$pageTitle = '打手罚款';
require __DIR__ . '/partials/header.php';
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2>下发罚款</h2></div>
    <div class="card-body">
        <?php if (!FineService::isReady($pdo)): ?>
            <div class="alert alert-error">请先执行 database/migrate_customer_portal.sql</div>
        <?php else: ?>
        <form method="post">
            <div class="form-row">
                <div class="form-group">
                    <label>打手</label>
                    <select name="staff_id" class="form-control" required>
                        <option value="">请选择</option>
                        <?php foreach ($staffList as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['nickname'] ?: $s['username']) ?> (#<?= (int) $s['id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>金额</label>
                    <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>原因</label>
                    <input type="text" name="reason" class="form-control" required>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <button type="submit" class="btn btn-primary">确认罚款</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>罚款记录</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>打手</th><th>金额</th><th>原因</th><th>操作人</th><th>时间</th></tr></thead>
                <tbody>
                <?php foreach ($fines as $f): ?>
                    <tr>
                        <td><?= (int) $f['id'] ?></td>
                        <td><?= e($f['staff_name']) ?></td>
                        <td class="money"><?= formatMoney($f['amount']) ?></td>
                        <td><?= e($f['reason']) ?></td>
                        <td><?= e($f['creator_name'] ?: '-') ?></td>
                        <td><?= formatDateTimeShort($f['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
