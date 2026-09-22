-- 派单信息字段 + 报单实付金额 + 业务板块
-- phpMyAdmin 选中业务库执行（可重复）

DROP PROCEDURE IF EXISTS qz_add_column_if_missing;
DELIMITER //
CREATE PROCEDURE qz_add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- 顾客单：老板下单信息
CALL qz_add_column_if_missing('client_orders', 'game_name', "VARCHAR(100) DEFAULT NULL COMMENT '游戏名' AFTER remark");
CALL qz_add_column_if_missing('client_orders', 'game_id', "VARCHAR(100) DEFAULT NULL COMMENT '游戏ID' AFTER game_name");
CALL qz_add_column_if_missing('client_orders', 'game_client', "VARCHAR(50) DEFAULT NULL COMMENT '客户端' AFTER game_id");
CALL qz_add_column_if_missing('client_orders', 'contact', "VARCHAR(100) DEFAULT NULL COMMENT '老板联系方式QQ/微信' AFTER game_client");

-- 报单：实付金额（空则按原价 amount）
CALL qz_add_column_if_missing('orders', 'paid_amount', "DECIMAL(12,2) DEFAULT NULL COMMENT '实付金额；空=按amount' AFTER amount");

-- 业务类型归属板块
CALL qz_add_column_if_missing('business_types', 'board', "VARCHAR(32) DEFAULT NULL COMMENT '三角洲/暗区/微契约' AFTER name");

DROP PROCEDURE IF EXISTS qz_add_column_if_missing;
