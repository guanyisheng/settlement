<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PermissionCatalog.php';

/**
 * 三层权限：角色 → 功能权限合集 → 数据范围合集
 */
class PermissionService
{
    private static bool $rbacReady = false;
    private static bool $rbacChecked = false;

    /** @var array<int, array{codes: string[], scopes: array}> */
    private static array $userCache = [];

    public static function isRbacReady(?PDO $pdo = null): bool
    {
        if (self::$rbacChecked) {
            return self::$rbacReady;
        }
        self::$rbacChecked = true;
        try {
            $pdo = $pdo ?? Database::getConnection();
            $pdo->query('SELECT 1 FROM roles LIMIT 1');
            $pdo->query('SELECT 1 FROM permissions LIMIT 1');
            $pdo->query('SELECT 1 FROM user_roles LIMIT 1');
            self::$rbacReady = true;
        } catch (Throwable) {
            self::$rbacReady = false;
        }
        return self::$rbacReady;
    }

    public static function clearCache(?int $userId = null): void
    {
        if ($userId === null) {
            self::$userCache = [];
            return;
        }
        unset(self::$userCache[$userId]);
    }

    /** @return string[] */
    public static function getPermissionCodes(PDO $pdo, int $userId): array
    {
        $bundle = self::loadUserBundle($pdo, $userId);
        return $bundle['codes'];
    }

    public static function userCan(PDO $pdo, int $userId, string $permission): bool
    {
        if (!self::isRbacReady($pdo)) {
            return false;
        }
        return in_array($permission, self::getPermissionCodes($pdo, $userId), true);
    }

