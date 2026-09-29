<?php
require_once __DIR__ . '/../../includes/icons.php';
$currentPage = $currentPage ?? '';
$loggedIn = $loggedIn ?? Auth::check();
$isClient = $isClient ?? Auth::isClient();
$isWorker = $isWorker ?? Auth::isWorker();
$user = $user ?? Auth::user();
$pageTitle = $pageTitle ?? brandName();
?>
<div class="layout" id="customerLayout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="<?= brandLogo() ?>" alt="<?= e(brandName()) ?>" class="sidebar-brand-logo">
        </div>
        <nav class="sidebar-nav">
            <a href="/customer/index.php" class="nav-item <?= $currentPage === 'home' ? 'active' : '' ?>">
                <?= svgIcon('dashboard') ?><span>首页 / 业务</span>
            </a>
            <a href="/customer/staff.php" class="nav-item <?= $currentPage === 'staff' ? 'active' : '' ?>">
                <?= svgIcon('staff') ?><span>打手广场</span>
            </a>
            <a href="/customer/orders.php" class="nav-item <?= $currentPage === 'orders' ? 'active' : '' ?>">
                <?= svgIcon('orders') ?><span>我的订单</span>
            </a>
            <a href="/customer/activity.php" class="nav-item <?= $currentPage === 'activity' ? 'active' : '' ?>">
                <?= svgIcon('dashboard') ?><span>活动抽奖</span>
            </a>
            <a href="/customer/profile.php" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
                <?= svgIcon('customers') ?><span>个人中心</span>
            </a>
            <?php if (!$loggedIn): ?>
            <a href="/customer/register.php" class="nav-item">
                <?= svgIcon('registrations') ?><span>顾客注册</span>
            </a>
            <a href="/login.php?next=<?= rawurlencode('/customer/index.php') ?>" class="nav-item">
                <?= svgIcon('password') ?><span>登录</span>
            </a>
            <?php endif; ?>
            <?php if ($isWorker): ?>
            <a href="/choose_portal.php" class="nav-item">
                <?= svgIcon('settings') ?><span>工作台</span>
            </a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="main">
        <header class="topbar">
            <button type="button" class="mobile-nav-toggle" id="mobileNavToggle" aria-label="打开菜单">☰</button>
            <div class="topbar-title"><?= e($pageTitle) ?></div>
            <div class="topbar-user">
                <?php if ($loggedIn): ?>
                    <span><?= e($user['nickname'] ?? $user['username'] ?? '') ?><?= $isClient ? ' · 顾客' : ($isWorker ? ' · 工作人员' : '') ?></span>
                    <a href="/logout.php" class="topbar-link"><span>退出</span></a>
                <?php else: ?>
                    <a href="/login.php?next=<?= rawurlencode($_SERVER['REQUEST_URI'] ?? '/customer/index.php') ?>" class="topbar-link"><span>登录</span></a>
                <?php endif; ?>
            </div>
        </header>
        <div class="content">
