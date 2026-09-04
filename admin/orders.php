<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/OrderService.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/CustomerService.php';
require_once __DIR__ . '/../includes/BusinessTypeService.php';
require_once __DIR__ . '/../includes/SettlementService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('orders');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

// 审核操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = (int) ($_POST['order_id'] ?? 0);
    try {
        if ($action === 'approve') {
            OrderService::approve($pdo, $orderId, Auth::id());
            flash('success', '订单已通过');
        } elseif ($action === 'reject') {
            OrderService::reject($pdo, $orderId, Auth::id(), $_POST['reject_reason'] ?? '');
            flash('success', '订单已拒绝');
        }
        redirect('/admin/orders.php?' . http_build_query($_GET));
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/orders.php?' . http_build_query($_GET));
    }
}

$filters = [
    'staff_id'         => $_GET['staff_id'] ?? '',
    'customer_id'      => $_GET['customer_id'] ?? '',
    'business_type_id' => $_GET['business_type_id'] ?? '',
    'status'           => $_GET['status'] ?? '',
    'date_from'        => $_GET['date_from'] ?? '',
    'date_to'          => $_GET['date_to'] ?? '',
];
$filters = array_filter($filters, fn($v) => $v !== '');

$orders = OrderService::search($pdo, $filters);
$staffList = UserService::getStaffList($pdo);
$customers = CustomerService::getAll($pdo);
$businessTypes = BusinessTypeService::getAll($pdo);

$viewOrder = null;
if (!empty($_GET['id'])) {
    $viewOrder = OrderService::getById($pdo, (int) $_GET['id']);
}

