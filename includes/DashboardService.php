<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/BalanceService.php';

class DashboardService
{
    /** 排除已删订单（兼容未加 deleted_at 的旧库） */
    private static function alive(string $alias = ''): string
    {
        $col = $alias === '' ? 'deleted_at' : "{$alias}.deleted_at";
        return "({$col} IS NULL)";
    }

    public static function getStats(PDO $pdo): array
    {
        $today = date('Y-m-d');
        $alive = self::alive();

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ? AND {$alive}");
            $stmt->execute([$today]);
            $todayOrders = (int) $stmt->fetchColumn();

            $pendingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'PENDING' AND {$alive}")->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND DATE(reviewed_at) = ? AND {$alive}"
            );
            $stmt->execute([$today]);
            $todaySettled = (float) $stmt->fetchColumn();

            $pendingWithdrawals = (int) $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'PENDING'")->fetchColumn();

            $totalIncome = (float) $pdo->query(
                "SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND {$alive}"
            )->fetchColumn();

            $pendingWithdrawAmount = (float) $pdo->query(
                "SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE status = 'PENDING'"
            )->fetchColumn();
        } catch (PDOException) {
            return self::getStatsLegacy($pdo, $today);
        }

        return [
            'today_orders'            => $todayOrders,
            'pending_orders'          => $pendingOrders,
            'today_settled'           => $todaySettled,
            'pending_withdrawals'     => $pendingWithdrawals,
            'total_income'            => $totalIncome,
            'pending_withdraw_amount' => $pendingWithdrawAmount,
        ];
    }

    private static function getStatsLegacy(PDO $pdo, string $today): array
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?');
        $stmt->execute([$today]);
        $todayOrders = (int) $stmt->fetchColumn();
        $pendingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'PENDING'")->fetchColumn();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND DATE(reviewed_at) = ?"
        );
        $stmt->execute([$today]);
        $todaySettled = (float) $stmt->fetchColumn();
        $pendingWithdrawals = (int) $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'PENDING'")->fetchColumn();
        $totalIncome = (float) $pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED')"
        )->fetchColumn();
        $pendingWithdrawAmount = (float) $pdo->query(
            "SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE status = 'PENDING'"
        )->fetchColumn();

        return [
            'today_orders'            => $todayOrders,
            'pending_orders'          => $pendingOrders,
            'today_settled'           => $todaySettled,
            'pending_withdrawals'     => $pendingWithdrawals,
            'total_income'            => $totalIncome,
            'pending_withdraw_amount' => $pendingWithdrawAmount,
        ];
    }

    /** 老板端数据统计；$rangeKey: today|yesterday|week|last_week|month|last_month|all */
    public static function getBossStatistics(PDO $pdo, string $rangeKey = 'month'): array
    {
        $base = self::getBossStatisticsBase($pdo);
        $range = self::resolveDateRange($rangeKey);
        $base['range'] = $range;
        $base['range_metrics'] = self::getRangeMetrics($pdo, $range['from'], $range['to']);
        try {
            $base['overview']['prepaid_balance'] = (float) $pdo->query(
                'SELECT COALESCE(SUM(balance), 0) FROM customers WHERE status = 1'
            )->fetchColumn();
        } catch (PDOException) {
            $base['overview']['prepaid_balance'] = 0.0;
        }
        return $base;
    }

    /** @return array{key:string,label:string,from:?string,to:?string} */
    public static function resolveDateRange(string $key): array
    {
        $today = date('Y-m-d');
        return match ($key) {
            'today' => [
                'key' => 'today', 'label' => '今天',
                'from' => $today, 'to' => $today,
            ],
            'yesterday' => [
                'key' => 'yesterday', 'label' => '昨天',
                'from' => date('Y-m-d', strtotime('-1 day')),
                'to' => date('Y-m-d', strtotime('-1 day')),
            ],
            'week' => [
                'key' => 'week', 'label' => '本周',
                'from' => date('Y-m-d', strtotime('monday this week')),
                'to' => $today,
            ],
            'last_week' => [
                'key' => 'last_week', 'label' => '上周',
                'from' => date('Y-m-d', strtotime('monday last week')),
                'to' => date('Y-m-d', strtotime('sunday last week')),
            ],
            'last_month' => [
                'key' => 'last_month', 'label' => '上月',
                'from' => date('Y-m-01', strtotime('first day of last month')),
                'to' => date('Y-m-t', strtotime('last day of last month')),
            ],
            'all' => [
                'key' => 'all', 'label' => '总共',
                'from' => null, 'to' => null,
            ],
            default => [
                'key' => 'month', 'label' => '本月',
                'from' => date('Y-m-01'), 'to' => $today,
            ],
        };
    }

    /** @return array{orders:int,flow:float,withdraw:float,commission:float} */
    public static function getRangeMetrics(PDO $pdo, ?string $from, ?string $to): array
    {
        $alive = self::alive();
        $orderDate = '';
        $reviewDate = '';
        $withdrawDate = '';
        $paramsOrders = [];
        $paramsReview = [];
        $paramsWithdraw = [];

        if ($from !== null && $to !== null) {
            $orderDate = ' AND DATE(created_at) BETWEEN ? AND ?';
            $reviewDate = ' AND DATE(reviewed_at) BETWEEN ? AND ?';
            $withdrawDate = ' AND DATE(COALESCE(updated_at, created_at)) BETWEEN ? AND ?';
            $paramsOrders = [$from, $to];
            $paramsReview = [$from, $to];
            $paramsWithdraw = [$from, $to];
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE {$alive}{$orderDate}");
            $stmt->execute($paramsOrders);
            $orders = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM orders
                 WHERE status IN ('APPROVED','SETTLED') AND {$alive}{$reviewDate}"
            );
            $stmt->execute($paramsReview);
            $flow = (float) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount - COALESCE(staff_amount, 0)), 0) FROM orders
                 WHERE status IN ('APPROVED','SETTLED') AND {$alive}{$reviewDate}"
            );
            $stmt->execute($paramsReview);
            $commission = (float) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM withdrawals
                 WHERE status = 'PAID'{$withdrawDate}"
            );
            $stmt->execute($paramsWithdraw);
            $withdraw = (float) $stmt->fetchColumn();
        } catch (PDOException) {
            return ['orders' => 0, 'flow' => 0.0, 'withdraw' => 0.0, 'commission' => 0.0];
        }

        return [
            'orders'     => $orders,
            'flow'       => round($flow, 2),
            'withdraw'   => round($withdraw, 2),
            'commission' => round(max(0, $commission), 2),
        ];
    }

    private static function getBossStatisticsBase(PDO $pdo): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $a = self::alive('o');
        $alive = self::alive();

        try {
            $overview = [
                'staff_total'     => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'STAFF' AND (deleted_at IS NULL)")->fetchColumn(),
                'staff_active'    => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'STAFF' AND status = 1 AND (deleted_at IS NULL)")->fetchColumn(),
                'customer_total'  => (int) $pdo->query('SELECT COUNT(*) FROM customers WHERE status = 1')->fetchColumn(),
                'order_total'     => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE {$alive}")->fetchColumn(),
                'order_amount'    => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND {$alive}")->fetchColumn(),
                'withdraw_paid'   => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE status = 'PAID'")->fetchColumn(),
                'pending_staff'   => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'STAFF' AND status = 2 AND (deleted_at IS NULL)")->fetchColumn(),
                'prepaid_balance' => 0.0,
            ];

            $period = [
                'today_orders'  => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today' AND {$alive}")->fetchColumn(),
                'today_amount'  => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND DATE(reviewed_at) = '$today' AND {$alive}")->fetchColumn(),
                'week_orders'   => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) >= '$weekStart' AND {$alive}")->fetchColumn(),
                'week_amount'   => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND DATE(reviewed_at) >= '$weekStart' AND {$alive}")->fetchColumn(),
                'month_orders'  => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) >= '$monthStart' AND {$alive}")->fetchColumn(),
                'month_amount'  => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND DATE(reviewed_at) >= '$monthStart' AND {$alive}")->fetchColumn(),
            ];

            $statusRows = $pdo->query(
                "SELECT status, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS amt FROM orders WHERE {$alive} GROUP BY status"
            )->fetchAll();

            $topStaff = $pdo->query(
                "SELECT u.nickname, COUNT(o.id) AS order_count, COALESCE(SUM(o.amount), 0) AS total_amount
                 FROM orders o
                 JOIN users u ON u.id = o.staff_id
                 WHERE o.status IN ('APPROVED','SETTLED') AND {$a}
                 GROUP BY o.staff_id, u.nickname
                 ORDER BY total_amount DESC
                 LIMIT 10"
            )->fetchAll();

            $topCustomers = $pdo->query(
                "SELECT c.name, COUNT(o.id) AS order_count, COALESCE(SUM(o.amount), 0) AS total_amount
                 FROM orders o
                 JOIN customers c ON c.id = o.customer_id
                 WHERE o.status IN ('APPROVED','SETTLED') AND {$a}
                 GROUP BY o.customer_id, c.name
                 ORDER BY total_amount DESC
                 LIMIT 10"
            )->fetchAll();

            $businessTypes = $pdo->query(
                "SELECT b.name, COUNT(o.id) AS order_count, COALESCE(SUM(o.amount), 0) AS total_amount
                 FROM orders o
                 JOIN business_types b ON b.id = o.business_type_id
                 WHERE o.status IN ('APPROVED','SETTLED') AND {$a}
                 GROUP BY o.business_type_id, b.name
                 ORDER BY total_amount DESC"
            )->fetchAll();

            $dailyTrend = [];
            for ($i = 13; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ? AND {$alive}");
                $stmt->execute([$date]);
                $count = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('APPROVED','SETTLED') AND DATE(reviewed_at) = ? AND {$alive}"
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
        } catch (PDOException) {
            return self::getBossStatisticsLegacy($pdo);
        }

        $byStatus = [];
        foreach ($statusRows as $row) {
            $byStatus[$row['status']] = [
                'count'  => (int) $row['cnt'],
                'amount' => (float) $row['amt'],
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

    private static function getBossStatisticsLegacy(PDO $pdo): array
    {
        // 极简回退：避免整站挂掉
        return [
            'overview' => [
                'staff_total' => 0, 'staff_active' => 0, 'customer_total' => 0,
                'order_total' => 0, 'order_amount' => 0.0, 'withdraw_paid' => 0.0, 'pending_staff' => 0,
                'prepaid_balance' => 0.0,
            ],
            'period' => [
                'today_orders' => 0, 'today_amount' => 0.0, 'week_orders' => 0, 'week_amount' => 0.0,
                'month_orders' => 0, 'month_amount' => 0.0,
            ],
            'by_status' => [],
            'top_staff' => [],
            'top_customers' => [],
            'business_types' => [],
            'daily_trend' => [],
            'max_daily_count' => 1,
            'max_daily_amount' => 1.0,
            'range' => self::resolveDateRange('month'),
            'range_metrics' => ['orders' => 0, 'flow' => 0.0, 'withdraw' => 0.0, 'commission' => 0.0],
        ];
    }
}
