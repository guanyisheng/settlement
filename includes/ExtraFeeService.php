<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

/**
 * 额外收费项目：加百分比 和/或 直接加固定金额（可叠加）
 * 最终订单金额 = 基础金额 × (1 + Σ百分比) + Σ固定加价
 */
class ExtraFeeService
{
    private static ?bool $tablesReady = null;
    private static ?bool $orderColsReady = null;

    public static function isReady(PDO $pdo): bool
    {
        if (self::$tablesReady !== null) {
            return self::$tablesReady;
        }
        try {
            $pdo->query('SELECT 1 FROM extra_fee_items LIMIT 0');
            $pdo->query('SELECT 1 FROM business_type_extra_fees LIMIT 0');
            self::$tablesReady = true;
        } catch (PDOException) {
            self::$tablesReady = false;
        }
        return self::$tablesReady;
    }

    public static function orderColumnsReady(PDO $pdo): bool
    {
        if (self::$orderColsReady !== null) {
            return self::$orderColsReady;
        }
        try {
            $pdo->query('SELECT base_amount, extra_fees_json, extra_fees_rate FROM orders LIMIT 0');
            self::$orderColsReady = true;
        } catch (PDOException) {
            self::$orderColsReady = false;
        }
        return self::$orderColsReady;
    }

