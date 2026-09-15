-- 已拒绝订单允许同一微信订单号重新报单
-- 把 UNIQUE(wechat_order_no) 改为普通索引；重复拦截由程序对「非 REJECTED」订单负责。
-- 在 phpMyAdmin 先选中业务库再执行（可重复执行）。

DROP PROCEDURE IF EXISTS qz_relax_wechat_order_unique;
DELIMITER //
CREATE PROCEDURE qz_relax_wechat_order_unique()
BEGIN
    DECLARE uniq_cnt INT DEFAULT 0;
    DECLARE idx_cnt INT DEFAULT 0;

    SELECT COUNT(*) INTO uniq_cnt
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'idx_wechat_order_no'
      AND NON_UNIQUE = 0;

    IF uniq_cnt > 0 THEN
        SET @sql = 'ALTER TABLE orders DROP INDEX idx_wechat_order_no';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;

    SELECT COUNT(*) INTO idx_cnt
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'idx_wechat_order_no';

    IF idx_cnt = 0 THEN
        SET @sql = 'ALTER TABLE orders ADD INDEX idx_wechat_order_no (wechat_order_no)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL qz_relax_wechat_order_unique();
DROP PROCEDURE IF EXISTS qz_relax_wechat_order_unique;
