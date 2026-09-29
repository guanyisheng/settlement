<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

/** 成长值：消费 1 元 +1 点；月卡/年卡延期并加赠点 */
class MembershipService
{
    public static function isReady(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM membership_tiers LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    public static function tiers(PDO $pdo): array
    {
        if (!self::isReady($pdo)) {
            return [];
        }
        return $pdo->query(
            'SELECT * FROM membership_tiers WHERE status = 1 ORDER BY min_points ASC, sort_order ASC'
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function cards(PDO $pdo): array
    {
        if (!self::isReady($pdo)) {
            return [];
        }
        return $pdo->query(
            'SELECT * FROM membership_cards WHERE status = 1 ORDER BY id ASC'
        )->fetchAll();
    }

    public static function addGrowthFromSpend(PDO $pdo, int $userId, float $amountYuan): void
    {
        $points = (int) floor(max(0, $amountYuan));
        if ($points <= 0 || $userId <= 0) {
            return;
        }
        try {
            $pdo->prepare('UPDATE users SET growth_points = IFNULL(growth_points,0) + ? WHERE id = ?')
                ->execute([$points, $userId]);
        } catch (PDOException) {
            // 未跑迁移则忽略
        }
    }

    public static function tierForPoints(PDO $pdo, int $points): ?array
    {
        $tiers = self::tiers($pdo);
        $best = null;
        foreach ($tiers as $t) {
            if ($points >= (int) $t['min_points']) {
                $best = $t;
            }
        }
        return $best;
    }

    public static function grantCard(PDO $pdo, int $userId, int $cardId): void
    {
        if (!self::isReady($pdo)) {
            throw new RuntimeException('请先执行 database/一键注入_全部更新.sql');
        }
        $stmt = $pdo->prepare('SELECT * FROM membership_cards WHERE id = ? AND status = 1');
        $stmt->execute([$cardId]);
        $card = $stmt->fetch();
        if (!$card) {
            throw new InvalidArgumentException('卡种不存在');
        }
        $days = (int) $card['duration_days'];
        $bonus = (int) $card['bonus_points'];
        $u = $pdo->prepare('SELECT membership_expire_at, growth_points FROM users WHERE id = ?');
        $u->execute([$userId]);
        $row = $u->fetch();
        if (!$row) {
            throw new RuntimeException('用户不存在');
        }
        $now = time();
        $base = $now;
        if (!empty($row['membership_expire_at'])) {
            $exp = strtotime((string) $row['membership_expire_at']);
            if ($exp > $now) {
                $base = $exp;
            }
        }
        $newExp = date('Y-m-d H:i:s', $base + $days * 86400);
        $pdo->prepare(
            'UPDATE users SET membership_expire_at = ?, growth_points = IFNULL(growth_points,0) + ? WHERE id = ?'
        )->execute([$newExp, $bonus, $userId]);
        require_once __DIR__ . '/CustomerService.php';
        try {
            CustomerService::ensureForClientUser($pdo, $userId);
        } catch (Throwable) {
        }
    }

    /** 客服编辑门户顾客：昵称/成长值/到期/状态，可选重置密码 */
    public static function updateClient(PDO $pdo, int $userId, array $data): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('无效顾客');
        }
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ? AND role = 'CLIENT'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('顾客账号不存在');
        }

        $nickname = trim((string) ($data['nickname'] ?? ''));
        if ($nickname === '') {
            $nickname = (string) $user['username'];
        }
        $growth = max(0, (int) ($data['growth_points'] ?? 0));
        $status = isset($data['status']) ? ((int) $data['status'] ? 1 : 0) : 1;
        $expireRaw = trim((string) ($data['membership_expire_at'] ?? ''));
        $expire = null;
        if ($expireRaw !== '') {
            $ts = strtotime($expireRaw);
            if ($ts === false) {
                throw new InvalidArgumentException('会员到期时间格式不对');
            }
            $expire = date('Y-m-d H:i:s', $ts);
        }

        try {
            $pdo->prepare(
                'UPDATE users SET nickname = ?, growth_points = ?, membership_expire_at = ?, status = ? WHERE id = ?'
            )->execute([$nickname, $growth, $expire, $status, $userId]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'growth_points') || str_contains($e->getMessage(), 'Unknown column')) {
                $pdo->prepare('UPDATE users SET nickname = ?, status = ? WHERE id = ?')
                    ->execute([$nickname, $status, $userId]);
            } else {
                throw $e;
            }
        }

