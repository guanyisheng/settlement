<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/PermissionCatalog.php';
require_once __DIR__ . '/PermissionService.php';

class Auth
{
    public const STATUS_DISABLED = 0;
    public const STATUS_ACTIVE   = 1;
    public const STATUS_PENDING  = 2;

    /** 可进后台的内置角色（兼容旧字段；真正权限看 permissions 合集） */
    private const ADMIN_ROLES = ['CUSTOMER_SERVICE', 'EXAMINER', 'BOSS', 'ADMIN'];

    /** 旧页面矩阵（RBAC 未就绪时回退）；ADMIN 视同 BOSS */
    private const PAGE_ACCESS = [
        'dashboard'      => ['BOSS', 'ADMIN'],
        'orders'         => ['BOSS', 'ADMIN'],
        'withdrawals'    => ['BOSS', 'ADMIN', 'CUSTOMER_SERVICE'],
        'password'       => ['BOSS', 'ADMIN', 'CUSTOMER_SERVICE', 'EXAMINER'],
        'statistics'     => ['BOSS', 'ADMIN'],
        'registrations'  => ['BOSS', 'ADMIN', 'CUSTOMER_SERVICE', 'EXAMINER'],
        'staff'          => ['BOSS', 'ADMIN', 'CUSTOMER_SERVICE', 'EXAMINER'],
        'employees'      => ['BOSS', 'ADMIN'],
        'customers'      => ['BOSS', 'ADMIN', 'CUSTOMER_SERVICE'],
        'business_types' => ['BOSS', 'ADMIN', 'CUSTOMER_SERVICE'],
        'settings'       => ['BOSS', 'ADMIN'],
        'roles'          => ['BOSS', 'ADMIN'],
        'rates'          => ['BOSS', 'ADMIN'],
        'report'         => ['BOSS', 'ADMIN', 'CUSTOMER_SERVICE', 'EXAMINER', 'STAFF'],
        'honors'         => ['BOSS', 'ADMIN', 'CUSTOMER_SERVICE', 'EXAMINER'],
    ];

    public const LOGIN_URL = '/login.php';

    /** 勾选「记住登录」后的会话保留时长（秒） */
    public const REMEMBER_SECONDS = 2592000; // 30 天

