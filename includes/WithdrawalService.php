<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/BalanceService.php';
require_once __DIR__ . '/helpers.php';

class WithdrawalService
{
    /** 当天是否已申请过提现（任意状态都算一次） */
    public static function hasAppliedToday(PDO $pdo, int $staffId): bool
    {
        $stmt = $pdo->prepare(
            'SELECT id FROM withdrawals
             WHERE staff_id = ? AND DATE(created_at) = CURDATE()
             LIMIT 1'
        );
        $stmt->execute([$staffId]);
        return (bool) $stmt->fetch();
    }

    public static function create(PDO $pdo, int $staffId, float $amount): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('提现金额必须大于0');
        }

        $pdo->beginTransaction();
        try {
            // 锁定当日记录，防止并发连点
            $stmt = $pdo->prepare(
                'SELECT id FROM withdrawals
                 WHERE staff_id = ? AND DATE(created_at) = CURDATE()
                 FOR UPDATE'
            );
            $stmt->execute([$staffId]);
            if ($stmt->fetch()) {
                throw new InvalidArgumentException('每天只能申请提现一次，请明天再试');
            }

            $available = BalanceService::getAvailableBalanceForUpdate($pdo, $staffId);

            if ($amount > $available) {
                throw new InvalidArgumentException('提现金额不能超过可提现余额');
            }

            $withdrawalNo = generateNo('WD');
            $stmt = $pdo->prepare(
                'INSERT INTO withdrawals (withdrawal_no, staff_id, amount, status) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$withdrawalNo, $staffId, $amount, 'PENDING']);

            $pdo->commit();
            return ['id' => (int) $pdo->lastInsertId(), 'withdrawal_no' => $withdrawalNo];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function approve(PDO $pdo, int $withdrawalId, int $processorId): void
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM withdrawals WHERE id = ? FOR UPDATE');
            $stmt->execute([$withdrawalId]);
            $withdrawal = $stmt->fetch();

            if (!$withdrawal) {
                throw new RuntimeException('提现申请不存在');
            }
            if ($withdrawal['status'] !== 'PENDING') {
                throw new RuntimeException('该提现申请已处理，请勿重复操作');
            }

            $staffId = (int) $withdrawal['staff_id'];
            $amount = (float) $withdrawal['amount'];

            // 再次锁定并验证余额
            $available = BalanceService::getAvailableBalanceForUpdate($pdo, $staffId);
            if ($amount > $available) {
                throw new RuntimeException('打手可提现余额不足，无法放款');
            }

            $stmt = $pdo->prepare(
                "UPDATE withdrawals SET status = 'PAID', processed_by = ?, processed_at = NOW(), reject_reason = NULL WHERE id = ? AND status = 'PENDING'"
            );
            $stmt->execute([$processorId, $withdrawalId]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('放款失败，请刷新后重试');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function reject(PDO $pdo, int $withdrawalId, int $processorId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('请填写拒绝原因');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM withdrawals WHERE id = ? FOR UPDATE');
            $stmt->execute([$withdrawalId]);
            $withdrawal = $stmt->fetch();

            if (!$withdrawal) {
                throw new RuntimeException('提现申请不存在');
            }
            if ($withdrawal['status'] !== 'PENDING') {
                throw new RuntimeException('该提现申请已处理，请勿重复操作');
            }

            $stmt = $pdo->prepare(
                "UPDATE withdrawals SET status = 'REJECTED', processed_by = ?, processed_at = NOW(), reject_reason = ? WHERE id = ? AND status = 'PENDING'"
            );
            $stmt->execute([$processorId, $reason, $withdrawalId]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('拒绝失败，请刷新后重试');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getByStaff(PDO $pdo, int $staffId): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM withdrawals WHERE staff_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$staffId]);
        return $stmt->fetchAll();
    }

    public static function search(PDO $pdo, array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['staff_id'])) {
            $where[] = 'w.staff_id = ?';
            $params[] = (int) $filters['staff_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'w.status = ?';
            $params[] = $filters['status'];
        }

        $sql = "SELECT w.*, u.nickname AS staff_name, u.username AS staff_username,
                       p.nickname AS processor_name
                FROM withdrawals w
                JOIN users u ON u.id = w.staff_id
                LEFT JOIN users p ON p.id = w.processed_by
                WHERE " . implode(' AND ', $where) . "
                ORDER BY w.created_at DESC
                LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getById(PDO $pdo, int $id): ?array
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT w.*, u.nickname AS staff_name, u.username AS staff_username,
                        u.pay_qr_key AS staff_pay_qr_key,
                        p.nickname AS processor_name
                 FROM withdrawals w
                 JOIN users u ON u.id = w.staff_id
                 LEFT JOIN users p ON p.id = w.processed_by
                 WHERE w.id = ?"
            );
            $stmt->execute([$id]);
        } catch (PDOException) {
            $stmt = $pdo->prepare(
                "SELECT w.*, u.nickname AS staff_name, u.username AS staff_username,
                        p.nickname AS processor_name
                 FROM withdrawals w
                 JOIN users u ON u.id = w.staff_id
                 LEFT JOIN users p ON p.id = w.processed_by
                 WHERE w.id = ?"
            );
            $stmt->execute([$id]);
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
