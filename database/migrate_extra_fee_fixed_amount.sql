-- 额外项目支持「直接加钱」（固定金额）
-- 已执行过 migrate_extra_fee_items.sql 的库再跑本文件即可。
-- 在 phpMyAdmin 先选中业务库再执行（可重复）。

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

CALL qz_add_column_if_missing('extra_fee_items', 'fee_type', "VARCHAR(16) NOT NULL DEFAULT 'percent' COMMENT 'percent=加百分比 fixed=直接加钱' AFTER name");
CALL qz_add_column_if_missing('extra_fee_items', 'fixed_amount', "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '直接加价金额' AFTER rate");

DROP PROCEDURE IF EXISTS qz_add_column_if_missing;
