<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

class OrderService
{
    private static ?bool $hasCoStaffColumn = null;

    public static function hasCoStaffColumn(PDO $pdo): bool
    {
        if (self::$hasCoStaffColumn !== null) {
            return self::$hasCoStaffColumn;
        }
        try {
            $pdo->query('SELECT co_staff_id FROM orders LIMIT 0');
            return self::$hasCoStaffColumn = true;
        } catch (PDOException) {
            return self::$hasCoStaffColumn = false;
        }
    }

    /** 双人单时两人平分总到手；返回当前用户应得 */
    public static function shareForStaff(array $order, int $staffId): float
    {
        $total = (float) ($order['staff_amount'] ?? $order['amount'] ?? 0);
        $coId = (int) ($order['co_staff_id'] ?? 0);
        $primaryId = (int) ($order['staff_id'] ?? 0);
        if ($coId <= 0) {
            return $primaryId === $staffId ? round($total, 2) : 0.0;
        }
        $half = round($total / 2, 2);
        if ($primaryId === $staffId) {
            return $half;
        }
        if ($coId === $staffId) {
            return round($total - $half, 2);
        }
        return 0.0;
    }

    public static function isParticipant(array $order, int $staffId): bool
    {
        return (int) ($order['staff_id'] ?? 0) === $staffId
            || (int) ($order['co_staff_id'] ?? 0) === $staffId;
    }

    public static function create(PDO $pdo, int $staffId, array $data, ?array $screenshotFiles = null): array
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        $businessTypeId = (int) ($data['business_type_id'] ?? 0);
        $quantity = (int) ($data['quantity'] ?? 0);
        $startTime = trim($data['start_time'] ?? '');
        $endTime = trim($data['end_time'] ?? '');
        $remark = trim($data['remark'] ?? '');
        $wechatOrderNo = trim($data['wechat_order_no'] ?? '');
        $coStaffId = (int) ($data['co_staff_id'] ?? 0);
        $crewMode = strtolower(trim((string) ($data['crew_mode'] ?? '')));
        $uploadedFiles = normalizeUploadedFiles($screenshotFiles);

        // 兼容 datetime-local 格式 (2026-09-02T20:00)
        $startTime = str_replace('T', ' ', $startTime);
        $endTime = str_replace('T', ' ', $endTime);
        if (strlen($startTime) === 16) {
            $startTime .= ':00';
        }
        if (strlen($endTime) === 16) {
            $endTime .= ':00';
        }

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

        if ($crewMode === 'duo' || $crewMode === 'double' || $crewMode === '2') {
            if ($coStaffId <= 0) {
                throw new InvalidArgumentException('双人接单请选择附加打手');
            }
        } elseif ($crewMode === 'solo' || $crewMode === 'single' || $crewMode === '1') {
            $coStaffId = 0;
        } elseif ($crewMode !== '' && $crewMode !== 'auto') {
            throw new InvalidArgumentException('接单方式无效');
        }

        if ($coStaffId > 0) {
            if (!self::hasCoStaffColumn($pdo)) {
                throw new InvalidArgumentException('系统尚未开通附加打手，请联系管理员执行数据库更新');
            }
            if ($coStaffId === $staffId) {
                throw new InvalidArgumentException('附加打手不能是自己');
            }
            require_once __DIR__ . '/UserService.php';
            $co = UserService::getStaffById($pdo, $coStaffId);
            if (!$co || (int) ($co['status'] ?? 0) !== 1) {
                throw new InvalidArgumentException('附加打手不存在或未启用');
            }
        } else {
            $coStaffId = 0;
        }

        self::assertWechatOrderAvailable($pdo, $wechatOrderNo, $staffId);

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
        $duoRateB = (float) $rates['rate_b'];
        $soloRateB = (float) ($rates['rate_solo'] ?? min(1.0, $duoRateB * 2));
        $isDuo = $coStaffId > 0;

