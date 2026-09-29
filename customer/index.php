<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/BusinessTypeService.php';
require_once __DIR__ . '/../includes/ExtraFeeService.php';
require_once __DIR__ . '/../includes/BoardService.php';
require_once __DIR__ . '/../includes/SettingsService.php';

if (!empty($demoMode)) {
    require_once __DIR__ . '/partials/demo_data.php';
    $ready = true;
    $types = customerDemoProducts();
    $boards = ['三角洲行动', '暗区突围', '无畏契约'];
    $bannerTitle = '所有订单都附赠活动福利';
    $bannerDesc = '【演示模式】本地未连库，展示 UI';
} else {
    $ready = ClientOrderService::isReady($pdo);
    $types = BusinessTypeService::getAll($pdo, true);
    $boards = BusinessTypeService::boards($pdo);
    $bannerTitle = SettingsService::get('customer_banner_title', '所有订单都附赠活动福利');
    $bannerDesc = SettingsService::get('customer_banner_desc', '登录下单 · 积分抽奖等你来');
}

$boardFilter = trim((string) ($_GET['board'] ?? ''));
$tab = trim((string) ($_GET['tab'] ?? '全部'));
$q = trim((string) ($_GET['q'] ?? ''));

// 从业务里收集出现过的板块，保证分类圆点有货
$boardCounts = [];
foreach ($types as $t) {
    $b = trim((string) ($t['board'] ?? ''));
    if ($b === '') {
        $b = '其他';
    }
    $boardCounts[$b] = ($boardCounts[$b] ?? 0) + 1;
}
foreach ($boards as $b) {
    if (!isset($boardCounts[$b])) {
        $boardCounts[$b] = 0;
    }
}

$tabs = ['全部'];
foreach (array_keys($boardCounts) as $b) {
    if ($b !== '其他') {
        $tabs[] = $b;
    }
}
if (isset($boardCounts['其他'])) {
    $tabs[] = '其他';
}

$filtered = array_values(array_filter($types, static function (array $t) use ($boardFilter, $tab, $q): bool {
    $b = trim((string) ($t['board'] ?? ''));
    if ($b === '') {
        $b = '其他';
    }
    if ($boardFilter !== '' && $b !== $boardFilter) {
        return false;
    }
    if ($tab !== '' && $tab !== '全部' && $b !== $tab) {
        return false;
    }
    if ($q !== '') {
        $hay = mb_strtolower(
            ($t['name'] ?? '') . ' ' . ($t['remark'] ?? '') . ' ' . $b . ' ' . ($t['badge_text'] ?? '')
        );
        if (!str_contains($hay, mb_strtolower($q))) {
            return false;
        }
    }
    return true;
}));

$pageTitle = brandName() . ' · 约单';
$appTab = 'home';
require __DIR__ . '/partials/app_head.php';
?>

<header class="app-topbar">
    <div class="app-topbar-title"><?= e(brandName()) ?></div>
    <div style="font-size:12px;color:var(--app-muted)">
        <?php if ($loggedIn): ?>
            <?= e($user['nickname'] ?? $user['username'] ?? '') ?>
        <?php else: ?>
            <a href="/login.php?next=<?= rawurlencode('/customer/index.php') ?>" style="color:var(--app-purple)">登录</a>
        <?php endif; ?>
    </div>
</header>

<?php if ($error): ?><div class="app-alert app-alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="app-alert app-alert-success"><?= e($success) ?></div><?php endif; ?>

<form class="app-search" method="get" action="/customer/index.php" role="search">
    <?php if ($boardFilter !== ''): ?>
        <input type="hidden" name="board" value="<?= e($boardFilter) ?>">
    <?php endif; ?>
    <?php if ($tab !== '' && $tab !== '全部'): ?>
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="搜索陪玩 / 业务名称" enterkeyhint="search" autocomplete="off">
    <?php if ($q !== ''): ?>
        <a class="app-search-clear" href="/customer/index.php<?= $boardFilter !== '' || ($tab !== '' && $tab !== '全部') ? '?' . http_build_query(array_filter(['board' => $boardFilter ?: null, 'tab' => ($tab !== '全部' ? $tab : null)])) : '' ?>" aria-label="清除">×</a>
    <?php endif; ?>
    <button type="submit">搜索</button>
