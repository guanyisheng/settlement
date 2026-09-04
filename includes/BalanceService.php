<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SettlementService.php';

class BalanceService
{
    private static function staffAmountExpr(): string
    {
        return 'COALESCE(staff_amount, ROUND(amount * 0.8 * 0.5, 2))';
    }

    /**
     * 累计收入 = 已通过 + 已结算订单的打手结算金额总和
     */
    public static function getTotalIncome(PDO $pdo, int $staffId): float
    {
        $expr = self::staffAmountExpr();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM({$expr}), 0) AS total
             FROM orders
             WHERE staff_id = ? AND status IN ('APPROVED', 'SETTLED')"
        );
        $stmt->execute([$staffId]);
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
        $pdo->prepare(
            "SELECT id FROM orders WHERE staff_id = ? FOR UPDATE"
        )->execute([$staffId]);

        $pdo->prepare(
            "SELECT id FROM withdrawals WHERE staff_id = ? FOR UPDATE"
        )->execute([$staffId]);

        return self::getAvailableBalance($pdo, $staffId);
    }
}
