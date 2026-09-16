<?php
require_once __DIR__ . '/../../includes/brand.php';
$pageTitle = $pageTitle ?? brandName();
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="<?= brandThemeColor() ?>">
    <link rel="icon" href="<?= brandLogo() ?>" type="image/png">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/staff/assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