</form>

<section class="app-banner">
    <h1><?= e($bannerTitle) ?></h1>
    <p><?= e($bannerDesc) ?></p>
    <a class="app-banner-cta" href="<?= e($csLink) ?>">考核入驻<br>联系客服</a>
</section>

<nav class="app-cats" aria-label="游戏分类">
    <a class="app-cat <?= $boardFilter === '' && $tab === '全部' ? 'active' : '' ?>" href="/customer/index.php">
        <div class="app-cat-icon"><span>全部</span></div>
        <div class="app-cat-name">全部</div>
    </a>
    <?php foreach ($boardCounts as $name => $cnt): ?>
        <a class="app-cat <?= ($boardFilter === $name || $tab === $name) ? 'active' : '' ?>"
           href="/customer/index.php?board=<?= rawurlencode($name) ?>&tab=<?= rawurlencode($name) ?>">
            <div class="app-cat-icon"><span><?= e(mb_substr($name, 0, 4)) ?></span></div>
            <div class="app-cat-name"><?= e($name) ?></div>
        </a>
    <?php endforeach; ?>
</nav>

<div class="app-tabs-wrap">
    <div class="app-tabs">
        <?php foreach ($tabs as $tName): ?>
            <a class="app-tab <?= $tab === $tName ? 'active' : '' ?>"
               href="/customer/index.php?tab=<?= rawurlencode($tName) ?><?= $boardFilter !== '' ? '&board=' . rawurlencode($boardFilter) : '' ?>">
                <?= e($tName) ?>
            </a>
        <?php endforeach; ?>
        <a class="app-tab <?= $tab === '活动' ? 'active' : '' ?>" href="/customer/activity.php">活动抽奖</a>
    </div>
    <span class="app-tabs-more" title="分类">☰</span>
</div>

<?php if (!$ready): ?>
    <div class="app-alert app-alert-error">请先执行 database/一键注入_全部更新.sql</div>
<?php elseif ($filtered === []): ?>
    <div class="app-empty"><?= $q !== '' ? '没有找到「' . e($q) . '」相关陪单' : '暂无上架陪单，请稍后再来' ?></div>
<?php else: ?>
    <div class="app-grid">
        <?php foreach ($filtered as $t): ?>
            <?php
            $price = (float) $t['unit_price'];
            $orig = isset($t['original_price']) && $t['original_price'] !== null && $t['original_price'] !== ''
                ? (float) $t['original_price']
                : round($price * 1.3, 2);
            $cover = trim((string) ($t['cover_url'] ?? ''));
            $badge = trim((string) ($t['badge_text'] ?? '')) ?: '限时优惠';
            $caption = trim((string) ($t['remark'] ?? ''));
            if ($caption === '') {
                $caption = '每日限时开放';
            }
            ?>
            <a class="app-card" href="/customer/order.php?bt=<?= (int) $t['id'] ?>">
                <div class="app-card-cover<?= $cover !== '' ? ' has-img' : '' ?>"
                     <?php if ($cover !== ''): ?>style="background-image:url('<?= e($cover) ?>')"<?php endif; ?>>
                    <span class="app-card-badge">⚡ <?= e($badge) ?></span>
                    <div class="app-card-cover-caption"><?= e(mb_strimwidth($caption, 0, 36, '…')) ?></div>
                </div>
                <div class="app-card-body">
                    <div class="app-card-title"><?= e($t['name']) ?></div>
                    <div class="app-card-price-row">
                        <span class="app-price"><?= rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.') ?></span>
                        <span class="app-coin">★</span>
                        <span class="app-price-unit">/单</span>
                        <?php if ($orig > $price): ?>
                            <span class="app-price-old"><?= rtrim(rtrim(number_format($orig, 2, '.', ''), '0'), '.') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/app_foot.php'; ?>
