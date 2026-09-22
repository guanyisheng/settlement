<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/ExtraFeeService.php';

if (!$isClient) {
    flash('error', '请先登录顾客账号');
    redirect('/login.php?next=' . rawurlencode('/customer/orders.php'));
}

$id = (int) ($_GET['id'] ?? 0);
$order = ClientOrderService::getById($pdo, $id);
if (!$order || (int) $order['client_id'] !== $uid) {
    flash('error', '订单不存在');
    redirect('/customer/orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'cancel') {
            ClientOrderService::cancel($pdo, $id, $uid);
            flash('success', '已取消');
        } elseif ($action === 'review') {
            ClientOrderService::addReview(
                $pdo, $id, $uid,
                (int) ($_POST['score'] ?? 5),
                (string) ($_POST['content'] ?? '')
            );
            flash('success', '评价已提交');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/customer/order_detail.php?id=' . $id);
}

$review = ClientOrderService::getReview($pdo, $id);
$feeLabel = ExtraFeeService::formatSnapshotLabel($order['extra_fees_json'] ?? null, isset($order['extra_fees_rate']) ? (float) $order['extra_fees_rate'] : null);

$currentPage = 'orders';
$pageTitle = '订单详情';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<p style="margin-bottom:12px"><a class="btn btn-sm" href="/customer/orders.php">← 返回订单列表</a></p>

<div class="card">
    <div class="card-header">
        <h2><?= e($order['order_no']) ?></h2>
        <span class="badge badge-settled"><?= e(ClientOrderService::statusLabel((string) $order['status'])) ?></span>
    </div>
    <div class="card-body">
        <p><?= e($order['business_type_name']) ?> ×<?= (int) $order['quantity'] ?></p>
        <p class="price">¥<?= formatMoney($order['amount']) ?></p>
        <?php if ($feeLabel !== ''): ?><p class="product-card-meta">额外项目：<?= e($feeLabel) ?></p><?php endif; ?>
        <p class="product-card-meta">游戏名：<?= e($order['game_name'] ?? '-') ?></p>
        <p class="product-card-meta">游戏ID：<?= e($order['game_id'] ?? '-') ?></p>
        <p class="product-card-meta">客户端：<?= e($order['game_client'] ?? '-') ?></p>
        <p class="product-card-meta">联系方式：<?= e($order['contact'] ?? '-') ?></p>
        <?php if (!empty($order['remark'])): ?><p class="product-card-meta">备注：<?= e($order['remark']) ?></p><?php endif; ?>
        <hr style="border:none;border-top:1px solid var(--border);margin:14px 0">
        <p>打手：<?= e($order['staff_name'] ?: '待接') ?></p>
        <?php if (!empty($order['staff_wechat'])): ?>
            <p class="product-card-meta">联系微信：<?= e($order['staff_wechat']) ?></p>
        <?php endif; ?>
        <?php if ((int) ($order['staff_id'] ?? 0) > 0): ?>
            <p style="margin-top:8px"><a href="/customer/staff_detail.php?id=<?= (int) $order['staff_id'] ?>">查看打手主页 →</a></p>
        <?php endif; ?>
        <?php if (in_array($order['status'], ['WAITING', 'POOL'], true)): ?>
            <form method="post" style="margin-top:14px" onsubmit="return confirm('确认取消？')">
                <input type="hidden" name="action" value="cancel">
                <button class="btn btn-danger" type="submit">取消订单</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($order['status'] === 'DONE'): ?>
<div class="card">
    <div class="card-header"><h2>评价</h2></div>
    <div class="card-body">
        <?php if ($review): ?>
            <p><?= (int) $review['score'] ?> 星 · <?= e($review['content'] ?: '（无文字）') ?></p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="review">
                <div class="form-group" style="margin-bottom:12px">
                    <label>星级</label>
                    <select name="score" class="form-control">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?= $i ?>"><?= $i ?> 星</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>文字评价（选填）</label>
                    <input type="text" name="content" class="form-control" placeholder="服务怎么样？">
                </div>
                <button class="btn btn-primary" type="submit">提交评价</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