    /** @return list<array<string,mixed>> */
    public static function getAllItems(PDO $pdo, bool $activeOnly = false): array
    {
        if (!self::isReady($pdo)) {
            return [];
        }
        $sql = 'SELECT * FROM extra_fee_items';
        if ($activeOnly) {
            $sql .= ' WHERE status = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $pdo->query($sql)->fetchAll();
    }

    public static function createItem(PDO $pdo, array $data): int
    {
        if (!self::isReady($pdo)) {
            throw new RuntimeException('请先执行 database/migrate_extra_fee_items.sql');
        }
        [$name, $type, $rate, $fixed, $sort, $remark] = self::normalizeWritePayload($data);
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO extra_fee_items (name, fee_type, rate, fixed_amount, sort_order, status, remark)
                 VALUES (?, ?, ?, ?, ?, 1, ?)'
            );
            $stmt->execute([$name, $type, $rate, $fixed, $sort, $remark]);
        } catch (PDOException) {
            if ($type === 'fixed') {
                throw new RuntimeException('请先执行 database/migrate_extra_fee_fixed_amount.sql 后再添加「直接加钱」项目');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO extra_fee_items (name, rate, sort_order, status, remark) VALUES (?, ?, ?, 1, ?)'
            );
            $stmt->execute([$name, $rate, $sort, $remark]);
        }
        return (int) $pdo->lastInsertId();
    }

    public static function updateItem(PDO $pdo, int $id, array $data): void
    {
        if (!self::isReady($pdo)) {
            throw new RuntimeException('请先执行 database/migrate_extra_fee_items.sql');
        }
        if ($id <= 0) {
            throw new InvalidArgumentException('项目无效');
        }
        [$name, $type, $rate, $fixed, $sort, $remark] = self::normalizeWritePayload($data);
        $status = isset($data['status']) ? ((int) $data['status'] ? 1 : 0) : 1;
        try {
            $stmt = $pdo->prepare(
                'UPDATE extra_fee_items SET name = ?, fee_type = ?, rate = ?, fixed_amount = ?, sort_order = ?, status = ?, remark = ? WHERE id = ?'
            );
            $stmt->execute([$name, $type, $rate, $fixed, $sort, $status, $remark, $id]);
        } catch (PDOException) {
            if ($type === 'fixed') {
                throw new RuntimeException('请先执行 database/migrate_extra_fee_fixed_amount.sql 后再改为「直接加钱」');
            }
            $stmt = $pdo->prepare(
                'UPDATE extra_fee_items SET name = ?, rate = ?, sort_order = ?, status = ?, remark = ? WHERE id = ?'
            );
            $stmt->execute([$name, $rate, $sort, $status, $remark, $id]);
        }
    }

    /** @return array{0:string,1:string,2:float,3:float,4:int,5:?string} */
    private static function normalizeWritePayload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $type = strtolower(trim((string) ($data['fee_type'] ?? 'percent')));
        if ($type !== 'fixed') {
            $type = 'percent';
        }
        $sort = (int) ($data['sort_order'] ?? 0);
        $remarkRaw = trim((string) ($data['remark'] ?? ''));
        $remark = $remarkRaw !== '' ? $remarkRaw : null;
        if ($name === '') {
            throw new InvalidArgumentException('项目名称不能为空');
        }
        $rate = 0.0;
        $fixed = 0.0;
        if ($type === 'fixed') {
            $fixed = round((float) ($data['fixed_amount'] ?? 0), 2);
            if ($fixed <= 0) {
                throw new InvalidArgumentException('加价金额必须大于 0');
            }
        } else {
            $ratePct = (float) ($data['rate_pct'] ?? 0);
            if ($ratePct <= 0 || $ratePct > 500) {
                throw new InvalidArgumentException('上调比例需在 0%～500% 之间');
            }
            $rate = round($ratePct / 100, 4);
        }
        return [$name, $type, $rate, $fixed, $sort, $remark];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,name:string,fee_type:string,rate:float,rate_pct:float,fixed_amount:float}
     */
    public static function normalizeItem(array $row): array
    {
        $type = strtolower(trim((string) ($row['fee_type'] ?? $row['type'] ?? 'percent')));
        if ($type !== 'fixed') {
            $type = 'percent';
        }
        $rate = (float) ($row['rate'] ?? 0);
        $fixed = (float) ($row['fixed_amount'] ?? $row['amount'] ?? 0);
        if ($type === 'fixed') {
            $rate = 0.0;
        } else {
            $fixed = 0.0;
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'fee_type' => $type,
            'rate' => $rate,
            'rate_pct' => round($rate * 100, 2),
            'fixed_amount' => round($fixed, 2),
        ];
    }

    public static function chargeLabel(array $row): string
    {
        $item = self::normalizeItem($row);
        if ($item['fee_type'] === 'fixed') {
            return '+' . formatMoney($item['fixed_amount']);
        }
        $pct = rtrim(rtrim(number_format($item['rate'] * 100, 2, '.', ''), '0'), '.');
        return '+' . $pct . '%';
    }

    /** @return list<int> */
    public static function getEnabledIdsForBusinessType(PDO $pdo, int $businessTypeId): array
    {
        if (!self::isReady($pdo) || $businessTypeId <= 0) {
            return [];
        }
        $stmt = $pdo->prepare(
            'SELECT extra_fee_item_id FROM business_type_extra_fees
             WHERE business_type_id = ? ORDER BY sort_order ASC, extra_fee_item_id ASC'
        );
        $stmt->execute([$businessTypeId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * 某业务类型下启用的额外项目（仅启用中的项目）
     * @return list<array{id:int,name:string,rate:float,rate_pct:float}>
     */
    public static function getEnabledItemsForBusinessType(PDO $pdo, int $businessTypeId): array
    {
        if (!self::isReady($pdo) || $businessTypeId <= 0) {
            return [];
        }
        $stmt = $pdo->prepare(
            'SELECT e.*
             FROM business_type_extra_fees m
             JOIN extra_fee_items e ON e.id = m.extra_fee_item_id
             WHERE m.business_type_id = ? AND e.status = 1
             ORDER BY m.sort_order ASC, e.sort_order ASC, e.id ASC'
        );
        $stmt->execute([$businessTypeId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = self::normalizeItem($r);
        }
        return $rows;
    }

    /** @param list<int|string> $feeIds */
    public static function setEnabledForBusinessType(PDO $pdo, int $businessTypeId, array $feeIds): void
    {
        if (!self::isReady($pdo) || $businessTypeId <= 0) {
            return;
        }
        $pdo->prepare('DELETE FROM business_type_extra_fees WHERE business_type_id = ?')
            ->execute([$businessTypeId]);
        $ids = [];
        foreach ($feeIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO business_type_extra_fees (business_type_id, extra_fee_item_id, sort_order) VALUES (?, ?, ?)'
        );
        $sort = 0;
        foreach ($ids as $id) {
            $stmt->execute([$businessTypeId, $id, $sort]);
            $sort += 10;
        }
    }

    /**
     * 校验并解析报单勾选的额外项目
     *
     * @param list<int|string> $selectedIds
     * @return array{items:list<array{id:int,name:string,fee_type:string,rate:float,fixed_amount:float}>,rate_total:float,fixed_total:float,json:string}
     */
    public static function resolveSelected(PDO $pdo, int $businessTypeId, array $selectedIds): array
    {
        $allowed = self::getEnabledItemsForBusinessType($pdo, $businessTypeId);
        $byId = [];
        foreach ($allowed as $item) {
            $byId[$item['id']] = $item;
        }
        $picked = [];
        $rateTotal = 0.0;
        $fixedTotal = 0.0;
        $seen = [];
        foreach ($selectedIds as $raw) {
            $id = (int) $raw;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            if (!isset($byId[$id])) {
                throw new InvalidArgumentException('所选额外收费项目对该业务类型不可用');
            }
            $seen[$id] = true;
            $item = $byId[$id];
            $picked[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'fee_type' => $item['fee_type'],
                'rate' => $item['rate'],
                'fixed_amount' => $item['fixed_amount'],
            ];
            $rateTotal += $item['rate'];
            $fixedTotal += $item['fixed_amount'];
        }
        $rateTotal = round($rateTotal, 4);
        $fixedTotal = round($fixedTotal, 2);
        return [
            'items' => $picked,
            'rate_total' => $rateTotal,
            'fixed_total' => $fixedTotal,
            'json' => $picked === [] ? '' : (string) json_encode($picked, JSON_UNESCAPED_UNICODE),
        ];
    }

    public static function applyToBaseAmount(float $baseAmount, float $rateTotal, float $fixedTotal = 0.0): float
    {
        if ($rateTotal < 0) {
            $rateTotal = 0;
        }
        if ($fixedTotal < 0) {
            $fixedTotal = 0;
        }
        return round($baseAmount * (1 + $rateTotal) + $fixedTotal, 2);
    }

    /** @return list<array{id?:int,name:string,fee_type:string,rate:float,fixed_amount:float}> */
    public static function parseSnapshot(?string $json): array
    {
        $json = trim((string) $json);
        if ($json === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = self::normalizeItem($row);
            if ($item['name'] === '') {
                continue;
            }
            if ($item['fee_type'] === 'fixed' && $item['fixed_amount'] <= 0) {
                continue;
            }
            if ($item['fee_type'] === 'percent' && $item['rate'] <= 0) {
                continue;
            }
            $out[] = $item;
        }
        return $out;
    }

    public static function formatSnapshotLabel(?string $json, ?float $rateTotal = null): string
    {
        $items = self::parseSnapshot($json);
        if ($items === []) {
            return '';
        }
        $parts = [];
        $fixedSum = 0.0;
        $pctSum = 0.0;
        foreach ($items as $item) {
            $parts[] = $item['name'] . self::chargeLabel($item);
            if ($item['fee_type'] === 'fixed') {
                $fixedSum += $item['fixed_amount'];
            } else {
                $pctSum += $item['rate'];
            }
        }
        $label = implode('、', $parts);
        $tails = [];
        $pct = $rateTotal !== null && $rateTotal > 0 ? $rateTotal : $pctSum;
        if ($pct > 0) {
            $sum = rtrim(rtrim(number_format($pct * 100, 2, '.', ''), '0'), '.');
            $tails[] = '百分比+' . $sum . '%';
        }
        if ($fixedSum > 0) {
            $tails[] = '加价' . formatMoney($fixedSum);
        }
        if ($tails !== []) {
            $label .= '（' . implode('，', $tails) . '）';
        }
        return $label;
    }

    /**
     * 报单页：业务类型 → 可用额外项目
     * @return array<string, list<array{id:int,name:string,fee_type:string,rate:float,rate_pct:float,fixed_amount:float}>>
     */
    public static function mapEnabledByBusinessType(PDO $pdo, array $businessTypes): array
    {
        $map = [];
        foreach ($businessTypes as $bt) {
            $id = (int) ($bt['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $map[(string) $id] = self::getEnabledItemsForBusinessType($pdo, $id);
        }
        return $map;
    }
}
