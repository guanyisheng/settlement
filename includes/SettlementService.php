<?php

declare(strict_types=1);

require_once __DIR__ . '/SettingsService.php';

class SettlementService
{
    public static function rates(): array
    {
        return SettingsService::getSettlementRates();
    }

    /** 打手到手 = 订单金额 × rate_a × rate_b */
    public static function calcStaffAmount(float $orderAmount, ?float $rateA = null, ?float $rateB = null): float
    {
        $cfg = self::rates();
        $a = $rateA ?? (float) $cfg['rate_a'];
        $b = $rateB ?? (float) $cfg['rate_b'];
        return round($orderAmount * $a * $b, 2);
    }

    public static function formulaLabel(?float $rateA = null, ?float $rateB = null): string
    {
        $cfg = self::rates();
        $a = $rateA ?? (float) $cfg['rate_a'];
        $b = $rateB ?? (float) $cfg['rate_b'];
        return sprintf('×%.0f%%×%.0f%%', $a * 100, $b * 100);
    }

    public static function setRates(PDO $pdo, float $rateA, float $rateB): void
    {
        if ($rateA <= 0 || $rateA > 1 || $rateB <= 0 || $rateB > 1) {
            throw new InvalidArgumentException('倍率需在 0～1 之间（如 0.8 表示 80%）');
        }
        SettingsService::set($pdo, 'settlement_rate_a', (string) $rateA);
        SettingsService::set($pdo, 'settlement_rate_b', (string) $rateB);
        SettingsService::reload($pdo);
    }
}
