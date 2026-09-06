-- 清账系统数据库（开源默认库名 settlement，可按需修改）

CREATE DATABASE IF NOT EXISTS settlement DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE settlement;

-- 系统设置（后台可改）
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nickname VARCHAR(50) NOT NULL DEFAULT '',
    role ENUM('STAFF', 'CUSTOMER_SERVICE', 'EXAMINER', 'BOSS', 'ADMIN') NOT NULL DEFAULT 'STAFF',
    hired_at DATE DEFAULT NULL COMMENT '入职时间',
    examiner VARCHAR(100) DEFAULT NULL COMMENT '考核官',
    deposit VARCHAR(50) DEFAULT NULL COMMENT '押金',
    photo_uploaded TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否上传毛照 1=是',
    photo_key VARCHAR(255) DEFAULT NULL COMMENT '毛照存储Key',
    pay_qr_key VARCHAR(512) DEFAULT NULL COMMENT '收款转账二维码存储Key',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 客户表
CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    remark VARCHAR(255) DEFAULT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '预存余额',
    is_prepaid TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=预存客户，审核扣余额',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 业务类型表
CREATE TABLE IF NOT EXISTS business_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    pricing_type VARCHAR(50) NOT NULL DEFAULT 'fixed' COMMENT 'fixed=固定单价 per_unit=按数量',
    remark VARCHAR(255) DEFAULT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 订单表
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(32) NOT NULL UNIQUE,
    wechat_order_no VARCHAR(64) NOT NULL COMMENT '微信订单编号，打手填写',
    screenshot_key TEXT DEFAULT NULL COMMENT 'COS订单截图Key JSON数组',
    staff_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    business_type_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    staff_amount DECIMAL(12,2) DEFAULT NULL COMMENT '打手结算金额',
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    remark TEXT DEFAULT NULL,
    status ENUM('PENDING', 'APPROVED', 'REJECTED', 'SETTLED') NOT NULL DEFAULT 'PENDING',
    reject_reason VARCHAR(255) DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_id (staff_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_business_type_id (business_type_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    UNIQUE INDEX idx_wechat_order_no (wechat_order_no),
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (business_type_id) REFERENCES business_types(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 提现申请表
CREATE TABLE IF NOT EXISTS withdrawals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    withdrawal_no VARCHAR(32) NOT NULL UNIQUE,
    staff_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('PENDING', 'PAID', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    reject_reason VARCHAR(255) DEFAULT NULL,
    processed_by INT UNSIGNED DEFAULT NULL,
    processed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_id (staff_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初始老板 (密码: boss123) — 老板即最高权限，不再单独设管理员
INSERT INTO users (username, password, nickname, role, status) VALUES
('boss1', '$2y$12$yLWb1cY2eKv/ZVxs0C39Luu4KURiamKikLFx1IpfaIi8vHNJS3FkW', '老板', 'BOSS', 1);

-- 示例打手 (密码: staff123)
INSERT INTO users (username, password, nickname, role, status) VALUES
('staff1', '$2y$12$O4H38ZkrHnTEwMKEh2cTQu3BTdoWac4BvqvsAcnfM638.yKnze.Im', '张三', 'STAFF', 1);

-- 示例客服 (密码: cs123)
INSERT INTO users (username, password, nickname, role, status) VALUES
('cs1', '$2y$12$Bz/RIV1EUkJ8PR0jNb69ieRcBC.EHYehAitw1VvsjDyBsvam/Rd5S', '客服小李', 'CUSTOMER_SERVICE', 1);

-- 示例客户
INSERT INTO customers (name, remark, status) VALUES
('惊鸿', 'VIP客户', 1),
('ahan', '', 1),
('无预存老板', '新客户', 1);

-- 示例业务类型
INSERT INTO business_types (name, unit_price, pricing_type, remark, status) VALUES
('轮回（二挡）', 444.00, 'fixed', '', 1),
('绝密双陪（半小时）', 45.00, 'fixed', '按半小时计价', 1),
('其他业务', 90.00, 'fixed', '', 1);
