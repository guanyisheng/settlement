#!/usr/bin/env php
<?php
/**
 * 数据库初始化脚本
 * 用法: php install.php
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/brand.php';
require_once __DIR__ . '/includes/SettingsService.php';

$config = require __DIR__ . '/config/database.php';

echo brandName() . " - 数据库初始化\n";
echo "========================\n\n";

try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $config['host'], $config['port'], $config['charset']);
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = file_get_contents(__DIR__ . '/database/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('无法读取 schema.sql');
    }

    // 分割并执行 SQL 语句
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*\n/', $sql)),
        fn($s) => $s !== '' && !str_starts_with($s, '--')
    );

    foreach ($statements as $statement) {
        if (trim($statement) === '') continue;
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            // 忽略重复插入等错误
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                echo "  [跳过] 数据已存在\n";
                continue;
            }
            throw $e;
        }
    }

    $dsnDb = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['dbname'], $config['charset']);
    $pdoDb = new PDO($dsnDb, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    SettingsService::seedDefaults($pdoDb);

    echo "✓ 数据库初始化成功！\n\n";
    echo "默认账号：\n";
    echo "  老板:   boss1 / boss123  (全部权限)\n";
    echo "  管理员: admin / admin123\n";
    echo "  客服:   cs1 / cs123\n";
    echo "  打手:   staff1 / staff123\n\n";
    echo "登录后可在【系统设置】修改品牌、结算系数、COS 等。\n\n";
    echo "启动服务: php -S localhost:8080 router.php\n";
    echo "  登录: http://localhost:8080/login.php\n";
    echo "  (打手/客服/老板统一入口，自动跳转)\n";

} catch (PDOException $e) {
    echo "✗ 数据库错误: " . $e->getMessage() . "\n";
    echo "\n请确保 MySQL 已启动，并检查 config/database.php 中的连接配置。\n";
    exit(1);
}
