<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SettlementService.php';

class BalanceService
{
    private static function staffAmountExpr(string $alias = ''): string
    {
        $col = $alias !== '' ? "{$alias}." : '';
        // 优先用落库的 staff_amount；勿用当前全局倍率重算历史单
        return "COALESCE({$col}staff_amount, {$col}amount)";
    }

    /**
     * 累计收入 = 已通过 + 已结算订单中，当前打手应得份额
     * 双人单：总 staff_amount 平分（主打手取一半，附加打手取剩余）
     */
    public static function getTotalIncome(PDO $pdo, int $staffId): float
    {
        require_once __DIR__ . '/OrderService.php';
        $expr = self::staffAmountExpr('o');
        $hasCo = OrderService::hasCoStaffColumn($pdo);

        if ($hasCo) {
            // 主打手应得 ROUND(total/2,2)；附加打手应得 total - 该值
            $shareExpr = "CASE
                WHEN o.staff_id = ? AND (o.co_staff_id IS NULL OR o.co_staff_id = 0) THEN {$expr}
                WHEN o.staff_id = ? AND o.co_staff_id IS NOT NULL AND o.co_staff_id > 0 THEN ROUND({$expr} / 2, 2)
                WHEN o.co_staff_id = ? THEN ({$expr} - ROUND({$expr} / 2, 2))
                ELSE 0
            END";
            $where = '(o.staff_id = ? OR o.co_staff_id = ?)';
            $params = [$staffId, $staffId, $staffId, $staffId, $staffId];
        } else {
            $shareExpr = $expr;
            $where = 'o.staff_id = ?';
            $params = [$staffId];
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM({$shareExpr}), 0) AS total
                 FROM orders o
                 WHERE {$where} AND o.status IN ('APPROVED', 'SETTLED') AND o.deleted_at IS NULL"
            );
            $stmt->execute($params);
        } catch (PDOException) {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM({$shareExpr}), 0) AS total
                 FROM orders o
                 WHERE {$where} AND o.status IN ('APPROVED', 'SETTLED')"
            );
            $stmt->execute($params);
        }
        return (float) $stmt->fetchColumn();
    }

    /**
     * 已放款提现总额
     */
    public static function getPaidWithdrawals(PDO $pdo, int $staffId): float
    {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM withdrawals
             WHERE staff_id = ? AND status = 'PAID'"
        );
        $stmt->execute([$staffId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * 待处理提现总额（占用额度）
     */
    public static function getPendingWithdrawals(PDO $pdo, int $staffId): float
    {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM withdrawals
             WHERE staff_id = ? AND status = 'PENDING'"
        );
        $stmt->execute([$staffId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * 可提现余额 = 累计收入 - 已放款 - 待处理
     */
    public static function getAvailableBalance(PDO $pdo, int $staffId): float
    {
        $income = self::getTotalIncome($pdo, $staffId);
        $paid = self::getPaidWithdrawals($pdo, $staffId);
        $pending = self::getPendingWithdrawals($pdo, $staffId);
        return round($income - $paid - $pending, 2);
    }

    /**
     * 当前余额（与可提现余额相同，用于展示）
     */
    public static function getCurrentBalance(PDO $pdo, int $staffId): float
    {
        return self::getAvailableBalance($pdo, $staffId);
    }

    public static function getBalanceSummary(PDO $pdo, int $staffId): array
    {
        $totalIncome = self::getTotalIncome($pdo, $staffId);
        $paidWithdrawals = self::getPaidWithdrawals($pdo, $staffId);
        $pendingWithdrawals = self::getPendingWithdrawals($pdo, $staffId);
        $available = round($totalIncome - $paidWithdrawals - $pendingWithdrawals, 2);

        return [
            'total_income'        => $totalIncome,
            'paid_withdrawals'    => $paidWithdrawals,
            'pending_withdrawals' => $pendingWithdrawals,
            'available_balance'   => $available,
            'current_balance'     => $available,
        ];
    }

    /**
     * 在事务内锁定并计算可提现余额（FOR UPDATE）
     */
    public static function getAvailableBalanceForUpdate(PDO $pdo, int $staffId): float
    {
        require_once __DIR__ . '/OrderService.php';
        if (OrderService::hasCoStaffColumn($pdo)) {
            $pdo->prepare(
                'SELECT id FROM orders WHERE staff_id = ? OR co_staff_id = ? FOR UPDATE'
            )->execute([$staffId, $staffId]);
        } else {
            $pdo->prepare(
                'SELECT id FROM orders WHERE staff_id = ? FOR UPDATE'
            )->execute([$staffId]);
        }

        $pdo->prepare(
            'SELECT id FROM withdrawals WHERE staff_id = ? FOR UPDATE'
        )->execute([$staffId]);

        return self::getAvailableBalance($pdo, $staffId);
    }
}
