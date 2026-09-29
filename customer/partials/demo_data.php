<?php

declare(strict_types=1);

/** 本地无数据库时的顾客端演示数据 */
function customerDemoProducts(): array
{
    return [
        [
            'id' => 1,
            'name' => '财神体验 · 单局物资1000w',
            'unit_price' => 188,
            'original_price' => 238,
            'remark' => '财神体验：单局物资1000w：必须单局内达标，否则一直打，没有任何物资抵扣项（指定一个3x3大红，出了反238）（不做兜底承诺）',
            'board' => '三角洲行动',
            'cover_url' => '',
            'badge_text' => '限时优惠',
            'status' => 1,
            'pricing_type' => 'fixed',
        ],
        [
            'id' => 2,
            'name' => '趣味单 · 每日限时',
            'unit_price' => 98,
            'original_price' => 128,
            'remark' => '每日限时开放，先到先得',
            'board' => '暗区突围',
            'cover_url' => '',
            'badge_text' => '限时优惠',
            'status' => 1,
            'pricing_type' => 'fixed',
        ],
        [
            'id' => 3,
            'name' => '无畏契约 · 技术陪',
            'unit_price' => 158,
            'original_price' => 198,
            'remark' => '技术向陪打，不做兜底承诺',
            'board' => '无畏契约',
            'cover_url' => '',
            'badge_text' => '热门',
            'status' => 1,
            'pricing_type' => 'fixed',
        ],
        [
            'id' => 4,
            'name' => '体验绝密单',
            'unit_price' => 218,
            'original_price' => 288,
            'remark' => '不做兜底承诺 · 每日限时开放',
            'board' => '三角洲行动',
            'cover_url' => '',
            'badge_text' => '限时优惠',
            'status' => 1,
            'pricing_type' => 'fixed',
        ],
    ];
}

function customerDemoProduct(int $id): ?array
{
    foreach (customerDemoProducts() as $p) {
        if ((int) $p['id'] === $id) {
            return $p;
        }
    }
    return customerDemoProducts()[0] ?? null;
}
