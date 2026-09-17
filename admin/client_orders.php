<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('client_orders');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    try {
        ClientOrderService::cancel($pdo, (int) ($_POST['id'] ?? 0), (int) Auth::id(), true);
        flash('success', '已取消');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin/client_orders.php');
}

$orders = ClientOrderService::isReady($pdo) ? ClientOrderService::listAll($pdo) : [];
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
    <div class="card-header"><h2>顾客订单</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>单号</th><th>顾客</th><th>业务</th><th>金额</th><th>打手结算</th><th>打手</th><th>状态</th><th>时间</th><th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= e($o['order_no']) ?></td>
                        <td><?= e($o['client_name']) ?></td>
                        <td><?= e($o['business_type_name']) ?></td>
                        <td class="money"><?= formatMoney($o['amount']) ?></td>
                        <td class="money"><?= $o['staff_amount'] !== null ? formatMoney($o['staff_amount']) : '-' ?></td>
                        <td><?= e($o['staff_name'] ?: '-') ?></td>
                        <td><?= e(ClientOrderService::statusLabel((string) $o['status'])) ?></td>
                        <td><?= formatDateTimeShort($o['created_at']) ?></td>
                        <td>
                            <?php if (!in_array($o['status'], ['DONE', 'CANCELLED'], true)): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('取消该单？')">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                <button class="btn btn-sm">取消</button>
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
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
