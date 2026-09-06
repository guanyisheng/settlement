-- ============================================================
-- 将「累计收入 / 总收入」清零
-- 库名请先在 phpMyAdmin 选中你的库（如 shasha_lunarhaor）再执行
-- 不要写 USE settlement;
-- ============================================================
--
-- 说明：
-- 系统里「累计收入」= 已通过/已结算订单的 staff_amount 合计
-- （见 BalanceService：status IN ('APPROVED','SETTLED') 且未删除）
--
-- 本脚本把这类订单的打手结算金额改为 0，页面上总收入就会变成 0。
-- 订单记录仍保留，只是结算金额归零。
--
-- 注意：
-- 1. 若还有「已放款 / 待处理」提现，可提现余额 = 0 - 提现，可能变成负数。
--    若也要一并清提现，取消文末「可选」段落的注释再执行。
-- 2. 执行前建议先备份，或先跑下面的「预览」SELECT 看影响行数。
-- ============================================================

-- ---------- 预览（只查不改）----------
-- 有 deleted_at 时：
SELECT
    COUNT(*) AS order_count,
    COALESCE(SUM(COALESCE(staff_amount, amount)), 0) AS current_total_income
FROM orders
WHERE status IN ('APPROVED', 'SETTLED')
  AND deleted_at IS NULL;

-- 若报错 Unknown column 'deleted_at'，改用：
-- SELECT COUNT(*) AS order_count,
--        COALESCE(SUM(COALESCE(staff_amount, amount)), 0) AS current_total_income
-- FROM orders
-- WHERE status IN ('APPROVED', 'SETTLED');

-- ---------- 正式清零 ----------
START TRANSACTION;

UPDATE orders
SET staff_amount = 0
WHERE status IN ('APPROVED', 'SETTLED')
  AND (deleted_at IS NULL);

-- 没有 deleted_at 时改用：
-- UPDATE orders SET staff_amount = 0 WHERE status IN ('APPROVED', 'SETTLED');

-- 确认已归零（应接近 0）
SELECT
    COUNT(*) AS order_count,
    COALESCE(SUM(COALESCE(staff_amount, amount)), 0) AS after_total_income
FROM orders
WHERE status IN ('APPROVED', 'SETTLED')
  AND (deleted_at IS NULL);

COMMIT;

-- ============================================================
-- 可选：同时清掉提现记录（避免可提现余额为负）
-- 需要时去掉下面注释再执行一次
-- ============================================================
-- START TRANSACTION;
-- UPDATE withdrawals SET status = 'REJECTED', reject_reason = '期初清零'
-- WHERE status IN ('PENDING', 'PAID');
-- -- 或直接删：DELETE FROM withdrawals;
-- COMMIT;

-- ============================================================
-- 可选方案 B：软删除历史已通过订单（不改金额，统计直接不算）
-- ============================================================
-- UPDATE orders
-- SET deleted_at = NOW()
-- WHERE status IN ('APPROVED', 'SETTLED')
--   AND deleted_at IS NULL;
