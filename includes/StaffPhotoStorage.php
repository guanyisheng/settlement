<?php

declare(strict_types=1);

require_once __DIR__ . '/CosService.php';
require_once __DIR__ . '/SettingsService.php';

class StaffPhotoStorage
{
    private const MAX_SIZE = 5 * 1024 * 1024;
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private array $storageConfig;
    private ?CosService $cos = null;

    public function __construct()
    {
        $this->storageConfig = [
            'local_dir' => __DIR__ . '/../uploads/staff',
            'local_url' => '/uploads/staff',
        ];
    }

    public function uploadPhoto(array $file, string $username): string
    {
        $this->validateFile($file);

        return SettingsService::shouldUseLocalStorage()
            ? $this->uploadLocal($file, $username)
            : $this->uploadCos($file, $username);
    }

    public function getAccessUrl(string $key): string
    {
        if (str_starts_with($key, 'local:')) {
            $relative = substr($key, 6);
            return rtrim($this->storageConfig['local_url'], '/') . '/' . ltrim($relative, '/');
        }

        return $this->getCos()->getSignedUrl($key);
    }

    private function getCos(): CosService
    {
        if ($this->cos === null) {
            $this->cos = new CosService(SettingsService::getCosConfig());
        }
        return $this->cos;
    }

    private function uploadLocal(array $file, string $username): string
    {
        $mime = $this->detectMime($file['tmp_name']);
        $ext = self::ALLOWED[$mime];
        $dir = rtrim($this->storageConfig['local_dir'], '/') . '/' . date('Y/m/d');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('无法创建上传目录');
        }

        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $username);
        $filename = $safe . '_' . date('His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('毛照保存失败，请检查 uploads/staff 目录权限');
        }

        return 'local:' . date('Y/m/d') . '/' . $filename;
    }

    private function uploadCos(array $file, string $username): string
    {
        $mime = $this->detectMime($file['tmp_name']);
        $ext = self::ALLOWED[$mime];
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $username);
        $prefix = rtrim(SettingsService::get('cos_prefix_staff', 'staff/'), '/');
        $key = $prefix . '/' . date('Y/m/d') . '/'
            . $safe . '_' . date('His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        return $this->getCos()->uploadRawImage($file, $key);
    }

    private function validateFile(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('请上传毛照');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('毛照上传失败，请重试');
        }
        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            throw new InvalidArgumentException('毛照大小不能超过5MB');
        }
        $mime = $this->detectMime($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            throw new InvalidArgumentException('毛照仅支持 JPG、PNG、WEBP');
        }
    }

    private function detectMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);
        return (string) $mime;
    }
}
