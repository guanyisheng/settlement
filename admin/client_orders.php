<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/ExtraFeeService.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('client_orders');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');
$staffList = UserService::getStaffList($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if ($action === 'cancel') {
            ClientOrderService::cancel($pdo, $id, (int) Auth::id(), true);
            flash('success', '已取消');
        } elseif ($action === 'assign') {
            ClientOrderService::assignStaff($pdo, $id, (int) ($_POST['staff_id'] ?? 0));
            flash('success', '已派单给打手');
        } elseif ($action === 'complete') {
            ClientOrderService::completeByAdmin($pdo, $id);
            flash('success', '已完成服务，打手结算已入账');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    $q = array_filter(['id' => $id > 0 ? $id : null]);
    redirect('/admin/client_orders.php' . ($q !== [] ? ('?' . http_build_query($q)) : ''));
}

$orders = ClientOrderService::isReady($pdo) ? ClientOrderService::listAll($pdo) : [];
$viewId = (int) ($_GET['id'] ?? 0);
$view = $viewId > 0 ? ClientOrderService::getById($pdo, $viewId) : null;

$currentPage = 'client_orders';
$pageTitle = '顾客订单';
require __DIR__ . '/partials/header.php';
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!ClientOrderService::isReady($pdo)): ?>
<div class="alert alert-error">请先执行 database/migrate_customer_portal.sql</div>
<?php else: ?>

<div class="card">
    <div class="card-header"><h2>顾客订单（派单流程）</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>单号</th><th>顾客</th><th>业务</th><th>游戏</th><th>金额</th><th>打手</th><th>状态</th><th>时间</th><th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= e($o['order_no']) ?></td>
                        <td><?= e($o['client_name']) ?></td>
                        <td><?= e($o['business_type_name']) ?></td>
                        <td style="font-size:12px"><?= e($o['game_name'] ?? '-') ?> / <?= e($o['game_client'] ?? '-') ?></td>
                        <td class="money"><?= formatMoney($o['amount']) ?></td>
                        <td><?= e($o['staff_name'] ?: '待派') ?></td>
                        <td><?= e(ClientOrderService::statusLabel((string) $o['status'])) ?></td>
                        <td><?= formatDateTimeShort($o['created_at']) ?></td>
                        <td><a class="btn btn-sm btn-primary" href="?id=<?= (int) $o['id'] ?>">详情派单</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($view): ?>
<?php
$feeLabel = ExtraFeeService::formatSnapshotLabel($view['extra_fees_json'] ?? null, isset($view['extra_fees_rate']) ? (float) $view['extra_fees_rate'] : null);
$st = (string) $view['status'];
?>
<div class="modal-overlay show" id="detailModal">
    <div class="modal" style="max-width:640px">
        <div class="modal-header">顾客单详情 · <?= e(ClientOrderService::statusLabel($st)) ?></div>
        <div class="modal-body">
            <dl class="detail-grid">
                <dt>单号</dt><dd><?= e($view['order_no']) ?></dd>
                <dt>顾客</dt><dd><?= e($view['client_name']) ?>（<?= e($view['client_username']) ?>）</dd>
                <dt>联系方式</dt><dd><?= e($view['contact'] ?? '-') ?></dd>
                <dt>业务</dt><dd><?= e($view['business_type_name']) ?> ×<?= (int) $view['quantity'] ?></dd>
                <dt>金额</dt><dd class="money"><?= formatMoney($view['amount']) ?></dd>
                <?php if ($feeLabel !== ''): ?><dt>额外项目</dt><dd><?= e($feeLabel) ?></dd><?php endif; ?>
                <dt>游戏名</dt><dd><?= e($view['game_name'] ?? '-') ?></dd>
                <dt>游戏ID</dt><dd><?= e($view['game_id'] ?? '-') ?></dd>
                <dt>客户端</dt><dd><?= e($view['game_client'] ?? '-') ?></dd>
                <dt>备注</dt><dd><?= e($view['remark'] ?: '-') ?></dd>
                <dt>当前打手</dt><dd><?= e($view['staff_name'] ?: '未派单') ?></dd>
                <?php if ($view['staff_amount'] !== null): ?>
                <dt>打手结算</dt><dd class="money"><?= formatMoney($view['staff_amount']) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if (!in_array($st, ['DONE', 'CANCELLED'], true)): ?>
            <hr style="border-color:var(--border);margin:16px 0">
            <form method="post" class="form-row" style="align-items:flex-end">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="id" value="<?= (int) $view['id'] ?>">
                <div class="form-group" style="flex:1">
                    <label>派单给打手（群内抢到后勾选）</label>
                    <select name="staff_id" class="form-control" required>
                        <option value="">请选择</option>
                        <?php foreach ($staffList as $s): ?>
                            <?php if ((int) ($s['status'] ?? 1) !== 1) continue; ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) ($view['staff_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= e($s['nickname'] ?: $s['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">确认派单</button>
            </form>
            <?php if (in_array($st, ['WAITING', 'ACCEPTED', 'DOING'], true) && (int) ($view['staff_id'] ?? 0) > 0): ?>
            <form method="post" style="margin-top:12px" onsubmit="return confirm('确认完成服务？打手将入账结算')">
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="id" value="<?= (int) $view['id'] ?>">
                <button class="btn btn-success" type="submit">完成服务并结算</button>
            </form>
            <?php endif; ?>
            <form method="post" style="margin-top:12px" onsubmit="return confirm('取消该单？')">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" value="<?= (int) $view['id'] ?>">
                <button class="btn btn-danger" type="submit">取消订单</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <a href="/admin/client_orders.php" class="btn">关闭</a>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
