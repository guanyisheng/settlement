<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/brand.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/helpers.php';

Auth::startSession();
if (Auth::isWorker()) {
    Auth::setPortal('front');
}

$demoMode = false;
$pdo = null;
try {
    $pdo = Database::getConnection();
} catch (Throwable $e) {
    $demoMode = true;
    // 本地无库时走演示数据，方便看 UI
    if (!class_exists('SettingsService', false)) {
        require_once __DIR__ . '/../../includes/SettingsService.php';
    }
}

$loggedIn = Auth::check();
$isClient = Auth::isClient();
$isWorker = Auth::isWorker();
$uid = $loggedIn ? (int) Auth::id() : 0;
$user = Auth::user();
$error = flash('error');
$success = flash('success');
$currentPage = $currentPage ?? 'home';

if ($demoMode && $error === null && empty($_GET['nodemo'])) {
    // 不打断浏览；演示提示可选
}
