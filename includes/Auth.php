<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

class Auth
{
    /** 账号状态 */
    public const STATUS_DISABLED = 0;
    public const STATUS_ACTIVE   = 1;
    public const STATUS_PENDING  = 2;

    /** 后台可登录角色 */
    private const ADMIN_ROLES = ['CUSTOMER_SERVICE', 'EXAMINER', 'BOSS', 'ADMIN'];

    /** 老板/超管，拥有全部权限 */
    private const BOSS_ROLES = ['BOSS', 'ADMIN'];

    /** 页面权限矩阵 */
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
    ];

    public const LOGIN_URL = '/login.php';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(array $user): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'       => (int) $user['id'],
            'username' => $user['username'],
            'nickname' => $user['nickname'],
            'role'     => $user['role'],
        ];
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
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
        return $user['role'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isStaff(): bool
    {
        $user = self::user();
        return $user && $user['role'] === 'STAFF';
    }

    public static function canAccessAdmin(): bool
    {
        $user = self::user();
        return $user && in_array($user['role'], self::ADMIN_ROLES, true);
    }

    public static function isBoss(): bool
    {
        $user = self::user();
        return $user && in_array($user['role'], self::BOSS_ROLES, true);
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
        $role = self::role();
        if (!$role) {
            return false;
        }
        $allowed = self::PAGE_ACCESS[$page] ?? [];
        return in_array($role, $allowed, true);
    }

    public static function adminHomeUrl(): string
    {
        return match (self::role()) {
            'CUSTOMER_SERVICE' => '/admin/withdrawals.php',
            'EXAMINER'         => '/admin/registrations.php',
            default            => '/admin/index.php',
        };
    }

    /** 登录后按角色跳转 */
    public static function redirectHome(): void
    {
        if (self::isStaff()) {
            redirect('/staff/index.php');
        }
        if (self::canAccessAdmin()) {
            redirect(self::adminHomeUrl());
        }
        redirect(self::LOGIN_URL);
    }

    public static function requireStaff(): void
    {
        if (!self::check()) {
            redirect(self::LOGIN_URL);
        }
        if (!self::isStaff()) {
            redirect(self::adminHomeUrl());
        }
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
        if (!self::canAccessPage($page)) {
            flash('error', '无权限访问该页面');
            redirect(self::adminHomeUrl());
        }
    }

    public static function requireBoss(): void
    {
        if (!self::check()) {
            redirect(self::LOGIN_URL);
        }
        if (!self::isBoss()) {
            flash('error', '无权限访问，仅老板可操作');
            redirect(self::adminHomeUrl());
        }
    }

    /** 统一登录，返回 user 或错误信息 */
    public static function attemptLogin(string $username, string $password): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
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

    /** @deprecated 使用 attemptLogin() */
    public static function attempt(string $username, string $password): ?array
    {
        $result = self::attemptLogin($username, $password);
        return $result['user'];
    }
}
