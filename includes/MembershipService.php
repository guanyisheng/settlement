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
            throw new RuntimeException('请先执行 database/migrate_customer_portal.sql');
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
    }

    public static function saveTier(PDO $pdo, array $data, ?int $id = null): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $min = max(0, (int) ($data['min_points'] ?? 0));
        $sort = (int) ($data['sort_order'] ?? 0);
        if ($name === '') {
            throw new InvalidArgumentException('档次名称不能为空');
        }
        if ($id) {
            $pdo->prepare('UPDATE membership_tiers SET name=?, min_points=?, sort_order=? WHERE id=?')
                ->execute([$name, $min, $sort, $id]);
        } else {
            $pdo->prepare('INSERT INTO membership_tiers (name, min_points, sort_order, status) VALUES (?,?,?,1)')
                ->execute([$name, $min, $sort]);
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
        if ($name === '') {
            throw new InvalidArgumentException('卡名不能为空');
        }
        if ($id) {
            $pdo->prepare(
                'UPDATE membership_cards SET name=?, card_type=?, duration_days=?, bonus_points=?, price=? WHERE id=?'
            )->execute([$name, $type, $days, $bonus, $price, $id]);
        } else {
            $pdo->prepare(
                'INSERT INTO membership_cards (name, card_type, duration_days, bonus_points, price, status) VALUES (?,?,?,?,?,1)'
            )->execute([$name, $type, $days, $bonus, $price]);
        }
    }
}
