<?php

declare(strict_types=1);

require_once __DIR__ . '/CosService.php';
require_once __DIR__ . '/SettingsService.php';

class OrderScreenshotStorage
{
    private array $storageConfig;
    private ?CosService $cos = null;

    public function __construct()
    {
        $this->storageConfig = [
            'local_dir' => __DIR__ . '/../uploads/orders',
            'local_url' => '/uploads/orders',
        ];
    }

    /** @return string[] storage keys */
    public function uploadScreenshots(array $files, string $wechatOrderNo): array
    {
        if ($files === []) {
            throw new InvalidArgumentException('请上传订单截图');
        }

        $keys = [];
        foreach ($files as $index => $file) {
            $keys[] = SettingsService::shouldUseLocalStorage()
                ? $this->uploadLocal($file, $wechatOrderNo, $index)
                : $this->getCos()->uploadOrderScreenshot($file, $wechatOrderNo, $index);
        }
        return $keys;
    }

    public function getAccessUrl(string $key): string
    {
        if (str_starts_with($key, 'local:')) {
            $relative = substr($key, 6);
            return rtrim($this->storageConfig['local_url'], '/') . '/' . ltrim($relative, '/');
        }

        return $this->getCos()->getSignedUrl($key);
    }

    public function isLocalMode(): bool
    {
        return SettingsService::shouldUseLocalStorage();
    }

    private function getCos(): CosService
    {
        if ($this->cos === null) {
            $this->cos = new CosService(SettingsService::getCosConfig());
        }
        return $this->cos;
    }

    private function uploadLocal(array $file, string $wechatOrderNo, int $index): string
    {
        $this->validateFile($file);

        $mime = $this->detectMime($file['tmp_name']);
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };

        $dir = rtrim($this->storageConfig['local_dir'], '/') . '/' . date('Y/m/d');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('无法创建上传目录，请检查 uploads 目录权限');
        }

        $safeNo = preg_replace('/[^a-zA-Z0-9_-]/', '_', $wechatOrderNo);
        $filename = $safeNo . '_' . ($index + 1) . '_' . date('His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('截图保存失败，请检查 uploads 目录权限');
        }

        return 'local:' . date('Y/m/d') . '/' . $filename;
    }

    private function validateFile(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('请上传订单截图');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('截图上传失败，请重试');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('每张截图大小不能超过5MB');
        }

        $mime = $this->detectMime($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InvalidArgumentException('仅支持 JPG、PNG、WEBP 格式截图');
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
