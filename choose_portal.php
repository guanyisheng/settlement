<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/brand.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/helpers.php';

Auth::startSession();

if (!Auth::check()) {
    redirect('/customer/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $portal = (string) ($_POST['portal'] ?? '');
    if ($portal === 'front') {
        Auth::setPortal('front');
        redirect('/customer/index.php');
    }
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
            <p class="login-role-hint">当前身份：<?= e(Auth::roleDisplay()) ?> · 请选择入口</p>
        </div>
        <div class="login-card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" style="display:flex;flex-direction:column;gap:10px">
                <button type="submit" name="portal" value="front" class="btn btn-primary">前台（顾客浏览/下单页）</button>
                <?php if (Auth::canAccessStaffPortal()): ?>
                <button type="submit" name="portal" value="staff" class="btn">打手工作台</button>
                <?php endif; ?>
                <?php if (Auth::canAccessAdmin()): ?>
                <button type="submit" name="portal" value="admin" class="btn">管理后台</button>
                <?php endif; ?>
            </form>
            <div class="auth-footer"><a href="/logout.php">退出登录</a></div>
        </div>
    </div>
</div>
</body>
</html>
