<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('password');

$pdo = Database::getConnection();
$user = Auth::user();
$error = flash('error');
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
        redirect('/admin/password.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/password.php');
    }
}

$currentPage = 'password';
$pageTitle = '修改密码';
require __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card" style="max-width:480px">
    <div class="card-header"><h2>修改密码</h2></div>
    <div class="card-body">
        <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px">
            当前账号：<?= e($user['nickname']) ?> (<?= e(Auth::roleDisplay()) ?>)
        </p>
        <form method="post">
            <div class="form-group" style="margin-bottom:16px">
                <label>当前密码</label>
                <input type="password" name="old_password" class="form-control" required autocomplete="current-password">
            </div>
            <div class="form-group" style="margin-bottom:16px">
                <label>新密码</label>
                <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
            </div>
            <div class="form-group" style="margin-bottom:20px">
                <label>确认新密码</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="6" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">保存密码</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
