<?php
$appTab = $appTab ?? '';
$hideTabbar = !empty($hideTabbar);
$csLink = $csLink ?? (trim(SettingsService::get('customer_service_link', '')) ?: '#');
$showFab = empty($hideFab) && !$hideTabbar;
?>
</div><!-- .app-page -->

<?php if ($showFab): ?>
<a class="app-fab-cs" href="<?= e($csLink) ?>" <?= str_starts_with($csLink, 'http') ? 'target="_blank" rel="noopener"' : '' ?>>
    <div class="app-fab-cs-avatar">🎧</div>
    联系客服
</a>
<?php endif; ?>

<?php if (!$hideTabbar): ?>
<nav class="app-tabbar" aria-label="底部导航">
    <div class="app-tabbar-inner">
        <a href="/customer/index.php" class="<?= $appTab === 'home' ? 'active' : '' ?>">
            <span class="app-tabbar-ico">🏠</span>
            <span>约单</span>
        </a>
        <a href="/customer/feed.php" class="<?= $appTab === 'feed' ? 'active' : '' ?>">
            <span class="app-tabbar-ico">🪐</span>
            <span>动态</span>
        </a>
        <a href="/customer/messages.php" class="<?= $appTab === 'msg' ? 'active' : '' ?>">
            <span class="app-tabbar-ico">💬</span>
            <span>消息</span>
        </a>
        <a href="/customer/me.php" class="<?= $appTab === 'me' ? 'active' : '' ?>">
            <span class="app-tabbar-ico">😊</span>
            <span>我的</span>
        </a>
    </div>
</nav>
<?php endif; ?>
</body>
</html>