$currentPage = 'orders';
$pageTitle = '订单管理';
require __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2>筛选条件</h2></div>
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
                <label>客户</label>
                <select name="customer_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($_GET['customer_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>业务类型</label>
                <select name="business_type_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($businessTypes as $bt): ?>
                        <option value="<?= $bt['id'] ?>" <?= ($_GET['business_type_id'] ?? '') == $bt['id'] ? 'selected' : '' ?>><?= e($bt['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>状态</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (['PENDING'=>'待审核','APPROVED'=>'已通过','REJECTED'=>'已拒绝','SETTLED'=>'已结算'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= ($_GET['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>开始日期</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($_GET['date_from'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>结束日期</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($_GET['date_to'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary">搜索</button>
            <a href="/admin/orders.php" class="btn">重置</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>订单列表 (<?= count($orders) ?>)</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>微信订单号</th><th>打手</th><th>客户</th><th>业务</th><th>数量</th>
                        <th>订单金额</th><th>打手结算</th><th>状态</th><th>提交时间</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="10" style="text-align:center;color:var(--text-muted)">暂无数据</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= e($o['wechat_order_no'] ?? $o['order_no']) ?></td>
                        <td><?= e($o['staff_name']) ?></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td><?= e($o['business_type_name']) ?></td>
                        <td><?= e((string) (int) $o['quantity']) ?></td>
                        <td class="money"><?= formatMoney($o['amount']) ?></td>
                        <td class="money"><?= formatMoney($o['staff_amount'] ?? SettlementService::calcStaffAmount((float) $o['amount'])) ?></td>
                        <td><span class="badge badge-<?= strtolower($o['status']) === 'pending' ? 'pending' : (strtolower($o['status']) === 'approved' ? 'approved' : (strtolower($o['status']) === 'rejected' ? 'rejected' : 'settled')) ?>"><?= orderStatusLabel($o['status']) ?></span></td>
                        <td><?= formatDateTimeShort($o['created_at']) ?></td>
                        <td class="actions">
                            <a href="?<?= http_build_query(array_merge($_GET, ['id' => $o['id']])) ?>" class="btn btn-sm">详情</a>
                            <?php if ($o['status'] === 'PENDING'): ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('确认通过此订单？')">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">通过</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-danger" onclick="openReject(<?= $o['id'] ?>)">拒绝</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($viewOrder): ?>
<div class="modal-overlay show" id="detailModal">
    <div class="modal" style="max-width:560px">
        <div class="modal-header">订单详情</div>
        <div class="modal-body">
            <dl class="detail-grid">
                <dt>微信订单号</dt><dd><?= e($viewOrder['wechat_order_no'] ?? '-') ?></dd>
                <dt>订单截图</dt>
                <dd>
                    <?php $screenshots = parseScreenshotKeys($viewOrder['screenshot_key'] ?? null); ?>
                    <?php if ($screenshots): ?>
                        <?php foreach ($screenshots as $i => $key): ?>
                            <a href="/admin/screenshot.php?id=<?= $viewOrder['id'] ?>&i=<?= $i ?>" target="_blank" class="btn btn-sm btn-primary" style="margin-right:6px;margin-bottom:6px">
                                查看截图<?= count($screenshots) > 1 ? (' ' . ($i + 1)) : '' ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>-<?php endif; ?>
                </dd>
                <dt>系统编号</dt><dd><?= e($viewOrder['order_no']) ?></dd>
                <dt>打手</dt><dd><?= e($viewOrder['staff_name']) ?></dd>
                <dt>客户</dt><dd><?= e($viewOrder['customer_name']) ?></dd>
                <dt>业务类型</dt><dd><?= e($viewOrder['business_type_name']) ?></dd>
                <dt>数量</dt><dd><?= e((string) (int) $viewOrder['quantity']) ?></dd>
                <dt>单价</dt><dd><?= formatMoney($viewOrder['unit_price']) ?></dd>
                <dt>订单金额</dt><dd class="money"><?= formatMoney($viewOrder['amount']) ?></dd>
                <dt>打手结算</dt><dd class="money"><?= formatMoney($viewOrder['staff_amount'] ?? SettlementService::calcStaffAmount((float) $viewOrder['amount'])) ?> <span style="color:var(--text-muted);font-size:12px"><?= SettlementService::formulaLabel() ?></span></dd>
                <dt>开始时间</dt><dd><?= formatDateTime($viewOrder['start_time']) ?></dd>
                <dt>结束时间</dt><dd><?= formatDateTime($viewOrder['end_time']) ?></dd>
                <dt>备注</dt><dd><?= e($viewOrder['remark'] ?: '-') ?></dd>
                <dt>状态</dt><dd><?= orderStatusLabel($viewOrder['status']) ?></dd>
                <dt>提交时间</dt><dd><?= formatDateTime($viewOrder['created_at']) ?></dd>
                <?php if ($viewOrder['reviewed_at']): ?>
                <dt>审核人</dt><dd><?= e($viewOrder['reviewer_name'] ?? '-') ?></dd>
                <dt>审核时间</dt><dd><?= formatDateTime($viewOrder['reviewed_at']) ?></dd>
                <?php endif; ?>
                <?php if ($viewOrder['reject_reason']): ?>
                <dt>拒绝原因</dt><dd style="color:var(--danger)"><?= e($viewOrder['reject_reason']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>
        <div class="modal-footer">
            <a href="/admin/orders.php?<?= http_build_query(array_diff_key($_GET, ['id' => ''])) ?>" class="btn">关闭</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">拒绝订单</div>
        <form method="post">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="order_id" id="rejectOrderId">
            <div class="modal-body">
                <div class="form-group">
                    <label>拒绝原因</label>
                    <textarea name="reject_reason" class="form-control" rows="3" required placeholder="请填写拒绝原因"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="document.getElementById('rejectModal').classList.remove('show')">取消</button>
                <button type="submit" class="btn btn-danger">确认拒绝</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReject(id) {
    document.getElementById('rejectOrderId').value = id;
    document.getElementById('rejectModal').classList.add('show');
}
document.getElementById('detailModal')?.addEventListener('click', e => {
    if (e.target.id === 'detailModal') location.href = '/admin/orders.php?<?= http_build_query(array_diff_key($_GET, ['id' => ''])) ?>';
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
