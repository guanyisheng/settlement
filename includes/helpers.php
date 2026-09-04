<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatMoney(float|string $amount): string
{
    return '¥' . number_format((float) $amount, 2, '.', ',');
}

function formatDateTime(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    return date('Y/m/d H:i:s', strtotime($datetime));
}

function formatDateTimeShort(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    return date('Y-m-d H:i', strtotime($datetime));
}

function generateNo(string $prefix): string
{
    return $prefix . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function orderStatusLabel(string $status): string
{
    return match ($status) {
        'PENDING'  => '待审核',
        'APPROVED' => '已通过',
        'REJECTED' => '已拒绝',
        'SETTLED'  => '已结算',
        default    => $status,
    };
}

function orderStatusClass(string $status): string
{
    return match ($status) {
        'PENDING'  => 'status-pending',
        'APPROVED' => 'status-approved',
        'REJECTED' => 'status-rejected',
        'SETTLED'  => 'status-settled',
        default    => '',
    };
}

function withdrawalStatusLabel(string $status): string
{
    return match ($status) {
        'PENDING'  => '待处理',
        'PAID'     => '已放款',
        'REJECTED' => '已拒绝',
        default    => $status,
    };
}

function withdrawalStatusClass(string $status): string
{
    return match ($status) {
        'PENDING'  => 'status-pending',
        'PAID'     => 'status-paid',
        'REJECTED' => 'status-rejected',
        default    => '',
    };
}

function roleLabel(string $role): string
{
    return match ($role) {
        'STAFF'            => '打手',
        'CUSTOMER_SERVICE' => '客服',
        'EXAMINER'         => '考官',
        'BOSS'             => '老板',
        'ADMIN'            => '管理员',
        default            => $role,
    };
}

function formatDate(?string $date): string
{
    if (!$date) {
        return '-';
    }
    return date('Y/m/d', strtotime($date));
}

function staffHasPhoto(array $user): bool
{
    return !empty($user['photo_key']) || !empty($user['photo_uploaded']);
}

function isToday(string $datetime): bool
{
    return date('Y-m-d', strtotime($datetime)) === date('Y-m-d');
}

/** 解析订单截图 Key（兼容旧版单条字符串） */
function parseScreenshotKeys(?string $raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return array_values(array_filter($decoded, static fn($key) => is_string($key) && $key !== ''));
    }

    return [$raw];
}

/** 编码订单截图 Key 为 JSON */
function encodeScreenshotKeys(array $keys): string
{
    $keys = array_values(array_filter($keys, static fn($key) => is_string($key) && $key !== ''));

    return json_encode($keys, JSON_UNESCAPED_UNICODE) ?: '[]';
}

/** 规范化 PHP 多文件上传结构 */
function normalizeUploadedFiles(?array $files): array
{
    if ($files === null) {
        return [];
    }

    if (!is_array($files['name'] ?? null)) {
        if (($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        return [$files];
    }

    $normalized = [];
    foreach ($files['name'] as $index => $name) {
        if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $normalized[] = [
            'name'     => $files['name'][$index],
            'type'     => $files['type'][$index],
            'tmp_name' => $files['tmp_name'][$index],
            'error'    => $files['error'][$index],
            'size'     => $files['size'][$index],
        ];
    }

    return $normalized;
}

