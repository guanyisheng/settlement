<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

/** 结算板块：可自定义，默认三角洲行动 / 暗区突围 / 无畏契约 */
class BoardService
{
    public static function isReady(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM settlement_boards LIMIT 0');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /** @return list<string> 启用中的板块名（按排序） */
    public static function names(PDO $pdo, bool $activeOnly = true): array
    {
        $rows = self::all($pdo, $activeOnly);
        $out = [];
        foreach ($rows as $r) {
            $out[] = (string) $r['name'];
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public static function all(PDO $pdo, bool $activeOnly = false): array
    {
        if (!self::isReady($pdo)) {
            return self::fallbackRows();
        }
        $sql = 'SELECT * FROM settlement_boards';
        if ($activeOnly) {
            $sql .= ' WHERE status = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $pdo->query($sql)->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private static function fallbackRows(): array
    {
        return [
            ['id' => 0, 'name' => '三角洲行动', 'sort_order' => 10, 'status' => 1],
            ['id' => 0, 'name' => '暗区突围', 'sort_order' => 20, 'status' => 1],
            ['id' => 0, 'name' => '无畏契约', 'sort_order' => 30, 'status' => 1],
        ];
    }

    public static function normalize(PDO $pdo, mixed $raw): ?string
    {
        $board = trim((string) ($raw ?? ''));
        if ($board === '') {
            return null;
        }
        $names = self::names($pdo, false);
        if (!in_array($board, $names, true)) {
            throw new InvalidArgumentException('请选择已有板块，或先在板块结算里新增');
        }
        return $board;
    }

    public static function save(PDO $pdo, array $data, ?int $id = null): void
    {
        if (!self::isReady($pdo)) {
            throw new RuntimeException('请先执行 database/一键注入_全部更新.sql');
        }
        $name = trim((string) ($data['name'] ?? ''));
        $sort = (int) ($data['sort_order'] ?? 0);
        $status = isset($data['status']) ? ((int) $data['status'] ? 1 : 0) : 1;
        if ($name === '') {
            throw new InvalidArgumentException('板块名称不能为空');
        }
        if (mb_strlen($name) > 64) {
            throw new InvalidArgumentException('板块名称过长');
        }

        if ($id) {
            $old = self::getById($pdo, $id);
            if (!$old) {
                throw new RuntimeException('板块不存在');
            }
            $oldName = (string) $old['name'];
            try {
                $pdo->prepare('UPDATE settlement_boards SET name=?, sort_order=?, status=? WHERE id=?')
                    ->execute([$name, $sort, $status, $id]);
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'uk_')) {
                    throw new InvalidArgumentException('板块名称已存在');
                }
                throw $e;
            }
            if ($oldName !== $name) {
                try {
                    $pdo->prepare('UPDATE business_types SET board = ? WHERE board = ?')
                        ->execute([$name, $oldName]);
                } catch (PDOException) {
                }
            }
            return;
        }

        try {
            $pdo->prepare('INSERT INTO settlement_boards (name, sort_order, status) VALUES (?,?,?)')
                ->execute([$name, $sort, $status]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'uk_')) {
                throw new InvalidArgumentException('板块名称已存在');
            }
            throw $e;
        }
    }

    public static function getById(PDO $pdo, int $id): ?array
    {
        if (!self::isReady($pdo) || $id <= 0) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM settlement_boards WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
