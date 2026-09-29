<?php
require_once __DIR__ . '/../../includes/brand.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/SettingsService.php';

$pageTitle = $pageTitle ?? brandName();
$appTab = $appTab ?? ''; // home|feed|msg|me|none
$hideTabbar = !empty($hideTabbar);
$bodyClass = trim('app-body ' . ($bodyClass ?? '') . ($hideTabbar ? ' has-sticky-order' : ''));
$ogTitle = $ogTitle ?? $pageTitle;
$ogDesc = $ogDesc ?? (SettingsService::get('activity_share_desc', '') ?: brandName());
$ogImage = $ogImage ?? brandLogo();
$ogUrl = $ogUrl ?? absoluteUrl($_SERVER['REQUEST_URI'] ?? '/customer/index.php');
if ($ogImage !== '' && !preg_match('#^https?://#i', $ogImage)) {
    $ogImage = absoluteUrl($ogImage);
}
$csLink = trim(SettingsService::get('customer_service_link', ''));
if ($csLink === '') {
    $csLink = 'javascript:alert("请联系客服（后台可配置客服链接）")';
}
$assetV = rawurlencode(assetBuildId());
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#7c5cff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="icon" href="<?= brandLogo() ?>" type="image/png">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($ogDesc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDesc) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:url" content="<?= e($ogUrl) ?>">
    <meta itemprop="name" content="<?= e($ogTitle) ?>">
    <meta itemprop="description" content="<?= e($ogDesc) ?>">
    <meta itemprop="image" content="<?= e($ogImage) ?>">
    <link rel="stylesheet" href="/customer/assets/css/app.css?v=<?= $assetV ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<div class="app-page">
