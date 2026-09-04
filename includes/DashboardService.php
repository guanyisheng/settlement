<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/BalanceService.php';

class DashboardService
{
    public static function getStats(PDO $pdo): array
    {
        $today = date('Y-m-d');

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?');
        $stmt->execute([$today]);
        $todayOrders = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'PENDING'");
        $pendingOrders = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND DATE(reviewed_at) = ?"
        );
        $stmt->execute([$today]);
        $todaySettled = (float) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'PENDING'");
        $pendingWithdrawals = (int) $stmt->fetchColumn();

        $stmt = $pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED')"
        );
        $totalIncome = (float) $stmt->fetchColumn();

        $stmt = $pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE status = 'PENDING'"
        );
        $pendingWithdrawAmount = (float) $stmt->fetchColumn();

        return [
            'today_orders'            => $todayOrders,
            'pending_orders'          => $pendingOrders,
            'today_settled'           => $todaySettled,
            'pending_withdrawals'     => $pendingWithdrawals,
            'total_income'            => $totalIncome,
            'pending_withdraw_amount' => $pendingWithdrawAmount,
        ];
    }

    /** 老板端数据统计 */
    public static function getBossStatistics(PDO $pdo): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $weekStart = date('Y-m-d', strtotime('monday this week'));

        $overview = [
            'staff_total'     => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'STAFF'")->fetchColumn(),
            'staff_active'    => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'STAFF' AND status = 1")->fetchColumn(),
            'customer_total'  => (int) $pdo->query('SELECT COUNT(*) FROM customers WHERE status = 1')->fetchColumn(),
            'order_total'     => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'order_amount'    => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED')")->fetchColumn(),
            'withdraw_paid'   => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE status = 'PAID'")->fetchColumn(),
            'pending_staff'   => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'STAFF' AND status = 2")->fetchColumn(),
        ];

        $period = [
            'today_orders'  => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today'")->fetchColumn(),
            'today_amount'  => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND DATE(reviewed_at) = '$today'")->fetchColumn(),
            'week_orders'   => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) >= '$weekStart'")->fetchColumn(),
            'week_amount'   => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND DATE(reviewed_at) >= '$weekStart'")->fetchColumn(),
            'month_orders'  => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) >= '$monthStart'")->fetchColumn(),
            'month_amount'  => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND DATE(reviewed_at) >= '$monthStart'")->fetchColumn(),
        ];

        $statusRows = $pdo->query(
            "SELECT status, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS amt FROM orders GROUP BY status"
        )->fetchAll();
        $byStatus = [];
        foreach ($statusRows as $row) {
            $byStatus[$row['status']] = [
                'count'  => (int) $row['cnt'],
                'amount' => (float) $row['amt'],
            ];
        }

        $topStaff = $pdo->query(
            "SELECT u.nickname, COUNT(o.id) AS order_count, COALESCE(SUM(o.amount), 0) AS total_amount
             FROM orders o
             JOIN users u ON u.id = o.staff_id
             WHERE o.status IN ('APPROVED','SETTLED')
             GROUP BY o.staff_id, u.nickname
             ORDER BY total_amount DESC
             LIMIT 10"
        )->fetchAll();

        $topCustomers = $pdo->query(
            "SELECT c.name, COUNT(o.id) AS order_count, COALESCE(SUM(o.amount), 0) AS total_amount
             FROM orders o
             JOIN customers c ON c.id = o.customer_id
             WHERE o.status IN ('APPROVED','SETTLED')
             GROUP BY o.customer_id, c.name
             ORDER BY total_amount DESC
             LIMIT 10"
        )->fetchAll();

        $businessTypes = $pdo->query(
            "SELECT b.name, COUNT(o.id) AS order_count, COALESCE(SUM(o.amount), 0) AS total_amount
             FROM orders o
             JOIN business_types b ON b.id = o.business_type_id
             WHERE o.status IN ('APPROVED','SETTLED')
             GROUP BY o.business_type_id, b.name
             ORDER BY total_amount DESC"
        )->fetchAll();

        $dailyTrend = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?');
            $stmt->execute([$date]);
            $count = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND DATE(reviewed_at) = ?"
            );
            $stmt->execute([$date]);
            $amount = (float) $stmt->fetchColumn();

            $dailyTrend[] = [
                'date'   => $date,
                'label'  => date('m/d', strtotime($date)),
                'count'  => $count,
                'amount' => $amount,
            ];
        }

        $maxDailyCount = max(array_column($dailyTrend, 'count') ?: [1]);
        $maxDailyAmount = max(array_column($dailyTrend, 'amount') ?: [1.0]);

        return [
            'overview'       => $overview,
            'period'         => $period,
            'by_status'      => $byStatus,
            'top_staff'      => $topStaff,
            'top_customers'  => $topCustomers,
            'business_types' => $businessTypes,
            'daily_trend'    => $dailyTrend,
            'max_daily_count'  => max(1, $maxDailyCount),
            'max_daily_amount' => max(1.0, $maxDailyAmount),
        ];
    }
}
