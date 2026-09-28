<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

class CustomerService
{
    public static function hasUserIdColumn(PDO $pdo): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $pdo->query('SELECT user_id FROM customers LIMIT 0');
            $ready = true;
        } catch (PDOException) {
            $ready = false;
        }
        return $ready;
    }

    public static function getAll(PDO $pdo, bool $activeOnly = false): array
    {
        $where = $activeOnly ? ' WHERE c.status = 1' : '';
        if (self::hasUserIdColumn($pdo)) {
            $sql = "SELECT c.*, u.username AS portal_username, u.nickname AS portal_nickname,
                           u.growth_points, u.membership_expire_at
                    FROM customers c
                    LEFT JOIN users u ON u.id = c.user_id AND u.role = 'CLIENT'
                    {$where}
                    ORDER BY c.name ASC";
            return $pdo->query($sql)->fetchAll();
        }
        $sql = 'SELECT * FROM customers' . ($activeOnly ? ' WHERE status = 1' : '') . ' ORDER BY name ASC';
        return $pdo->query($sql)->fetchAll();
    }

    public static function getById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** 门户顾客注册后：确保有一条报单客户可编辑、可选 */
    public static function ensureForClientUser(PDO $pdo, int $userId): int
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('无效用户');
        }
        $stmt = $pdo->prepare("SELECT id, username, nickname, status FROM users WHERE id = ? AND role = 'CLIENT'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('顾客账号不存在');
        }
        $name = trim((string) ($user['nickname'] ?: $user['username']));
        $status = (int) ($user['status'] ?? 1) === 1 ? 1 : 0;

        if (!self::hasUserIdColumn($pdo)) {
            // 未跑关联迁移：仍尽量按备注/名称建一条（不强制）
            $stmt = $pdo->prepare('SELECT id FROM customers WHERE name = ? LIMIT 1');
            $stmt->execute([$name]);
            $row = $stmt->fetch();
            if ($row) {
                return (int) $row['id'];
            }
            return self::create($pdo, [
                'name' => $name,
                'remark' => '门户账号 ' . $user['username'],
                'balance' => 0,
                'is_prepaid' => 0,
            ]);
        }

        $stmt = $pdo->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) {
            $cid = (int) $row['id'];
            $pdo->prepare('UPDATE customers SET name = ?, status = ? WHERE id = ?')
                ->execute([$name, $status, $cid]);
            return $cid;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO customers (name, remark, balance, is_prepaid, status, user_id) VALUES (?, ?, 0, 0, ?, ?)'
        );
        $stmt->execute([$name, '门户账号 ' . $user['username'], $status, $userId]);
        return (int) $pdo->lastInsertId();
    }

    /** 把门户顾客资料同步到关联的报单客户 */
    public static function syncFromClientUser(PDO $pdo, int $userId, string $name, int $status): void
    {
        if (!self::hasUserIdColumn($pdo) || $userId <= 0) {
            return;
        }
        $name = trim($name);
        if ($name === '') {
            return;
        }
        $pdo->prepare('UPDATE customers SET name = ?, status = ? WHERE user_id = ?')
            ->execute([$name, $status ? 1 : 0, $userId]);
    }

    public static function create(PDO $pdo, array $data): int
    {
        $name = trim($data['name'] ?? '');
        $remark = trim($data['remark'] ?? '');
        $balance = round((float) ($data['balance'] ?? 0), 2);
        $isPrepaid = !empty($data['is_prepaid']) ? 1 : 0;
        $userId = (int) ($data['user_id'] ?? 0);

        if ($name === '') {
            throw new InvalidArgumentException('客户名称不能为空');
        }
        if ($balance < 0) {
            throw new InvalidArgumentException('预存余额不能为负数');
        }

        if (self::hasUserIdColumn($pdo) && $userId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM customers WHERE user_id = ?');
            $stmt->execute([$userId]);
            if ($stmt->fetch()) {
                throw new InvalidArgumentException('该门户顾客已关联客户');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO customers (name, remark, balance, is_prepaid, status, user_id) VALUES (?, ?, ?, ?, 1, ?)'
            );
            $stmt->execute([$name, $remark, $balance, $isPrepaid, $userId]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO customers (name, remark, balance, is_prepaid, status) VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute([$name, $remark, $balance, $isPrepaid]);
        }
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

        // 关联门户账号：名称/状态一并改
        if (self::hasUserIdColumn($pdo)) {
            $row = self::getById($pdo, $id);
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0) {
                try {
                    $pdo->prepare('UPDATE users SET nickname = ?, status = ? WHERE id = ? AND role = ?')
                        ->execute([$name, $status ? 1 : 0, $uid, 'CLIENT']);
                } catch (PDOException) {
                }
            }
        }
    }
}
