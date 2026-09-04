<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/brand.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/UserService.php';
require_once __DIR__ . '/includes/helpers.php';

Auth::startSession();

if (Auth::check()) {
    Auth::redirectHome();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = Database::getConnection();
        UserService::registerStaff($pdo, $_POST, $_FILES['photo'] ?? null);
        flash('success', '注册申请已提交，请等待老板审核通过后登录');
        redirect('/login.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = brandTitle('打手注册');
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
            <p class="login-tagline">打手注册</p>
        </div>
        <div class="login-card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           autocomplete="username" placeholder="3-50位字母、数字或下划线"
                           pattern="[a-zA-Z0-9_]{3,50}"
                           value="<?= e($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>昵称</label>
                    <input type="text" name="nickname" class="form-control"
                           placeholder="选填，默认同用户名"
                           value="<?= e($_POST['nickname'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>毛照 <span class="required-mark">*</span></label>
                    <input type="file" name="photo" class="form-control file-input" required
                           accept="image/jpeg,image/png,image/webp">
                    <p class="order-no-hint">支持 JPG/PNG/WEBP，最大 5MB</p>
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" class="form-control" required
                           autocomplete="new-password" placeholder="至少6位" minlength="6">
                </div>
                <div class="form-group">
                    <label>确认密码</label>
                    <input type="password" name="password_confirm" class="form-control" required
                           autocomplete="new-password" placeholder="再次输入密码" minlength="6">
                </div>
                <button type="submit" class="btn btn-primary">提交注册申请</button>
            </form>
            <p class="login-role-hint">提交后需老板审核通过方可登录</p>
            <div class="auth-footer">
                已有账号？<a href="/login.php">去登录</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
