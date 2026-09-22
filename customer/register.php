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
require __DIR__ . '/partials/head.php';
?>
<div class="layout" style="display:block">
    <div class="main" style="margin-left:0;max-width:480px;margin:40px auto;padding:0 16px">
        <div class="card">
            <div class="card-header"><h2>顾客注册</h2></div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
                <form method="post">
                    <div class="form-group" style="margin-bottom:12px">
                        <label>用户名</label>
                        <input type="text" name="username" class="form-control" required value="<?= e($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:12px">
                        <label>昵称</label>
                        <input type="text" name="nickname" class="form-control" value="<?= e($_POST['nickname'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:12px">
                        <label>密码（至少6位）</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary">注册</button>
                    <a href="/login.php" class="btn" style="margin-left:8px">去登录</a>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
