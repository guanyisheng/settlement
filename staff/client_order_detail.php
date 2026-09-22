<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/ExtraFeeService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireStaff();

$pdo = Database::getConnection();
$staffId = (int) Auth::id();
$id = (int) ($_GET['id'] ?? 0);
$order = ClientOrderService::getById($pdo, $id);
if (!$order) {
    flash('error', '订单不存在');
    redirect('/staff/client_orders.php');
}

$st = (string) $order['status'];
$mine = (int) ($order['staff_id'] ?? 0) === $staffId;
$canSee = $st === 'POOL' || $mine;
if (!$canSee) {
    flash('error', '无权查看该单');
    redirect('/staff/client_orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        match ($action) {
            'accept' => ClientOrderService::accept($pdo, $id, $staffId),
            'start' => ClientOrderService::start($pdo, $id, $staffId),
            'transfer' => ClientOrderService::transferToPool($pdo, $id, $staffId, (string) ($_POST['note'] ?? '')),
            default => throw new InvalidArgumentException('未知操作（完成服务由客服操作）'),
        };
        flash('success', '操作成功');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/staff/client_order_detail.php?id=' . $id);
}

$error = flash('error');
$success = flash('success');
$feeLabel = ExtraFeeService::formatSnapshotLabel($order['extra_fees_json'] ?? null, isset($order['extra_fees_rate']) ? (float) $order['extra_fees_rate'] : null);
$order = ClientOrderService::getById($pdo, $id);
$st = (string) $order['status'];
$mine = (int) ($order['staff_id'] ?? 0) === $staffId;

$mask = static function (?string $v): string {
    $v = trim((string) $v);
    if ($v === '') {
        return '-';
    }
    $len = mb_strlen($v);
    if ($len <= 2) {
        return str_repeat('*', $len);
    }
    return mb_substr($v, 0, 1) . str_repeat('*', max(1, $len - 2)) . mb_substr($v, -1);
};

$user = Auth::user();
$currentPage = 'client_orders';
$pageTitle = brandTitle('顾客单详情');
$bodyClass = 'has-nav';
require __DIR__ . '/partials/head.php';
?>
<div class="app-shell">
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="top-bar-info">
                <h1>顾客单详情</h1>
                <p class="subtitle"><a href="/staff/client_orders.php">← 返回列表</a></p>
            </div>
            <?php require __DIR__ . '/partials/user-chip.php'; ?>
        </div>
    </header>
    <main class="page-content">
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

        <div class="order-card" style="padding:14px">
            <div><strong><?= e($order['order_no']) ?></strong> · <?= e(ClientOrderService::statusLabel($st)) ?></div>
            <div style="margin-top:8px"><?= e($order['business_type_name']) ?> ×<?= (int) $order['quantity'] ?></div>
            <div>金额 ¥<?= formatMoney($order['amount']) ?></div>
            <?php if ($feeLabel !== ''): ?><div style="color:var(--text-muted);font-size:12px">额外项目：<?= e($feeLabel) ?></div><?php endif; ?>
            <div style="margin-top:10px;font-size:13px">客户端：<?= e($order['game_client'] ?? '-') ?></div>
            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                <span>游戏名：<?= e($mask($order['game_name'] ?? '')) ?></span>
                <button type="button" class="btn btn-sm" data-copy="<?= e($order['game_name'] ?? '') ?>">复制</button>
            </div>
            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                <span>游戏ID：<?= e($mask($order['game_id'] ?? '')) ?></span>
                <button type="button" class="btn btn-sm" data-copy="<?= e($order['game_id'] ?? '') ?>">复制</button>
            </div>
            <?php if (!empty($order['remark'])): ?><div style="color:var(--text-muted);margin-top:8px">备注：<?= e($order['remark']) ?></div><?php endif; ?>
            <?php if (!empty($order['transfer_note'])): ?><div style="color:var(--text-muted)">转单：<?= e($order['transfer_note']) ?></div><?php endif; ?>
            <p style="font-size:12px;color:var(--text-muted);margin-top:10px">老板联系方式仅客服可见。完成服务由客服点击结算。</p>
            <?php if ($st === 'DONE'): ?>
                <div style="margin-top:8px">结算 ¥<?= formatMoney($order['staff_amount'] ?? 0) ?></div>
            <?php endif; ?>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px">
            <?php if ($st === 'POOL' || ($st === 'WAITING' && $mine)): ?>
                <form method="post"><input type="hidden" name="action" value="accept"><button class="btn btn-primary"><?= $st === 'POOL' ? '抢单/接单' : '接单确认' ?></button></form>
            <?php endif; ?>
            <?php if ($mine && $st === 'ACCEPTED'): ?>
                <form method="post"><input type="hidden" name="action" value="start"><button class="btn">确认开始服务</button></form>
            <?php endif; ?>
            <?php if ($mine && in_array($st, ['ACCEPTED', 'DOING'], true)): ?>
                <form method="post" onsubmit="return confirm('转单回池？你将拿不到这单钱')">
                    <input type="hidden" name="action" value="transfer">
                    <input type="hidden" name="note" value="打手转单">
                    <button class="btn">转单回池</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
    <?php require __DIR__ . '/partials/nav.php'; ?>
</div>
<script>
document.querySelectorAll('[data-copy]').forEach(btn => {
  btn.addEventListener('click', async () => {
    const text = btn.getAttribute('data-copy') || '';
    try {
      await navigator.clipboard.writeText(text);
      const old = btn.textContent;
      btn.textContent = '已复制';
      setTimeout(() => { btn.textContent = old; }, 1200);
    } catch (e) {
      prompt('复制以下内容', text);
    }
  });
});
</script>
</body>
</html>
