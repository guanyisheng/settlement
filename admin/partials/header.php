<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/brand.php';
require_once __DIR__ . '/../../includes/icons.php';
$currentPage = $currentPage ?? '';
$pageTitle = $pageTitle ?? '管理后台';
// 会话用户：勿与业务页「被编辑用户」变量同名冲突（旧代码用 $user 存详情会被这里盖掉）
$adminSessionUser = Auth::user();
$user = $adminSessionUser;
$isBoss = Auth::isBoss();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="screen-orientation" content="portrait">
    <meta name="theme-color" content="<?= brandThemeColor() ?>">
    <link rel="icon" href="<?= brandLogo() ?>" type="image/png">
    <title><?= e(brandTitle($pageTitle)) ?></title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
    <style>
      @media screen and (orientation: landscape) and (max-width: 900px) {
        body::after {
          content: '请竖屏使用管理后台';
          position: fixed; inset: 0; z-index: 9999;
          display: flex; align-items: center; justify-content: center;
          background: #0d1117; color: #e6edf3; font-size: 16px; font-weight: 600;
          padding: 24px; text-align: center;
        }
        .layout { filter: blur(2px); pointer-events: none; }
      }
    </style>
</head>
<body class="admin-body">
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
            <?php if (Auth::can('report.create')): ?>
            <a href="/staff/report.php" class="nav-item <?= $currentPage === 'report' ? 'active' : '' ?>">
                <?= svgIcon('report') ?><span>报单</span>
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
            <?php if (Auth::canAccessPage('users') || Auth::canAccessPage('staff') || Auth::canAccessPage('employees')): ?>
            <a href="/admin/users.php" class="nav-item <?= in_array($currentPage, ['users', 'staff', 'employees'], true) ? 'active' : '' ?>">
                <?= svgIcon('staff') ?><span>用户中心</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('roles')): ?>
            <a href="/admin/roles.php" class="nav-item <?= $currentPage === 'roles' ? 'active' : '' ?>">
                <?= svgIcon('employees') ?><span>角色权限</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::canAccessPage('settings')): ?>
            <a href="/admin/settings.php" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <?= svgIcon('settings') ?><span>系统设置</span>
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
            <button type="button" class="mobile-nav-toggle" id="mobileNavToggle" aria-label="打开菜单">☰</button>
            <div class="topbar-title"><?= e($pageTitle) ?></div>
            <div class="topbar-user">
                <span>登录：<?= e($user['nickname'] ?? '') ?> (<?= e(Auth::roleDisplay()) ?>)</span>
                <?php if (method_exists('Auth', 'needsPortalChoice') && Auth::needsPortalChoice()): ?>
                <a href="/choose_portal.php" class="topbar-link"><span>切换入口</span></a>
                <?php endif; ?>
                <a href="/admin/password.php" class="topbar-link">
                    <?= svgIcon('password', 'topbar-icon') ?><span>改密</span>
                </a>
                <a href="/logout.php" class="topbar-link">
                    <?= svgIcon('logout', 'topbar-icon') ?><span>退出</span>
                </a>
            </div>
        </header>
        <div class="content">
