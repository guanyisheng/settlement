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

// 审核 / 改结算 / 删除
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = (int) ($_POST['order_id'] ?? 0);
    try {
        if ($action === 'approve') {
            if (!Auth::can('order.review') && !Auth::canAccessPage('orders')) {
                throw new RuntimeException('无审核权限');
            }
            OrderService::approve($pdo, $orderId, Auth::id(), [
                'staff_amount'  => $_POST['staff_amount'] ?? '',
                'rate_a_pct'    => $_POST['rate_a_pct'] ?? '',
                'rate_b_pct'    => $_POST['rate_b_pct'] ?? '',
                'manual_amount' => $_POST['manual_amount'] ?? '',
            ]);
            flash('success', '订单已通过');
        } elseif ($action === 'reject') {
            OrderService::reject($pdo, $orderId, Auth::id(), $_POST['reject_reason'] ?? '');
            flash('success', '订单已拒绝');
        } elseif ($action === 'update_settlement') {
            OrderService::updateSettlement($pdo, $orderId, $_POST);
            flash('success', '本单结算已更新（未改全局倍率）');
        } elseif ($action === 'delete') {
            if (!Auth::can('order.delete') && !Auth::isBoss()) {
                throw new RuntimeException('无删除权限');
            }
            OrderService::hardDelete($pdo, $orderId);
            flash('success', '订单已彻底删除，不再计入统计');
            redirect('/admin/orders.php');
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
$defaultRates = SettlementService::rates();
$canDelete = Auth::can('order.delete') || Auth::isBoss();
$canReview = Auth::can('order.review') || Auth::isBoss();

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
                            <?php if ($o['status'] === 'PENDING' && $canReview): ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('确认通过此订单？')">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">通过</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-danger" onclick="openReject(<?= $o['id'] ?>)">拒绝</button>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('彻底删除后不可恢复，且不再计入统计/余额。确定？')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <button type="submit" class="btn btn-sm">删除</button>
                                </form>
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
                <dt>打手结算</dt>
                <dd class="money">
                    <?= formatMoney($viewOrder['staff_amount'] ?? SettlementService::calcStaffAmount((float) $viewOrder['amount'])) ?>
                    <span style="color:var(--text-muted);font-size:12px">
                        <?= e(SettlementService::formulaLabel(
                            isset($viewOrder['rate_a']) ? (float) $viewOrder['rate_a'] : null,
                            isset($viewOrder['rate_b']) ? (float) $viewOrder['rate_b'] : null
                        )) ?>
                        （本单快照，改全局倍率不影响）
                    </span>
                </dd>
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

            <?php if ($viewOrder['status'] === 'PENDING' && $canReview): ?>
            <?php
                $ra = round(((float) ($viewOrder['rate_a'] ?? $defaultRates['rate_a'])) * 100, 2);
                $rb = round(((float) ($viewOrder['rate_b'] ?? $defaultRates['rate_b'])) * 100, 2);
                $orderAmount = (float) $viewOrder['amount'];
                $calcSa = SettlementService::calcStaffAmount(
                    $orderAmount,
                    $ra / 100,
                    $rb / 100
                );
            ?>
            <hr style="border-color:var(--border);margin:16px 0">
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:10px">
                改倍率会<strong>自动重算</strong>结算金额。特殊单若要直接定金额，勾选「手动指定」后再填。
            </p>
            <form method="post" id="settleForm">
                <input type="hidden" name="order_id" value="<?= (int) $viewOrder['id'] ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>基础倍率 %</label>
                        <input type="number" name="rate_a_pct" id="settleRateA" class="form-control" step="0.01" min="0" max="100" value="<?= e((string) $ra) ?>">
                    </div>
                    <div class="form-group">
                        <label>打手倍率 %</label>
                        <input type="number" name="rate_b_pct" id="settleRateB" class="form-control" step="0.01" min="0" max="100" value="<?= e((string) $rb) ?>">
                    </div>
                    <div class="form-group">
                        <label>打手结算金额</label>
                        <input type="number" name="staff_amount" id="settleAmount" class="form-control" step="0.01" min="0"
                               value="<?= e((string) $calcSa) ?>" readonly
                               data-order-amount="<?= e((string) $orderAmount) ?>">
                        <p style="font-size:12px;color:var(--text-muted);margin-top:6px" id="settleHint">
                            按倍率自动计算：订单 <?= formatMoney($orderAmount) ?> × 倍率
                        </p>
                    </div>
                </div>
                <label style="display:flex;align-items:center;gap:8px;margin:8px 0 16px;font-size:13px;cursor:pointer">
                    <input type="checkbox" name="manual_amount" id="manualAmount" value="1">
                    手动指定结算金额（不按倍率算，适合体验单）
                </label>
                <button type="submit" name="action" value="approve" class="btn btn-success"
                        onclick="return confirm('按上方金额/倍率通过此单？')">按此结算通过</button>
                <button type="submit" name="action" value="update_settlement" class="btn">仅保存结算</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <?php if ($canDelete): ?>
            <form method="post" style="margin-right:auto" onsubmit="return confirm('彻底删除？删后统计/余额都不再算这单')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="order_id" value="<?= (int) $viewOrder['id'] ?>">
                <button type="submit" class="btn btn-danger">删除订单</button>
            </form>
            <?php endif; ?>
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

(function () {
    const rateA = document.getElementById('settleRateA');
    const rateB = document.getElementById('settleRateB');
    const amount = document.getElementById('settleAmount');
    const manual = document.getElementById('manualAmount');
    const hint = document.getElementById('settleHint');
    if (!rateA || !rateB || !amount) return;
    const orderAmount = parseFloat(amount.dataset.orderAmount || '0') || 0;

    function recalc() {
        if (manual?.checked) return;
        const a = (parseFloat(rateA.value) || 0) / 100;
        const b = (parseFloat(rateB.value) || 0) / 100;
        const sa = Math.round(orderAmount * a * b * 100) / 100;
        amount.value = sa.toFixed(2);
        if (hint) {
            hint.textContent = '按倍率自动计算：' + orderAmount.toFixed(2) + ' × ' +
                (a * 100).toFixed(2) + '% × ' + (b * 100).toFixed(2) + '% = ' + sa.toFixed(2);
        }
    }

    rateA.addEventListener('input', recalc);
    rateB.addEventListener('input', recalc);
    manual?.addEventListener('change', function () {
        amount.readOnly = !manual.checked;
        if (!manual.checked) recalc();
        else if (hint) hint.textContent = '已手动指定，改倍率不会改这个金额';
    });
    recalc();
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
