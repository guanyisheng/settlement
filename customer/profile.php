<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/MembershipService.php';

if (!$isClient) {
    flash('error', '请先登录顾客账号');
    redirect('/login.php?next=' . rawurlencode('/customer/profile.php'));
}

$row = $pdo->prepare('SELECT username, nickname, growth_points, membership_expire_at FROM users WHERE id = ?');
$row->execute([$uid]);
$me = $row->fetch() ?: [];
$points = (int) ($me['growth_points'] ?? 0);
$tier = MembershipService::tierForPoints($pdo, $points);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nickname = trim((string) ($_POST['nickname'] ?? ''));
        if ($nickname === '') {
            throw new InvalidArgumentException('昵称不能为空');
        }
        $pdo->prepare('UPDATE users SET nickname = ? WHERE id = ? AND role = ?')->execute([$nickname, $uid, 'CLIENT']);
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

$currentPage = 'profile';
$pageTitle = '个人中心';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2>会员信息</h2></div>
    <div class="card-body">
        <p>档次 <?= e($tier['name'] ?? '普通') ?> · 成长值 <?= $points ?>
            <?php if (!empty($me['membership_expire_at'])): ?>
                · 月/年卡到期 <?= e($me['membership_expire_at']) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>账号资料</h2></div>
    <div class="card-body">
        <form method="post">
            <div class="form-group" style="margin-bottom:12px">
                <label>用户名</label>
                <input type="text" class="form-control" value="<?= e($me['username'] ?? '') ?>" disabled>
            </div>
            <div class="form-group" style="margin-bottom:12px">
                <label>昵称</label>
                <input type="text" name="nickname" class="form-control" value="<?= e($me['nickname'] ?? '') ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:12px">
                <label>新密码（不改留空）</label>
                <input type="password" name="new_password" class="form-control" minlength="6" autocomplete="new-password">
            </div>
            <div class="form-group" style="margin-bottom:12px">
                <label>确认新密码</label>
                <input type="password" name="confirm_password" class="form-control" minlength="6" autocomplete="new-password">
            </div>
            <button class="btn btn-primary" type="submit">保存</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
