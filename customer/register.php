<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::startSession();
if (Auth::check()) {
    Auth::redirectHome();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = Database::getConnection();
        UserService::registerClient($pdo, $_POST);
        flash('success', '注册成功，请登录');
        redirect('/login.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = brandTitle('顾客注册');
$bodyClass = 'login-body';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="<?= brandThemeColor() ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/staff/assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<div class="app-shell">
    <div class="login-page">
        <div class="login-card">
            <h2 style="margin:0 0 16px">顾客注册</h2>
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" class="form-control" required value="<?= e($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>昵称</label>
                    <input type="text" name="nickname" class="form-control" value="<?= e($_POST['nickname'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>密码（至少6位）</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary">注册</button>
            </form>
            <div class="auth-footer"><a href="/login.php">已有账号去登录</a></div>
        </div>
    </div>
</div>
</body>
</html>
