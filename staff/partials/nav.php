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
    <a href="/staff/photos.php" class="nav-link <?= $currentPage === 'photos' ? 'active' : '' ?>">
        <?= svgIcon('staff') ?>
        <span>毛照</span>
    </a>
    <a href="/staff/honors.php" class="nav-link <?= $currentPage === 'honors' ? 'active' : '' ?>">
        <?= svgIcon('registrations') ?>
        <span>荣誉</span>
    </a>
    <a href="/staff/withdrawals.php" class="nav-link <?= $currentPage === 'withdrawals' ? 'active' : '' ?>">
        <?= svgIcon('withdrawals') ?>
        <span>提现</span>
    </a>
</nav>
