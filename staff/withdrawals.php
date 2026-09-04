<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/BalanceService.php';
require_once __DIR__ . '/../includes/WithdrawalService.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/icons.php';

Auth::requireStaff();

$pdo = Database::getConnection();
$staffId = Auth::id();
$balance = BalanceService::getBalanceSummary($pdo, $staffId);
$withdrawals = WithdrawalService::getByStaff($pdo, $staffId);

$error = '';
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    try {
        $amount = (float) ($_POST['amount'] ?? 0);
        WithdrawalService::create($pdo, $staffId, $amount);
        flash('success', '提现申请已提交，等待客服处理');
        redirect('/staff/withdrawals.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$balance = BalanceService::getBalanceSummary($pdo, $staffId);
$currentPage = 'withdrawals';
$pageTitle = brandTitle('提现记录');
$bodyClass = 'has-nav';

require __DIR__ . '/partials/head.php';
?>
<div class="app-shell">
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="top-bar-info">
                <h1>提现记录</h1>
                <p class="subtitle">申请记录与状态</p>
            </div>
            <?php require __DIR__ . '/partials/user-chip.php'; ?>
        </div>
    </header>

    <div class="balance-hero">
        <div class="balance-hero-label">可提现余额</div>
        <div class="balance-hero-main">
            <span class="currency">¥</span><?= number_format($balance['available_balance'], 2) ?>
        </div>
        <div class="balance-stats">
            <div class="balance-stat">
                <label>当前余额</label>
                <div class="val money"><?= formatMoney($balance['current_balance']) ?></div>
            </div>
            <div class="balance-stat">
                <label>累计收入</label>
                <div class="val income"><?= formatMoney($balance['total_income']) ?></div>
            </div>
        </div>
    </div>

    <main class="page-content">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <div class="section-title">提现申请</div>

        <?php if (empty($withdrawals)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <?= svgIcon('empty-withdrawals', 'empty-icon-svg') ?>
                </div>
                <p>暂无提现记录</p>
                <?php if ($balance['available_balance'] > 0): ?>
                    <a href="#" class="btn btn-primary" id="openWithdrawEmpty">申请提现</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="withdraw-list">
                <?php foreach ($withdrawals as $w): ?>
                <div class="withdraw-item">
                    <div class="withdraw-item-left">
                        <div class="amount"><?= formatMoney($w['amount']) ?></div>
                        <div class="time"><?= formatDateTime($w['created_at']) ?></div>
                        <?php if ($w['status'] === 'REJECTED' && $w['reject_reason']): ?>
                            <div class="reject-reason"><?= e($w['reject_reason']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="status-badge <?= withdrawalStatusClass($w['status']) ?>">
                        <?= withdrawalStatusLabel($w['status']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php if ($balance['available_balance'] > 0): ?>
    <a href="#" class="fab-btn" id="openWithdraw" aria-label="申请提现">+</a>
    <?php endif; ?>

    <div class="modal-overlay" id="withdrawModal">
        <div class="modal-sheet">
            <div class="modal-handle"></div>
            <h3>申请提现</h3>
            <p class="modal-balance-hint">
                可提现余额：<strong><?= formatMoney($balance['available_balance']) ?></strong>
            </p>
            <form method="post">
                <input type="hidden" name="action" value="withdraw">
                <div class="form-group">
                    <label>提现金额</label>
                    <input type="number" name="amount" class="form-control" required
                           min="0.01" step="0.01" max="<?= $balance['available_balance'] ?>"
                           inputmode="decimal" placeholder="0.00">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">提交申请</button>
                    <button type="button" class="btn btn-outline" id="closeWithdraw">取消</button>
                </div>
            </form>
        </div>
    </div>

    <?php require __DIR__ . '/partials/nav.php'; ?>
</div>

<script>
const modal = document.getElementById('withdrawModal');
function openModal(e) {
    e.preventDefault();
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}
document.getElementById('openWithdraw')?.addEventListener('click', openModal);
document.getElementById('openWithdrawEmpty')?.addEventListener('click', openModal);
document.getElementById('closeWithdraw').addEventListener('click', () => {
    modal.classList.remove('show');
    document.body.style.overflow = '';
});
modal.addEventListener('click', e => {
    if (e.target === modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
});
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
