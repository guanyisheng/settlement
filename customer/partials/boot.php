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

$pdo = Database::getConnection();
$loggedIn = Auth::check();
$isClient = Auth::isClient();
$isWorker = Auth::isWorker();
$uid = $loggedIn ? (int) Auth::id() : 0;
$user = Auth::user();
$error = flash('error');
$success = flash('success');
$currentPage = $currentPage ?? 'home';
