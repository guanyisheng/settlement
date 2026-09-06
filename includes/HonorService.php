<?php

declare(strict_types=1);

require_once __DIR__ . '/StaffPhotoStorage.php';
require_once __DIR__ . '/helpers.php';

class HonorService
{
    public static function listByStaff(PDO $pdo, int $staffId): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM staff_honors
             WHERE staff_id = ? AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$staffId]);
        $honors = $stmt->fetchAll();
        foreach ($honors as &$h) {
            $h['images'] = self::imagesOf($pdo, (int) $h['id']);
        }
        unset($h);
        return $honors;
    }

    public static function imagesOf(PDO $pdo, int $honorId): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM staff_honor_images
             WHERE honor_id = ? AND deleted_at IS NULL
             ORDER BY id ASC'
        );
        $stmt->execute([$honorId]);
        return $stmt->fetchAll();
    }

    public static function getById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM staff_honors WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['images'] = self::imagesOf($pdo, (int) $row['id']);
        return $row;
    }

    public static function create(
        PDO $pdo,
        int $staffId,
        int $createdBy,
        string $title,
        string $remark,
        array $files,
        string $username
    ): int {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('请填写荣誉名称');
        }
        $uploaded = normalizeUploadedFiles($files);
        if ($uploaded === []) {
            throw new InvalidArgumentException('请至少上传一张荣誉图片');
        }
        if (count($uploaded) > 9) {
            throw new InvalidArgumentException('最多上传9张图片');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO staff_honors (staff_id, title, remark, created_by) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$staffId, $title, trim($remark), $createdBy]);
            $honorId = (int) $pdo->lastInsertId();

            $storage = new StaffPhotoStorage();
            $imgStmt = $pdo->prepare(
                'INSERT INTO staff_honor_images (honor_id, image_key) VALUES (?, ?)'
            );
            foreach ($uploaded as $file) {
                $key = $storage->uploadPhoto($file, $username . '_honor');
                $imgStmt->execute([$honorId, $key]);
            }
            $pdo->commit();
            return $honorId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function softDelete(PDO $pdo, int $honorId, int $actorId, bool $allowManage = false): void
    {
        $honor = self::getById($pdo, $honorId);
        if (!$honor) {
            throw new RuntimeException('荣誉不存在');
        }
        if (!$allowManage && (int) $honor['staff_id'] !== $actorId) {
            throw new RuntimeException('只能删除自己的荣誉');
        }
        $pdo->prepare('UPDATE staff_honors SET deleted_at = NOW() WHERE id = ?')->execute([$honorId]);
    }
}
