-- 清空全部提现记录（按客户要求「提现记录全部删了清空」）
-- 执行前请备份。清空后打手可提现余额会回到「仅按已通过订单累计收入」计算。
-- 在 phpMyAdmin 先选中业务库再执行。

START TRANSACTION;

-- 方案 A：硬删除全部提现记录
DELETE FROM withdrawals;

-- 若只想作废、保留痕迹，可改用下面两行代替 DELETE：
-- UPDATE withdrawals
-- SET status = 'REJECTED', reject_reason = '历史清空', processed_at = NOW()
-- WHERE status IN ('PENDING', 'PAID');

COMMIT;
