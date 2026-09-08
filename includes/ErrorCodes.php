<?php

declare(strict_types=1);

/**
 * 用户可见错误码：截屏里带 【E-XXX-####】，方便定位模块。
 */
class ErrorCodes
{
    /** 稳定文案 → 固定码（高频业务校验） */
    private const MESSAGE_MAP = [
        '请选择客户和业务类型' => 'E-RPT-0001',
        '请填写微信订单编号' => 'E-RPT-0002',
        '请上传订单截图' => 'E-RPT-0003',
        '请填写有效的数量（正整数）' => 'E-RPT-0004',
        '请填写完整的接单开始和结束时间' => 'E-RPT-0005',
        '结束时间不能早于开始时间' => 'E-RPT-0006',
        '用户名或密码错误' => 'E-AUTH-0001',
        '账号已禁用，请联系管理员' => 'E-AUTH-0002',
        '无权限执行此操作' => 'E-AUTH-0003',
        '无权限访问该页面' => 'E-AUTH-0004',
        '业务名称不能为空' => 'E-BIZ-0001',
        '单价不能为负数' => 'E-BIZ-0002',
    ];

    public static function codeFor(Throwable $e, string $module = 'SYS'): string
    {
        $msg = trim($e->getMessage());
        if (isset(self::MESSAGE_MAP[$msg])) {
            return self::MESSAGE_MAP[$msg];
        }
        // 微信单号查重等动态文案
        if (str_contains($msg, '微信订单') && str_contains($msg, '报')) {
            return 'E-RPT-0100';
        }
        if (str_contains($msg, '附加打手')) {
            return 'E-RPT-0101';
        }
        if (str_contains($msg, '余额不足') || str_contains($msg, '可提现')) {
            return 'E-BAL-0001';
        }
        if (str_contains($msg, '无权限')) {
            return 'E-AUTH-0003';
        }

        $mod = strtoupper(preg_replace('/[^A-Z]/', '', strtoupper($module)) ?: 'SYS');
        if (strlen($mod) > 4) {
            $mod = substr($mod, 0, 4);
        }
        $hash = strtoupper(substr(sha1($msg !== '' ? $msg : $e::class), 0, 4));
        return 'E-' . $mod . '-' . $hash;
    }

    /** 展示给用户：原文案 + 错误码 */
    public static function format(Throwable $e, string $module = 'SYS'): string
    {
        $msg = trim($e->getMessage());
        if ($msg === '') {
            $msg = '操作失败，请稍后重试';
        }
        return $msg . ' 【' . self::codeFor($e, $module) . '】';
    }
}
