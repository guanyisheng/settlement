<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/MembershipService.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/ActivityService.php';

if (!$loggedIn) {
    flash('error', '请先登录');
    redirect('/login.php?next=' . rawurlencode('/customer/me.php'));
}

$row = $pdo->prepare('SELECT id, username, nickname, growth_points, membership_expire_at, role FROM users WHERE id = ?');
$row->execute([$uid]);
$me = $row->fetch(PDO::FETCH_ASSOC) ?: [];

$points = (int) ($me['growth_points'] ?? 0);
$tier = MembershipService::isReady($pdo) ? MembershipService::tierForPoints($pdo, $points) : ['name' => '普通'];

$walletBal = 0.0;
try {
    $st = $pdo->prepare('SELECT balance FROM customers WHERE user_id = ? LIMIT 1');
    $st->execute([$uid]);
    $walletBal = (float) ($st->fetchColumn() ?: 0);
} catch (Throwable) {
}

$orderCnt = 0;
if ($isClient && ClientOrderService::isReady($pdo)) {
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM client_orders WHERE client_id = ?');
        $st->execute([$uid]);
        $orderCnt = (int) $st->fetchColumn();
    } catch (Throwable) {
    }
}

$actPoints = 0;
if (ActivityService::isReady($pdo)) {
    $actPoints = ActivityService::getWallet($pdo, $uid)['points'];
}

$pageTitle = '我的';
$appTab = 'me';
require __DIR__ . '/partials/app_head.php';

$nick = (string) ($me['nickname'] ?? $me['username'] ?? '用户');
$initial = mb_substr($nick, 0, 1);
?>

<div class="app-me-hero">
    <div class="app-me-row">
        <div class="app-me-avatar"><?= e($initial) ?></div>
        <div class="app-me-meta">
            <div class="name">
                <?= e($nick) ?>
                <span class="app-me-id">ID <?= (int) ($me['id'] ?? 0) ?></span>
            </div>
            <a class="app-me-edit" href="/customer/profile.php">✎ 编辑资料</a>
            <div class="app-me-tags">
                <span><?= e($tier['name'] ?? '普通') ?></span>
                <span>成长值 <?= $points ?></span>
                <?php if ($isWorker): ?><span>工作人员</span><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="app-wallet-card">
    <div class="cell">
        <div class="lab">我的钱包</div>
        <div class="val"><span class="app-coin">★</span><?= rtrim(rtrim(number_format($walletBal, 2, '.', ''), '0'), '.') ?: '0' ?></div>
    </div>
    <a class="cell" href="/customer/orders.php" style="color:inherit">
        <div class="lab">我的订单</div>
        <div class="val">📋 <?= $orderCnt ?></div>
    </a>
</div>

<a class="app-promo" href="/customer/activity.php">
    活动积分 <?= $actPoints ?> · 去抽奖兑换 ›
</a>

<div class="app-menu-grid">
    <a class="app-menu-item" href="/customer/activity.php">
        <div class="app-menu-ico">🎁</div>活动抽奖
    </a>
    <a class="app-menu-item" href="/customer/orders.php">
        <div class="app-menu-ico">📦</div>我的订单
    </a>
    <a class="app-menu-item" href="/customer/staff.php">
        <div class="app-menu-ico">🎮</div>打手广场
    </a>
    <a class="app-menu-item" href="/customer/register.php">
        <div class="app-menu-ico">✨</div>邀请注册
    </a>
    <a class="app-menu-item" href="<?= e($csLink) ?>" <?= str_starts_with((string) $csLink, 'http') ? 'target="_blank" rel="noopener"' : '' ?>>
        <div class="app-menu-ico">📣</div>投诉大厅
    </a>
    <a class="app-menu-item" href="/customer/profile.php">
        <div class="app-menu-ico">⚙️</div>设置
    </a>
    <?php if ($isWorker): ?>
    <a class="app-menu-item" href="/choose_portal.php">
        <div class="app-menu-ico">🛠️</div>工作台
    </a>
    <?php endif; ?>
    <a class="app-menu-item" href="/logout.php">
        <div class="app-menu-ico">🚪</div>退出登录
    </a>
</div>

<?php require __DIR__ . '/partials/app_foot.php'; ?>
