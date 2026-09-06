<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

class OrderService
{
    public static function create(PDO $pdo, int $staffId, array $data, ?array $screenshotFiles = null): array
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        $businessTypeId = (int) ($data['business_type_id'] ?? 0);
        $quantity = (int) ($data['quantity'] ?? 0);
        $startTime = trim($data['start_time'] ?? '');
        $endTime = trim($data['end_time'] ?? '');
        $remark = trim($data['remark'] ?? '');
        $wechatOrderNo = trim($data['wechat_order_no'] ?? '');
        $uploadedFiles = normalizeUploadedFiles($screenshotFiles);

        // 兼容 datetime-local 格式 (2026-09-02T20:00)
        $startTime = str_replace('T', ' ', $startTime);
        $endTime = str_replace('T', ' ', $endTime);
        if (strlen($startTime) === 16) $startTime .= ':00';
        if (strlen($endTime) === 16) $endTime .= ':00';

        if ($customerId <= 0 || $businessTypeId <= 0) {
            throw new InvalidArgumentException('请选择客户和业务类型');
        }
        if ($wechatOrderNo === '') {
            throw new InvalidArgumentException('请填写微信订单编号');
        }
        if ($uploadedFiles === []) {
            throw new InvalidArgumentException('请上传订单截图');
        }
        if (count($uploadedFiles) > 9) {
            throw new InvalidArgumentException('最多上传9张截图');
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('请填写有效的数量（正整数）');
        }
        if (trim($startTime) === '' || trim($endTime) === '') {
            throw new InvalidArgumentException('请填写完整的接单开始和结束时间');
        }
        if (strtotime($endTime) < strtotime($startTime)) {
            throw new InvalidArgumentException('结束时间不能早于开始时间');
        }
        if (mb_strlen($wechatOrderNo) > 64) {
            throw new InvalidArgumentException('微信订单编号过长');
        }

        $stmt = $pdo->prepare('SELECT id FROM orders WHERE wechat_order_no = ? LIMIT 1');
        $stmt->execute([$wechatOrderNo]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('该微信订单编号已报备，请勿重复提交');
        }

        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? AND status = 1');
        $stmt->execute([$customerId]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException('客户不存在或已禁用');
        }

        $stmt = $pdo->prepare('SELECT * FROM business_types WHERE id = ? AND status = 1');
        $stmt->execute([$businessTypeId]);
        $businessType = $stmt->fetch();
        if (!$businessType) {
            throw new InvalidArgumentException('业务类型不存在或已禁用');
        }

        $unitPrice = (float) $businessType['unit_price'];
        $amount = round($unitPrice * $quantity, 2);

        require_once __DIR__ . '/SettlementService.php';
        $rates = SettlementService::rates();
        $rateA = (float) $rates['rate_a'];
        $rateB = (float) $rates['rate_b'];
        $staffAmount = SettlementService::calcStaffAmount($amount, $rateA, $rateB);

        $orderNo = generateNo('ORD');