        // 本单自定义倍率 / 结算金额；未自定义时按一人/双人后台费率
        $manualRateB = false;
        if (isset($data['rate_a']) && $data['rate_a'] !== '') {
            $rateA = (float) $data['rate_a'];
        } elseif (isset($data['rate_a_pct']) && $data['rate_a_pct'] !== '') {
            $rateA = (float) $data['rate_a_pct'] / 100;
        }
        if (isset($data['rate_b']) && $data['rate_b'] !== '') {
            $duoRateB = (float) $data['rate_b'];
            $soloRateB = $duoRateB; // 审核端单字段覆盖时按本单填写值理解
            $manualRateB = true;
        } elseif (isset($data['rate_b_pct']) && $data['rate_b_pct'] !== '') {
            $duoRateB = (float) $data['rate_b_pct'] / 100;
            $soloRateB = $duoRateB;
            $manualRateB = true;
        }
        if (isset($data['rate_solo']) && $data['rate_solo'] !== '') {
            $soloRateB = (float) $data['rate_solo'];
        } elseif (isset($data['rate_solo_pct']) && $data['rate_solo_pct'] !== '') {
            $soloRateB = (float) $data['rate_solo_pct'] / 100;
        }
        if ($rateA < 0 || $rateA > 1 || $duoRateB < 0 || $duoRateB > 1 || $soloRateB < 0 || $soloRateB > 1) {
            throw new InvalidArgumentException('倍率需在 0%～100% 之间');
        }

        if (isset($data['staff_amount']) && $data['staff_amount'] !== '') {
            $staffAmount = round((float) $data['staff_amount'], 2);
            if ($staffAmount < 0) {
                throw new InvalidArgumentException('打手结算金额不能为负');
            }
            $rateB = $isDuo ? $duoRateB : ($manualRateB ? $duoRateB : $soloRateB);
        } else {
            $calc = SettlementService::calcByCrewMode($amount, $isDuo, $rateA, $duoRateB, $soloRateB);
            $staffAmount = $calc['staff_amount'];
            $rateA = $calc['rate_a'];
            $rateB = $calc['rate_b'];
        }

        $orderNo = generateNo('ORD');

        require_once __DIR__ . '/OrderScreenshotStorage.php';
        $storage = new OrderScreenshotStorage();
        $screenshotKeys = $storage->uploadScreenshots($uploadedFiles, $wechatOrderNo);
        $screenshotKey = encodeScreenshotKeys($screenshotKeys);

        $coValue = $coStaffId > 0 ? $coStaffId : null;

