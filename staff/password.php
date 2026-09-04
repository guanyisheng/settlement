<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireStaff();

$pdo = Database::getConnection();
$user = Auth::user();
$error = '';
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        UserService::changeOwnPassword(
            $pdo,
            Auth::id(),
            $_POST['old_password'] ?? '',
            $_POST['new_password'] ?? '',
            $_POST['confirm_password'] ?? ''
        );
        flash('success', '密码修改成功');
        redirect('/staff/password.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$currentPage = 'account';
$pageTitle = brandTitle('修改密码');
$bodyClass = 'has-nav';

require __DIR__ . '/partials/head.php';
?>
<div class="app-shell">
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="top-bar-info">
                <h1>修改密码</h1>
                <p class="subtitle"><?= e($user['nickname']) ?> · <?= e($user['username']) ?></p>
            </div>
            <?php require __DIR__ . '/partials/user-chip.php'; ?>
        </div>
    </header>

    <main class="page-content">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <div class="login-card" style="margin:0">
            <form method="post">
                <div class="form-group">
                    <label>当前密码 <span class="required-mark">*</span></label>
                    <input type="password" name="old_password" class="form-control" required
                           autocomplete="current-password" placeholder="请输入当前密码">
                </div>
                <div class="form-group">
                    <label>新密码 <span class="required-mark">*</span></label>
                    <input type="password" name="new_password" class="form-control" required
                           minlength="6" autocomplete="new-password" placeholder="至少6位">
                </div>
                <div class="form-group">
                    <label>确认新密码 <span class="required-mark">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required
                           minlength="6" autocomplete="new-password" placeholder="再次输入新密码">
                </div>
                <button type="submit" class="btn btn-primary">保存密码</button>
            </form>
        </div>
    </main>

    <?php require __DIR__ . '/partials/nav.php'; ?>
</div>
<?php require __DIR__ . '/partials/foot.php'; ?>
