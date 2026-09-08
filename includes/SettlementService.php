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

    /**
     * 一人/双人接单结算：
     * - 配置 rate_b 表示「双人时每人份额」（默认 0.5 → 80×50）
     * - 一人接单：按全部份额（默认 rate_b×2=1.0 → 80×100，相对半份 ×2 加钱）
     * - 双人接单：订单总 staff_amount = 一人同额，再由两人平分（每人约 80×50）
     *
     * @return array{rate_a:float,rate_b:float,staff_amount:float,is_duo:bool}
     */
    public static function calcByCrewMode(
        float $orderAmount,
        bool $isDuo,
        ?float $rateA = null,
        ?float $halfShareRateB = null
    ): array {
        $cfg = self::rates();
        $a = $rateA ?? (float) $cfg['rate_a'];
        $half = $halfShareRateB ?? (float) $cfg['rate_b'];
        if ($a < 0 || $a > 1 || $half < 0 || $half > 1) {
            throw new InvalidArgumentException('倍率需在 0%～100% 之间');
        }

        // 一人全部份额；默认 half=0.5 → full=1.0
        $fullShare = min(1.0, round($half * 2, 4));
        if ($isDuo) {
            // 快照 rate_b 记每人半份，总额按两人合计（=一人全部）
            $snapB = $half;
            $staffAmount = round($orderAmount * $a * $fullShare, 2);
        } else {
            $snapB = $fullShare;
            $staffAmount = round($orderAmount * $a * $fullShare, 2);
        }

        return [
            'rate_a' => $a,
            'rate_b' => $snapB,
            'staff_amount' => $staffAmount,
            'is_duo' => $isDuo,
        ];
    }

    public static function formulaLabel(?float $rateA = null, ?float $rateB = null): string
    {
        $cfg = self::rates();
        $a = $rateA ?? (float) $cfg['rate_a'];
        $b = $rateB ?? (float) $cfg['rate_b'];
        return sprintf('×%.0f%%×%.0f%%', $a * 100, $b * 100);
    }

    /** 报单预览文案：一人 ×A%×100%；双人每人 ×A%×B% */
    public static function crewModeHint(bool $isDuo, ?float $rateA = null, ?float $halfShareRateB = null): string
    {
        $cfg = self::rates();
        $a = $rateA ?? (float) $cfg['rate_a'];
        $half = $halfShareRateB ?? (float) $cfg['rate_b'];
        $full = min(1.0, round($half * 2, 4));
        if ($isDuo) {
            return sprintf('双人接单 · 每人约 ×%.0f%%×%.0f%%', $a * 100, $half * 100);
        }
        return sprintf('一人接单 · ×%.0f%%×%.0f%%（半份×2）', $a * 100, $full * 100);
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
