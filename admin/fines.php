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
    $action = $_POST['action'] ?? 'create';
    try {
        if ($action === 'revoke') {
            FineService::revoke(
                $pdo,
                (int) ($_POST['id'] ?? 0),
                (int) Auth::id(),
                (string) ($_POST['revoke_note'] ?? '')
            );
            flash('success', '罚款已撤销，余额已恢复');
        } else {
            FineService::create(
                $pdo,
                (int) ($_POST['staff_id'] ?? 0),
                (float) ($_POST['amount'] ?? 0),
                (string) ($_POST['reason'] ?? ''),
                (int) Auth::id()
            );
            flash('success', '罚款已登记，将从可提现余额扣除');
        }
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
            <input type="hidden" name="action" value="create">
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
                <thead>
                <tr>
                    <th>ID</th><th>打手</th><th>金额</th><th>原因</th><th>状态</th><th>操作人</th><th>时间</th><th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($fines as $f): ?>
                    <?php $st = strtoupper((string) ($f['status'] ?? 'ACTIVE')); ?>
                    <tr>
                        <td><?= (int) $f['id'] ?></td>
                        <td><?= e($f['staff_name']) ?></td>
                        <td class="money"><?= formatMoney($f['amount']) ?></td>
                        <td><?= e($f['reason']) ?></td>
                        <td>
                            <?php if ($st === 'REVOKED'): ?>
                                <span class="badge badge-disabled">已撤销</span>
                                <?php if (!empty($f['revoker_name'])): ?>
                                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px">
                                        <?= e($f['revoker_name']) ?> · <?= formatDateTimeShort($f['revoked_at'] ?? null) ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-active">生效中</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($f['creator_name'] ?: '-') ?></td>
                        <td><?= formatDateTimeShort($f['created_at']) ?></td>
                        <td>
                            <?php if ($st !== 'REVOKED' && FineService::hasStatusColumn($pdo)): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('确认撤销该罚款？余额将恢复')">
                                <input type="hidden" name="action" value="revoke">
                                <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                <input type="hidden" name="revoke_note" value="后台撤销">
                                <button type="submit" class="btn btn-sm">撤销</button>
                            </form>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
