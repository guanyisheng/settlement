<?php
require_once __DIR__ . '/../../includes/icons.php';
$currentPage = $currentPage ?? '';
?>
<nav class="bottom-nav" aria-label="主导航">
    <a href="/staff/index.php" class="nav-link <?= $currentPage === 'orders' ? 'active' : '' ?>">
        <?= svgIcon('orders') ?>
        <span>订单</span>
    </a>
    <a href="/staff/report.php" class="nav-link <?= $currentPage === 'report' ? 'active' : '' ?>">
        <?= svgIcon('report') ?>
        <span>报单</span>
    </a>
    <a href="/staff/withdrawals.php" class="nav-link <?= $currentPage === 'withdrawals' ? 'active' : '' ?>">
        <?= svgIcon('withdrawals') ?>
        <span>提现</span>
    </a>
    <a href="/staff/profile.php" class="nav-link <?= $currentPage === 'profile' || $currentPage === 'account' ? 'active' : '' ?>">
        <?= svgIcon('staff') ?>
        <span>我的</span>
    </a>
</nav>
