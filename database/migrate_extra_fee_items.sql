-- 额外收费项目（按订单金额上调百分比）+ 业务类型启用关系 + 订单快照字段
-- 在 phpMyAdmin 先选中业务库再执行（可重复执行）。

CREATE TABLE IF NOT EXISTS extra_fee_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '如：包卡、选图',
    fee_type VARCHAR(16) NOT NULL DEFAULT 'percent' COMMENT 'percent=加百分比 fixed=直接加钱',
    rate DECIMAL(8,4) NOT NULL DEFAULT 0.1000 COMMENT '上调比例，0.10=10%',
    fixed_amount DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '直接加价金额',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    remark VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_type_extra_fees (
    business_type_id INT UNSIGNED NOT NULL,
    extra_fee_item_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (business_type_id, extra_fee_item_id),
    KEY idx_bt_extra_fee_item (extra_fee_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CALL qz_add_column_if_missing('orders', 'base_amount', "DECIMAL(12,2) DEFAULT NULL COMMENT '单价×数量（未加额外项目）' AFTER unit_price");
CALL qz_add_column_if_missing('orders', 'extra_fees_json', "TEXT DEFAULT NULL COMMENT '额外收费快照 JSON' AFTER amount");
CALL qz_add_column_if_missing('orders', 'extra_fees_rate', "DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT '额外上调比例合计' AFTER extra_fees_json");

DROP PROCEDURE IF EXISTS qz_add_column_if_missing;

INSERT INTO extra_fee_items (name, rate, sort_order, status, remark)
SELECT '包卡', 0.1000, 10, 1, '订单金额上调10%'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM extra_fee_items WHERE name = '包卡' LIMIT 1);

INSERT INTO extra_fee_items (name, rate, sort_order, status, remark)
SELECT '选图', 0.1000, 20, 1, '订单金额上调10%'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM extra_fee_items WHERE name = '选图' LIMIT 1);
