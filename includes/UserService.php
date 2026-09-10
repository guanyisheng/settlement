<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/helpers.php';

class UserService
{
    // ─── 打手 ───────────────────────────────────────────

    public static function getStaffList(PDO $pdo, ?string $keyword = null): array
    {
        $keyword = trim((string) $keyword);
        $params = [];
        $searchSql = '';
        $order = 'ORDER BY u.status DESC, u.created_at DESC';
        $pending = Auth::STATUS_PENDING;

        if ($keyword !== '') {
            $searchSql = ' AND (u.username LIKE ? OR u.nickname LIKE ? OR IFNULL(u.examiner, \'\') LIKE ? OR CAST(u.id AS CHAR) = ?)';
            $like = '%' . $keyword . '%';
            $params = [$like, $like, $like, $keyword];
        }

        require_once __DIR__ . '/PermissionService.php';
        if (PermissionService::isRbacReady($pdo)) {
            // 含「考官+打手」等：legacy role 不一定是 STAFF，但 user_roles 里有打手
            try {
                $sql = "SELECT DISTINCT u.*
                        FROM users u
                        LEFT JOIN user_roles ur ON ur.user_id = u.id
                        LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL AND r.status = 1
                        WHERE u.deleted_at IS NULL
                          AND u.status != {$pending}
                          AND (u.role = 'STAFF' OR r.code = 'STAFF')
                          {$searchSql}
                        {$order}";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            } catch (PDOException) {
                // fall through
            }
        }

        try {
            $sql = "SELECT u.* FROM users u
                    WHERE u.role = 'STAFF' AND u.status != {$pending} AND u.deleted_at IS NULL
                    {$searchSql} {$order}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException) {
            $sql = "SELECT u.* FROM users u
                    WHERE u.role = 'STAFF' AND u.status != {$pending}
                    {$searchSql} {$order}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        }
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

            require_once __DIR__ . '/RoleService.php';
            require_once __DIR__ . '/PermissionService.php';
            if (PermissionService::isRbacReady($pdo)) {
                RoleService::ensureUserHasLegacyRole($pdo, $id, 'STAFF');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function rejectRegistration(PDO $pdo, int $id, string $reason = ''): void
    {
        // 打回 = 直接删除账号（待审核无历史单，可物理删除）
        self::purgeUser($pdo, $id, true);
    }

    /**
     * 物理删除用户及相关附属数据。
     * @param bool $pendingOnly 仅允许删除待审核打手
     */
    public static function purgeUser(PDO $pdo, int $id, bool $pendingOnly = false): void
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) {
                throw new RuntimeException('用户不存在');
            }
            if ($pendingOnly) {
                if ($user['role'] !== 'STAFF' || (int) $user['status'] !== Auth::STATUS_PENDING) {
                    throw new RuntimeException('只能打回待审核的注册申请');
                }
            }

            // 有正式订单/提现时不能硬删（外键），改为隐藏并释放用户名
            $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE staff_id = ?');
            $cntStmt->execute([$id]);
            $orderCnt = (int) $cntStmt->fetchColumn();
            $wStmt = $pdo->prepare('SELECT COUNT(*) FROM withdrawals WHERE staff_id = ?');
            $wStmt->execute([$id]);
            $wCnt = (int) $wStmt->fetchColumn();

            if ($orderCnt > 0 || $wCnt > 0) {
                $newName = 'deleted_' . $id . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
                try {
                    $pdo->prepare(
                        'UPDATE users SET status = 0, deleted_at = NOW(), username = ?, nickname = CONCAT(nickname, "(已删)") WHERE id = ?'
                    )->execute([$newName, $id]);
                } catch (PDOException) {
                    $pdo->prepare('UPDATE users SET status = 0, username = ?, nickname = CONCAT(IFNULL(nickname,""), "(已删)") WHERE id = ?')
                        ->execute([$newName, $id]);
                }
            } else {
                try {
                    $pdo->prepare('DELETE FROM staff_honor_images WHERE honor_id IN (SELECT id FROM staff_honors WHERE staff_id = ?)')->execute([$id]);
                } catch (PDOException) {
                }
                try {
                    $pdo->prepare('DELETE FROM staff_honors WHERE staff_id = ?')->execute([$id]);
                } catch (PDOException) {
                }
                try {
                    $pdo->prepare('DELETE FROM staff_photos WHERE staff_id = ?')->execute([$id]);
                } catch (PDOException) {
                }
                try {
                    $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$id]);
                } catch (PDOException) {
                }
                // 若被其他表引用为 reviewed_by 等，先清空
                try {
                    $pdo->prepare('UPDATE orders SET reviewed_by = NULL WHERE reviewed_by = ?')->execute([$id]);
                } catch (PDOException) {
                }
                try {
                    $pdo->prepare('UPDATE withdrawals SET processed_by = NULL WHERE processed_by = ?')->execute([$id]);
                } catch (PDOException) {
                }
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getStaffById(PDO $pdo, int $id): ?array
    {
        $user = self::getById($pdo, $id);
        if (!$user || !empty($user['deleted_at'])) {
            return null;
        }
        // 多角色：legacy 可能是考官/客服，但 RBAC 仍带打手
        if (!self::userIsStaffLike($pdo, $user)) {
            return null;
        }
        return $user;
    }

