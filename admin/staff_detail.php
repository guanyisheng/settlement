<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/OrderService.php';
require_once __DIR__ . '/../includes/WithdrawalService.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/icons.php';

Auth::requirePage('staff');

$pdo = Database::getConnection();
$staffId = (int) ($_GET['id'] ?? 0);
$staff = UserService::getStaffById($pdo, $staffId);

if (!$staff) {
    flash('error', '打手不存在');
    redirect('/admin/staff.php');
}

$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    try {
        UserService::updateStaff($pdo, $staffId, $_POST, $_FILES['photo'] ?? null);
        flash('success', '档案已更新');
        redirect('/admin/staff_detail.php?id=' . $staffId);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/staff_detail.php?id=' . $staffId);
    }
}

$staff = UserService::getStaffById($pdo, $staffId);
$stats = UserService::getStaffStats($pdo, $staffId);
$orders = OrderService::search($pdo, ['staff_id' => $staffId]);
$withdrawals = WithdrawalService::search($pdo, ['staff_id' => $staffId]);

$currentPage = 'staff';
$pageTitle = '打手详情 - ' . $staff['nickname'];
require __DIR__ . '/partials/header.php';
?>

<a href="/admin/staff.php" class="btn btn-sm btn-back" style="margin-bottom:16px">
    <?= svgIcon('arrow-left', 'btn-icon') ?><span>返回列表</span>
</a>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">累计订单</div>
        <div class="value primary"><?= $stats['order_count'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label">累计收入</div>
        <div class="value success"><?= formatMoney($stats['total_income']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">累计提现</div>
        <div class="value"><?= formatMoney($stats['paid_withdrawals']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">可提现余额</div>
        <div class="value danger"><?= formatMoney($stats['available_balance']) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>档案信息</h2></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:160px 1fr;gap:24px;align-items:start">
            <div>
                <?php if (!empty($staff['photo_key'])): ?>
                    <a href="/admin/staff_photo.php?id=<?= $staff['id'] ?>" target="_blank">
                        <img src="/admin/staff_photo.php?id=<?= $staff['id'] ?>" alt="毛照"
                             style="width:140px;height:140px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
                    </a>
                    <p style="font-size:12px;color:var(--text-muted);margin-top:8px;text-align:center">
                        <a href="/admin/staff_photo.php?id=<?= $staff['id'] ?>" target="_blank">查看大图</a>
                    </p>
                <?php else: ?>
                    <div style="width:140px;height:140px;background:var(--bg);border:1px dashed var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:13px">
                        未上传毛照
                    </div>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-row">
                    <div class="form-group">
                        <label>用户名</label>
                        <input type="text" class="form-control" value="<?= e($staff['username']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>昵称</label>
                        <input type="text" name="nickname" class="form-control" value="<?= e($staff['nickname']) ?>">
                    </div>
                    <div class="form-group">
                        <label>入职时间</label>
                        <input type="date" name="hired_at" class="form-control" value="<?= e($staff['hired_at'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>考核官</label>
                        <input type="text" name="examiner" class="form-control" value="<?= e($staff['examiner'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>押金</label>
                        <input type="text" name="deposit" class="form-control" value="<?= e($staff['deposit'] ?? '') ?>" placeholder="如 100">
                    </div>
                    <div class="form-group">
                        <label>状态</label>
                        <select name="status" class="form-control">
                            <option value="1" <?= $staff['status'] ? 'selected' : '' ?>>启用</option>
                            <option value="0" <?= !$staff['status'] ? 'selected' : '' ?>>禁用</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= !empty($staff['photo_key']) ? '更换毛照' : '上传毛照' ?></label>
                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>
                <p style="font-size:12px;color:var(--text-muted);margin:8px 0 16px">注册时间：<?= formatDateTime($staff['created_at']) ?></p>
                <button type="submit" class="btn btn-primary">保存档案</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>订单记录</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead><tr><th>微信订单号</th><th>客户</th><th>业务</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($orders, 0, 20) as $o): ?>
                    <tr>
                        <td><?= e($o['wechat_order_no'] ?? $o['order_no']) ?></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td><?= e($o['business_type_name']) ?></td>
                        <td class="money"><?= formatMoney($o['amount']) ?></td>
                        <td><?= orderStatusLabel($o['status']) ?></td>
                        <td><?= formatDateTimeShort($o['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--text-muted)">暂无订单</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>提现记录</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead><tr><th>单号</th><th>金额</th><th>状态</th><th>申请时间</th></tr></thead>
                <tbody>
                <?php foreach ($withdrawals as $w): ?>
                    <tr>
                        <td><?= e($w['withdrawal_no']) ?></td>
                        <td class="money"><?= formatMoney($w['amount']) ?></td>
                        <td><?= withdrawalStatusLabel($w['status']) ?></td>
                        <td><?= formatDateTimeShort($w['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($withdrawals)): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted)">暂无提现</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
