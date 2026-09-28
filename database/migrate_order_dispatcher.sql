-- 报单：派单客服归属（结单报备归属）
-- phpMyAdmin 先选中业务库再执行（可重复）。

DROP PROCEDURE IF EXISTS qz_add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE qz_add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL qz_add_column_if_missing(
    'orders',
    'dispatcher_id',
    "INT UNSIGNED DEFAULT NULL COMMENT '派单客服 users.id' AFTER staff_id"
);

DROP PROCEDURE IF EXISTS qz_add_column_if_missing;
