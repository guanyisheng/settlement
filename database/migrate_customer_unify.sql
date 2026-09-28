-- 报单客户 customers 与门户顾客 users(CLIENT) 合并关联
-- 可重复执行。phpMyAdmin 先选中业务库。

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
    'customers',
    'user_id',
    "INT UNSIGNED DEFAULT NULL COMMENT '关联门户顾客 users.id' AFTER id"
);

-- 唯一索引（已存在则忽略）
SET @idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND INDEX_NAME = 'uk_customers_user_id'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE customers ADD UNIQUE KEY uk_customers_user_id (user_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 为已有 CLIENT 账号补一条可编辑的报单客户（同名）
INSERT INTO customers (name, remark, user_id, balance, is_prepaid, status)
SELECT
    COALESCE(NULLIF(TRIM(u.nickname), ''), u.username),
    CONCAT('门户账号 ', u.username),
    u.id,
    0,
    0,
    IF(u.status = 1, 1, 0)
FROM users u
WHERE u.role = 'CLIENT'
  AND NOT EXISTS (SELECT 1 FROM customers c WHERE c.user_id = u.id);

DROP PROCEDURE IF EXISTS qz_add_column_if_missing;