    public static function userCanAny(PDO $pdo, int $userId, array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::userCan($pdo, $userId, $p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 合并后的数据范围
     * @return array{type: string, staff_ids: int[], customer_ids: int[], business_ids: int[]}
     */
    public static function getMergedScope(PDO $pdo, int $userId): array
    {
        $bundle = self::loadUserBundle($pdo, $userId);
        return $bundle['scope'];
    }

    /** @return array{id:int,name:string,code:?string}[] */
    public static function getUserRoles(PDO $pdo, int $userId): array
    {
        if (!self::isRbacReady($pdo)) {
            return [];
        }
        $stmt = $pdo->prepare(
            'SELECT r.id, r.name, r.code
             FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = ? AND r.status = 1 AND r.deleted_at IS NULL
             ORDER BY r.is_system DESC, r.id ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function hasStaffLikeRole(PDO $pdo, int $userId): bool
    {
        foreach (self::getUserRoles($pdo, $userId) as $role) {
            if (($role['code'] ?? '') === 'STAFF') {
                return true;
            }
        }
        // 兼容：无 user_roles 时看 users.role
        if (!self::isRbacReady($pdo)) {
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            return $stmt->fetchColumn() === 'STAFF';
        }
        // 仅有 STAFF 角色、或没有任何可进后台的权限时视为打手端用户
        $codes = self::getPermissionCodes($pdo, $userId);
        $adminish = array_diff($codes, [
            'dashboard.view', 'report.create', 'order.view', 'withdrawal.view',
            'photo.view', 'honor.view', 'honor.manage', 'password.change',
        ]);
        return $adminish === [] && in_array('report.create', $codes, true);
    }

    public static function canAccessAdminPortal(PDO $pdo, int $userId): bool
    {
        if (!self::isRbacReady($pdo)) {
            return false;
        }
        // 有任一非纯打手端权限即可进后台
        $adminPerms = [
            'order.review', 'withdrawal.process', 'registration.review',
            'staff.manage', 'staff.view', 'customer.view', 'customer.manage',
            'business.view', 'business.manage', 'stats.view', 'board.view',
            'user.view', 'user.manage', 'role.view', 'role.manage',
            'rate.manage', 'settings.manage', 'photo.download', 'honor.manage',
            'order.delete', 'user.delete', 'permission.manage',
        ];
        return self::userCanAny($pdo, $userId, $adminPerms)
            || self::userCan($pdo, $userId, 'dashboard.view')
                && !self::isOnlyStaffPortal($pdo, $userId);
    }

    private static function isOnlyStaffPortal(PDO $pdo, int $userId): bool
    {
        $roles = self::getUserRoles($pdo, $userId);
        if ($roles === []) {
            return false;
        }
        foreach ($roles as $role) {
            if (($role['code'] ?? '') !== 'STAFF') {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array{codes: string[], scope: array}
     */
    private static function loadUserBundle(PDO $pdo, int $userId): array
    {
        if (isset(self::$userCache[$userId])) {
            return self::$userCache[$userId];
        }

        if (!self::isRbacReady($pdo)) {
            return self::$userCache[$userId] = [
                'codes' => [],
                'scope' => self::emptyScope('self'),
            ];
        }

        $stmt = $pdo->prepare(
            'SELECT DISTINCT p.code
             FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id AND r.status = 1 AND r.deleted_at IS NULL
             JOIN role_permissions rp ON rp.role_id = r.id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?'
        );
        $stmt->execute([$userId]);
        $codes = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $stmt = $pdo->prepare(
            'SELECT s.scope_type, s.staff_ids, s.customer_ids, s.business_ids
             FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id AND r.status = 1 AND r.deleted_at IS NULL
             LEFT JOIN role_data_scopes s ON s.role_id = r.id
             WHERE ur.user_id = ?'
        );
        $stmt->execute([$userId]);
        $scope = self::mergeScopes($stmt->fetchAll() ?: []);

        return self::$userCache[$userId] = [
            'codes' => array_values(array_unique($codes)),
            'scope' => $scope,
        ];
    }

    private static function mergeScopes(array $rows): array
    {
        if ($rows === []) {
            return self::emptyScope('self');
        }

        $hasAll = false;
        $hasAssigned = false;
        $hasSelf = false;
        $staffIds = [];
        $customerIds = [];
        $businessIds = [];

        foreach ($rows as $row) {
            $type = $row['scope_type'] ?? 'self';
            if ($type === 'all') {
                $hasAll = true;
            } elseif ($type === 'assigned') {
                $hasAssigned = true;
            } elseif ($type === 'self') {
                $hasSelf = true;
            }

            $staffIds = array_merge($staffIds, self::decodeIdList($row['staff_ids'] ?? null));
            $customerIds = array_merge($customerIds, self::decodeIdList($row['customer_ids'] ?? null));
            $businessIds = array_merge($businessIds, self::decodeIdList($row['business_ids'] ?? null));
        }

        if ($hasAll) {
            return self::emptyScope('all');
        }

        $staffIds = array_values(array_unique(array_map('intval', $staffIds)));
        $customerIds = array_values(array_unique(array_map('intval', $customerIds)));
        $businessIds = array_values(array_unique(array_map('intval', $businessIds)));

        // 指定 ID 优先于 assigned/self
        if ($staffIds !== [] || $customerIds !== [] || $businessIds !== []) {
            return [
                'type' => 'ids',
                'staff_ids' => $staffIds,
                'customer_ids' => $customerIds,
                'business_ids' => $businessIds,
            ];
        }

        if ($hasAssigned) {
            return self::emptyScope('assigned');
        }

        return self::emptyScope($hasSelf ? 'self' : 'self');
    }

    private static function emptyScope(string $type): array
    {
        return [
            'type' => $type,
            'staff_ids' => [],
            'customer_ids' => [],
            'business_ids' => [],
        ];
    }

    /** @return int[] */
    private static function decodeIdList(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $decoded), static fn($id) => $id > 0));
    }

    /**
     * 为订单列表等生成 WHERE 片段
     * @return array{sql: string, params: array}
     */
    public static function buildOrderScopeFilter(PDO $pdo, int $userId, string $alias = 'o'): array
    {
        $scope = self::getMergedScope($pdo, $userId);
        if ($scope['type'] === 'all') {
            return ['sql' => '1=1', 'params' => []];
        }
        if ($scope['type'] === 'self') {
            return ['sql' => "{$alias}.staff_id = ?", 'params' => [$userId]];
        }
        if ($scope['type'] === 'ids') {
            $parts = [];
            $params = [];
            if ($scope['staff_ids'] !== []) {
                $in = implode(',', array_fill(0, count($scope['staff_ids']), '?'));
                $parts[] = "{$alias}.staff_id IN ({$in})";
                $params = array_merge($params, $scope['staff_ids']);
            }
            if ($scope['customer_ids'] !== []) {
                $in = implode(',', array_fill(0, count($scope['customer_ids']), '?'));
                $parts[] = "{$alias}.customer_id IN ({$in})";
                $params = array_merge($params, $scope['customer_ids']);
            }
            if ($scope['business_ids'] !== []) {
                $in = implode(',', array_fill(0, count($scope['business_ids']), '?'));
                $parts[] = "{$alias}.business_type_id IN ({$in})";
                $params = array_merge($params, $scope['business_ids']);
            }
            if ($parts === []) {
                // 指定范围但未配置 ID：不可见
                return ['sql' => '1=0', 'params' => []];
            }
            return ['sql' => '(' . implode(' OR ', $parts) . ')', 'params' => $params];
        }
        // assigned：暂无单独「负责关系」表，先按本人 + 空（需在角色里配置指定 ID）
        // 兼容：客服/考官未配 ID 时看全部（迁移期），避免突然空白；后续可收紧
        return ['sql' => '1=1', 'params' => []];
    }

    /**
     * 打手列表数据范围
     * @return array{sql: string, params: array}
     */
    public static function buildStaffScopeFilter(PDO $pdo, int $userId, string $alias = 'u'): array
    {
        $scope = self::getMergedScope($pdo, $userId);
        if ($scope['type'] === 'all') {
            return ['sql' => '1=1', 'params' => []];
        }
        if ($scope['type'] === 'self') {
            return ['sql' => "{$alias}.id = ?", 'params' => [$userId]];
        }
        if ($scope['type'] === 'ids' && $scope['staff_ids'] !== []) {
            $in = implode(',', array_fill(0, count($scope['staff_ids']), '?'));
            return ['sql' => "{$alias}.id IN ({$in})", 'params' => $scope['staff_ids']];
        }
        if ($scope['type'] === 'ids') {
            return ['sql' => '1=0', 'params' => []];
        }
        return ['sql' => '1=1', 'params' => []];
    }
}
