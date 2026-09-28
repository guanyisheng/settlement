-- 结算板块（可自定义）；先写入 3 个官方板块
-- phpMyAdmin 先选中业务库再执行（可重复）。

CREATE TABLE IF NOT EXISTS settlement_boards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL COMMENT '板块名，如三角洲行动',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_settlement_boards_name (name),
    KEY idx_settlement_boards_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 三个板块（已存在则忽略）
INSERT IGNORE INTO settlement_boards (name, sort_order, status) VALUES
('三角洲行动', 10, 1),
('暗区突围', 20, 1),
('无畏契约', 30, 1);

-- 旧短名 → 正式名（业务类型上的 board 字段）
UPDATE business_types SET board = '三角洲行动' WHERE board IN ('三角洲', '三角洲行动');
UPDATE business_types SET board = '暗区突围' WHERE board IN ('暗区', '暗区突围');
UPDATE business_types SET board = '无畏契约' WHERE board IN ('微契约', '无畏契约');

-- 把旧短名板块记录也迁掉（若曾手工插过）
UPDATE settlement_boards SET name = '三角洲行动' WHERE name = '三角洲';
UPDATE settlement_boards SET name = '暗区突围' WHERE name = '暗区';
UPDATE settlement_boards SET name = '无畏契约' WHERE name IN ('微契约');

-- 确保 business_types.board 够长
DROP PROCEDURE IF EXISTS qz_mod_board_len;
DELIMITER $$
CREATE PROCEDURE qz_mod_board_len()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'business_types' AND COLUMN_NAME = 'board'
    ) THEN
        ALTER TABLE business_types
            MODIFY COLUMN board VARCHAR(64) DEFAULT NULL COMMENT '结算板块名';
    END IF;
END$$
DELIMITER ;
CALL qz_mod_board_len();
DROP PROCEDURE IF EXISTS qz_mod_board_len;
