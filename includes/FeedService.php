<?php

declare(strict_types=1);

/** 顾客端「动态」资讯 */
class FeedService
{
    public static function isReady(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM client_feeds LIMIT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    public static function listPublished(PDO $pdo, int $limit = 50): array
    {
        if (!self::isReady($pdo)) {
            return self::fallback();
        }
        $limit = max(1, min(100, $limit));
        return $pdo->query(
            'SELECT * FROM client_feeds WHERE status = 1 ORDER BY sort_order ASC, id DESC LIMIT ' . $limit
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public static function fallback(): array
    {
        return [
            [
                'id' => 0,
                'title' => '为什么选择我们？',
                'cover_url' => '',
                'content' => '三重检验 · 平均 15 选 1 · 售后 24 小时受理',
                'published_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 0,
                'title' => '下单流程引导',
                'cover_url' => '',
                'content' => '① 选择游戏板块 ② 挑选陪单 ③ 填写信息下单',
                'published_at' => date('Y-m-d H:i:s', time() - 86400),
            ],
        ];
    }
}
