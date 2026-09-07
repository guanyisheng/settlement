<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/brand.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/helpers.php';

Auth::startSession();

if (!Auth::check()) {
    redirect(Auth::LOGIN_URL);
}

// 只有一种入口时直接进，不展示选择页
if (!Auth::needsPortalChoice()) {
    Auth::redirectHome();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $portal = (string) ($_POST['portal'] ?? '');
    if ($portal === 'staff' && Auth::canAccessStaffPortal()) {
        Auth::setPortal('staff');
        redirect('/staff/index.php');
    }
    if ($portal === 'admin' && Auth::canAccessAdmin()) {
        Auth::setPortal('admin');
        redirect(Auth::adminHomeUrl());
    }
    $error = '请选择要进入的界面';
}

$user = Auth::user();
$pageTitle = brandTitle('选择入口');
$bodyClass = 'login-body';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="<?= brandThemeColor() ?>">
    <link rel="icon" href="<?= brandLogo() ?>" type="image/png">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/staff/assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<div class="app-shell">
    <div class="login-page">
        <div class="login-brand">
            <img src="<?= brandLogo() ?>" alt="<?= e(brandName()) ?>" class="brand-logo">
            <p class="login-tagline">你好，<?= e($user['nickname'] ?? '') ?></p>
            <p class="login-role-hint">当前身份：<?= e(Auth::roleDisplay()) ?> · 请选择要进入的界面</p>
        </div>
        <div class="login-card portal-card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="portal-form">
                <button type="submit" name="portal" value="staff" class="portal-option">
                    <span class="portal-option-title">打手工作台</span>
                    <span class="portal-option-desc">手机端 · 报单 / 订单 / 提现 / 我的</span>
                </button>
                <button type="submit" name="portal" value="admin" class="portal-option portal-option-admin">
                    <span class="portal-option-title">管理后台</span>
                    <span class="portal-option-desc">电脑端 · 审核 / 提现处理 / 系统管理</span>
                </button>
            </form>

            <div class="auth-footer">
                <a href="/logout.php">退出登录</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
