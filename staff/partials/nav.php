<?php
require_once __DIR__ . '/../../includes/icons.php';
$currentPage = $currentPage ?? '';
?>
<nav class="bottom-nav" aria-label="主导航">
    <a href="/staff/index.php" class="nav-link <?= $currentPage === 'orders' ? 'active' : '' ?>">
        <?= svgIcon('orders') ?>
        <span>我的订单</span>
    </a>
    <a href="/staff/report.php" class="nav-link <?= $currentPage === 'report' ? 'active' : '' ?>">
        <?= svgIcon('report') ?>
        <span>我要报单</span>
    </a>
    <a href="/staff/withdrawals.php" class="nav-link <?= $currentPage === 'withdrawals' ? 'active' : '' ?>">
        <?= svgIcon('withdrawals') ?>
        <span>提现记录</span>
    </a>
    <a href="/staff/password.php" class="nav-link <?= $currentPage === 'account' ? 'active' : '' ?>">
        <?= svgIcon('password') ?>
        <span>修改密码</span>
    </a>
</nav>
