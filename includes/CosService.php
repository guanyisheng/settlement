<?php

declare(strict_types=1);

class CosService
{
    private array $config;
    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require __DIR__ . '/../config/cos.php';
        if (empty($this->config['secret_key'])) {
            throw new RuntimeException('COS SecretKey 未配置，请在 config/cos.php 或环境变量 COS_SECRET_KEY 中设置');
        }
    }

    /**
     * 上传订单截图，返回 COS 对象 Key
     */
    public function uploadOrderScreenshot(array $file, string $wechatOrderNo, ?int $index = null): string
    {
        $this->validateScreenshotFile($file);

        $mime = $this->detectMime($file['tmp_name']);
        $ext = self::ALLOWED_TYPES[$mime];
        $safeNo = preg_replace('/[^a-zA-Z0-9_-]/', '_', $wechatOrderNo);
        $suffix = $index === null ? '' : '_' . ($index + 1);
        $key = rtrim($this->config['prefix'], '/') . '/'
            . date('Y/m/d') . '/'
            . $safeNo . $suffix . '_' . date('His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        $this->putObject($key, (string) file_get_contents($file['tmp_name']), $mime);

        return $key;
    }

    /** 批量上传订单截图 */
    public function uploadOrderScreenshots(array $files, string $wechatOrderNo): array
    {
        if ($files === []) {
            throw new InvalidArgumentException('请上传订单截图');
        }

        $keys = [];
        foreach ($files as $index => $file) {
            $keys[] = $this->uploadOrderScreenshot($file, $wechatOrderNo, $index);
        }

        return $keys;
    }

    /** 上传图片到指定 COS Key */
    public function uploadRawImage(array $file, string $key): string
    {
        $this->validateScreenshotFile($file);
        $mime = $this->detectMime($file['tmp_name']);
        $this->putObject($key, (string) file_get_contents($file['tmp_name']), $mime);
        return $key;
    }

    private function validateScreenshotFile(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('请上传订单截图');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('截图上传失败，请重试');
        }
        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            throw new InvalidArgumentException('每张截图大小不能超过5MB');
        }

        $mime = $this->detectMime($file['tmp_name']);
        if (!isset(self::ALLOWED_TYPES[$mime])) {
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

    /** 上传纯文本（用于连通性测试） */
    public function putText(string $key, string $body): void
    {
        $this->putObject($key, $body, 'text/plain');
    }

    /** 生成临时访问链接（私有桶） */
    public function getSignedUrl(string $key, int $expires = 3600): string
    {
        $path = $this->encodePath($key);
        $host = $this->getHost();

        $start = time();
        $end = $start + $expires;
        $keyTime = $start . ';' . $end;

        $signKey = hash_hmac('sha1', $keyTime, $this->config['secret_key']);

        $paramList = '';
        $paramString = '';
        $headerList = 'host';
        $headerString = 'host=' . $host;

        $httpString = "get\n{$path}\n{$paramString}\n{$headerString}\n";
        $stringToSign = "sha1\n{$keyTime}\n" . sha1($httpString) . "\n";
        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        $auth = http_build_query([
            'q-sign-algorithm'  => 'sha1',
            'q-ak'              => $this->config['secret_id'],
            'q-sign-time'       => $keyTime,
            'q-key-time'        => $keyTime,
            'q-header-list'     => $headerList,
            'q-url-param-list'  => $paramList,
            'q-signature'       => $signature,
        ], '', '&', PHP_QUERY_RFC3986);

        return "https://{$host}{$path}?{$auth}";
    }

    private function putObject(string $key, string $body, string $contentType): void
    {
        $path = $this->encodePath($key);
        $host = $this->getHost();

        $headers = [
            'Host'           => $host,
            'Content-Type'   => $contentType,
            'Content-Length' => (string) strlen($body),
        ];

        $authorization = $this->buildAuthorization('put', $path, $headers);

        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = $k . ': ' . $v;
        }
        $curlHeaders[] = 'Authorization: ' . $authorization;

        $url = "https://{$host}{$path}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('COS 上传失败: ' . $error);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $detail = trim((string) $response);
            if ($detail !== '') {
                $detail = ' — ' . (strlen($detail) > 200 ? substr($detail, 0, 200) . '...' : $detail);
            }
            $hint = match ($httpCode) {
                403 => '（密钥错误或无权写入该 Bucket）',
                404 => '（Bucket 或 Region 不正确）',
                401 => '（认证失败，密钥无效或已禁用）',
                default => '',
            };
            throw new RuntimeException('COS 上传失败 (HTTP ' . $httpCode . ')' . $hint . $detail);
        }
    }

    private function buildAuthorization(string $method, string $path, array $headers): string
    {
        $start = time();
        $end = $start + 3600;
        $keyTime = $start . ';' . $end;
        $signKey = hash_hmac('sha1', $keyTime, $this->config['secret_key']);

        $lowerHeaders = [];
        foreach ($headers as $k => $v) {
            $lowerHeaders[strtolower($k)] = $v;
        }
        ksort($lowerHeaders);

        $headerList = implode(';', array_keys($lowerHeaders));
        $parts = [];
        foreach ($lowerHeaders as $k => $v) {
            $parts[] = $k . '=' . rawurlencode((string) $v);
        }
        $headerString = implode('&', $parts);

        $httpString = strtolower($method) . "\n" . $path . "\n\n" . $headerString . "\n";
        $stringToSign = "sha1\n{$keyTime}\n" . sha1($httpString) . "\n";
        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        return sprintf(
            'q-sign-algorithm=sha1&q-ak=%s&q-sign-time=%s&q-key-time=%s&q-header-list=%s&q-url-param-list=&q-signature=%s',
            $this->config['secret_id'],
            $keyTime,
            $keyTime,
            $headerList,
            $signature
        );
    }

    private function getHost(): string
    {
        return sprintf('%s.cos.%s.myqcloud.com', $this->config['bucket'], $this->config['region']);
    }

    private function encodePath(string $key): string
    {
        $key = ltrim($key, '/');
        $segments = explode('/', $key);
        return '/' . implode('/', array_map('rawurlencode', $segments));
    }
}
