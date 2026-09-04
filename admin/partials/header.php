<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/brand.php';
require_once __DIR__ . '/../../includes/icons.php';
$currentPage = $currentPage ?? '';
$pageTitle = $pageTitle ?? '管理后台';
$user = Auth::user();
$isBoss = Auth::isBoss();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= brandThemeColor() ?>">
    <link rel="icon" href="<?= brandLogo() ?>" type="image/png">
    <title><?= e(brandTitle($pageTitle)) ?></title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="<?= brandLogo() ?>" alt="<?= e(brandName()) ?>" class="sidebar-brand-logo">
        </div>
        <nav class="sidebar-nav">
            <?php if (Auth::canAccessPage('dashboard')): ?>
            <a href="/admin/index.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <?= svgIcon('dashboard') ?><span>工作台</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('orders')): ?>
            <a href="/admin/orders.php" class="nav-item <?= $currentPage === 'orders' ? 'active' : '' ?>">
                <?= svgIcon('orders') ?><span>订单管理</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('withdrawals')): ?>
            <a href="/admin/withdrawals.php" class="nav-item <?= $currentPage === 'withdrawals' ? 'active' : '' ?>">
                <?= svgIcon('withdrawals') ?><span>提现管理</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('password')): ?>
            <a href="/admin/password.php" class="nav-item <?= $currentPage === 'password' ? 'active' : '' ?>">
                <?= svgIcon('password') ?><span>修改密码</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('statistics')): ?>
            <a href="/admin/statistics.php" class="nav-item <?= $currentPage === 'statistics' ? 'active' : '' ?>">
                <?= svgIcon('statistics') ?><span>数据统计</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('registrations')): ?>
            <a href="/admin/registrations.php" class="nav-item <?= $currentPage === 'registrations' ? 'active' : '' ?>">
                <?= svgIcon('registrations') ?><span>注册审核</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('staff')): ?>
            <a href="/admin/staff.php" class="nav-item <?= $currentPage === 'staff' ? 'active' : '' ?>">
                <?= svgIcon('staff') ?><span>打手管理</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('settings')): ?>
            <a href="/admin/settings.php" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <?= svgIcon('settings') ?><span>系统设置</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('employees')): ?>
            <a href="/admin/employees.php" class="nav-item <?= $currentPage === 'employees' ? 'active' : '' ?>">
                <?= svgIcon('employees') ?><span>员工管理</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('customers')): ?>
            <a href="/admin/customers.php" class="nav-item <?= $currentPage === 'customers' ? 'active' : '' ?>">
                <?= svgIcon('customers') ?><span>客户管理</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('business_types')): ?>
            <a href="/admin/business_types.php" class="nav-item <?= $currentPage === 'business_types' ? 'active' : '' ?>">
                <?= svgIcon('business_types') ?><span>业务类型</span>
            </a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="main">
        <header class="topbar">
            <div class="topbar-title"><?= e($pageTitle) ?></div>
            <div class="topbar-user">
                <span><?= e($user['nickname'] ?? '') ?> (<?= roleLabel($user['role'] ?? '') ?>)</span>
                <a href="/admin/password.php" class="topbar-link">
                    <?= svgIcon('password', 'topbar-icon') ?><span>改密</span>
                </a>
                <a href="/logout.php" class="topbar-link">
                    <?= svgIcon('logout', 'topbar-icon') ?><span>退出</span>
                </a>
            </div>
        </header>
        <div class="content">