    private const REMEMBER_COOKIE = 'settlement_remember';

    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $remember = !empty($_COOKIE[self::REMEMBER_COOKIE]);
        $lifetime = $remember ? self::REMEMBER_SECONDS : 0;
        // 服务端会话文件至少保留 30 天，避免 cookie 还在、session 已被 GC
        ini_set('session.gc_maxlifetime', (string) self::REMEMBER_SECONDS);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => self::cookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // 已登录且勾选过记住：滑动续期
        if (!empty($_SESSION['user']) && $remember) {
            self::applyRememberCookies(true);
        }
    }

    public static function login(array $user, bool $remember = false): void
    {
        self::startSession();
        session_regenerate_id(true);

        $legacyRole = (string) $user['role'];
        if ($legacyRole === 'ADMIN') {
            $legacyRole = 'BOSS';
        }

        $roles = [];
        $permissions = [];
        try {
            require_once __DIR__ . '/Database.php';
            $pdo = Database::getConnection();
            if (PermissionService::isRbacReady($pdo)) {
                require_once __DIR__ . '/RoleService.php';
                RoleService::ensureUserHasLegacyRole($pdo, (int) $user['id'], $legacyRole);
                // 历史 ADMIN → BOSS
                if ((string) $user['role'] === 'ADMIN') {
                    $pdo->prepare("UPDATE users SET role = 'BOSS' WHERE id = ?")->execute([(int) $user['id']]);
                    RoleService::ensureUserHasLegacyRole($pdo, (int) $user['id'], 'BOSS');
                }
                $roles = PermissionService::getUserRoles($pdo, (int) $user['id']);
                $permissions = PermissionService::getPermissionCodes($pdo, (int) $user['id']);
            }
        } catch (Throwable) {
        }

        $_SESSION['user'] = [
            'id'          => (int) $user['id'],
            'username'    => $user['username'],
            'nickname'    => $user['nickname'],
            'role'        => $legacyRole,
            'roles'       => $roles,
            'permissions' => $permissions,
        ];
        $_SESSION['remember'] = $remember;
        self::applyRememberCookies($remember);
    }

    /** 资料变更后刷新会话中的展示信息 */
    public static function refreshSessionUser(PDO $pdo, int $userId): void
    {
        self::startSession();
        if (!isset($_SESSION['user']) || (int) ($_SESSION['user']['id'] ?? 0) !== $userId) {
            return;
        }
        $stmt = $pdo->prepare('SELECT id, username, nickname, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }
        $_SESSION['user']['username'] = $row['username'];
        $_SESSION['user']['nickname'] = $row['nickname'];
        $role = (string) $row['role'];
        if ($role === 'ADMIN') {
            $role = 'BOSS';
        }
        $_SESSION['user']['role'] = $role;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        self::applyRememberCookies(false);
        if (ini_get('session.use_cookies')) {
            $secure = self::cookieSecure();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    private static function cookieSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        return (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /** 同步会话 Cookie 与记住标记 Cookie */
    private static function applyRememberCookies(bool $remember): void
    {
        $secure = self::cookieSecure();
        $expires = $remember ? time() + self::REMEMBER_SECONDS : time() - 42000;

        setcookie(self::REMEMBER_COOKIE, $remember ? '1' : '', [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // expires=0 表示浏览器会话级 Cookie（关浏览器即失效）
        setcookie(session_name(), session_id(), [
            'expires'  => $remember ? $expires : 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function user(): ?array
    {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        $role = $user['role'] ?? null;
        return $role === 'ADMIN' ? 'BOSS' : $role;
    }

    /** @return array{id:int,name:string,code:?string}[] */
    public static function roles(): array
    {
        return self::user()['roles'] ?? [];
    }

    /** 顶栏展示：客服&考官 */
    public static function roleDisplay(): string
    {
        $roles = self::roles();
        if ($roles !== []) {
            return implode('&', array_map(static fn($r) => $r['name'], $roles));
        }
        return roleLabel(self::role() ?? '');
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }
        $user = self::user();
        // session 有权限合集时先用；空数组视为「未灌入」，继续查库/回退
        if (!empty($user['permissions']) && is_array($user['permissions'])) {
            if (in_array($permission, $user['permissions'], true)) {
                return true;
            }
            // session 有合集但未命中：仍允许 legacy 回退（迁移期角色权限未挂全）
            return self::legacyCan($permission);
        }
        try {
            $pdo = Database::getConnection();
            if (PermissionService::isRbacReady($pdo) && self::id()) {
                if (PermissionService::userCan($pdo, (int) self::id(), $permission)) {
                    return true;
                }
            }
        } catch (Throwable) {
        }
        return self::legacyCan($permission);
    }

    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::can($p)) {
                return true;
            }
        }
        return false;
    }

    public static function requirePermission(string $permission): void
    {
        if (!self::check()) {
            redirect(self::LOGIN_URL);
        }
        if (!self::can($permission)) {
            flash('error', '无权限执行此操作');
            redirect(self::homeUrl());
        }
    }

    public static function isStaff(): bool
    {
        if (!self::check()) {
            return false;
        }
        // 纯打手：只有 STAFF 角色，或旧字段为 STAFF 且无后台权限
        $roles = self::roles();
        if ($roles !== []) {
            foreach ($roles as $r) {
                if (($r['code'] ?? '') !== 'STAFF') {
                    return false;
                }
            }
            return true;
        }
        return self::role() === 'STAFF';
    }

    public static function canAccessAdmin(): bool
    {
        if (!self::check()) {
            return false;
        }
        // 纯打手只进手机端工作台，禁止落到 PC 管理后台
        if (self::isStaff()) {
            return false;
        }
        try {
            $pdo = Database::getConnection();
            if (PermissionService::isRbacReady($pdo) && self::id()) {
                if (PermissionService::canAccessAdminPortal($pdo, (int) self::id())) {
                    return true;
                }
            }
        } catch (Throwable) {
        }
        // RBAC 未就绪 / 权限未挂全时，按旧角色字段放行后台
        if (in_array(self::role(), self::ADMIN_ROLES, true)) {
            return true;
        }
        foreach (self::roles() as $r) {
            $code = (string) ($r['code'] ?? '');
            if ($code === 'ADMIN') {
                $code = 'BOSS';
            }
            if (in_array($code, self::ADMIN_ROLES, true)) {
                return true;
            }
        }
        return false;
    }

    public static function isBoss(): bool
    {
        foreach (self::roles() as $r) {
            if (($r['code'] ?? '') === 'BOSS') {
                return true;
            }
        }
        return self::role() === 'BOSS' || self::can('settings.manage');
    }

    public static function isCustomerService(): bool
    {
        return self::role() === 'CUSTOMER_SERVICE';
    }

    public static function isExaminer(): bool
    {
        return self::role() === 'EXAMINER';
    }

    public static function canAccessPage(string $page): bool
    {
        // 改密：已登录且可进后台即可（不依赖 password.change，避免互踢）
        if ($page === 'password') {
            return self::canAccessAdmin();
        }

        $needed = PermissionCatalog::MENU_PERMISSIONS[$page] ?? null;
        if ($needed !== null) {
            try {
                $pdo = Database::getConnection();
                if (PermissionService::isRbacReady($pdo) && self::id()) {
                    if (PermissionService::userCanAny($pdo, (int) self::id(), $needed)) {
                        return true;
                    }
                }
            } catch (Throwable) {
            }
            if (self::canAny($needed)) {
                return true;
            }
        }

        $allowed = self::PAGE_ACCESS[$page] ?? [];
        if ($allowed === []) {
            return false;
        }
        $role = self::role();
        if ($role && in_array($role, $allowed, true)) {
            return true;
        }
        foreach (self::roles() as $r) {
            $code = (string) ($r['code'] ?? '');
            if ($code === 'ADMIN') {
                $code = 'BOSS';
            }
            if ($code !== '' && in_array($code, $allowed, true)) {
                return true;
            }
        }
        return false;
    }

    public static function homeUrl(): string
    {
        if (self::canAccessAdmin()) {
            return self::adminHomeUrl();
        }
        if (self::check()) {
            return '/staff/index.php';
        }
        return self::LOGIN_URL;
    }

    public static function adminHomeUrl(): string
    {
        $candidates = [
            'dashboard'     => '/admin/index.php',
            'orders'        => '/admin/orders.php',
            'withdrawals'   => '/admin/withdrawals.php',
            'registrations' => '/admin/registrations.php',
            'staff'         => '/admin/staff.php',
            'customers'     => '/admin/customers.php',
            'employees'     => '/admin/employees.php',
            'statistics'    => '/admin/statistics.php',
            'roles'         => '/admin/roles.php',
            'rates'         => '/admin/rates.php',
            'settings'      => '/admin/settings.php',
            'password'      => '/admin/password.php',
        ];
        foreach ($candidates as $page => $url) {
            if (self::canAccessPage($page)) {
                return $url;
            }
        }
        // 后台进不去时落到打手端，避免 password↔首页死循环
        return '/staff/index.php';
    }

    /** 登录后直接进：有后台权限 → 后台；否则打手端。双角色合集权限已在 session */
    public static function redirectHome(): void
    {
        if (self::canAccessAdmin()) {
            redirect(self::adminHomeUrl());
        }
        if (self::check()) {
            redirect('/staff/index.php');
        }
        redirect(self::LOGIN_URL);
    }

    public static function requireStaff(): void
    {
        if (!self::check()) {
            redirect(self::LOGIN_URL);
        }
        // 打手端：本人订单/报单；双角色后台用户也可进打手端看自己的报单
        if (self::isStaff() || self::can('report.create') || self::can('order.view')) {
            return;
        }
        // 无打手能力时再回后台；若后台也无处可去则留在打手端，避免互踢
        if (self::canAccessAdmin()) {
            $home = self::adminHomeUrl();
            if ($home !== '/staff/index.php') {
                redirect($home);
            }
        }
    }

    /** 报单权限（报单=报备，同一权限） */
    public static function requireReport(): void
    {
        if (!self::check()) {
            redirect(self::LOGIN_URL);
        }
        if (self::can('report.create') || self::role() === 'STAFF') {
            return;
        }
        flash('error', '无报单权限');
        redirect(self::homeUrl());
    }

    public static function requireAdminAccess(): void
    {
        if (!self::check()) {
            redirect(self::LOGIN_URL);
        }
        if (!self::canAccessAdmin()) {
            redirect('/staff/index.php');
        }
    }

    public static function requirePage(string $page): void
    {
        self::requireAdminAccess();
        if (self::canAccessPage($page)) {
            return;
        }
        flash('error', '无权限访问该页面');
        $home = self::adminHomeUrl();
        // 避免跳回当前页造成重定向循环
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if ($home === $current || ($page === 'password' && str_ends_with($home, '/password.php'))) {
            redirect('/staff/index.php');
        }
        redirect($home);
    }

    public static function requireBoss(): void
    {
        if (!self::check()) {
            redirect(self::LOGIN_URL);
        }
        if (!self::isBoss()) {
            flash('error', '无权限，仅老板可操作');
            redirect(self::adminHomeUrl());
        }
    }

    public static function attemptLogin(string $username, string $password): array
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$username]);
        } catch (PDOException) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
        }
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return ['user' => null, 'error' => '用户名或密码错误'];
        }
        if ((int) $user['status'] === self::STATUS_PENDING) {
            return ['user' => null, 'error' => '账号待老板审核，请耐心等待'];
        }
        if ((int) $user['status'] !== self::STATUS_ACTIVE) {
            return ['user' => null, 'error' => '账号已禁用，请联系管理员'];
        }

        return ['user' => $user, 'error' => null];
    }

    /** @deprecated */
    public static function attempt(string $username, string $password): ?array
    {
        return self::attemptLogin($username, $password)['user'];
    }

    private static function legacyCan(string $permission): bool
    {
        $role = self::role();
        if (!$role) {
            return false;
        }
        if ($role === 'BOSS' || $role === 'ADMIN') {
            return true;
        }
        $map = [
            'CUSTOMER_SERVICE' => [
                'dashboard.view', 'report.create', 'order.view', 'order.review',
                'withdrawal.view', 'withdrawal.process', 'registration.review',
                'staff.view', 'photo.view', 'honor.view',
                'customer.view', 'customer.manage', 'business.view', 'business.manage',
                'password.change',
            ],
            'EXAMINER' => [
                'dashboard.view', 'report.create', 'registration.review',
                'staff.view', 'staff.manage', 'photo.view', 'photo.download',
                'honor.view', 'honor.manage', 'order.view', 'stats.view', 'password.change',
            ],
            'STAFF' => [
                'dashboard.view', 'report.create', 'order.view', 'withdrawal.view',
                'photo.view', 'honor.view', 'honor.manage', 'password.change',
            ],
        ];
        return in_array($permission, $map[$role] ?? [], true);
    }
}
