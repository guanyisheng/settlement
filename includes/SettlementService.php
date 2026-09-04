<?php

declare(strict_types=1);

require_once __DIR__ . '/SettingsService.php';

class SettlementService
{
    private static function rates(): array
    {
        return SettingsService::getSettlementRates();
    }

    /** 打手到手 = 订单金额 × rate_a × rate_b */
    public static function calcStaffAmount(float $orderAmount): float
    {
        $cfg = self::rates();
        return round($orderAmount * (float) $cfg['rate_a'] * (float) $cfg['rate_b'], 2);
    }

    public static function formulaLabel(): string
    {
        $cfg = self::rates();
        $a = (float) $cfg['rate_a'];
        $b = (float) $cfg['rate_b'];
        return sprintf('×%.0f%%×%.0f%%', $a * 100, $b * 100);
    }
}
