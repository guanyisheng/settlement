<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireReport();

$q = trim((string) ($_GET['q'] ?? ''));
$selfId = (int) Auth::id();
$pdo = Database::getConnection();

$list = [];
if (mb_strlen($q) >= 1) {
    foreach (UserService::searchCoStaffCandidates($pdo, $q, $selfId, 20) as $row) {
        $list[] = [
            'id'       => (int) $row['id'],
            'username' => (string) $row['username'],
            'nickname' => (string) $row['nickname'],
            'label'    => trim(($row['nickname'] ?: $row['username']) . ' (@' . $row['username'] . ')'),
        ];
    }
}

jsonResponse(['ok' => true, 'items' => $list]);
