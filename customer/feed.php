<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/FeedService.php';

$feeds = FeedService::listPublished($pdo);

$pageTitle = '动态';
$appTab = 'feed';
require __DIR__ . '/partials/app_head.php';
?>

<header class="app-topbar">
    <div class="app-topbar-title">动态</div>
</header>

<div class="app-feed-list">
    <?php if ($feeds === []): ?>
        <div class="app-empty">暂无动态</div>
    <?php endif; ?>
    <?php foreach ($feeds as $f): ?>
        <?php $cover = trim((string) ($f['cover_url'] ?? '')); ?>
        <article class="app-feed-card">
            <h3><?= e((string) $f['title']) ?></h3>
            <time><?= e((string) ($f['published_at'] ?? $f['created_at'] ?? '')) ?></time>
            <div class="cover<?= $cover !== '' ? ' has-img' : '' ?>"
                 <?php if ($cover !== ''): ?>style="background-image:url('<?= e($cover) ?>')"<?php endif; ?>></div>
            <?php if (!empty($f['content'])): ?>
                <div class="body"><?= nl2br(e((string) $f['content'])) ?></div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/partials/app_foot.php'; ?>
