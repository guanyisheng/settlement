<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

class BusinessTypeService
{
    public static function getAll(PDO $pdo, bool $activeOnly = false, ?string $keyword = null): array
    {
        $keyword = trim((string) $keyword);
        $where = [];
        $params = [];
        if ($activeOnly) {
            $where[] = 'status = 1';
        }
        if ($keyword !== '') {
            $where[] = '(name LIKE ? OR IFNULL(remark, \'\') LIKE ? OR CAST(id AS CHAR) = ?)';
            $like = '%' . $keyword . '%';
            $params = [$like, $like, $keyword];
        }
        $sql = 'SELECT * FROM business_types';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC';
        if ($params === []) {
            return $pdo->query($sql)->fetchAll();
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return list<string> */
    public static function boards(?PDO $pdo = null): array
    {
        require_once __DIR__ . '/BoardService.php';
        if ($pdo instanceof PDO) {
            return BoardService::names($pdo, true);
        }
        try {
            return BoardService::names(Database::getConnection(), true);
        } catch (Throwable) {
            return ['三角洲行动', '暗区突围', '无畏契约'];
        }
    }

    public static function normalizeBoard(mixed $raw, ?PDO $pdo = null): ?string
    {
        require_once __DIR__ . '/BoardService.php';
        $conn = $pdo instanceof PDO ? $pdo : Database::getConnection();
        return BoardService::normalize($conn, $raw);
    }

    public static function create(PDO $pdo, array $data): int
    {
        $name = trim($data['name'] ?? '');
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $pricingType = trim($data['pricing_type'] ?? 'fixed');
        $remark = trim($data['remark'] ?? '');
        $board = self::normalizeBoard($data['board'] ?? null, $pdo);

        if ($name === '') {
            throw new InvalidArgumentException('业务名称不能为空');
        }
        if ($unitPrice < 0) {
            throw new InvalidArgumentException('单价不能为负数');
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO business_types (name, unit_price, pricing_type, remark, board, status) VALUES (?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([$name, $unitPrice, $pricingType, $remark, $board]);
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'board') && !str_contains($e->getMessage(), 'Unknown column')) {
                throw $e;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO business_types (name, unit_price, pricing_type, remark, status) VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute([$name, $unitPrice, $pricingType, $remark]);
        }
        $id = (int) $pdo->lastInsertId();
        require_once __DIR__ . '/ExtraFeeService.php';
        if (ExtraFeeService::isReady($pdo)) {
            ExtraFeeService::setEnabledForBusinessType($pdo, $id, (array) ($data['extra_fee_ids'] ?? []));
        }
        return $id;
    }

    public static function update(PDO $pdo, int $id, array $data): void
    {
        $name = trim($data['name'] ?? '');
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $pricingType = trim($data['pricing_type'] ?? 'fixed');
        $remark = trim($data['remark'] ?? '');
        $status = isset($data['status']) ? (int) $data['status'] : 1;
        $board = self::normalizeBoard($data['board'] ?? null, $pdo);

        if ($name === '') {
            throw new InvalidArgumentException('业务名称不能为空');
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE business_types SET name = ?, unit_price = ?, pricing_type = ?, remark = ?, board = ?, status = ? WHERE id = ?'
            );
            $stmt->execute([$name, $unitPrice, $pricingType, $remark, $board, $status, $id]);
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'board') && !str_contains($e->getMessage(), 'Unknown column')) {
                throw $e;
            }
            $stmt = $pdo->prepare(
                'UPDATE business_types SET name = ?, unit_price = ?, pricing_type = ?, remark = ?, status = ? WHERE id = ?'
            );
            $stmt->execute([$name, $unitPrice, $pricingType, $remark, $status, $id]);
        }
        require_once __DIR__ . '/ExtraFeeService.php';
        if (ExtraFeeService::isReady($pdo)) {
            ExtraFeeService::setEnabledForBusinessType($pdo, $id, (array) ($data['extra_fee_ids'] ?? []));
        }
    }
}
