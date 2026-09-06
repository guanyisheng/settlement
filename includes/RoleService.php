<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PermissionService.php';

class RoleService
{
    public static function listRoles(PDO $pdo, bool $includeDisabled = false): array
    {
        $sql = 'SELECT r.*,
                       (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS user_count
                FROM roles r
                WHERE r.deleted_at IS NULL';
        if (!$includeDisabled) {
            $sql .= ' AND r.status = 1';
        }
        $sql .= ' ORDER BY r.is_system DESC, r.id ASC';
        return $pdo->query($sql)->fetchAll();
    }

    /**
     * 员工可分配的角色：启用中的全部角色，排除打手/老板
     * （含自定义角色，如「财务」）
     */
    public static function listAssignableForEmployees(PDO $pdo): array
    {
        $blocked = ['STAFF', 'BOSS', 'ADMIN'];
        $list = [];
        foreach (self::listRoles($pdo, false) as $r) {
            $code = trim((string) ($r['code'] ?? ''));
            if ($code !== '' && in_array($code, $blocked, true)) {
                continue;
            }
            $list[] = $r;
        }
        // 系统角色在前，自定义在后
        usort($list, static function ($a, $b) {
            $as = (int) ($a['is_system'] ?? 0);
            $bs = (int) ($b['is_system'] ?? 0);
            if ($as !== $bs) {
                return $bs <=> $as;
            }
            return ((int) $a['id']) <=> ((int) $b['id']);
        });
        return $list;
    }

    public static function getById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $role = $stmt->fetch();
        return $role ?: null;
    }

    public static function getPermissionIds(PDO $pdo, int $roleId): array
    {
        $stmt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
        $stmt->execute([$roleId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public static function getScope(PDO $pdo, int $roleId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM role_data_scopes WHERE role_id = ?');
        $stmt->execute([$roleId]);
        $row = $stmt->fetch();
        if (!$row) {
            return [
                'scope_type' => 'self',
                'staff_ids' => [],
                'customer_ids' => [],
                'business_ids' => [],
            ];
        }
        return [
            'scope_type' => $row['scope_type'],
            'staff_ids' => json_decode($row['staff_ids'] ?: '[]', true) ?: [],
            'customer_ids' => json_decode($row['customer_ids'] ?: '[]', true) ?: [],
            'business_ids' => json_decode($row['business_ids'] ?: '[]', true) ?: [],
        ];
    }

    public static function allPermissions(PDO $pdo): array
    {
        return $pdo->query(
            'SELECT * FROM permissions ORDER BY group_name ASC, sort_order ASC, id ASC'
        )->fetchAll();
    }

    public static function create(PDO $pdo, string $name, string $description = ''): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('请填写角色名称');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO roles (code, name, description, is_system, status) VALUES (NULL, ?, ?, 0, 1)'
        );
        try {
            $stmt->execute([$name, trim($description)]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                throw new InvalidArgumentException('角色名称已存在');
            }
            throw $e;
        }
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO role_data_scopes (role_id, scope_type) VALUES (?, ?)'
        )->execute([$id, 'self']);
        return $id;
    }