    public static function createStaff(PDO $pdo, array $data, ?array $photoFile = null): int
    {
        $username = trim($data['username'] ?? '');
        $id = self::createUser($pdo, $data, 'STAFF');
        self::updateStaffProfile($pdo, $id, $data, $photoFile, $username);
        return $id;
    }

    /** 打手自助注册（公开接口，固定 STAFF 角色；毛照选填） */
    public static function registerStaff(PDO $pdo, array $data, ?array $photoFile = null): int
    {
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $confirm = $data['password_confirm'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            throw new InvalidArgumentException('用户名需为3-50位字母、数字或下划线（不要用中文）');
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('密码至少6位');
        }
        if ($password !== $confirm) {
            throw new InvalidArgumentException('两次输入的密码不一致');
        }

        $id = self::createUser($pdo, [
            'username' => $username,
            'password' => $password,
            'nickname' => trim($data['nickname'] ?? ''),
        ], 'STAFF', Auth::STATUS_PENDING);

        // 毛照选填：有实际上传文件才处理；上传失败不阻断注册
        $files = normalizeUploadedFiles($photoFile);
        if ($files !== []) {
            try {
                require_once __DIR__ . '/StaffPhotoService.php';
                require_once __DIR__ . '/PermissionService.php';
                if (PermissionService::isRbacReady($pdo)) {
                    try {
                        StaffPhotoService::uploadMany($pdo, $id, $id, $photoFile, $username);
                    } catch (Throwable $e) {
                        // staff_photos 表可能未建：回退单张旧逻辑
                        if (count($files) === 1) {
                            self::saveStaffPhoto($pdo, $id, $username, $files[0]);
                        } else {
                            foreach ($files as $f) {
                                self::saveStaffPhoto($pdo, $id, $username, $f);
                            }
                        }
                    }
                } else {
                    self::saveStaffPhoto($pdo, $id, $username, $files[0]);
                }
            } catch (Throwable $e) {
                // 账号已创建，毛照失败只记日志不回滚，避免「选了图却注册失败」或 COS 故障导致无法注册
                error_log('[registerStaff] photo upload failed for user #' . $id . ': ' . $e->getMessage());
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

    /**
     * 注册前检测用户名是否可用（格式 + 是否已被占用）
     * @return array{available:bool,message:string}
     */
    public static function checkUsername(string $username): array
    {
        $username = trim($username);
        if ($username === '') {
            return ['available' => false, 'message' => '请输入用户名'];
        }
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            return ['available' => false, 'message' => '须为 3–50 位英文、数字或下划线，不能用中文'];
        }

        $pdo = Database::getConnection();
        if (self::getByUsername($pdo, $username)) {
            return ['available' => false, 'message' => '用户名已存在，请换一个'];
        }

        return ['available' => true, 'message' => '用户名可用'];
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

    /**
     * 打手端「修改信息」：昵称、毛照、收款二维码、可选改密
     * 凡能进入打手端的账号（老板/客服/打手等）均可改自己的资料
     */
    public static function updateOwnProfile(PDO $pdo, int $userId, array $data, array $files = []): void
    {
        $user = self::getById($pdo, $userId);
        if (!$user || !empty($user['deleted_at'])) {
            throw new RuntimeException('用户不存在');
        }

        $nickname = trim((string) ($data['nickname'] ?? ''));
        if ($nickname === '') {
            throw new InvalidArgumentException('请填写昵称');
        }

        $newPassword = (string) ($data['new_password'] ?? '');
        $confirm = (string) ($data['confirm_password'] ?? '');
        $oldPassword = (string) ($data['old_password'] ?? '');
        if ($newPassword !== '' || $confirm !== '') {
            if ($oldPassword === '') {
                throw new InvalidArgumentException('修改密码请先填写当前密码');
            }
            self::changeOwnPassword($pdo, $userId, $oldPassword, $newPassword, $confirm);
        }

        $pdo->prepare('UPDATE users SET nickname = ? WHERE id = ?')->execute([$nickname, $userId]);

        // 毛照（可多选）
        $photoFiles = $files['photos'] ?? null;
        if ($photoFiles !== null && normalizeUploadedFiles($photoFiles) !== []) {
            require_once __DIR__ . '/StaffPhotoService.php';
            StaffPhotoService::uploadMany($pdo, $userId, $userId, $photoFiles, $user['username']);
        }

        // 收款二维码（单张）
        $qrFile = $files['pay_qr'] ?? null;
        if ($qrFile !== null && ($qrFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            require_once __DIR__ . '/StaffPhotoStorage.php';
            $storage = new StaffPhotoStorage();
            $key = $storage->uploadPhoto($qrFile, $user['username'] . '_payqr');
            try {
                $pdo->prepare('UPDATE users SET pay_qr_key = ? WHERE id = ?')->execute([$key, $userId]);
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'pay_qr_key') || str_contains($e->getMessage(), 'Unknown column')) {
                    throw new RuntimeException('请先执行 database/migrate_pay_qr.sql 增加收款二维码字段');
                }
                throw $e;
            }
        }

        // 荣誉（名称 + 图片都填了才新增一条）
        $honorTitle = trim((string) ($data['honor_title'] ?? ''));
        $honorImages = $files['honor_images'] ?? null;
        $hasHonorImages = $honorImages !== null && normalizeUploadedFiles($honorImages) !== [];
        if ($honorTitle !== '' || $hasHonorImages) {
            if ($honorTitle === '') {
                throw new InvalidArgumentException('添加荣誉请填写荣誉名称');
            }
            if (!$hasHonorImages) {
                throw new InvalidArgumentException('添加荣誉请至少上传一张图片');
            }
            require_once __DIR__ . '/HonorService.php';
            HonorService::create(
                $pdo,
                $userId,
                $userId,
                $honorTitle,
                (string) ($data['honor_remark'] ?? ''),
                $honorImages,
                $user['username']
            );
        }

        require_once __DIR__ . '/Auth.php';
        Auth::refreshSessionUser($pdo, $userId);
    }

    public static function updateStaff(PDO $pdo, int $id, array $data, ?array $photoFile = null): void
    {
        $staff = self::getById($pdo, $id);
        if (!$staff || !empty($staff['deleted_at'])) {
            throw new RuntimeException('用户不存在');
        }

        $nickname = trim($data['nickname'] ?? '');
        $status = isset($data['status']) ? (int) $data['status'] : (int) $staff['status'];

        $stmt = $pdo->prepare('UPDATE users SET nickname = ?, status = ? WHERE id = ?');
        $stmt->execute([$nickname, $status, $id]);

        self::updateStaffProfile($pdo, $id, $data, $photoFile, $staff['username']);
    }

    public static function resetStaffPassword(PDO $pdo, int $id, string $password): void
    {
        self::resetPasswordById($pdo, $id, $password);
    }

    public static function deleteStaff(PDO $pdo, int $id): void
    {
        // 禁用/删除：尽量物理删除；有历史单则隐藏
        self::purgeUser($pdo, $id, false);
    }

    private static function deleteUser(PDO $pdo, int $id, string $role): void
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = ?');
        $stmt->execute([$id, $role]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('用户不存在或无法删除');
        }
        self::purgeUser($pdo, $id, false);
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

    public static function getEmployeeList(PDO $pdo, ?string $keyword = null): array
    {
        $keyword = trim((string) $keyword);
        $likeParams = [];
        $searchSql = '';
        if ($keyword !== '') {
            $searchSql = ' AND (u.username LIKE ? OR u.nickname LIKE ? OR CAST(u.id AS CHAR) = ?)';
            $like = '%' . $keyword . '%';
            $likeParams = [$like, $like, $keyword];
        }
        $order = 'ORDER BY u.status DESC, u.created_at DESC'; // 启用在前，禁用沉底

        require_once __DIR__ . '/PermissionService.php';
        if (PermissionService::isRbacReady($pdo)) {
            try {
                $stmt = $pdo->prepare(
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
                       {$searchSql}
                     {$order}"
                );
                $stmt->execute($likeParams);
                return $stmt->fetchAll();
            } catch (PDOException) {
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT u.*
                     FROM users u
                     LEFT JOIN user_roles ur ON ur.user_id = u.id
                     LEFT JOIN roles r ON r.id = ur.role_id
                     WHERE (
                            u.role IN ('CUSTOMER_SERVICE', 'EXAMINER', 'ADMIN', 'BOSS')
                         OR (r.id IS NOT NULL AND (r.code IS NULL OR r.code = '' OR r.code != 'STAFF'))
                       )
                       {$searchSql}
                     {$order}"
                );
                $stmt->execute($likeParams);
                return $stmt->fetchAll();
            }
        }
        $stmt = $pdo->prepare(
            "SELECT u.* FROM users u
             WHERE u.role IN ('CUSTOMER_SERVICE', 'EXAMINER')
             {$searchSql}
             {$order}"
        );
        $stmt->execute($likeParams);
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
        self::resetPasswordById($pdo, $id, $password);
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
        require_once __DIR__ . '/OrderService.php';

        $balance = BalanceService::getBalanceSummary($pdo, $staffId);

        if (OrderService::hasCoStaffColumn($pdo)) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE staff_id = ? OR co_staff_id = ?');
            $stmt->execute([$staffId, $staffId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE staff_id = ?');
            $stmt->execute([$staffId]);
        }
        $orderCount = (int) $stmt->fetchColumn();

        return array_merge($balance, ['order_count' => $orderCount]);
    }

    /**
     * 统一用户中心：打手 + 员工揉一块
     * @param int|null $roleId 按角色筛选（roles.id）；null=全部
     * @return array<int, array>
     */
    public static function getUnifiedUserList(PDO $pdo, ?string $keyword = null, ?int $roleId = null, ?string $roleCode = null): array
    {
        $keyword = trim((string) $keyword);
        $params = [];
        $where = ['1=1'];

        try {
            $pdo->query('SELECT deleted_at FROM users LIMIT 0');
            $where[] = 'u.deleted_at IS NULL';
        } catch (PDOException) {
        }

        if ($keyword !== '') {
            $where[] = '(u.username LIKE ? OR u.nickname LIKE ? OR CAST(u.id AS CHAR) = ? OR IFNULL(u.examiner, \'\') LIKE ?)';
            $like = '%' . $keyword . '%';
            array_push($params, $like, $like, $keyword, $like);
        }

        require_once __DIR__ . '/PermissionService.php';
        $rbac = PermissionService::isRbacReady($pdo);

        if ($roleId !== null && $roleId > 0) {
            if ($rbac) {
                $where[] = '(EXISTS (
                    SELECT 1 FROM user_roles urf
                    WHERE urf.user_id = u.id AND urf.role_id = ?
                ) OR EXISTS (
                    SELECT 1 FROM roles rf
                    WHERE rf.id = ? AND rf.code = u.role AND rf.deleted_at IS NULL
                ))';
                $params[] = $roleId;
                $params[] = $roleId;
            }
        } elseif ($roleCode !== null && $roleCode !== '') {
            $code = $roleCode === 'ADMIN' ? 'BOSS' : $roleCode;
            $where[] = 'u.role = ?';
            $params[] = $code;
        }

        $sql = 'SELECT u.* FROM users u WHERE ' . implode(' AND ', $where)
            . ' ORDER BY u.status DESC, u.created_at DESC LIMIT 1000';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** 是否打手向（含多角色里带 STAFF） */
    public static function userIsStaffLike(PDO $pdo, array $user): bool
    {
        if (($user['role'] ?? '') === 'STAFF') {
            return true;
        }
        require_once __DIR__ . '/PermissionService.php';
        if (!PermissionService::isRbacReady($pdo)) {
            return false;
        }
        foreach (PermissionService::getUserRoles($pdo, (int) $user['id']) as $r) {
            if (($r['code'] ?? '') === 'STAFF') {
                return true;
            }
        }
        return false;
    }

    /** 保存打手毛照（单张，兼容旧调用） */
    public static function saveStaffPhoto(PDO $pdo, int $staffId, string $username, array $photoFile): void
    {
        require_once __DIR__ . '/StaffPhotoService.php';
        $normalized = normalizeUploadedFiles($photoFile);
        if ($normalized === []) {
            return;
        }
        StaffPhotoService::uploadMany($pdo, $staffId, (int) (Auth::id() ?: $staffId), $photoFile, $username);
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
            'UPDATE users SET hired_at = ?, examiner = ?, deposit = ? WHERE id = ?'
        );
        $stmt->execute([$hiredAt, $examiner, $deposit, $id]);

        $files = normalizeUploadedFiles($photoFile);
        if ($files !== []) {
            require_once __DIR__ . '/StaffPhotoService.php';
            StaffPhotoService::uploadMany(
                $pdo,
                $id,
                (int) (Auth::id() ?: $id),
                $photoFile ?? [],
                $username
            );
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
        $newRole = $role;
        if (isset($data['role']) && is_string($data['role']) && $data['role'] !== '') {
            $candidate = $data['role'] === 'ADMIN' ? 'BOSS' : $data['role'];
            if (in_array($candidate, ['STAFF', 'CUSTOMER_SERVICE', 'EXAMINER', 'BOSS'], true)) {
                $newRole = $candidate;
            }
        }

        $stmt = $pdo->prepare('UPDATE users SET nickname = ?, status = ?, role = ? WHERE id = ?');
        $stmt->execute([$nickname, $status, $newRole, $id]);
    }

    private static function resetUserPassword(PDO $pdo, int $id, string $role, string $password): void
    {
        self::resetPasswordById($pdo, $id, $password);
    }

    private static function resetPasswordById(PDO $pdo, int $id, string $password): void
    {
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('密码至少6位');
        }
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT id FROM users WHERE id = ?');
            $check->execute([$id]);
            if (!$check->fetch()) {
                throw new RuntimeException('用户不存在');
            }
        }
    }
}
