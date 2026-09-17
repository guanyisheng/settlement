<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/SettlementService.php';
require_once __DIR__ . '/BusinessTypeService.php';

/** 顾客单：下单→接单/抢池→转单回池→结单→评价（转单人无结算） */
class ClientOrderService
{
    public static function isReady(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM client_orders LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'WAITING' => '待接单',
            'POOL' => '抢单池',
            'ACCEPTED' => '已接单',
            'DOING' => '服务中',
            'DONE' => '已完成',
            'CANCELLED' => '已取消',
            default => $status,
        };
    }

    public static function create(PDO $pdo, int $clientId, array $data): int
    {
        if (!self::isReady($pdo)) {
            throw new RuntimeException('请先执行 database/migrate_customer_portal.sql');
        }
        $btId = (int) ($data['business_type_id'] ?? 0);
        $staffId = (int) ($data['staff_id'] ?? 0);
        $qty = max(1, (int) ($data['quantity'] ?? 1));
        $remark = trim((string) ($data['remark'] ?? ''));
        $toPool = !empty($data['to_pool']) || $staffId <= 0;

        $stmt = $pdo->prepare('SELECT * FROM business_types WHERE id = ? AND status = 1');
        $stmt->execute([$btId]);
        $bt = $stmt->fetch();
        if (!$bt) {
            throw new InvalidArgumentException('请选择业务类型');
        }
        if (!$toPool) {
            $staff = self::getAcceptingStaff($pdo, $staffId);
            if (!$staff) {
                throw new InvalidArgumentException('该打手不可接单');
            }
        }

        $unit = (float) $bt['unit_price'];
        $amount = round($unit * $qty, 2);
        $orderNo = generateNo('CO');
        $status = $toPool ? 'POOL' : 'WAITING';
        $staffVal = $toPool ? null : $staffId;

        $ins = $pdo->prepare(
            'INSERT INTO client_orders (order_no, client_id, business_type_id, staff_id, quantity, unit_price, amount, status, remark)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$orderNo, $clientId, $btId, $staffVal, $qty, $unit, $amount, $status, $remark !== '' ? $remark : null]);
        return (int) $pdo->lastInsertId();
    }

    public static function getAcceptingStaff(PDO $pdo, int $staffId): ?array
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL AND status = 1
                 AND (role = 'STAFF' OR accept_client_orders = 1)"
            );
            $stmt->execute([$staffId]);
        } catch (PDOException) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 1 AND role = 'STAFF'");
            $stmt->execute([$staffId]);
        }
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        try {
            if (isset($row['accept_client_orders']) && (int) $row['accept_client_orders'] === 0) {
                return null;
            }
        } catch (Throwable) {
        }
        return $row;
    }

    /** @return list<array<string,mixed>> */
    public static function listAcceptingStaff(PDO $pdo): array
    {
        try {
            $sql = "SELECT u.id, u.username, u.nickname, u.contact_wechat
                    FROM users u
                    WHERE u.deleted_at IS NULL AND u.status = 1
                      AND (u.role = 'STAFF' OR u.accept_client_orders = 1)
                      AND IFNULL(u.accept_client_orders, 1) = 1
                    ORDER BY u.id DESC LIMIT 200";
            return $pdo->query($sql)->fetchAll();
        } catch (PDOException) {
            return $pdo->query(
                "SELECT id, username, nickname FROM users WHERE role = 'STAFF' AND status = 1 ORDER BY id DESC LIMIT 200"
            )->fetchAll();
        }
    }

    public static function getById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT o.*,
                    c.nickname AS client_name, c.username AS client_username,
                    s.nickname AS staff_name, s.username AS staff_username, s.contact_wechat AS staff_wechat,
                    b.name AS business_type_name
             FROM client_orders o
             JOIN users c ON c.id = o.client_id
             LEFT JOIN users s ON s.id = o.staff_id
             JOIN business_types b ON b.id = o.business_type_id
             WHERE o.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function listForClient(PDO $pdo, int $clientId): array
    {
        $stmt = $pdo->prepare(
            "SELECT o.*, b.name AS business_type_name, s.nickname AS staff_name
             FROM client_orders o
             JOIN business_types b ON b.id = o.business_type_id
             LEFT JOIN users s ON s.id = o.staff_id
             WHERE o.client_id = ?
             ORDER BY o.id DESC LIMIT 100"
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function listForStaff(PDO $pdo, int $staffId): array
    {
        $stmt = $pdo->prepare(
            "SELECT o.*, b.name AS business_type_name, c.nickname AS client_name
             FROM client_orders o
             JOIN business_types b ON b.id = o.business_type_id
             JOIN users c ON c.id = o.client_id
             WHERE o.staff_id = ? OR o.status = 'POOL'
             ORDER BY FIELD(o.status,'POOL','WAITING','ACCEPTED','DOING','DONE','CANCELLED'), o.id DESC
             LIMIT 100"
        );
        $stmt->execute([$staffId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function listAll(PDO $pdo, ?string $status = null): array
    {
        $sql = "SELECT o.*, b.name AS business_type_name, c.nickname AS client_name, s.nickname AS staff_name
                FROM client_orders o
                JOIN business_types b ON b.id = o.business_type_id
                JOIN users c ON c.id = o.client_id
                LEFT JOIN users s ON s.id = o.staff_id";
        $params = [];
        if ($status) {
            $sql .= ' WHERE o.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY o.id DESC LIMIT 200';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function accept(PDO $pdo, int $orderId, int $staffId): void
    {
        $order = self::getById($pdo, $orderId);
        if (!$order) {
            throw new RuntimeException('订单不存在');
        }
        if ($order['status'] === 'POOL') {
            $stmt = $pdo->prepare(
                "UPDATE client_orders SET staff_id = ?, status = 'ACCEPTED', accepted_at = NOW(), transfer_note = NULL
                 WHERE id = ? AND status = 'POOL'"
            );
            $stmt->execute([$staffId, $orderId]);
        } elseif ($order['status'] === 'WAITING' && (int) $order['staff_id'] === $staffId) {
            $stmt = $pdo->prepare(
                "UPDATE client_orders SET status = 'ACCEPTED', accepted_at = NOW() WHERE id = ? AND status = 'WAITING' AND staff_id = ?"
            );
            $stmt->execute([$orderId, $staffId]);
        } else {
            throw new RuntimeException('无法接单');
        }
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('接单失败，可能已被抢走');
        }
    }

    public static function start(PDO $pdo, int $orderId, int $staffId): void
    {
        $stmt = $pdo->prepare(
            "UPDATE client_orders SET status = 'DOING' WHERE id = ? AND staff_id = ? AND status = 'ACCEPTED'"
        );
        $stmt->execute([$orderId, $staffId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('无法开始服务');
        }
    }

    /** 转单：丢回抢单池，转单人无钱 */
    public static function transferToPool(PDO $pdo, int $orderId, int $staffId, string $note = ''): void
    {
        $note = trim($note);
        $stmt = $pdo->prepare(
            "UPDATE client_orders
             SET status = 'POOL', transferred_from = staff_id, staff_id = NULL,
                 transfer_note = ?, accepted_at = NULL
             WHERE id = ? AND staff_id = ? AND status IN ('ACCEPTED','DOING','WAITING')"
        );
        $stmt->execute([$note !== '' ? $note : '打手转单回池', $orderId, $staffId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('无法转单');
        }
    }

    public static function complete(PDO $pdo, int $orderId, int $staffId): void
    {
        $order = self::getById($pdo, $orderId);
        if (!$order || (int) $order['staff_id'] !== $staffId) {
            throw new RuntimeException('订单不存在');
        }
        if (!in_array($order['status'], ['ACCEPTED', 'DOING'], true)) {
            throw new RuntimeException('当前状态不可结单');
        }
        $amount = (float) $order['amount'];
        $calc = SettlementService::calcByCrewMode($amount, false);
        $staffAmount = $calc['staff_amount'];

        $stmt = $pdo->prepare(
            "UPDATE client_orders SET status = 'DONE', staff_amount = ?, completed_at = NOW()
             WHERE id = ? AND staff_id = ? AND status IN ('ACCEPTED','DOING')"
        );
        $stmt->execute([$staffAmount, $orderId, $staffId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('结单失败');
        }

        require_once __DIR__ . '/MembershipService.php';
        MembershipService::addGrowthFromSpend($pdo, (int) $order['client_id'], $amount);
    }

    public static function cancel(PDO $pdo, int $orderId, int $actorId, bool $asAdmin = false): void
    {
        $order = self::getById($pdo, $orderId);
        if (!$order) {
            throw new RuntimeException('订单不存在');
        }
        if (!$asAdmin && (int) $order['client_id'] !== $actorId) {
            throw new RuntimeException('无权取消');
        }
        if (in_array($order['status'], ['DONE', 'CANCELLED'], true)) {
            throw new RuntimeException('订单已结束');
        }
        $pdo->prepare("UPDATE client_orders SET status = 'CANCELLED' WHERE id = ?")->execute([$orderId]);
    }

    public static function addReview(PDO $pdo, int $orderId, int $clientId, int $score, string $content): void
    {
        $order = self::getById($pdo, $orderId);
        if (!$order || (int) $order['client_id'] !== $clientId || $order['status'] !== 'DONE') {
            throw new RuntimeException('仅已完成订单可评价');
        }
        $score = max(1, min(5, $score));
        $content = trim($content);
        try {
            $pdo->prepare(
                'INSERT INTO client_order_reviews (order_id, client_id, staff_id, score, content) VALUES (?, ?, ?, ?, ?)'
            )->execute([$orderId, $clientId, (int) $order['staff_id'], $score, $content !== '' ? $content : null]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                throw new InvalidArgumentException('已评价过');
            }
            throw $e;
        }
    }

    public static function getReview(PDO $pdo, int $orderId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM client_order_reviews WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function staffDoneIncome(PDO $pdo, int $staffId): float
    {
        if (!self::isReady($pdo)) {
            return 0.0;
        }
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(staff_amount),0) FROM client_orders WHERE staff_id = ? AND status = 'DONE'"
        );
        $stmt->execute([$staffId]);
        return (float) $stmt->fetchColumn();
    }
}
