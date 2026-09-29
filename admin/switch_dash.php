<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireAdminAccess();

// 新财务看板仅老板；其他人强制回旧版工作台
$v = trim((string) ($_GET['v'] ?? 'v2'));
$v = $v === 'classic' ? 'classic' : 'v2';
if ($v === 'v2' && !Auth::isBoss()) {
    flash('error', '新财务看板仅老板可查看');
    $v = 'classic';
}

setcookie('admin_dash', $v, [
    'expires' => time() + 86400 * 400,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => false,
    'samesite' => 'Lax',
]);

redirect($v === 'v2' ? '/admin/index_v2.php' : '/admin/index.php');
