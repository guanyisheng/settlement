<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/WithdrawalService.php';
require_once __DIR__ . '/../includes/BalanceService.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('withdrawals');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $withdrawalId = (int) ($_POST['withdrawal_id'] ?? 0);
    try {
        if ($action === 'approve') {
            WithdrawalService::approve($pdo, $withdrawalId, Auth::id());
            flash('success', '已确认放款');
        } elseif ($action === 'reject') {
            WithdrawalService::reject($pdo, $withdrawalId, Auth::id(), $_POST['reject_reason'] ?? '');
            flash('success', '已拒绝提现');
        }
        redirect('/admin/withdrawals.php?' . http_build_query(array_diff_key($_GET, ['id' => ''])));
    } catch (Throwable $e) {
        flashError($e, 'WDR');
        redirect('/admin/withdrawals.php?' . http_build_query($_GET));
    }
}

$filters = [];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
if (!empty($_GET['staff_id'])) $filters['staff_id'] = $_GET['staff_id'];

$withdrawals = WithdrawalService::search($pdo, $filters);
$staffList = UserService::getStaffList($pdo);

$viewWithdrawal = null;
$staffBalance = null;
if (!empty($_GET['id'])) {
    $viewWithdrawal = WithdrawalService::getById($pdo, (int) $_GET['id']);
    if ($viewWithdrawal) {
        $staffBalance = BalanceService::getBalanceSummary($pdo, (int) $viewWithdrawal['staff_id']);
    }
}

$currentPage = 'withdrawals';
$pageTitle = '提现管理';
require __DIR__ . '/partials/header.php';
?>

<?php renderAlertError($error); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2>筛选</h2></div>
    <div class="card-body">
        <form method="get" class="filter-bar">
            <div class="form-group">
                <label>打手</label>
                <select name="staff_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($_GET['staff_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['nickname']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>状态</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (['PENDING'=>'待处理','PAID'=>'已放款','REJECTED'=>'已拒绝'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= ($_GET['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">搜索</button>
            <a href="/admin/withdrawals.php" class="btn">重置</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>提现列表 (<?= count($withdrawals) ?>)</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>单号</th><th>打手</th><th>提现金额</th><th>申请时间</th><th>状态</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($withdrawals)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--text-muted)">暂无数据</td></tr>
                <?php else: ?>
                    <?php foreach ($withdrawals as $w): ?>
                    <tr>
                        <td><?= e($w['withdrawal_no']) ?></td>
                        <td><?= e($w['staff_name']) ?></td>
                        <td class="money"><?= formatMoney($w['amount']) ?></td>
                        <td><?= formatDateTimeShort($w['created_at']) ?></td>
                        <td><span class="badge badge-<?= strtolower($w['status']) === 'pending' ? 'pending' : (strtolower($w['status']) === 'paid' ? 'paid' : 'rejected') ?>"><?= withdrawalStatusLabel($w['status']) ?></span></td>
                        <td>
                            <a href="?<?= http_build_query(array_merge($_GET, ['id' => $w['id']])) ?>" class="btn btn-sm <?= $w['status'] === 'PENDING' ? 'btn-primary' : '' ?>">
                                <?= $w['status'] === 'PENDING' ? '处理' : '查看' ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($viewWithdrawal && $staffBalance): ?>
<div class="modal-overlay show" id="processModal">
    <div class="modal">
        <div class="modal-header">处理提现 #<?= e($viewWithdrawal['withdrawal_no']) ?></div>
        <div class="modal-body">
            <dl class="detail-grid">
                <dt>打手</dt><dd><?= e($viewWithdrawal['staff_name']) ?> (<?= e($viewWithdrawal['staff_username']) ?>)</dd>
                <dt>申请金额</dt><dd class="money"><?= formatMoney($viewWithdrawal['amount']) ?></dd>
                <dt>当前可提现余额</dt><dd class="money"><?= formatMoney($staffBalance['available_balance']) ?></dd>
                <dt>累计收入</dt><dd><?= formatMoney($staffBalance['total_income']) ?></dd>
                <dt>已放款提现</dt><dd><?= formatMoney($staffBalance['paid_withdrawals']) ?></dd>
                <dt>申请时间</dt><dd><?= formatDateTime($viewWithdrawal['created_at']) ?></dd>
                <dt>状态</dt><dd><?= withdrawalStatusLabel($viewWithdrawal['status']) ?></dd>
                <dt>收款二维码</dt>
                <dd>
                    <?php if (!empty($viewWithdrawal['staff_pay_qr_key'])): ?>
                        <a href="/admin/staff_photo.php?pay_qr=1&id=<?= (int) $viewWithdrawal['staff_id'] ?>" target="_blank">
                            <img src="/admin/staff_photo.php?pay_qr=1&id=<?= (int) $viewWithdrawal['staff_id'] ?>"
                                 alt="收款码"
                                 style="max-width:220px;width:100%;border-radius:8px;border:1px solid var(--border);background:#fff;display:block;margin-top:6px">
                        </a>
                        <p style="font-size:12px;color:var(--text-muted);margin-top:6px">扫码转账给打手后，再点「确认已放款」</p>
                    <?php else: ?>
                        <span style="color:var(--text-muted)">该打手尚未上传收款码，可让其在打手端「资料」里上传</span>
                    <?php endif; ?>
                </dd>
                <?php if ($viewWithdrawal['processed_at']): ?>
                <dt>处理人</dt><dd><?= e($viewWithdrawal['processor_name'] ?? '-') ?></dd>
                <dt>处理时间</dt><dd><?= formatDateTime($viewWithdrawal['processed_at']) ?></dd>
                <?php endif; ?>
                <?php if ($viewWithdrawal['reject_reason']): ?>
                <dt>拒绝原因</dt><dd style="color:var(--danger)"><?= e($viewWithdrawal['reject_reason']) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if ($viewWithdrawal['status'] === 'PENDING'): ?>
            <div style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap">
                <form method="post" onsubmit="return confirm('确认已线下放款？此操作不可撤销。')">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="withdrawal_id" value="<?= $viewWithdrawal['id'] ?>">
                    <button type="submit" class="btn btn-success">确认已放款</button>
                </form>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('rejectForm').style.display='block'">拒绝提现</button>
            </div>
            <form method="post" id="rejectForm" style="display:none;margin-top:16px">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="withdrawal_id" value="<?= $viewWithdrawal['id'] ?>">
                <div class="form-group">
                    <label>拒绝原因</label>
                    <textarea name="reject_reason" class="form-control" rows="2" required></textarea>
                </div>
                <button type="submit" class="btn btn-danger btn-sm">确认拒绝</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <a href="/admin/withdrawals.php?<?= http_build_query(array_diff_key($_GET, ['id' => ''])) ?>" class="btn">关闭</a>
        </div>
    </div>
</div>
<script>
document.getElementById('processModal').addEventListener('click', e => {
    if (e.target.id === 'processModal') location.href = '/admin/withdrawals.php?<?= http_build_query(array_diff_key($_GET, ['id' => ''])) ?>';
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
