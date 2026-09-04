<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

class CustomerService
{
    public static function getAll(PDO $pdo, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM customers';
        if ($activeOnly) {
            $sql .= ' WHERE status = 1';
        }
        $sql .= ' ORDER BY name ASC';
        return $pdo->query($sql)->fetchAll();
    }

    public static function getById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(PDO $pdo, array $data): int
    {
        $name = trim($data['name'] ?? '');
        $remark = trim($data['remark'] ?? '');
        $balance = round((float) ($data['balance'] ?? 0), 2);
        $isPrepaid = !empty($data['is_prepaid']) ? 1 : 0;

        if ($name === '') {
            throw new InvalidArgumentException('客户名称不能为空');
        }
        if ($balance < 0) {
            throw new InvalidArgumentException('预存余额不能为负数');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO customers (name, remark, balance, is_prepaid, status) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$name, $remark, $balance, $isPrepaid]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(PDO $pdo, int $id, array $data): void
    {
        $name = trim($data['name'] ?? '');
        $remark = trim($data['remark'] ?? '');
        $status = isset($data['status']) ? (int) $data['status'] : 1;
        $balance = round((float) ($data['balance'] ?? 0), 2);
        $isPrepaid = !empty($data['is_prepaid']) ? 1 : 0;

        if ($name === '') {
            throw new InvalidArgumentException('客户名称不能为空');
        }
        if ($balance < 0) {
            throw new InvalidArgumentException('预存余额不能为负数');
        }

        $stmt = $pdo->prepare(
            'UPDATE customers SET name = ?, remark = ?, balance = ?, is_prepaid = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([$name, $remark, $balance, $isPrepaid, $status, $id]);
    }
}
