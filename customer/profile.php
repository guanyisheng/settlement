<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/MembershipService.php';

if (!$loggedIn) {
    flash('error', '请先登录');
    redirect('/login.php?next=' . rawurlencode('/customer/profile.php'));
}

$row = $pdo->prepare('SELECT username, nickname, growth_points, membership_expire_at, role FROM users WHERE id = ?');
$row->execute([$uid]);
$me = $row->fetch() ?: [];
$points = (int) ($me['growth_points'] ?? 0);
$tier = MembershipService::isReady($pdo) ? MembershipService::tierForPoints($pdo, $points) : ['name' => '普通'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nickname = trim((string) ($_POST['nickname'] ?? ''));
        if ($nickname === '') {
            throw new InvalidArgumentException('昵称不能为空');
        }
        $pdo->prepare('UPDATE users SET nickname = ? WHERE id = ?')->execute([$nickname, $uid]);
        $newPass = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if ($newPass !== '' || $confirm !== '') {
            if (strlen($newPass) < 6) {
                throw new InvalidArgumentException('新密码至少6位');
            }
            if ($newPass !== $confirm) {
                throw new InvalidArgumentException('两次密码不一致');
            }
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($newPass, PASSWORD_DEFAULT), $uid]);
        }
        flash('success', '已保存');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/customer/profile.php');
}

$pageTitle = '编辑资料';
$appTab = 'me';
require __DIR__ . '/partials/app_head.php';
?>

<header class="app-topbar">
    <a class="app-topbar-back" href="/customer/me.php" aria-label="返回">‹</a>
    <div class="app-topbar-center">编辑资料</div>
    <span style="width:36px"></span>
</header>

<?php if ($error): ?><div class="app-alert app-alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="app-alert app-alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="app-form-panel" style="margin-bottom:24px">
    <p style="margin:0 0 12px;font-size:13px;color:var(--app-muted)">
        档次 <?= e($tier['name'] ?? '普通') ?> · 成长值 <?= $points ?>
        <?php if (!empty($me['membership_expire_at'])): ?>
            · 月/年卡到期 <?= e($me['membership_expire_at']) ?>
        <?php endif; ?>
    </p>
    <form method="post">
        <div class="form-group">
            <label>用户名</label>
            <input type="text" class="form-control" value="<?= e($me['username'] ?? '') ?>" disabled>
        </div>
        <div class="form-group">
            <label>昵称</label>
            <input type="text" name="nickname" class="form-control" value="<?= e($me['nickname'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>新密码（不改留空）</label>
            <input type="password" name="new_password" class="form-control" minlength="6" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>确认新密码</label>
            <input type="password" name="confirm_password" class="form-control" minlength="6" autocomplete="new-password">
        </div>
        <button class="app-order-btn" type="submit" style="width:100%;margin-top:8px">保存</button>
    </form>
</div>

<?php require __DIR__ . '/partials/app_foot.php'; ?>
