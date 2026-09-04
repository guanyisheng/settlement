<?php
/**
 * COS 连通性诊断（命令行: php scripts/cos_diagnose.php）
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/CosService.php';

$config = require __DIR__ . '/../config/cos.php';
$storage = require __DIR__ . '/../config/storage.php';

echo "=== COS 配置诊断 ===\n\n";

$secretId = $config['secret_id'] ?? '';
$secretKey = $config['secret_key'] ?? '';
$region = $config['region'] ?? '';
$bucket = $config['bucket'] ?? '';
$prefix = $config['prefix'] ?? '';

echo "Storage driver: {$storage['driver']}\n";
echo "Region:         {$region}\n";
echo "Bucket:         {$bucket}\n";
echo "Prefix:         {$prefix}\n";
echo "SecretId:       " . substr($secretId, 0, 8) . '...' . substr($secretId, -4) . " (len=" . strlen($secretId) . ")\n";

$keyPreview = strlen($secretKey) > 12
    ? substr($secretKey, 0, 8) . '...' . substr($secretKey, -4)
    : '(empty)';
echo "SecretKey:      {$keyPreview} (len=" . strlen($secretKey) . ")\n";

$issues = [];

if ($secretKey === '' || $secretKey === 'YOUR_SECRET_KEY') {
    $issues[] = 'SecretKey 未配置';
}
if (str_starts_with($secretKey, 'ENCv1:')) {
    $issues[] = 'SecretKey 是 ENCv1 加密格式，不是腾讯云明文密钥 —— 这是当前无法上传 COS 的主要原因';
    $issues[] = '请到 腾讯云控制台 → 访问管理 → API密钥 复制 SecretKey 明文写入 config/cos.php 或环境变量 COS_SECRET_KEY';
}
if (!str_starts_with($secretId, 'AKID')) {
    $issues[] = 'SecretId 格式异常（正常应以 AKID 开头）';
}

$host = "{$bucket}.cos.{$region}.myqcloud.com";
echo "Host:           {$host}\n\n";

if ($storage['driver'] === 'auto' && str_starts_with($secretKey, 'ENCv1:')) {
    echo ">>> 当前 auto 模式会检测到无效密钥，报单截图走本地 uploads/orders/，不会调用 COS\n\n";
}

if ($issues) {
    echo "=== 发现的问题 ===\n";
    foreach ($issues as $i => $issue) {
        echo ($i + 1) . ". {$issue}\n";
    }
    echo "\n";
}

if (str_starts_with($secretKey, 'ENCv1:') || $secretKey === 'YOUR_SECRET_KEY' || $secretKey === '') {
    echo "=== 跳过实际上传测试（密钥无效）===\n";
    exit(1);
}

echo "=== 实际上传测试 ===\n";

$testKey = rtrim($prefix, '/') . '/_diagnose/' . date('YmdHis') . '_test.txt';
$testBody = 'cos diagnose ' . date('c');

try {
    $cos = new CosService($config);

    $ref = new ReflectionClass($cos);
    $putMethod = $ref->getMethod('putObject');
    $putMethod->setAccessible(true);
    $putMethod->invoke($cos, $testKey, $testBody, 'text/plain');

    echo "上传成功! Key: {$testKey}\n";

    $signMethod = $ref->getMethod('getSignedUrl');
    $signMethod->setAccessible(false);
    $url = $cos->getSignedUrl($testKey, 300);
    echo "签名 URL (5分钟有效):\n{$url}\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "读取测试: HTTP {$code}, body=" . substr((string) $body, 0, 80) . "\n";
    echo "\nCOS 工作正常。\n";
    exit(0);
} catch (Throwable $e) {
    echo "上传失败: " . $e->getMessage() . "\n";

    // 手动 PUT 获取详细响应
    echo "\n=== 详细 HTTP 诊断 ===\n";
    $path = '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($testKey, '/'))));
    $hostHeader = $host;
    $headers = [
        'Host'           => $hostHeader,
        'Content-Type'   => 'text/plain',
        'Content-Length' => (string) strlen($testBody),
    ];

    $start = time();
    $end = $start + 3600;
    $keyTime = $start . ';' . $end;
    $signKey = hash_hmac('sha1', $keyTime, $secretKey);
    $lowerHeaders = array_change_key_case($headers, CASE_LOWER);
    ksort($lowerHeaders);
    $headerList = implode(';', array_keys($lowerHeaders));
    $parts = [];
    foreach ($lowerHeaders as $k => $v) {
        $parts[] = $k . '=' . rawurlencode((string) $v);
    }
    $headerString = implode('&', $parts);
    $httpString = "put\n{$path}\n\n{$headerString}\n";
    $stringToSign = "sha1\n{$keyTime}\n" . sha1($httpString) . "\n";
    $signature = hash_hmac('sha1', $stringToSign, $signKey);
    $auth = sprintf(
        'q-sign-algorithm=sha1&q-ak=%s&q-sign-time=%s&q-key-time=%s&q-header-list=%s&q-url-param-list=&q-signature=%s',
        $secretId, $keyTime, $keyTime, $headerList, $signature
    );

    $curlHeaders = [];
    foreach ($headers as $k => $v) {
        $curlHeaders[] = "{$k}: {$v}";
    }
    $curlHeaders[] = "Authorization: {$auth}";

    $url = "https://{$host}{$path}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => $testBody,
        CURLOPT_HTTPHEADER     => $curlHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    echo "URL:      {$url}\n";
    echo "HTTP:     {$httpCode}\n";
    if ($curlErr) {
        echo "cURL:     {$curlErr}\n";
    }
    if ($response) {
        echo "Response: {$response}\n";
    }

    $hints = [
        403 => '403 通常是 SecretId/SecretKey 错误或签名无效，或子账号无 COS 写权限',
        404 => '404 通常是 Bucket 名称或 Region 不正确',
        401 => '401 认证失败，检查密钥是否有效、是否被禁用',
    ];
    if (isset($hints[$httpCode])) {
        echo "Hint:     {$hints[$httpCode]}\n";
    }

    exit(1);
}