        require_once __DIR__ . '/OrderScreenshotStorage.php';
        $storage = new OrderScreenshotStorage();
        $screenshotKeys = $storage->uploadScreenshots($uploadedFiles, $wechatOrderNo);
        $screenshotKey = encodeScreenshotKeys($screenshotKeys);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO orders (order_no, wechat_order_no, screenshot_key, staff_id, customer_id, business_type_id, quantity, unit_price, amount, staff_amount, rate_a, rate_b, start_time, end_time, remark, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $orderNo, $wechatOrderNo, $screenshotKey, $staffId, $customerId, $businessTypeId,
                $quantity, $unitPrice, $amount, $staffAmount, $rateA, $rateB, $startTime, $endTime, $remark, 'PENDING',
            ]);
        } catch (PDOException $e) {
            // 未跑 migration 时无 rate_a/rate_b 列
            if (!str_contains($e->getMessage(), 'rate_a') && !str_contains($e->getMessage(), 'Unknown column')) {
                throw $e;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO orders (order_no, wechat_order_no, screenshot_key, staff_id, customer_id, business_type_id, quantity, unit_price, amount, staff_amount, start_time, end_time, remark, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $orderNo, $wechatOrderNo, $screenshotKey, $staffId, $customerId, $businessTypeId,
                $quantity, $unitPrice, $amount, $staffAmount, $startTime, $endTime, $remark, 'PENDING',
            ]);
        }

        return [
            'id'              => (int) $pdo->lastInsertId(),
            'order_no'        => $orderNo,
            'wechat_order_no' => $wechatOrderNo,
            'amount'          => $amount,
            'staff_amount'    => $staffAmount,
        ];
    }

    public static function approve(PDO $pdo, int $orderId, int $reviewerId): void
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new RuntimeException('订单不存在');
            }
            if ($order['status'] !== 'PENDING') {
                throw new RuntimeException('订单状态不允许审核');
            }

            require_once __DIR__ . '/SettlementService.php';
            if ($order['staff_amount'] !== null) {
                $staffAmount = (float) $order['staff_amount'];
            } else {
                $rateA = isset($order['rate_a']) && $order['rate_a'] !== null ? (float) $order['rate_a'] : null;
                $rateB = isset($order['rate_b']) && $order['rate_b'] !== null ? (float) $order['rate_b'] : null;
                $staffAmount = SettlementService::calcStaffAmount((float) $order['amount'], $rateA, $rateB);
            }

            $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? FOR UPDATE');
            $stmt->execute([(int) $order['customer_id']]);
            $customer = $stmt->fetch();
            if (!$customer) {
                throw new RuntimeException('客户不存在');
            }

            if ((int) ($customer['is_prepaid'] ?? 0) === 1) {
                $balance = (float) ($customer['balance'] ?? 0);
                $deduct = (float) $order['amount'];
                if ($balance < $deduct) {
                    throw new RuntimeException('客户预存余额不足（当前 ' . formatMoney($balance) . '，需扣 ' . formatMoney($deduct) . '）');
                }
                $stmt = $pdo->prepare('UPDATE customers SET balance = balance - ? WHERE id = ?');
                $stmt->execute([$deduct, $customer['id']]);
            }

            $stmt = $pdo->prepare(
                "UPDATE orders SET status = 'APPROVED', staff_amount = ?, reviewed_by = ?, reviewed_at = NOW(), reject_reason = NULL WHERE id = ?"
            );
            $stmt->execute([$staffAmount, $reviewerId, $orderId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function reject(PDO $pdo, int $orderId, int $reviewerId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('请填写拒绝原因');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new RuntimeException('订单不存在');
            }
            if ($order['status'] !== 'PENDING') {
                throw new RuntimeException('订单状态不允许审核');
            }

            $stmt = $pdo->prepare(
                "UPDATE orders SET status = 'REJECTED', reviewed_by = ?, reviewed_at = NOW(), reject_reason = ? WHERE id = ?"
            );
            $stmt->execute([$reviewerId, $reason, $orderId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getByStaff(PDO $pdo, int $staffId): array
    {
        $stmt = $pdo->prepare(
            "SELECT o.*, c.name AS customer_name, b.name AS business_type_name, u.nickname AS staff_name
             FROM orders o
             JOIN customers c ON c.id = o.customer_id
             JOIN business_types b ON b.id = o.business_type_id
             JOIN users u ON u.id = o.staff_id
             WHERE o.staff_id = ?
             ORDER BY o.created_at DESC"
        );
        $stmt->execute([$staffId]);
        return $stmt->fetchAll();
    }

    public static function search(PDO $pdo, array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        // 软删过滤（列可能不存在）
        try {
            $pdo->query('SELECT deleted_at FROM orders LIMIT 0');
            $where[] = 'o.deleted_at IS NULL';
        } catch (PDOException) {
        }

        if (!empty($filters['staff_id'])) {
            $where[] = 'o.staff_id = ?';
            $params[] = (int) $filters['staff_id'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 'o.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['business_type_id'])) {
            $where[] = 'o.business_type_id = ?';
            $params[] = (int) $filters['business_type_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'o.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(o.created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(o.created_at) <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['scope_sql'])) {
            $where[] = '(' . $filters['scope_sql'] . ')';
            foreach ($filters['scope_params'] ?? [] as $p) {
                $params[] = $p;
            }
        }

        $sql = "SELECT o.*, c.name AS customer_name, b.name AS business_type_name, u.nickname AS staff_name
                FROM orders o
                JOIN customers c ON c.id = o.customer_id
                JOIN business_types b ON b.id = o.business_type_id
                JOIN users u ON u.id = o.staff_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY o.created_at DESC
                LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function softDelete(PDO $pdo, int $orderId): void
    {
        $stmt = $pdo->prepare('UPDATE orders SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$orderId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('订单不存在或已删除');
        }
    }

    public static function softDeleteMany(PDO $pdo, array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE orders SET deleted_at = NOW() WHERE id IN ({$in}) AND deleted_at IS NULL");
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    public static function getById(PDO $pdo, int $orderId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT o.*, c.name AS customer_name, b.name AS business_type_name, u.nickname AS staff_name,
                    r.nickname AS reviewer_name
             FROM orders o
             JOIN customers c ON c.id = o.customer_id
             JOIN business_types b ON b.id = o.business_type_id
             JOIN users u ON u.id = o.staff_id
             LEFT JOIN users r ON r.id = o.reviewed_by
             WHERE o.id = ?"
        );
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