        $newPass = trim((string) ($data['new_password'] ?? ''));
        if ($newPass !== '') {
            if (strlen($newPass) < 6) {
                throw new InvalidArgumentException('新密码至少6位');
            }
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
        }

        require_once __DIR__ . '/CustomerService.php';
        try {
            CustomerService::ensureForClientUser($pdo, $userId);
            CustomerService::syncFromClientUser($pdo, $userId, $nickname, $status);
        } catch (Throwable) {
        }
    }

    /** 补齐历史门户顾客 → 报单客户 */
    public static function backfillCustomerLinks(PDO $pdo): int
    {
        require_once __DIR__ . '/CustomerService.php';
        if (!CustomerService::hasUserIdColumn($pdo)) {
            return 0;
        }
        $rows = $pdo->query("SELECT id FROM users WHERE role = 'CLIENT'")->fetchAll();
        $n = 0;
        foreach ($rows as $r) {
            CustomerService::ensureForClientUser($pdo, (int) $r['id']);
            $n++;
        }
        return $n;
    }

    public static function saveTier(PDO $pdo, array $data, ?int $id = null): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $min = max(0, (int) ($data['min_points'] ?? 0));
        $sort = (int) ($data['sort_order'] ?? 0);
        $status = isset($data['status']) ? ((int) $data['status'] ? 1 : 0) : 1;
        if ($name === '') {
            throw new InvalidArgumentException('档次名称不能为空');
        }
        if ($id) {
            $pdo->prepare('UPDATE membership_tiers SET name=?, min_points=?, sort_order=?, status=? WHERE id=?')
                ->execute([$name, $min, $sort, $status, $id]);
        } else {
            $pdo->prepare('INSERT INTO membership_tiers (name, min_points, sort_order, status) VALUES (?,?,?,?)')
                ->execute([$name, $min, $sort, $status]);
        }
    }

    public static function saveCard(PDO $pdo, array $data, ?int $id = null): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $type = strtolower(trim((string) ($data['card_type'] ?? 'month')));
        if (!in_array($type, ['month', 'year'], true)) {
            $type = 'month';
        }
        $days = max(1, (int) ($data['duration_days'] ?? ($type === 'year' ? 365 : 30)));
        $bonus = max(0, (int) ($data['bonus_points'] ?? 0));
        $price = max(0, (float) ($data['price'] ?? 0));
        $status = isset($data['status']) ? ((int) $data['status'] ? 1 : 0) : 1;
        if ($name === '') {
            throw new InvalidArgumentException('卡名不能为空');
        }
        if ($id) {
            $pdo->prepare(
                'UPDATE membership_cards SET name=?, card_type=?, duration_days=?, bonus_points=?, price=?, status=? WHERE id=?'
            )->execute([$name, $type, $days, $bonus, $price, $status, $id]);
        } else {
            $pdo->prepare(
                'INSERT INTO membership_cards (name, card_type, duration_days, bonus_points, price, status) VALUES (?,?,?,?,?,?)'
            )->execute([$name, $type, $days, $bonus, $price, $status]);
        }
    }

    /** @return list<array<string,mixed>> */
    public static function tiersAll(PDO $pdo): array
    {
        if (!self::isReady($pdo)) {
            return [];
        }
        return $pdo->query('SELECT * FROM membership_tiers ORDER BY min_points ASC, sort_order ASC')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function cardsAll(PDO $pdo): array
    {
        if (!self::isReady($pdo)) {
            return [];
        }
        return $pdo->query('SELECT * FROM membership_cards ORDER BY id ASC')->fetchAll();
    }
}
