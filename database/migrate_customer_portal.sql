-- 顾客端：下单/接单/转单回池/评价 + 罚款 + 会员档次与月年卡
-- phpMyAdmin 先选中业务库再执行（可重复）。

CREATE TABLE IF NOT EXISTS client_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(32) NOT NULL,
    client_id INT UNSIGNED NOT NULL COMMENT '顾客 users.id',
    business_type_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED DEFAULT NULL COMMENT '当前打手；池中为空',
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '顾客消费金额',
    staff_amount DECIMAL(12,2) DEFAULT NULL COMMENT '结单打手结算；转单人无',
    status VARCHAR(20) NOT NULL DEFAULT 'WAITING' COMMENT 'WAITING/POOL/ACCEPTED/DOING/DONE/CANCELLED',
    remark VARCHAR(500) DEFAULT NULL,
    transfer_note VARCHAR(255) DEFAULT NULL,
    transferred_from INT UNSIGNED DEFAULT NULL,
    accepted_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_client_order_no (order_no),
    KEY idx_client_orders_client (client_id),
    KEY idx_client_orders_staff (staff_id),
    KEY idx_client_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_order_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    score TINYINT UNSIGNED NOT NULL DEFAULT 5,
    content VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_review_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_fines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fines_staff (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS membership_tiers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    min_points INT UNSIGNED NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS membership_cards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    card_type VARCHAR(16) NOT NULL COMMENT 'month/year',
    duration_days INT UNSIGNED NOT NULL,
    bonus_points INT UNSIGNED NOT NULL DEFAULT 0,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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

CALL qz_add_column_if_missing('users', 'accept_client_orders', "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否接顾客单' AFTER status");
CALL qz_add_column_if_missing('users', 'contact_wechat', "VARCHAR(100) DEFAULT NULL COMMENT '联系微信' AFTER accept_client_orders");
CALL qz_add_column_if_missing('users', 'growth_points', "INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员成长值' AFTER contact_wechat");
CALL qz_add_column_if_missing('users', 'membership_expire_at', "DATETIME DEFAULT NULL COMMENT '月卡年卡到期' AFTER growth_points");

-- 顾客角色（ENUM 扩值，可重复执行）
DROP PROCEDURE IF EXISTS qz_ensure_client_role;
DELIMITER //
CREATE PROCEDURE qz_ensure_client_role()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'
          AND COLUMN_TYPE NOT LIKE '%CLIENT%'
    ) THEN
        SET @sql = "ALTER TABLE users MODIFY COLUMN role ENUM('STAFF','CUSTOMER_SERVICE','EXAMINER','BOSS','ADMIN','CLIENT') NOT NULL DEFAULT 'STAFF'";
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;
CALL qz_ensure_client_role();
DROP PROCEDURE IF EXISTS qz_ensure_client_role;

DROP PROCEDURE IF EXISTS qz_add_column_if_missing;

INSERT INTO membership_tiers (name, min_points, sort_order, status)
SELECT '普通', 0, 0, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM membership_tiers WHERE name = '普通' LIMIT 1);

INSERT INTO membership_tiers (name, min_points, sort_order, status)
SELECT '银卡', 100, 10, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM membership_tiers WHERE name = '银卡' LIMIT 1);

INSERT INTO membership_tiers (name, min_points, sort_order, status)
SELECT '金卡', 500, 20, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM membership_tiers WHERE name = '金卡' LIMIT 1);

INSERT INTO membership_cards (name, card_type, duration_days, bonus_points, price, status)
SELECT '月卡', 'month', 30, 50, 30, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM membership_cards WHERE name = '月卡' LIMIT 1);

INSERT INTO membership_cards (name, card_type, duration_days, bonus_points, price, status)
SELECT '年卡', 'year', 365, 300, 298, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM membership_cards WHERE name = '年卡' LIMIT 1);
