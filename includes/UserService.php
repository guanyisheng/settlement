<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/helpers.php';

class UserService
{
    // ─── 打手 ───────────────────────────────────────────

    public static function getStaffList(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT * FROM users WHERE role = 'STAFF' AND status != " . Auth::STATUS_PENDING . " ORDER BY created_at DESC"
        );
        return $stmt->fetchAll();
    }

    public static function getPendingRegistrations(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT * FROM users WHERE role = 'STAFF' AND status = " . Auth::STATUS_PENDING . " ORDER BY created_at ASC"
        );
        return $stmt->fetchAll();
    }

    public static function countPendingRegistrations(PDO $pdo): int
    {
        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM users WHERE role = 'STAFF' AND status = " . Auth::STATUS_PENDING
        );
        return (int) $stmt->fetchColumn();
    }

    public static function approveRegistration(PDO $pdo, int $id, int $reviewerId): void
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'STAFF' AND status = ? FOR UPDATE");
            $stmt->execute([$id, Auth::STATUS_PENDING]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('注册申请不存在或已处理');
            }
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'STAFF' AND status = ?");
            $stmt->execute([Auth::STATUS_ACTIVE, $id, Auth::STATUS_PENDING]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('审核失败，请刷新后重试');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function rejectRegistration(PDO $pdo, int $id, string $reason = ''): void
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'STAFF' AND status = ? FOR UPDATE");
            $stmt->execute([$id, Auth::STATUS_PENDING]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('注册申请不存在或已处理');
            }
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'STAFF' AND status = ?");
            $stmt->execute([Auth::STATUS_DISABLED, $id, Auth::STATUS_PENDING]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('拒绝失败，请刷新后重试');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getStaffById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'STAFF'");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function createStaff(PDO $pdo, array $data, ?array $photoFile = null): int
    {
        $username = trim($data['username'] ?? '');
        $id = self::createUser($pdo, $data, 'STAFF');
        self::updateStaffProfile($pdo, $id, $data, $photoFile, $username);
        return $id;
    }

    /** 打手自助注册（公开接口，固定 STAFF 角色） */
    public static function registerStaff(PDO $pdo, array $data, ?array $photoFile = null): int
    {
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $confirm = $data['password_confirm'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            throw new InvalidArgumentException('用户名需为3-50位字母、数字或下划线');
        }
        if ($password !== $confirm) {
            throw new InvalidArgumentException('两次输入的密码不一致');
        }

        $id = self::createUser($pdo, [
            'username' => $username,
            'password' => $password,
            'nickname' => trim($data['nickname'] ?? ''),
        ], 'STAFF', Auth::STATUS_PENDING);

        if ($photoFile !== null && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            require_once __DIR__ . '/StaffPhotoService.php';
            require_once __DIR__ . '/PermissionService.php';
            if (PermissionService::isRbacReady($pdo)) {
                try {
                    StaffPhotoService::uploadMany($pdo, $id, $id, $photoFile, $username);
                } catch (Throwable) {
                    self::saveStaffPhoto($pdo, $id, $username, $photoFile);
                }
            } else {
                self::saveStaffPhoto($pdo, $id, $username, $photoFile);
            }
        }

        return $id;
    }

    public static function getByUsername(PDO $pdo, string $username): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** 用户自行修改密码 */
    public static function changeOwnPassword(PDO $pdo, int $userId, string $oldPassword, string $newPassword, string $confirm): void
    {
        if (strlen($newPassword) < 6) {
            throw new InvalidArgumentException('新密码至少6位');
        }
        if ($newPassword !== $confirm) {
            throw new InvalidArgumentException('两次输入的新密码不一致');
        }

        $user = self::getById($pdo, $userId);
        if (!$user) {
            throw new RuntimeException('用户不存在');
        }
        if (!password_verify($oldPassword, $user['password'])) {
            throw new InvalidArgumentException('当前密码错误');
        }

        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
    }

    public static function updateStaff(PDO $pdo, int $id, array $data, ?array $photoFile = null): void
    {
        $staff = self::getStaffById($pdo, $id);
        if (!$staff) {
            throw new RuntimeException('打手不存在');
        }

        $nickname = trim($data['nickname'] ?? '');
        $status = isset($data['status']) ? (int) $data['status'] : (int) $staff['status'];

        $stmt = $pdo->prepare('UPDATE users SET nickname = ?, status = ? WHERE id = ? AND role = ?');
        $stmt->execute([$nickname, $status, $id, 'STAFF']);

        self::updateStaffProfile($pdo, $id, $data, $photoFile, $staff['username']);
    }

    public static function resetStaffPassword(PDO $pdo, int $id, string $password): void
    {
        self::resetUserPassword($pdo, $id, 'STAFF', $password);
    }

    public static function deleteStaff(PDO $pdo, int $id): void
    {
        self::deleteUser($pdo, $id, 'STAFF');
    }

    // ─── 员工（客服） ───────────────────────────────────

    public static function createEmployee(PDO $pdo, array $data): int
    {
        $roleIds = $data['role_ids'] ?? [];
        if (!is_array($roleIds)) {
            $roleIds = [];
        }
        $roleIds = array_map('intval', $roleIds);

        require_once __DIR__ . '/PermissionService.php';
        require_once __DIR__ . '/RoleService.php';

        if (PermissionService::isRbacReady($pdo) && $roleIds !== []) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT code FROM roles WHERE id IN ({$placeholders}) AND code IS NOT NULL
                 AND code NOT IN ('STAFF','BOSS','ADMIN') ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute($roleIds);
            $legacy = $stmt->fetchColumn() ?: 'CUSTOMER_SERVICE';
            $data['role_ids'] = $roleIds;
            return self::createUser($pdo, $data, $legacy);
        }

        if (PermissionService::isRbacReady($pdo) && $roleIds === []) {
            throw new InvalidArgumentException('请至少勾选一个角色');
        }

        $role = $data['role'] ?? 'CUSTOMER_SERVICE';
        if (!in_array($role, ['CUSTOMER_SERVICE', 'EXAMINER'], true)) {
            throw new InvalidArgumentException('无效的员工角色');
        }
        return self::createUser($pdo, $data, $role);
    }

    public static function updateEmployee(PDO $pdo, int $id, array $data): void
    {
        $user = self::getEmployeeById($pdo, $id);
        if (!$user) {
            // 兼容：多角色用户可能 legacy role 不是 CS/EXAMINER
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) {
                throw new RuntimeException('员工不存在');
            }
        }
        self::updateUser($pdo, $id, $user['role'], $data);

        require_once __DIR__ . '/PermissionService.php';
        require_once __DIR__ . '/RoleService.php';
        if (PermissionService::isRbacReady($pdo) && isset($data['role_ids']) && is_array($data['role_ids'])) {
            RoleService::setUserRoles($pdo, $id, $data['role_ids']);
        }
    }

    public static function getEmployeeList(PDO $pdo): array
    {
        require_once __DIR__ . '/PermissionService.php';
        if (PermissionService::isRbacReady($pdo)) {
            try {
                // 含任意非打手角色的用户（含自定义角色如财务）
                $stmt = $pdo->query(
                    "SELECT DISTINCT u.*
                     FROM users u
                     LEFT JOIN user_roles ur ON ur.user_id = u.id
                     LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL AND r.status = 1
                     WHERE u.deleted_at IS NULL
                       AND (
                            u.role IN ('CUSTOMER_SERVICE', 'EXAMINER', 'ADMIN', 'BOSS')
                         OR (r.id IS NOT NULL AND (r.code IS NULL OR r.code = '' OR r.code NOT IN ('STAFF')))
                       )
                       AND NOT (
                            u.role = 'STAFF'
                            AND NOT EXISTS (
                                SELECT 1 FROM user_roles ur2
                                JOIN roles r2 ON r2.id = ur2.role_id
                                WHERE ur2.user_id = u.id
                                  AND r2.deleted_at IS NULL AND r2.status = 1
                                  AND (r2.code IS NULL OR r2.code = '' OR r2.code != 'STAFF')
                            )
                       )
                     ORDER BY u.created_at DESC"
                );
                return $stmt->fetchAll();
            } catch (PDOException) {
                $stmt = $pdo->query(
                    "SELECT DISTINCT u.*
                     FROM users u
                     LEFT JOIN user_roles ur ON ur.user_id = u.id
                     LEFT JOIN roles r ON r.id = ur.role_id
                     WHERE u.role IN ('CUSTOMER_SERVICE', 'EXAMINER', 'ADMIN', 'BOSS')
                        OR (r.id IS NOT NULL AND (r.code IS NULL OR r.code = '' OR r.code != 'STAFF'))
                     ORDER BY u.created_at DESC"
                );
                return $stmt->fetchAll();
            }
        }
        $stmt = $pdo->query(
            "SELECT * FROM users WHERE role IN ('CUSTOMER_SERVICE', 'EXAMINER') ORDER BY created_at DESC"
        );
        return $stmt->fetchAll();
    }

    public static function getEmployeeById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function resetEmployeePassword(PDO $pdo, int $id, string $password): void
    {
        $user = self::getEmployeeById($pdo, $id);
        if (!$user) {
            throw new RuntimeException('员工不存在');
        }
        self::resetUserPassword($pdo, $id, $user['role'], $password);
    }

    public static function deleteEmployee(PDO $pdo, int $id): void
    {
        $user = self::getEmployeeById($pdo, $id);
        if (!$user) {
            throw new RuntimeException('员工不存在');
        }
        self::deleteUser($pdo, $id, $user['role']);
    }

    /** @deprecated */
    public static function resetPassword(PDO $pdo, int $id, string $password): void
    {
        self::resetStaffPassword($pdo, $id, $password);
    }

    public static function getStaffStats(PDO $pdo, int $staffId): array
    {
        require_once __DIR__ . '/BalanceService.php';

        $balance = BalanceService::getBalanceSummary($pdo, $staffId);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE staff_id = ?');
        $stmt->execute([$staffId]);
        $orderCount = (int) $stmt->fetchColumn();

        return array_merge($balance, ['order_count' => $orderCount]);
    }

    /** 保存打手毛照 */
    public static function saveStaffPhoto(PDO $pdo, int $staffId, string $username, array $photoFile): void
    {
        require_once __DIR__ . '/StaffPhotoStorage.php';
        $storage = new StaffPhotoStorage();
        $photoKey = $storage->uploadPhoto($photoFile, $username);

        $stmt = $pdo->prepare("UPDATE users SET photo_key = ?, photo_uploaded = 1 WHERE id = ? AND role = 'STAFF'");
        $stmt->execute([$photoKey, $staffId]);
    }

    private static function updateStaffProfile(PDO $pdo, int $id, array $data, ?array $photoFile, string $username): void
    {
        $hiredAt = trim($data['hired_at'] ?? '');
        $hiredAt = $hiredAt !== '' ? $hiredAt : null;
        $examiner = trim($data['examiner'] ?? '');
        $examiner = $examiner !== '' ? $examiner : null;
        $deposit = trim($data['deposit'] ?? '');
        $deposit = $deposit !== '' ? $deposit : null;

        $stmt = $pdo->prepare(
            "UPDATE users SET hired_at = ?, examiner = ?, deposit = ? WHERE id = ? AND role = 'STAFF'"
        );
        $stmt->execute([$hiredAt, $examiner, $deposit, $id]);

        if ($photoFile !== null && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            self::saveStaffPhoto($pdo, $id, $username, $photoFile);
        }
    }

    // ─── 通用 ───────────────────────────────────────────

    private static function createUser(PDO $pdo, array $data, string $role, int $status = 1): int
    {
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');
        $nickname = trim($data['nickname'] ?? '');

        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('用户名和密码不能为空');
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('密码至少6位');
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('用户名已存在');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password, nickname, role, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $nickname ?: $username, $role, $status]);
        $userId = (int) $pdo->lastInsertId();

        require_once __DIR__ . '/RoleService.php';
        require_once __DIR__ . '/PermissionService.php';
        if (PermissionService::isRbacReady($pdo)) {
            RoleService::ensureUserHasLegacyRole($pdo, $userId, $role);
            if (!empty($data['role_ids']) && is_array($data['role_ids'])) {
                RoleService::setUserRoles($pdo, $userId, $data['role_ids']);
            }
        }

        return $userId;
    }

    private static function updateUser(PDO $pdo, int $id, string $role, array $data): void
    {
        $nickname = trim($data['nickname'] ?? '');
        $status = isset($data['status']) ? (int) $data['status'] : 1;

        $stmt = $pdo->prepare('UPDATE users SET nickname = ?, status = ? WHERE id = ? AND role = ?');
        $stmt->execute([$nickname, $status, $id, $role]);
    }

    private static function resetUserPassword(PDO $pdo, int $id, string $role, string $password): void
    {
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('密码至少6位');
        }
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ? AND role = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id, $role]);
    }

    private static function deleteUser(PDO $pdo, int $id, string $role): void
    {
        // 禁用账号（保留历史订单关联）
        $stmt = $pdo->prepare('UPDATE users SET status = 0 WHERE id = ? AND role = ?');
        $stmt->execute([$id, $role]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('用户不存在或无法删除');
        }
    }
}
