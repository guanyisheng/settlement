<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/brand.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/helpers.php';

Auth::startSession();

if (Auth::check()) {
    Auth::redirectHome();
}

$error = '';
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = Auth::attemptLogin($username, $password);
    if ($user['user']) {
        Auth::login($user['user']);
        Auth::redirectHome();
    }
    $error = $user['error'] ?? '用户名或密码错误';
}

$pageTitle = brandTitle('登录');
$bodyClass = 'login-body';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="<?= brandThemeColor() ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= e(brandName()) ?>">
    <link rel="icon" href="<?= brandLogo() ?>" type="image/png">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/staff/assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<div class="app-shell">
    <div class="login-page">
        <div class="login-brand">
            <img src="<?= brandLogo() ?>" alt="<?= e(brandName()) ?>" class="brand-logo">
            <p class="login-tagline">统一登录 · 自动识别身份</p>
        </div>
        <div class="login-card">
            <?php if ($success): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           autocomplete="username" placeholder="请输入用户名"
                           value="<?= e($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" class="form-control" required
                           autocomplete="current-password" placeholder="请输入密码">
                </div>
                <button type="submit" class="btn btn-primary">登录</button>
            </form>
            <div class="auth-footer">
                还没有账号？<a href="/register.php">注册成为打手</a>
            </div>
            <p class="login-role-hint">客服、老板请使用管理员分配的账号登录</p>
        </div>
    </div>
</div>
</body>
</html>
