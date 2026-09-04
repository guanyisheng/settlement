<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

class BusinessTypeService
{
    public static function getAll(PDO $pdo, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM business_types';
        if ($activeOnly) {
            $sql .= ' WHERE status = 1';
        }
        $sql .= ' ORDER BY created_at DESC';
        return $pdo->query($sql)->fetchAll();
    }

    public static function create(PDO $pdo, array $data): int
    {
        $name = trim($data['name'] ?? '');
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $pricingType = trim($data['pricing_type'] ?? 'fixed');
        $remark = trim($data['remark'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException('业务名称不能为空');
        }
        if ($unitPrice < 0) {
            throw new InvalidArgumentException('单价不能为负数');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO business_types (name, unit_price, pricing_type, remark, status) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$name, $unitPrice, $pricingType, $remark]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(PDO $pdo, int $id, array $data): void
    {
        $name = trim($data['name'] ?? '');
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $pricingType = trim($data['pricing_type'] ?? 'fixed');
        $remark = trim($data['remark'] ?? '');
        $status = isset($data['status']) ? (int) $data['status'] : 1;

        if ($name === '') {
            throw new InvalidArgumentException('业务名称不能为空');
        }

        $stmt = $pdo->prepare(
            'UPDATE business_types SET name = ?, unit_price = ?, pricing_type = ?, remark = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([$name, $unitPrice, $pricingType, $remark, $status, $id]);
    }
}
