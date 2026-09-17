<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

class FineService
{
    public static function isReady(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM staff_fines LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public static function create(PDO $pdo, int $staffId, float $amount, string $reason, int $by): void
    {
        if (!self::isReady($pdo)) {
            throw new RuntimeException('请先执行 database/migrate_customer_portal.sql');
        }
        $reason = trim($reason);
        if ($staffId <= 0) {
            throw new InvalidArgumentException('请选择打手');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('罚款金额须大于 0');
        }
        if ($reason === '') {
            throw new InvalidArgumentException('请填写原因');
        }
        $pdo->prepare(
            'INSERT INTO staff_fines (staff_id, amount, reason, created_by) VALUES (?, ?, ?, ?)'
        )->execute([$staffId, round($amount, 2), $reason, $by]);
    }

    public static function totalForStaff(PDO $pdo, int $staffId): float
    {
        if (!self::isReady($pdo)) {
            return 0.0;
        }
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM staff_fines WHERE staff_id = ?');
        $stmt->execute([$staffId]);
        return (float) $stmt->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public static function listRecent(PDO $pdo, int $limit = 100): array
    {
        if (!self::isReady($pdo)) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        return $pdo->query(
            "SELECT f.*, u.nickname AS staff_name, p.nickname AS creator_name
             FROM staff_fines f
             JOIN users u ON u.id = f.staff_id
             LEFT JOIN users p ON p.id = f.created_by
             ORDER BY f.id DESC LIMIT {$limit}"
        )->fetchAll();
    }
}