    public static function update(PDO $pdo, int $id, string $name, string $description, int $status): void
    {
        $role = self::getById($pdo, $id);
        if (!$role) {
            throw new RuntimeException('角色不存在');
        }
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('请填写角色名称');
        }
        if ((int) $role['is_system'] === 1) {
            // 系统角色允许改描述/状态，名称保持
            $stmt = $pdo->prepare('UPDATE roles SET description = ?, status = ? WHERE id = ?');
            $stmt->execute([trim($description), $status ? 1 : 0, $id]);
            return;
        }
        $stmt = $pdo->prepare('UPDATE roles SET name = ?, description = ?, status = ? WHERE id = ?');
        try {
            $stmt->execute([$name, trim($description), $status ? 1 : 0, $id]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                throw new InvalidArgumentException('角色名称已存在');
            }
            throw $e;
        }
    }

    public static function setPermissions(PDO $pdo, int $roleId, array $permissionIds): void
    {
        $role = self::getById($pdo, $roleId);
        if (!$role) {
            throw new RuntimeException('角色不存在');
        }
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
        $stmt = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach (array_unique(array_map('intval', $permissionIds)) as $pid) {
            if ($pid > 0) {
                $stmt->execute([$roleId, $pid]);
            }
        }
        PermissionService::clearCache();
    }

    public static function setScope(
        PDO $pdo,
        int $roleId,
        string $scopeType,
        array $staffIds = [],
        array $customerIds = [],
        array $businessIds = []
    ): void {
        $allowed = ['all', 'self', 'assigned', 'staff_ids', 'customer_ids', 'business_ids', 'ids'];
        if (!in_array($scopeType, $allowed, true)) {
            throw new InvalidArgumentException('无效的数据范围类型');
        }
        // 统一：有指定 ID 时存为 ids 语义，scope_type 用 staff_ids/customer_ids/business_ids/assigned/all/self
        if (in_array($scopeType, ['staff_ids', 'customer_ids', 'business_ids', 'ids'], true)) {
            $scopeType = 'assigned'; // 库内用 assigned + JSON 列表
        }
        $staffIds = array_values(array_unique(array_filter(array_map('intval', $staffIds))));
        $customerIds = array_values(array_unique(array_filter(array_map('intval', $customerIds))));
        $businessIds = array_values(array_unique(array_filter(array_map('intval', $businessIds))));

        $stmt = $pdo->prepare(
            'INSERT INTO role_data_scopes (role_id, scope_type, staff_ids, customer_ids, business_ids)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                scope_type = VALUES(scope_type),
                staff_ids = VALUES(staff_ids),
                customer_ids = VALUES(customer_ids),
                business_ids = VALUES(business_ids)'
        );
        $stmt->execute([
            $roleId,
            $scopeType,
            json_encode($staffIds, JSON_UNESCAPED_UNICODE),
            json_encode($customerIds, JSON_UNESCAPED_UNICODE),
            json_encode($businessIds, JSON_UNESCAPED_UNICODE),
        ]);
        PermissionService::clearCache();
    }

    public static function softDelete(PDO $pdo, int $id): void
    {
        $role = self::getById($pdo, $id);
        if (!$role) {
            throw new RuntimeException('角色不存在');
        }
        if ((int) $role['is_system'] === 1) {
            throw new RuntimeException('系统角色不能删除');
        }
        $pdo->prepare('UPDATE roles SET deleted_at = NOW(), status = 0 WHERE id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM user_roles WHERE role_id = ?')->execute([$id]);
        PermissionService::clearCache();
    }

    public static function setUserRoles(PDO $pdo, int $userId, array $roleIds): void
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));
        if ($roleIds === []) {
            throw new InvalidArgumentException('用户至少需要一个角色');
        }
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
        $stmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
        foreach ($roleIds as $rid) {
            $stmt->execute([$userId, $rid]);
        }

        // 双写兼容：取第一个系统角色 code 回写 users.role
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT code FROM roles WHERE id IN ({$placeholders}) AND code IS NOT NULL ORDER BY is_system DESC, id ASC LIMIT 1"
        );
        $stmt->execute($roleIds);
        $code = $stmt->fetchColumn();
        if ($code) {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$code, $userId]);
        }

        PermissionService::clearCache($userId);
    }

    public static function ensureUserHasLegacyRole(PDO $pdo, int $userId, string $legacyRole): void
    {
        if (!PermissionService::isRbacReady($pdo)) {
            return;
        }
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE code = ? LIMIT 1');
        $stmt->execute([$legacyRole]);
        $roleId = (int) $stmt->fetchColumn();
        if ($roleId <= 0) {
            return;
        }
        $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')
            ->execute([$userId, $roleId]);
        PermissionService::clearCache($userId);
    }
}
