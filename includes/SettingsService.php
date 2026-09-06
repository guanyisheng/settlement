<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

class SettingsService
{
    private static bool $loaded = false;
    /** @var array<string, string> */
    private static array $cache = [];

    private static function defaults(): array
    {
        $settlement = file_exists(__DIR__ . '/../config/settlement.php')
            ? require __DIR__ . '/../config/settlement.php'
            : ['rate_a' => 0.8, 'rate_b' => 0.5];
        $storage = file_exists(__DIR__ . '/../config/storage.php')
            ? require __DIR__ . '/../config/storage.php'
            : ['driver' => 'auto'];
        $cos = file_exists(__DIR__ . '/../config/cos.php')
            ? require __DIR__ . '/../config/cos.php'
            : require __DIR__ . '/../config/cos.example.php';

        return [
            'brand_name'        => '清账系统',
            'brand_logo'        => '/img/logo.png',
            'brand_theme_color' => '#001A72',
            'settlement_rate_a' => (string) ($settlement['rate_a'] ?? 0.8),
            'settlement_rate_b' => (string) ($settlement['rate_b'] ?? 0.5),
            'storage_driver'    => (string) ($storage['driver'] ?? 'auto'),
            'cos_secret_id'     => (string) ($cos['secret_id'] ?? ''),
            'cos_secret_key'    => (string) ($cos['secret_key'] ?? ''),
            'cos_region'        => (string) ($cos['region'] ?? 'ap-chengdu'),
            'cos_bucket'        => (string) ($cos['bucket'] ?? ''),
            'cos_prefix_orders' => (string) ($cos['prefix'] ?? 'orders/'),
            'cos_prefix_staff'  => 'staff/',
        ];
    }

    public static function load(PDO $pdo): void
    {
        if (self::$loaded) {
            return;
        }
        self::$cache = self::defaults();
        try {
            $stmt = $pdo->query('SELECT setting_key, setting_value FROM system_settings');
            while ($row = $stmt->fetch()) {
                if ($row['setting_value'] !== '') {
                    self::$cache[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (PDOException) {
            // 表未创建时使用文件默认配置
        }
        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): string
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $defaults = self::defaults();
        return $defaults[$key] ?? ($default ?? '');
    }

    public static function getAll(): array
    {
        return array_merge(self::defaults(), self::$cache);
    }

    public static function set(PDO $pdo, string $key, string $value): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([$key, $value]);
        self::$cache[$key] = $value;
        self::$loaded = true;
    }

    public static function reload(PDO $pdo): void
    {
        self::$loaded = false;
        self::$cache = [];
        self::load($pdo);
    }

    /** @param array<string, string> $pairs */
    public static function setMany(PDO $pdo, array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            self::set($pdo, $key, (string) $value);
        }
    }

    public static function getSettlementRates(): array
    {
        return [
            'rate_a' => (float) self::get('settlement_rate_a', '0.8'),
            'rate_b' => (float) self::get('settlement_rate_b', '0.5'),
        ];
    }

    public static function getCosConfig(): array
    {
        return [
            'secret_id'  => self::get('cos_secret_id'),
            'secret_key' => self::get('cos_secret_key'),
            'region'     => self::get('cos_region', 'ap-chengdu'),
            'bucket'     => self::get('cos_bucket'),
            'prefix'     => self::get('cos_prefix_orders', 'orders/'),
            'app_id'     => '',
        ];
    }

    public static function getStorageDriver(): string
    {
        return self::get('storage_driver', 'auto');
    }

    public static function shouldUseLocalStorage(): bool
    {
        $driver = self::getStorageDriver();
        if ($driver === 'local') {
            return true;
        }
        if ($driver === 'cos') {
            return false;
        }
        $key = trim(self::get('cos_secret_key'));
        if ($key === '' || $key === 'YOUR_SECRET_KEY' || str_starts_with($key, 'ENCv1:')) {
            return true;
        }
        return false;
    }

    public static function seedDefaults(PDO $pdo): void
    {
        foreach (self::defaults() as $key => $value) {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)'
            );
            $stmt->execute([$key, $value]);
        }
        self::$loaded = false;
        self::load($pdo);
    }
}
