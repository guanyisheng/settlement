<?php
require_once __DIR__ . '/../../includes/icons.php';
$user = Auth::user();
$initial = mb_substr($user['nickname'] ?: $user['username'], 0, 1);
?>
<div class="user-chip">
    <div class="avatar"><?= e($initial) ?></div>
    <a href="/staff/profile.php" class="logout-btn">
        <?= svgIcon('staff', 'chip-icon') ?><span>我的</span>
    </a>
    <a href="/logout.php" class="logout-btn">
        <?= svgIcon('logout', 'chip-icon') ?><span>退出</span>
    </a>
</div>
