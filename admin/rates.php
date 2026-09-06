<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/SettlementService.php';
require_once __DIR__ . '/../includes/SettingsService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('rates');
Auth::requirePermission('rate.manage');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $a = (float) ($_POST['rate_a_pct'] ?? 0) / 100;
        $b = (float) ($_POST['rate_b_pct'] ?? 0) / 100;
        SettlementService::setRates($pdo, $a, $b);
        flash('success', '结算倍率已更新。历史订单结算金额不会重新计算。');
        redirect('/admin/rates.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/rates.php');
    }
}

$rates = SettlementService::rates();
$example = SettlementService::calcStaffAmount(100);

$currentPage = 'rates';
$pageTitle = '结算倍率管理';
require __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2>当前倍率</h2></div>
    <div class="card-body">
        <p>公式：<strong>订单金额 × 基础倍率 × 打手倍率</strong></p>
        <p>示例：订单 ¥100 → 打手结算 <?= formatMoney($example) ?>（<?= e(SettlementService::formulaLabel()) ?>）</p>
        <p style="color:var(--text-muted);font-size:13px;margin-top:12px">
            修改后仅影响<strong>新报单</strong>。历史订单结算金额不会重新计算。
        </p>
        <form method="post" style="margin-top:20px;max-width:420px">
            <div class="form-group">
                <label>基础倍率（%）</label>
                <input type="number" name="rate_a_pct" class="form-control" step="0.01" min="0.01" max="100"
                       value="<?= e((string) round($rates['rate_a'] * 100, 2)) ?>" required>
            </div>
            <div class="form-group">
                <label>打手倍率（%）</label>
                <input type="number" name="rate_b_pct" class="form-control" step="0.01" min="0.01" max="100"
                       value="<?= e((string) round($rates['rate_b'] * 100, 2)) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return confirm('确认修改倍率？历史订单不会重算。')">保存倍率</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
