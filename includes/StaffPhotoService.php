<?php

declare(strict_types=1);

require_once __DIR__ . '/StaffPhotoStorage.php';
require_once __DIR__ . '/helpers.php';

class StaffPhotoService
{
    public static function listByStaff(PDO $pdo, int $staffId): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM staff_photos
             WHERE staff_id = ? AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$staffId]);
        return $stmt->fetchAll();
    }

    public static function getById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM staff_photos WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return int[] 新建 photo id 列表 */
    public static function uploadMany(PDO $pdo, int $staffId, int $uploaderId, array $files, string $username): array
    {
        $uploaded = normalizeUploadedFiles($files);
        if ($uploaded === []) {
            throw new InvalidArgumentException('请选择要上传的毛照');
        }
        if (count($uploaded) > 9) {
            throw new InvalidArgumentException('一次最多上传9张');
        }

        $storage = new StaffPhotoStorage();
        $ids = [];
        $stmt = $pdo->prepare(
            'INSERT INTO staff_photos (staff_id, photo_key, uploaded_by) VALUES (?, ?, ?)'
        );

        foreach ($uploaded as $file) {
            $key = $storage->uploadPhoto($file, $username);
            $stmt->execute([$staffId, $key, $uploaderId]);
            $ids[] = (int) $pdo->lastInsertId();
        }

        // 兼容旧字段：更新最新一张到 users.photo_key
        $latest = self::listByStaff($pdo, $staffId);
        if ($latest !== []) {
            $pdo->prepare('UPDATE users SET photo_key = ?, photo_uploaded = 1 WHERE id = ?')
                ->execute([$latest[0]['photo_key'], $staffId]);
        }

        return $ids;
    }

    public static function softDelete(PDO $pdo, int $photoId, int $actorId, bool $allowAny = false): void
    {
        $photo = self::getById($pdo, $photoId);
        if (!$photo) {
            throw new RuntimeException('毛照不存在');
        }
        if (!$allowAny && (int) $photo['staff_id'] !== $actorId && (int) ($photo['uploaded_by'] ?? 0) !== $actorId) {
            throw new RuntimeException('只能删除自己上传的毛照');
        }
        $pdo->prepare('UPDATE staff_photos SET deleted_at = NOW() WHERE id = ?')->execute([$photoId]);
    }
}
