<?php

declare(strict_types=1);

/**
 * 执行 RBAC 等增量迁移（可重复尝试；已存在的表/列会跳过报错）
 * 用法：php scripts/migrate_rbac.php
 */

require_once __DIR__ . '/../includes/Database.php';

$pdo = Database::getConnection();
$sqlFile = __DIR__ . '/../database/migrate_rbac_v2.sql';
$raw = file_get_contents($sqlFile);
if ($raw === false) {
    fwrite(STDERR, "找不到 migrate_rbac_v2.sql\n");
    exit(1);
}

// 去掉 USE 语句，走当前库
$raw = preg_replace('/^\s*USE\s+\w+\s*;/mi', '', $raw);

$statements = array_filter(array_map('trim', explode(';', $raw)));
$ok = 0;
$skip = 0;
$fail = 0;

foreach ($statements as $sql) {
    if ($sql === '' || str_starts_with($sql, '--')) {
        continue;
    }
    // 跳过纯注释块
    $lines = array_filter(explode("\n", $sql), static fn($l) => !str_starts_with(trim($l), '--'));
    $sql = trim(implode("\n", $lines));
    if ($sql === '') {
        continue;
    }
    try {
        $pdo->exec($sql);
        $ok++;
        echo "OK: " . substr(preg_replace('/\s+/', ' ', $sql), 0, 80) . "...\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate') || str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate column')) {
            $skip++;
            echo "SKIP: " . substr($msg, 0, 100) . "\n";
            continue;
        }
        $fail++;
        echo "FAIL: " . $msg . "\n  SQL: " . substr(preg_replace('/\s+/', ' ', $sql), 0, 120) . "\n";
    }
}

echo "\n完成：成功 {$ok}，跳过 {$skip}，失败 {$fail}\n";
echo $fail > 0 ? "请人工检查失败项。\n" : "可以重新登录验证多角色。\n";
exit($fail > 0 ? 1 : 0);
