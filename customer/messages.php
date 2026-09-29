<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';

$msgs = [];
if ($loggedIn && $isClient && ClientOrderService::isReady($pdo)) {
    try {
        $st = $pdo->prepare(
            'SELECT id, order_no, status, amount, created_at, updated_at
             FROM client_orders WHERE client_id = ?
             ORDER BY updated_at DESC, id DESC LIMIT 30'
        );
        $st->execute([$uid]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $statusMap = [
                'WAITING' => '等待接单',
                'POOL' => '抢单池中',
                'ACCEPTED' => '已接单',
                'DOING' => '进行中',
                'DONE' => '已完成',
                'CANCELLED' => '已取消',
            ];
            $msgs[] = [
                'title' => '订单 ' . $o['order_no'],
                'sub' => ($statusMap[$o['status']] ?? $o['status']) . ' · ¥' . number_format((float) $o['amount'], 2),
                'time' => $o['updated_at'] ?: $o['created_at'],
                'href' => '/customer/order_detail.php?id=' . (int) $o['id'],
                'icon' => '📦',
            ];
        }
    } catch (Throwable) {
    }
}

$pageTitle = '消息';
$appTab = 'msg';
require __DIR__ . '/partials/app_head.php';
?>

<header class="app-topbar">
    <div class="app-topbar-title">消息</div>
</header>

<div class="app-msg-list">
    <a class="app-msg-item" href="<?= e($csLink) ?>" <?= str_starts_with((string) $csLink, 'http') ? 'target="_blank" rel="noopener"' : '' ?>>
        <div class="app-msg-avatar">🎧</div>
        <div class="app-msg-body">
            <div class="t">在线客服</div>
            <div class="s">有问题点这里联系客服</div>
        </div>
        <div class="app-msg-time">客服</div>
    </a>

    <?php if (!$loggedIn): ?>
        <div class="app-empty">
            登录后可查看订单消息<br>
            <a href="/login.php?next=<?= rawurlencode('/customer/messages.php') ?>" style="color:var(--app-purple)">去登录</a>
        </div>
    <?php elseif ($msgs === []): ?>
        <div class="app-empty">暂无订单消息</div>
    <?php else: ?>
        <?php foreach ($msgs as $m): ?>
            <a class="app-msg-item" href="<?= e($m['href']) ?>">
                <div class="app-msg-avatar"><?= e($m['icon']) ?></div>
                <div class="app-msg-body">
                    <div class="t"><?= e($m['title']) ?></div>
                    <div class="s"><?= e($m['sub']) ?></div>
                </div>
                <div class="app-msg-time"><?= e(mb_substr((string) $m['time'], 5, 11)) ?></div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/app_foot.php'; ?>
