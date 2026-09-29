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

    public static function hasStatusColumn(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT status FROM staff_fines LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public static function create(PDO $pdo, int $staffId, float $amount, string $reason, int $by): void
    {
        if (!self::isReady($pdo)) {
            throw new RuntimeException('请先执行 database/一键注入_全部更新.sql');
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
        if (self::hasStatusColumn($pdo)) {
            $pdo->prepare(
                "INSERT INTO staff_fines (staff_id, amount, reason, created_by, status) VALUES (?, ?, ?, ?, 'ACTIVE')"
            )->execute([$staffId, round($amount, 2), $reason, $by]);
        } else {
            $pdo->prepare(
                'INSERT INTO staff_fines (staff_id, amount, reason, created_by) VALUES (?, ?, ?, ?)'
            )->execute([$staffId, round($amount, 2), $reason, $by]);
        }
    }

    /** 撤销罚款（恢复可提现余额） */
    public static function revoke(PDO $pdo, int $fineId, int $by, string $note = ''): void
    {
        if (!self::isReady($pdo)) {
            throw new RuntimeException('请先执行 database/一键注入_全部更新.sql');
        }
        if (!self::hasStatusColumn($pdo)) {
            throw new RuntimeException('请重新执行 database/一键注入_全部更新.sql 以启用撤销罚款');
        }
        if ($fineId <= 0) {
            throw new InvalidArgumentException('罚款无效');
        }
        $note = trim($note);
        $stmt = $pdo->prepare(
            "UPDATE staff_fines
             SET status = 'REVOKED', revoked_by = ?, revoked_at = NOW(), revoke_note = ?
             WHERE id = ? AND status = 'ACTIVE'"
        );
        $stmt->execute([$by, $note !== '' ? $note : null, $fineId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('无法撤销（不存在或已撤销）');
        }
    }

    public static function totalForStaff(PDO $pdo, int $staffId): float
    {
        if (!self::isReady($pdo)) {
            return 0.0;
        }
        if (self::hasStatusColumn($pdo)) {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount),0) FROM staff_fines WHERE staff_id = ? AND status = 'ACTIVE'"
            );
        } else {
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM staff_fines WHERE staff_id = ?');
        }
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
        if (self::hasStatusColumn($pdo)) {
            return $pdo->query(
                "SELECT f.*, u.nickname AS staff_name, p.nickname AS creator_name, r.nickname AS revoker_name
                 FROM staff_fines f
                 JOIN users u ON u.id = f.staff_id
                 LEFT JOIN users p ON p.id = f.created_by
                 LEFT JOIN users r ON r.id = f.revoked_by
                 ORDER BY f.id DESC LIMIT {$limit}"
            )->fetchAll();
        }
        return $pdo->query(
            "SELECT f.*, u.nickname AS staff_name, p.nickname AS creator_name, NULL AS revoker_name
             FROM staff_fines f
             JOIN users u ON u.id = f.staff_id
             LEFT JOIN users p ON p.id = f.created_by
             ORDER BY f.id DESC LIMIT {$limit}"
        )->fetchAll();
    }
}
