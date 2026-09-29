<?php

declare(strict_types=1);

require_once __DIR__ . '/SettingsService.php';

/**
 * 多活动：积分钱包共用 / 抽奖次数按活动 / 奖品·兑换按活动
 * 任务积分与抽奖次数均由后台人工发放。
 */
class ActivityService
{
    private static ?bool $hasCampaigns = null;

    public static function isReady(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM activity_wallets LIMIT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * 自动补齐多活动表/字段（未跑完一键注入时也能新建活动）
     */
    public static function ensureCampaignSchema(PDO $pdo): bool
    {
        if (!self::isReady($pdo)) {
            return false;
        }
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS activities (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL COMMENT '后台显示名',
                    slug VARCHAR(64) DEFAULT NULL,
                    title VARCHAR(120) NOT NULL DEFAULT '',
                    description VARCHAR(255) NOT NULL DEFAULT '',
                    logo_url VARCHAR(512) DEFAULT NULL,
                    status TINYINT(1) NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_activities_slug (slug)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS activity_user_chances (
                    user_id INT UNSIGNED NOT NULL,
                    activity_id INT UNSIGNED NOT NULL,
                    chances INT NOT NULL DEFAULT 0,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (user_id, activity_id),
                    KEY idx_auc_activity (activity_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            self::addColumnIfMissing($pdo, 'activity_lottery_prizes', 'activity_id',
                "INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '所属活动' AFTER id");
            self::addColumnIfMissing($pdo, 'activity_lottery_logs', 'activity_id',
                "INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '所属活动' AFTER id");
            self::addColumnIfMissing($pdo, 'activity_exchange_items', 'activity_id',
                "INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '所属活动' AFTER id");
            self::addColumnIfMissing($pdo, 'activity_exchange_orders', 'activity_id',
                "INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '所属活动' AFTER id");

            $cnt = (int) $pdo->query('SELECT COUNT(*) FROM activities')->fetchColumn();
            if ($cnt === 0) {
                $title = SettingsService::get('activity_share_title', '活动抽奖 · 积分兑换') ?: '默认抽奖';
                $desc = SettingsService::get('activity_share_desc', '登录参与抽奖，积分可兑换好礼') ?: '';
                $enabled = SettingsService::get('activity_enabled', '1') === '1' ? 1 : 0;
                $pdo->prepare(
                    'INSERT INTO activities (id, name, title, description, status, sort_order)
                     VALUES (1, ?, ?, ?, ?, 10)'
                )->execute(['默认抽奖', $title, $desc, $enabled]);
                try {
                    $pdo->exec('UPDATE activity_lottery_prizes SET activity_id = 1 WHERE activity_id IS NULL OR activity_id = 0');
                    $pdo->exec('UPDATE activity_exchange_items SET activity_id = 1 WHERE activity_id IS NULL OR activity_id = 0');
                } catch (PDOException) {
                }
                // 旧钱包次数迁到默认活动
                try {
                    $pdo->exec(
                        'INSERT INTO activity_user_chances (user_id, activity_id, chances)
                         SELECT w.user_id, 1, w.lottery_chances
                         FROM activity_wallets w
                         WHERE w.lottery_chances > 0
                           AND NOT EXISTS (
                               SELECT 1 FROM activity_user_chances c
                               WHERE c.user_id = w.user_id AND c.activity_id = 1
                           )'
                    );
                } catch (PDOException) {
                }
            }
            self::$hasCampaigns = true;
            return true;
        } catch (Throwable) {
            self::$hasCampaigns = false;
            return false;
        }
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $pdo->query('SELECT `' . str_replace('`', '', $column) . '` FROM `' . str_replace('`', '', $table) . '` LIMIT 0');
        } catch (PDOException) {
            try {
                $pdo->exec('ALTER TABLE `' . str_replace('`', '', $table) . '` ADD COLUMN `' . str_replace('`', '', $column) . '` ' . $definition);
            } catch (PDOException) {
            }
        }
    }

    public static function hasCampaigns(PDO $pdo): bool
    {
        if (self::$hasCampaigns !== null) {
            return self::$hasCampaigns;
        }
        try {
            $pdo->query('SELECT 1 FROM activities LIMIT 0');
            return self::$hasCampaigns = true;
        } catch (PDOException) {
            return self::$hasCampaigns = false;
        }
    }

    /** @return list<array<string,mixed>> */
    public static function listActivities(PDO $pdo, bool $activeOnly = false): array
    {
        if (!self::hasCampaigns($pdo)) {
            return [self::legacyActivityMeta()];
        }
        $sql = 'SELECT * FROM activities';
        if ($activeOnly) {
            $sql .= ' WHERE status = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getActivity(PDO $pdo, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        if (!self::hasCampaigns($pdo)) {
            return $id === 1 ? self::legacyActivityMeta() : null;
        }
        $st = $pdo->prepare('SELECT * FROM activities WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getDefaultActivityId(PDO $pdo): int
    {
        if (!self::hasCampaigns($pdo)) {
            return 1;
        }
        $id = (int) $pdo->query(
            'SELECT id FROM activities WHERE status = 1 ORDER BY sort_order ASC, id ASC LIMIT 1'
        )->fetchColumn();
        if ($id > 0) {
            return $id;
        }
        $id = (int) $pdo->query('SELECT id FROM activities ORDER BY id ASC LIMIT 1')->fetchColumn();
        return $id > 0 ? $id : 1;
    }

    /** @return array{title:string,desc:string,logo:string,enabled:bool,id:int,name:string} */
    public static function activityMeta(PDO $pdo, int $activityId): array
    {
        $a = self::getActivity($pdo, $activityId);
        if (!$a) {
            return self::shareMeta($pdo);
        }
        $logo = trim((string) ($a['logo_url'] ?? ''));
        if ($logo === '') {
            $logo = SettingsService::get('brand_logo', '');
        }
        return [
            'id'      => (int) $a['id'],
            'name'    => (string) ($a['name'] ?? ''),
            'title'   => (string) ($a['title'] ?: $a['name'] ?: '活动抽奖'),
            'desc'    => (string) ($a['description'] ?? ''),
            'logo'    => $logo,
            'enabled' => (int) ($a['status'] ?? 0) === 1,
        ];
    }

    /** @return array{title:string,desc:string,logo:string,enabled:bool,id:int,name:string} */
    public static function shareMeta(?PDO $pdo = null): array
    {
        if ($pdo && self::hasCampaigns($pdo)) {
            $id = self::getDefaultActivityId($pdo);
            return self::activityMeta($pdo, $id);
        }
        $logo = trim(SettingsService::get('activity_share_logo', ''));
        if ($logo === '') {
            $logo = SettingsService::get('brand_logo', '');
        }
        return [
            'id'      => 1,
            'name'    => '默认抽奖',
            'title'   => SettingsService::get('activity_share_title', '活动抽奖 · 积分兑换') ?: '活动抽奖 · 积分兑换',
            'desc'    => SettingsService::get('activity_share_desc', '登录参与抽奖，积分可兑换好礼') ?: '登录参与抽奖，积分可兑换好礼',
            'logo'    => $logo,
            'enabled' => SettingsService::get('activity_enabled', '1') === '1',
        ];
    }

    /** @return array{id:int,name:string,title:string,description:string,logo_url:?string,status:int,sort_order:int} */
    private static function legacyActivityMeta(): array
    {
        return [
            'id'          => 1,
            'name'        => '默认抽奖',
            'title'       => SettingsService::get('activity_share_title', '活动抽奖 · 积分兑换') ?: '活动抽奖',
            'description' => SettingsService::get('activity_share_desc', '登录参与抽奖') ?: '',
            'logo_url'    => SettingsService::get('activity_share_logo', '') ?: null,
            'status'      => SettingsService::get('activity_enabled', '1') === '1' ? 1 : 0,
            'sort_order'  => 10,
        ];
    }

    public static function saveActivity(PDO $pdo, array $data, ?int $id = null): int
    {
        if (!self::hasCampaigns($pdo)) {
            throw new RuntimeException('请先执行 database/一键注入_全部更新.sql 开通多活动');
        }
        $name = trim((string) ($data['name'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $desc = trim((string) ($data['description'] ?? ''));
        $logo = trim((string) ($data['logo_url'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $sort = (int) ($data['sort_order'] ?? 0);
        $status = !empty($data['status']) ? 1 : 0;
        if ($name === '') {
            throw new InvalidArgumentException('活动名称不能为空');
        }
        if ($title === '') {
            $title = $name;
        }
        if ($slug === '') {
            $slug = null;
        }
        if ($id) {
            $pdo->prepare(
                'UPDATE activities
                 SET name=?, slug=?, title=?, description=?, logo_url=?, status=?, sort_order=?
                 WHERE id=?'
            )->execute([$name, $slug, $title, $desc, $logo !== '' ? $logo : null, $status, $sort, $id]);
            return $id;
        }
        $pdo->prepare(
            'INSERT INTO activities (name, slug, title, description, logo_url, status, sort_order)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$name, $slug, $title, $desc, $logo !== '' ? $logo : null, $status ?: 1, $sort]);
        return (int) $pdo->lastInsertId();
    }

    /** 兼容旧分享设置页：写回默认活动 */
    public static function saveShareSettings(PDO $pdo, array $data): void
    {
        if (self::hasCampaigns($pdo)) {
            $id = self::getDefaultActivityId($pdo);
            self::saveActivity($pdo, [
                'name'        => trim((string) ($data['activity_share_title'] ?? '默认抽奖')) ?: '默认抽奖',
                'title'       => (string) ($data['activity_share_title'] ?? ''),
                'description' => (string) ($data['activity_share_desc'] ?? ''),
                'logo_url'    => (string) ($data['activity_share_logo'] ?? ''),
                'status'      => !empty($data['activity_enabled']) ? 1 : 0,
                'sort_order'  => 10,
            ], $id);
        }
        SettingsService::set($pdo, 'activity_enabled', !empty($data['activity_enabled']) ? '1' : '0');
        SettingsService::set($pdo, 'activity_share_title', trim((string) ($data['activity_share_title'] ?? '')));
        SettingsService::set($pdo, 'activity_share_desc', trim((string) ($data['activity_share_desc'] ?? '')));
        SettingsService::set($pdo, 'activity_share_logo', trim((string) ($data['activity_share_logo'] ?? '')));
        SettingsService::reload($pdo);
    }

    public static function isEnabled(?PDO $pdo = null, ?int $activityId = null): bool
    {
        if ($pdo && $activityId) {
            $a = self::getActivity($pdo, $activityId);
            return $a !== null && (int) ($a['status'] ?? 0) === 1;
        }
        if ($pdo && self::hasCampaigns($pdo)) {
            $n = (int) $pdo->query('SELECT COUNT(*) FROM activities WHERE status = 1')->fetchColumn();
            return $n > 0;
        }
        return SettingsService::get('activity_enabled', '1') === '1';
    }

    /** @return array{points:int,lottery_chances:int} */
    public static function getWallet(PDO $pdo, int $userId, ?int $activityId = null): array
    {
        self::ensureWallet($pdo, $userId);
        $st = $pdo->prepare('SELECT points, lottery_chances FROM activity_wallets WHERE user_id = ?');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $points = (int) ($row['points'] ?? 0);
        $chances = (int) ($row['lottery_chances'] ?? 0);
        if ($activityId && self::hasCampaigns($pdo)) {
            $chances = self::getChances($pdo, $userId, $activityId);
        }
        return [
            'points'          => $points,
            'lottery_chances' => $chances,
        ];
    }

    public static function getChances(PDO $pdo, int $userId, int $activityId): int
    {
        if (!self::hasCampaigns($pdo)) {
            $w = self::getWallet($pdo, $userId);
            return (int) $w['lottery_chances'];
        }
        self::ensureChances($pdo, $userId, $activityId);
        $st = $pdo->prepare(
            'SELECT chances FROM activity_user_chances WHERE user_id = ? AND activity_id = ?'
        );
        $st->execute([$userId, $activityId]);
        return (int) $st->fetchColumn();
    }

    private static function ensureWallet(PDO $pdo, int $userId): void
    {
        $pdo->prepare(
            'INSERT IGNORE INTO activity_wallets (user_id, points, lottery_chances) VALUES (?, 0, 0)'
        )->execute([$userId]);
    }

    private static function ensureChances(PDO $pdo, int $userId, int $activityId): void
    {
        $pdo->prepare(
            'INSERT IGNORE INTO activity_user_chances (user_id, activity_id, chances) VALUES (?, ?, 0)'
        )->execute([$userId, $activityId]);
    }

    public static function findUserByUsername(PDO $pdo, string $username): ?array
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }
        $st = $pdo->prepare(
            'SELECT id, username, nickname, role, status FROM users
             WHERE username = ? AND deleted_at IS NULL LIMIT 1'
        );
        $st->execute([$username]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function adjustPoints(
        PDO $pdo,
        int $userId,
        int $delta,
        string $reason,
        ?int $adminId = null,
        ?int $presetId = null,
        ?string $refType = 'manual',
        ?int $refId = null
    ): int {
        if ($delta === 0) {
            throw new InvalidArgumentException('积分为 0，无需操作');
        }
        self::ensureWallet($pdo, $userId);
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT points FROM activity_wallets WHERE user_id = ? FOR UPDATE');
            $st->execute([$userId]);
            $points = (int) $st->fetchColumn();
            $after = $points + $delta;
            if ($after < 0) {
                throw new RuntimeException('积分不足（当前 ' . $points . '）');
            }
            $pdo->prepare('UPDATE activity_wallets SET points = ? WHERE user_id = ?')
                ->execute([$after, $userId]);
            $pdo->prepare(
                'INSERT INTO activity_point_ledger
                 (user_id, delta, balance_after, reason, preset_id, admin_id, ref_type, ref_id)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([
                $userId, $delta, $after, mb_substr($reason, 0, 255),
                $presetId, $adminId, $refType, $refId,
            ]);
            $pdo->commit();
            return $after;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function grantByPreset(PDO $pdo, int $userId, int $presetId, ?int $adminId): int
    {
        $st = $pdo->prepare('SELECT id, name, points, status FROM activity_point_presets WHERE id = ?');
        $st->execute([$presetId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p || !(int) $p['status']) {
            throw new RuntimeException('快捷加分项不存在或已停用');
        }
        $pts = (int) $p['points'];
        if ($pts <= 0) {
            throw new RuntimeException('该模板积分为 0');
        }
        return self::adjustPoints(
            $pdo, $userId, $pts,
            '快捷加分：' . $p['name'],
            $adminId, (int) $p['id'], 'preset', (int) $p['id']
        );
    }

    public static function adjustLotteryChances(
        PDO $pdo,
        int $userId,
        int $delta,
        ?int $adminId = null,
        string $reason = '',
        ?int $activityId = null
    ): int {
        if ($delta === 0) {
            throw new InvalidArgumentException('次数为 0，无需操作');
        }
        $activityId = $activityId ?: self::getDefaultActivityId($pdo);
        $act = self::getActivity($pdo, $activityId);
        if (!$act) {
            throw new RuntimeException('活动不存在');
        }
        $actName = (string) ($act['name'] ?? ('活动#' . $activityId));

        self::ensureWallet($pdo, $userId);
        $pdo->beginTransaction();
        try {
            if (self::hasCampaigns($pdo)) {
                self::ensureChances($pdo, $userId, $activityId);
                $st = $pdo->prepare(
                    'SELECT chances FROM activity_user_chances
                     WHERE user_id = ? AND activity_id = ? FOR UPDATE'
                );
                $st->execute([$userId, $activityId]);
                $ch = (int) $st->fetchColumn();
                $after = $ch + $delta;
                if ($after < 0) {
                    throw new RuntimeException('抽奖次数不足（当前 ' . $ch . '）');
                }
                $pdo->prepare(
                    'UPDATE activity_user_chances SET chances = ? WHERE user_id = ? AND activity_id = ?'
                )->execute([$after, $userId, $activityId]);
            } else {
                $st = $pdo->prepare('SELECT lottery_chances FROM activity_wallets WHERE user_id = ? FOR UPDATE');
                $st->execute([$userId]);
                $ch = (int) $st->fetchColumn();
                $after = $ch + $delta;
                if ($after < 0) {
                    throw new RuntimeException('抽奖次数不足（当前 ' . $ch . '）');
                }
                $pdo->prepare('UPDATE activity_wallets SET lottery_chances = ? WHERE user_id = ?')
                    ->execute([$after, $userId]);
            }

            $ptsSt = $pdo->prepare('SELECT points FROM activity_wallets WHERE user_id = ?');
            $ptsSt->execute([$userId]);
            $pointsBal = (int) $ptsSt->fetchColumn();

            $defaultReason = ($delta > 0 ? '发放抽奖×' : '扣减抽奖×') . abs($delta) . '（' . $actName . '）';
            $pdo->prepare(
                'INSERT INTO activity_point_ledger
                 (user_id, delta, balance_after, reason, admin_id, ref_type, ref_id)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $userId,
                0,
                $pointsBal,
                mb_substr($reason !== '' ? $reason : $defaultReason, 0, 255),
                $adminId,
                'chance',
                $activityId,
            ]);
            $pdo->commit();
            return $after;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public static function listPresets(PDO $pdo, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM activity_point_presets';
        if ($activeOnly) {
            $sql .= ' WHERE status = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function savePreset(PDO $pdo, array $data, ?int $id = null): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $points = (int) ($data['points'] ?? 0);
        $sort = (int) ($data['sort_order'] ?? 0);
        $status = !empty($data['status']) ? 1 : 0;
        if ($name === '') {
            throw new InvalidArgumentException('名称不能为空');
        }
        if ($id) {
            $pdo->prepare(
                'UPDATE activity_point_presets SET name=?, points=?, sort_order=?, status=? WHERE id=?'
            )->execute([$name, $points, $sort, $status, $id]);
            return;
        }
        $pdo->prepare(
            'INSERT INTO activity_point_presets (name, points, sort_order, status) VALUES (?,?,?,?)'
        )->execute([$name, $points, $sort, $status ?: 1]);
    }

    /** @return list<array<string,mixed>> */
    public static function listPrizes(PDO $pdo, bool $activeOnly = false, ?int $activityId = null): array
    {
        $activityId = $activityId ?: self::getDefaultActivityId($pdo);
        $hasAid = self::columnExists($pdo, 'activity_lottery_prizes', 'activity_id');
        if ($hasAid) {
            $sql = 'SELECT * FROM activity_lottery_prizes WHERE activity_id = ?';
            if ($activeOnly) {
                $sql .= ' AND status = 1 AND (stock IS NULL OR stock > 0)';
            }
            $sql .= ' ORDER BY sort_order ASC, id ASC';
            $st = $pdo->prepare($sql);
            $st->execute([$activityId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }
        $sql = 'SELECT * FROM activity_lottery_prizes';
        if ($activeOnly) {
            $sql .= ' WHERE status = 1 AND (stock IS NULL OR stock > 0)';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function savePrize(PDO $pdo, array $data, ?int $id = null): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $weight = max(1, (int) ($data['weight'] ?? 1));
        $type = (string) ($data['prize_type'] ?? 'empty');
        if (!in_array($type, ['empty', 'points', 'claim'], true)) {
            $type = 'empty';
        }
        $pts = (int) ($data['points_value'] ?? 0);
        $stockRaw = trim((string) ($data['stock'] ?? ''));
        $stock = $stockRaw === '' ? null : max(0, (int) $stockRaw);
        $sort = (int) ($data['sort_order'] ?? 0);
        $status = !empty($data['status']) ? 1 : 0;
        $activityId = max(1, (int) ($data['activity_id'] ?? self::getDefaultActivityId($pdo)));
        if ($name === '') {
            throw new InvalidArgumentException('奖品名称不能为空');
        }
        $hasAid = self::columnExists($pdo, 'activity_lottery_prizes', 'activity_id');
        if ($id) {
            if ($hasAid) {
                $pdo->prepare(
                    'UPDATE activity_lottery_prizes
                     SET activity_id=?, name=?, weight=?, prize_type=?, points_value=?, stock=?, sort_order=?, status=?
                     WHERE id=?'
                )->execute([$activityId, $name, $weight, $type, $pts, $stock, $sort, $status, $id]);
            } else {
                $pdo->prepare(
                    'UPDATE activity_lottery_prizes
                     SET name=?, weight=?, prize_type=?, points_value=?, stock=?, sort_order=?, status=?
                     WHERE id=?'
                )->execute([$name, $weight, $type, $pts, $stock, $sort, $status, $id]);
            }
            return;
        }
        if ($hasAid) {
            $pdo->prepare(
                'INSERT INTO activity_lottery_prizes
                 (activity_id, name, weight, prize_type, points_value, stock, sort_order, status)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$activityId, $name, $weight, $type, $pts, $stock, $sort, $status ?: 1]);
        } else {
            $pdo->prepare(
                'INSERT INTO activity_lottery_prizes
                 (name, weight, prize_type, points_value, stock, sort_order, status)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$name, $weight, $type, $pts, $stock, $sort, $status ?: 1]);
        }
    }

    /** @return list<array<string,mixed>> */
    public static function listExchangeItems(PDO $pdo, bool $activeOnly = false, ?int $activityId = null): array
    {
        $activityId = $activityId ?: self::getDefaultActivityId($pdo);
        $hasAid = self::columnExists($pdo, 'activity_exchange_items', 'activity_id');
        if ($hasAid) {
            $sql = 'SELECT * FROM activity_exchange_items WHERE activity_id = ?';
            if ($activeOnly) {
                $sql .= ' AND status = 1 AND (stock IS NULL OR stock > 0)';
            }
            $sql .= ' ORDER BY sort_order ASC, id ASC';
            $st = $pdo->prepare($sql);
            $st->execute([$activityId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }
        $sql = 'SELECT * FROM activity_exchange_items';
        if ($activeOnly) {
            $sql .= ' WHERE status = 1 AND (stock IS NULL OR stock > 0)';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function saveExchangeItem(PDO $pdo, array $data, ?int $id = null): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $cost = max(0, (int) ($data['cost_points'] ?? 0));
        $stockRaw = trim((string) ($data['stock'] ?? ''));
        $stock = $stockRaw === '' ? null : max(0, (int) $stockRaw);
        $sort = (int) ($data['sort_order'] ?? 0);
        $status = !empty($data['status']) ? 1 : 0;
        $remark = trim((string) ($data['remark'] ?? ''));
        $activityId = max(1, (int) ($data['activity_id'] ?? self::getDefaultActivityId($pdo)));
        if ($name === '') {
            throw new InvalidArgumentException('兑换物名称不能为空');
        }
        $hasAid = self::columnExists($pdo, 'activity_exchange_items', 'activity_id');
        if ($id) {
            if ($hasAid) {
                $pdo->prepare(
                    'UPDATE activity_exchange_items
                     SET activity_id=?, name=?, cost_points=?, stock=?, sort_order=?, status=?, remark=? WHERE id=?'
                )->execute([$activityId, $name, $cost, $stock, $sort, $status, $remark ?: null, $id]);
            } else {
                $pdo->prepare(
                    'UPDATE activity_exchange_items
                     SET name=?, cost_points=?, stock=?, sort_order=?, status=?, remark=? WHERE id=?'
                )->execute([$name, $cost, $stock, $sort, $status, $remark ?: null, $id]);
            }
            return;
        }
        if ($hasAid) {
            $pdo->prepare(
                'INSERT INTO activity_exchange_items
                 (activity_id, name, cost_points, stock, sort_order, status, remark)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$activityId, $name, $cost, $stock, $sort, $status ?: 1, $remark ?: null]);
        } else {
            $pdo->prepare(
                'INSERT INTO activity_exchange_items (name, cost_points, stock, sort_order, status, remark)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$name, $cost, $stock, $sort, $status ?: 1, $remark ?: null]);
        }
    }

    /**
     * @return array{prize_id:?int,prize_name:string,prize_type:string,points_awarded:int}
     */
    public static function drawLottery(PDO $pdo, int $userId, ?int $activityId = null): array
    {
        $activityId = $activityId ?: self::getDefaultActivityId($pdo);
        if (!self::isEnabled($pdo, $activityId)) {
            throw new RuntimeException('活动未开启');
        }
        self::ensureWallet($pdo, $userId);
        $pdo->beginTransaction();
        try {
            $hasChances = self::hasCampaigns($pdo);
            if ($hasChances) {
                self::ensureChances($pdo, $userId, $activityId);
                $st = $pdo->prepare(
                    'SELECT chances FROM activity_user_chances
                     WHERE user_id = ? AND activity_id = ? FOR UPDATE'
                );
                $st->execute([$userId, $activityId]);
                $chances = (int) $st->fetchColumn();
            } else {
                $st = $pdo->prepare(
                    'SELECT lottery_chances FROM activity_wallets WHERE user_id = ? FOR UPDATE'
                );
                $st->execute([$userId]);
                $chances = (int) $st->fetchColumn();
            }
            if ($chances < 1) {
                throw new RuntimeException('没有抽奖次数，请联系客服发放');
            }

            $ptsLock = $pdo->prepare('SELECT points FROM activity_wallets WHERE user_id = ? FOR UPDATE');
            $ptsLock->execute([$userId]);
            $pointsNow = (int) $ptsLock->fetchColumn();

            $hasAid = self::columnExists($pdo, 'activity_lottery_prizes', 'activity_id');
            if ($hasAid) {
                $pst = $pdo->prepare(
                    'SELECT * FROM activity_lottery_prizes
                     WHERE activity_id = ? AND status = 1 AND (stock IS NULL OR stock > 0)
                     ORDER BY id ASC'
                );
                $pst->execute([$activityId]);
                $prizes = $pst->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $prizes = $pdo->query(
                    'SELECT * FROM activity_lottery_prizes
                     WHERE status = 1 AND (stock IS NULL OR stock > 0)
                     ORDER BY id ASC'
                )->fetchAll(PDO::FETCH_ASSOC);
            }
            if ($prizes === []) {
                throw new RuntimeException('暂无奖品配置');
            }

            $total = 0;
            foreach ($prizes as $p) {
                $total += max(1, (int) $p['weight']);
            }
            $r = random_int(1, $total);
            $acc = 0;
            $hit = $prizes[0];
            foreach ($prizes as $p) {
                $acc += max(1, (int) $p['weight']);
                if ($r <= $acc) {
                    $hit = $p;
                    break;
                }
            }

            $prizeId = (int) $hit['id'];
            $lock = $pdo->prepare('SELECT stock FROM activity_lottery_prizes WHERE id = ? FOR UPDATE');
            $lock->execute([$prizeId]);
            $stock = $lock->fetchColumn();
            if ($stock !== false && $stock !== null && (int) $stock < 1) {
                throw new RuntimeException('奖品刚被抽完，请再试一次');
            }
            if ($stock !== false && $stock !== null) {
                $pdo->prepare('UPDATE activity_lottery_prizes SET stock = stock - 1 WHERE id = ?')
                    ->execute([$prizeId]);
            }

            if ($hasChances) {
                $pdo->prepare(
                    'UPDATE activity_user_chances SET chances = chances - 1
                     WHERE user_id = ? AND activity_id = ?'
                )->execute([$userId, $activityId]);
            } else {
                $pdo->prepare('UPDATE activity_wallets SET lottery_chances = lottery_chances - 1 WHERE user_id = ?')
                    ->execute([$userId]);
            }

            $type = (string) $hit['prize_type'];
            $ptsAward = max(0, (int) $hit['points_value']);
            if ($type === 'empty') {
                $ptsAward = 0;
            }

            if ($ptsAward > 0) {
                $pointsNow += $ptsAward;
                $pdo->prepare('UPDATE activity_wallets SET points = ? WHERE user_id = ?')
                    ->execute([$pointsNow, $userId]);
                $pdo->prepare(
                    'INSERT INTO activity_point_ledger
                     (user_id, delta, balance_after, reason, ref_type, ref_id)
                     VALUES (?,?,?,?,?,?)'
                )->execute([
                    $userId, $ptsAward, $pointsNow,
                    '抽奖获得：' . $hit['name'], 'lottery', $prizeId,
                ]);
            }

            if (self::columnExists($pdo, 'activity_lottery_logs', 'activity_id')) {
                $pdo->prepare(
                    'INSERT INTO activity_lottery_logs
                     (activity_id, user_id, prize_id, prize_name, prize_type, points_awarded)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$activityId, $userId, $prizeId, $hit['name'], $type, $ptsAward]);
            } else {
                $pdo->prepare(
                    'INSERT INTO activity_lottery_logs
                     (user_id, prize_id, prize_name, prize_type, points_awarded)
                     VALUES (?,?,?,?,?)'
                )->execute([$userId, $prizeId, $hit['name'], $type, $ptsAward]);
            }

            $pdo->commit();
            return [
                'prize_id'       => $prizeId,
                'prize_name'     => (string) $hit['name'],
                'prize_type'     => $type,
                'points_awarded' => $ptsAward,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function requestExchange(PDO $pdo, int $userId, int $itemId): int
    {
        $st = $pdo->prepare('SELECT * FROM activity_exchange_items WHERE id = ? AND status = 1');
        $st->execute([$itemId]);
        $item = $st->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new RuntimeException('兑换物不存在或已下架');
        }
        $activityId = (int) ($item['activity_id'] ?? self::getDefaultActivityId($pdo));
        if (!self::isEnabled($pdo, $activityId)) {
            throw new RuntimeException('活动未开启');
        }
        $cost = (int) $item['cost_points'];
        if ($cost < 1) {
            throw new RuntimeException('兑换积分配置异常');
        }

        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT stock FROM activity_exchange_items WHERE id = ? FOR UPDATE');
            $lock->execute([$itemId]);
            $stock = $lock->fetchColumn();
            if ($stock !== false && $stock !== null && (int) $stock < 1) {
                throw new RuntimeException('库存不足');
            }

            self::ensureWallet($pdo, $userId);
            $w = $pdo->prepare('SELECT points FROM activity_wallets WHERE user_id = ? FOR UPDATE');
            $w->execute([$userId]);
            $points = (int) $w->fetchColumn();
            if ($points < $cost) {
                throw new RuntimeException('积分不足（需要 ' . $cost . '，当前 ' . $points . '）');
            }
            $after = $points - $cost;
            $pdo->prepare('UPDATE activity_wallets SET points = ? WHERE user_id = ?')
                ->execute([$after, $userId]);

            if ($stock !== false && $stock !== null) {
                $pdo->prepare('UPDATE activity_exchange_items SET stock = stock - 1 WHERE id = ?')
                    ->execute([$itemId]);
            }

            if (self::columnExists($pdo, 'activity_exchange_orders', 'activity_id')) {
                $pdo->prepare(
                    'INSERT INTO activity_exchange_orders
                     (activity_id, user_id, item_id, item_name, cost_points, status)
                     VALUES (?,?,?,?,?,\'PENDING\')'
                )->execute([$activityId, $userId, $itemId, $item['name'], $cost]);
            } else {
                $pdo->prepare(
                    'INSERT INTO activity_exchange_orders (user_id, item_id, item_name, cost_points, status)
                     VALUES (?,?,?,?,\'PENDING\')'
                )->execute([$userId, $itemId, $item['name'], $cost]);
            }
            $orderId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO activity_point_ledger
                 (user_id, delta, balance_after, reason, ref_type, ref_id)
                 VALUES (?,?,?,?,?,?)'
            )->execute([
                $userId, -$cost, $after,
                '兑换申请：' . $item['name'], 'exchange', $orderId,
            ]);

            $pdo->commit();
            return $orderId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function fulfillExchange(PDO $pdo, int $orderId, int $adminId, string $note = '', bool $cancel = false): void
    {
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT * FROM activity_exchange_orders WHERE id = ? FOR UPDATE');
            $st->execute([$orderId]);
            $order = $st->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new RuntimeException('兑换单不存在');
            }
            if ($order['status'] !== 'PENDING') {
                throw new RuntimeException('该单已处理');
            }
            if ($cancel) {
                $uid = (int) $order['user_id'];
                $cost = (int) $order['cost_points'];
                self::ensureWallet($pdo, $uid);
                $w = $pdo->prepare('SELECT points FROM activity_wallets WHERE user_id = ? FOR UPDATE');
                $w->execute([$uid]);
                $after = (int) $w->fetchColumn() + $cost;
                $pdo->prepare('UPDATE activity_wallets SET points = ? WHERE user_id = ?')
                    ->execute([$after, $uid]);
                if (!empty($order['item_id'])) {
                    $pdo->prepare(
                        'UPDATE activity_exchange_items SET stock = stock + 1
                         WHERE id = ? AND stock IS NOT NULL'
                    )->execute([(int) $order['item_id']]);
                }
                $pdo->prepare(
                    'UPDATE activity_exchange_orders
                     SET status=\'CANCELLED\', admin_note=?, fulfilled_at=NOW(), fulfilled_by=? WHERE id=?'
                )->execute([mb_substr($note ?: '已取消并退回积分', 0, 255), $adminId, $orderId]);
                $pdo->prepare(
                    'INSERT INTO activity_point_ledger
                     (user_id, delta, balance_after, reason, admin_id, ref_type, ref_id)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([
                    $uid, $cost, $after, '兑换取消退回：' . $order['item_name'],
                    $adminId, 'exchange', $orderId,
                ]);
            } else {
                $pdo->prepare(
                    'UPDATE activity_exchange_orders
                     SET status=\'DONE\', admin_note=?, fulfilled_at=NOW(), fulfilled_by=? WHERE id=?'
                )->execute([mb_substr($note, 0, 255) ?: null, $adminId, $orderId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public static function leaderboard(PDO $pdo, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        return $pdo->query(
            'SELECT w.user_id, w.points, u.username, u.nickname
             FROM activity_wallets w
             INNER JOIN users u ON u.id = w.user_id AND u.deleted_at IS NULL
             WHERE w.points > 0
             ORDER BY w.points DESC, w.user_id ASC
             LIMIT ' . $limit
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{rank:?int,points:int} */
    public static function myRank(PDO $pdo, int $userId): array
    {
        $wallet = self::getWallet($pdo, $userId);
        $points = (int) $wallet['points'];
        if ($points <= 0) {
            return ['rank' => null, 'points' => 0];
        }
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM activity_wallets w
             INNER JOIN users u ON u.id = w.user_id AND u.deleted_at IS NULL
             WHERE w.points > ?
                OR (w.points = ? AND w.user_id < ?)'
        );
        $st->execute([$points, $points, $userId]);
        return ['rank' => (int) $st->fetchColumn() + 1, 'points' => $points];
    }

    /** @return list<array<string,mixed>> */
    public static function ledger(PDO $pdo, ?int $userId = null, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        if ($userId) {
            $st = $pdo->prepare(
                'SELECT l.*, u.username, u.nickname
                 FROM activity_point_ledger l
                 JOIN users u ON u.id = l.user_id
                 WHERE l.user_id = ?
                 ORDER BY l.id DESC LIMIT ' . $limit
            );
            $st->execute([$userId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }
        return $pdo->query(
            'SELECT l.*, u.username, u.nickname
             FROM activity_point_ledger l
             JOIN users u ON u.id = l.user_id
             ORDER BY l.id DESC LIMIT ' . $limit
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public static function exchangeOrders(
        PDO $pdo,
        ?string $status = null,
        int $limit = 100,
        ?int $activityId = null
    ): array {
        $limit = max(1, min(500, $limit));
        $hasAid = self::columnExists($pdo, 'activity_exchange_orders', 'activity_id');
        $where = [];
        $params = [];
        if ($status) {
            $where[] = 'o.status = ?';
            $params[] = $status;
        }
        if ($activityId && $hasAid) {
            $where[] = 'o.activity_id = ?';
            $params[] = $activityId;
        }
        $sql = 'SELECT o.*, u.username, u.nickname
                FROM activity_exchange_orders o
                JOIN users u ON u.id = o.user_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY o.id DESC LIMIT ' . $limit;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public static function userLotteryLogs(PDO $pdo, int $userId, int $limit = 50, ?int $activityId = null): array
    {
        $limit = max(1, min(200, $limit));
        if ($activityId && self::columnExists($pdo, 'activity_lottery_logs', 'activity_id')) {
            $st = $pdo->prepare(
                'SELECT * FROM activity_lottery_logs
                 WHERE user_id = ? AND activity_id = ? ORDER BY id DESC LIMIT ' . $limit
            );
            $st->execute([$userId, $activityId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }
        $st = $pdo->prepare(
            'SELECT * FROM activity_lottery_logs WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit
        );
        $st->execute([$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public static function userExchangeOrders(PDO $pdo, int $userId, int $limit = 50, ?int $activityId = null): array
    {
        $limit = max(1, min(200, $limit));
        if ($activityId && self::columnExists($pdo, 'activity_exchange_orders', 'activity_id')) {
            $st = $pdo->prepare(
                'SELECT * FROM activity_exchange_orders
                 WHERE user_id = ? AND activity_id = ? ORDER BY id DESC LIMIT ' . $limit
            );
            $st->execute([$userId, $activityId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }
        $st = $pdo->prepare(
            'SELECT * FROM activity_exchange_orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit
        );
        $st->execute([$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $pdo->query('SELECT `' . str_replace('`', '', $column) . '` FROM `' . str_replace('`', '', $table) . '` LIMIT 0');
            return $cache[$key] = true;
        } catch (PDOException) {
            return $cache[$key] = false;
        }
    }
}