        try {
            if (self::hasCoStaffColumn($pdo)) {
                $stmt = $pdo->prepare(
                    'INSERT INTO orders (order_no, wechat_order_no, screenshot_key, staff_id, co_staff_id, customer_id, business_type_id, quantity, unit_price, amount, staff_amount, rate_a, rate_b, start_time, end_time, remark, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $orderNo, $wechatOrderNo, $screenshotKey, $staffId, $coValue, $customerId, $businessTypeId,
                    $quantity, $unitPrice, $amount, $staffAmount, $rateA, $rateB, $startTime, $endTime, $remark, 'PENDING',
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO orders (order_no, wechat_order_no, screenshot_key, staff_id, customer_id, business_type_id, quantity, unit_price, amount, staff_amount, rate_a, rate_b, start_time, end_time, remark, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $orderNo, $wechatOrderNo, $screenshotKey, $staffId, $customerId, $businessTypeId,
                    $quantity, $unitPrice, $amount, $staffAmount, $rateA, $rateB, $startTime, $endTime, $remark, 'PENDING',
                ]);
            }
        } catch (PDOException $e) {
            // 未跑 migration 时无 rate_a/rate_b 列
            if (!str_contains($e->getMessage(), 'rate_a') && !str_contains($e->getMessage(), 'Unknown column')) {
                if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
                    throw new InvalidArgumentException('该微信订单编号已报单，请勿重复提交');
                }
                throw $e;
            }
            if (self::hasCoStaffColumn($pdo)) {
                $stmt = $pdo->prepare(
                    'INSERT INTO orders (order_no, wechat_order_no, screenshot_key, staff_id, co_staff_id, customer_id, business_type_id, quantity, unit_price, amount, staff_amount, start_time, end_time, remark, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $orderNo, $wechatOrderNo, $screenshotKey, $staffId, $coValue, $customerId, $businessTypeId,
                    $quantity, $unitPrice, $amount, $staffAmount, $startTime, $endTime, $remark, 'PENDING',
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO orders (order_no, wechat_order_no, screenshot_key, staff_id, customer_id, business_type_id, quantity, unit_price, amount, staff_amount, start_time, end_time, remark, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $orderNo, $wechatOrderNo, $screenshotKey, $staffId, $customerId, $businessTypeId,
                    $quantity, $unitPrice, $amount, $staffAmount, $startTime, $endTime, $remark, 'PENDING',
                ]);
            }
        }

        return [
            'id'              => (int) $pdo->lastInsertId(),
            'order_no'        => $orderNo,
            'wechat_order_no' => $wechatOrderNo,
            'amount'          => $amount,
            'staff_amount'    => $staffAmount,
            'co_staff_id'     => $coStaffId ?: null,
        ];
    }

    /** 微信单号查重，并给出是否已被拉为附加打手的提示 */
    public static function assertWechatOrderAvailable(PDO $pdo, string $wechatOrderNo, int $viewerStaffId): void
    {
        $existing = self::findByWechatOrderNo($pdo, $wechatOrderNo);
        if (!$existing) {
            return;
        }

        $primaryName = (string) ($existing['staff_name'] ?? '其他打手');
        $coName = (string) ($existing['co_staff_name'] ?? '');
        $primaryId = (int) ($existing['staff_id'] ?? 0);
        $coId = (int) ($existing['co_staff_id'] ?? 0);

        if ($primaryId === $viewerStaffId) {
            throw new InvalidArgumentException('该微信订单编号你已报过单，请勿重复提交');
        }
        if ($coId === $viewerStaffId) {
            throw new InvalidArgumentException(
                '该微信订单编号已被「' . $primaryName . '」报单，且已将你加为附加打手，请到「订单」查看，勿再重复提交'
            );
        }
        if ($coId > 0 && $coName !== '') {
            throw new InvalidArgumentException(
                '该微信订单编号已被「' . $primaryName . '」报单（附加打手：' . $coName . '），请勿重复提交'
            );
        }
        throw new InvalidArgumentException(
            '该微信订单编号已被「' . $primaryName . '」报单。若为双人单，请让对方在报单时搜索添加你为附加打手'
        );
    }

    public static function findByWechatOrderNo(PDO $pdo, string $wechatOrderNo): ?array
    {
        $hasCo = self::hasCoStaffColumn($pdo);
        $coSelect = $hasCo
            ? ', o.co_staff_id, cu.nickname AS co_staff_name'
            : ', NULL AS co_staff_id, NULL AS co_staff_name';
        $coJoin = $hasCo ? 'LEFT JOIN users cu ON cu.id = o.co_staff_id' : '';

        try {
            $sql = "SELECT o.*, u.nickname AS staff_name {$coSelect}
                    FROM orders o
                    JOIN users u ON u.id = o.staff_id
                    {$coJoin}
                    WHERE o.wechat_order_no = ? AND o.deleted_at IS NULL
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$wechatOrderNo]);
        } catch (PDOException) {
            $sql = "SELECT o.*, u.nickname AS staff_name {$coSelect}
                    FROM orders o
                    JOIN users u ON u.id = o.staff_id
                    {$coJoin}
                    WHERE o.wechat_order_no = ?
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$wechatOrderNo]);
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function approve(PDO $pdo, int $orderId, int $reviewerId, array $override = []): void
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new RuntimeException('订单不存在');
            }
            if (!empty($order['deleted_at'])) {
                throw new RuntimeException('订单已删除');
            }
            if ($order['status'] !== 'PENDING') {
                throw new RuntimeException('订单状态不允许审核');
            }

            require_once __DIR__ . '/SettlementService.php';
            [$rateA, $rateB, $staffAmount] = self::resolveSettlementOverride($order, $override);

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

            try {
                $stmt = $pdo->prepare(
                    "UPDATE orders SET status = 'APPROVED', staff_amount = ?, rate_a = ?, rate_b = ?,
                     reviewed_by = ?, reviewed_at = NOW(), reject_reason = NULL WHERE id = ?"
                );
                $stmt->execute([$staffAmount, $rateA, $rateB, $reviewerId, $orderId]);
            } catch (PDOException) {
                $stmt = $pdo->prepare(
                    "UPDATE orders SET status = 'APPROVED', staff_amount = ?, reviewed_by = ?, reviewed_at = NOW(), reject_reason = NULL WHERE id = ?"
                );
                $stmt->execute([$staffAmount, $reviewerId, $orderId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 解析本单结算：改倍率默认按公式重算金额；仅勾选「手动金额」时才用填写值。
     * @return array{0:float,1:float,2:float} [rate_a, rate_b, staff_amount]
     */
    public static function resolveSettlementOverride(array $order, array $data): array
    {
        require_once __DIR__ . '/SettlementService.php';

        $defaults = SettlementService::rates();
        $rateA = isset($order['rate_a']) && $order['rate_a'] !== null && $order['rate_a'] !== ''
            ? (float) $order['rate_a']
            : (float) $defaults['rate_a'];
        $rateB = isset($order['rate_b']) && $order['rate_b'] !== null && $order['rate_b'] !== ''
            ? (float) $order['rate_b']
            : (float) $defaults['rate_b'];

        if (isset($data['rate_a']) && $data['rate_a'] !== '') {
            $rateA = (float) $data['rate_a'];
        } elseif (isset($data['rate_a_pct']) && $data['rate_a_pct'] !== '') {
            $rateA = (float) $data['rate_a_pct'] / 100;
        }
        if (isset($data['rate_b']) && $data['rate_b'] !== '') {
            $rateB = (float) $data['rate_b'];
        } elseif (isset($data['rate_b_pct']) && $data['rate_b_pct'] !== '') {
            $rateB = (float) $data['rate_b_pct'] / 100;
        }

        $isDuo = (int) ($order['co_staff_id'] ?? 0) > 0;
        if ($isDuo) {
            // 双人：表单/快照里的 rate_b 按「每人半份」理解，总额=半份×2
            $calc = SettlementService::calcByCrewMode((float) $order['amount'], true, $rateA, $rateB);
            $rateA = $calc['rate_a'];
            $rateB = $calc['rate_b'];
            $calculated = $calc['staff_amount'];
        } else {
            // 一人：rate_b 即全部份额（常见 100%）
            $calculated = SettlementService::calcStaffAmount((float) $order['amount'], $rateA, $rateB);
        }
        $manual = !empty($data['manual_amount']) || !empty($data['settlement_manual']);

        if ($manual && isset($data['staff_amount']) && $data['staff_amount'] !== '') {
            $staffAmount = round((float) $data['staff_amount'], 2);
        } else {
            $staffAmount = $calculated;
        }

        return [$rateA, $rateB, $staffAmount];
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
        $hasCo = self::hasCoStaffColumn($pdo);
        $coSelect = $hasCo
            ? ', o.co_staff_id, cu.nickname AS co_staff_name'
            : ', NULL AS co_staff_id, NULL AS co_staff_name';
        $coJoin = $hasCo ? 'LEFT JOIN users cu ON cu.id = o.co_staff_id' : '';
        $whereStaff = $hasCo
            ? '(o.staff_id = ? OR o.co_staff_id = ?)'
            : 'o.staff_id = ?';
        $params = $hasCo ? [$staffId, $staffId] : [$staffId];

        try {
            $stmt = $pdo->prepare(
                "SELECT o.*, c.name AS customer_name, b.name AS business_type_name, u.nickname AS staff_name {$coSelect}
                 FROM orders o
                 JOIN customers c ON c.id = o.customer_id
                 JOIN business_types b ON b.id = o.business_type_id
                 JOIN users u ON u.id = o.staff_id
                 {$coJoin}
                 WHERE {$whereStaff} AND o.deleted_at IS NULL
                 ORDER BY o.created_at DESC"
            );
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException) {
            $stmt = $pdo->prepare(
                "SELECT o.*, c.name AS customer_name, b.name AS business_type_name, u.nickname AS staff_name {$coSelect}
                 FROM orders o
                 JOIN customers c ON c.id = o.customer_id
                 JOIN business_types b ON b.id = o.business_type_id
                 JOIN users u ON u.id = o.staff_id
                 {$coJoin}
                 WHERE {$whereStaff}
                 ORDER BY o.created_at DESC"
            );
            $stmt->execute($params);
            return $stmt->fetchAll();
        }
    }

    public static function search(PDO $pdo, array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        $hasCo = self::hasCoStaffColumn($pdo);

        // 软删过滤（列可能不存在）
        try {
            $pdo->query('SELECT deleted_at FROM orders LIMIT 0');
            $where[] = 'o.deleted_at IS NULL';
        } catch (PDOException) {
        }

        if (!empty($filters['staff_id'])) {
            $sid = (int) $filters['staff_id'];
            if ($hasCo) {
                $where[] = '(o.staff_id = ? OR o.co_staff_id = ?)';
                $params[] = $sid;
                $params[] = $sid;
            } else {
                $where[] = 'o.staff_id = ?';
                $params[] = $sid;
            }
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

        $coSelect = $hasCo
            ? ', o.co_staff_id, cu.nickname AS co_staff_name'
            : ', NULL AS co_staff_id, NULL AS co_staff_name';
        $coJoin = $hasCo ? 'LEFT JOIN users cu ON cu.id = o.co_staff_id' : '';

        $sql = "SELECT o.*, c.name AS customer_name, b.name AS business_type_name, u.nickname AS staff_name {$coSelect}
                FROM orders o
                JOIN customers c ON c.id = o.customer_id
                JOIN business_types b ON b.id = o.business_type_id
                JOIN users u ON u.id = o.staff_id
                {$coJoin}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY o.created_at DESC
                LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** 物理删除订单（删了就没了，统计不再计入）；已通过预存单会退回客户余额 */
    public static function hardDelete(PDO $pdo, int $orderId): void
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order) {
                throw new RuntimeException('订单不存在');
            }

            if ($order['status'] === 'APPROVED' || $order['status'] === 'SETTLED') {
                $cStmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? FOR UPDATE');
                $cStmt->execute([(int) $order['customer_id']]);
                $customer = $cStmt->fetch();
                if ($customer && (int) ($customer['is_prepaid'] ?? 0) === 1) {
                    $pdo->prepare('UPDATE customers SET balance = balance + ? WHERE id = ?')
                        ->execute([(float) $order['amount'], $customer['id']]);
                }
            }

            $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @deprecated 使用 hardDelete */
    public static function softDelete(PDO $pdo, int $orderId): void
    {
        self::hardDelete($pdo, $orderId);
    }

    public static function softDeleteMany(PDO $pdo, array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $n = 0;
        foreach ($ids as $id) {
            self::hardDelete($pdo, $id);
            $n++;
        }
        return $n;
    }

    /** 待审核订单改结算金额（不审核） */
    public static function updateSettlement(PDO $pdo, int $orderId, array $data): void
    {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException('订单不存在');
        }
        if ($order['status'] !== 'PENDING') {
            throw new RuntimeException('仅待审核订单可改结算');
        }

        [$rateA, $rateB, $staffAmount] = self::resolveSettlementOverride($order, $data);

        try {
            $pdo->prepare('UPDATE orders SET staff_amount = ?, rate_a = ?, rate_b = ? WHERE id = ?')
                ->execute([$staffAmount, $rateA, $rateB, $orderId]);
        } catch (PDOException) {
            $pdo->prepare('UPDATE orders SET staff_amount = ? WHERE id = ?')
                ->execute([$staffAmount, $orderId]);
        }
    }

    public static function getById(PDO $pdo, int $orderId): ?array
    {
        $hasCo = self::hasCoStaffColumn($pdo);
        $coSelect = $hasCo
            ? ', o.co_staff_id, cu.nickname AS co_staff_name'
            : ', NULL AS co_staff_id, NULL AS co_staff_name';
        $coJoin = $hasCo ? 'LEFT JOIN users cu ON cu.id = o.co_staff_id' : '';

        $stmt = $pdo->prepare(
            "SELECT o.*, c.name AS customer_name, b.name AS business_type_name, u.nickname AS staff_name,
                    r.nickname AS reviewer_name {$coSelect}
             FROM orders o
             JOIN customers c ON c.id = o.customer_id
             JOIN business_types b ON b.id = o.business_type_id
             JOIN users u ON u.id = o.staff_id
             LEFT JOIN users r ON r.id = o.reviewed_by
             {$coJoin}
             WHERE o.id = ?"
        );
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
