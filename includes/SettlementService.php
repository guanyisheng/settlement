<?php

declare(strict_types=1);

require_once __DIR__ . '/SettingsService.php';

class SettlementService
{
    public static function rates(): array
    {
        return SettingsService::getSettlementRates();
    }

    /** 打手到手 = 订单金额 × rate_a × rate_b（通用；一人/双人请用 calcByCrewMode） */
    public static function calcStaffAmount(float $orderAmount, ?float $rateA = null, ?float $rateB = null): float
    {
        $cfg = self::rates();
        $a = $rateA ?? (float) $cfg['rate_a'];
        $b = $rateB ?? (float) $cfg['rate_b'];
        return round($orderAmount * $a * $b, 2);
    }

    /**
     * 一人/双人接单结算（后台可分别配置一人倍率、双人每人倍率）
     *
     * 一人：staff_amount = 金额 × 基础 × 一人倍率
     * 双人：每人 = 金额 × 基础 × 双人每人倍率；落库 staff_amount = 两人合计
     *
     * @return array{rate_a:float,rate_b:float,staff_amount:float,is_duo:bool,each_amount:?float}
     */
    public static function calcByCrewMode(
        float $orderAmount,
        bool $isDuo,
        ?float $rateA = null,
        ?float $duoHalfRateB = null,
        ?float $soloRateB = null
    ): array {
        $cfg = self::rates();
        $a = $rateA ?? (float) $cfg['rate_a'];
        $duo = $duoHalfRateB ?? (float) $cfg['rate_b'];
        $solo = $soloRateB ?? (float) ($cfg['rate_solo'] ?? min(1.0, $duo * 2));
        if ($a < 0 || $a > 1 || $duo < 0 || $duo > 1 || $solo < 0 || $solo > 1) {
            throw new InvalidArgumentException('倍率需在 0%～100% 之间');
        }

        if ($isDuo) {
            $each = round($orderAmount * $a * $duo, 2);
            $staffAmount = round($each * 2, 2);
            return [
                'rate_a' => $a,
                'rate_b' => $duo,
                'staff_amount' => $staffAmount,
                'is_duo' => true,
                'each_amount' => $each,
            ];
        }

        return [
            'rate_a' => $a,
            'rate_b' => $solo,
            'staff_amount' => round($orderAmount * $a * $solo, 2),
            'is_duo' => false,
            'each_amount' => null,
        ];
    }

    public static function formulaLabel(?float $rateA = null, ?float $rateB = null): string
    {
        $cfg = self::rates();
        $a = $rateA ?? (float) $cfg['rate_a'];
        $b = $rateB ?? (float) $cfg['rate_b'];
        return sprintf('×%.0f%%×%.0f%%', $a * 100, $b * 100);
    }

    public static function crewModeHint(
        bool $isDuo,
        ?float $rateA = null,
        ?float $duoHalfRateB = null,
        ?float $soloRateB = null
    ): string {
        $cfg = self::rates();
        $a = $rateA ?? (float) $cfg['rate_a'];
        $duo = $duoHalfRateB ?? (float) $cfg['rate_b'];
        $solo = $soloRateB ?? (float) ($cfg['rate_solo'] ?? min(1.0, $duo * 2));
        if ($isDuo) {
            return sprintf('双人接单 · 每人 ×%.0f%%×%.0f%%', $a * 100, $duo * 100);
        }
        return sprintf('一人接单 · ×%.0f%%×%.0f%%', $a * 100, $solo * 100);
    }

    /** @param float $rateSolo 一人打手倍率 0～1 */
    public static function setRates(PDO $pdo, float $rateA, float $rateDuo, float $rateSolo): void
    {
        foreach (['基础倍率' => $rateA, '双人每人倍率' => $rateDuo, '一人倍率' => $rateSolo] as $label => $v) {
            if ($v <= 0 || $v > 1) {
                throw new InvalidArgumentException($label . '需在 0～100% 之间（如 80 表示 80%）');
            }
        }
        SettingsService::set($pdo, 'settlement_rate_a', (string) $rateA);
        SettingsService::set($pdo, 'settlement_rate_b', (string) $rateDuo);
        SettingsService::set($pdo, 'settlement_rate_solo', (string) $rateSolo);
        SettingsService::reload($pdo);
    }
}
