-- ============================================================
-- 清账系统｜本次更新总 SQL（多角色 RBAC + 毛照多图 + 荣誉 + 倍率快照）
-- ============================================================
-- 使用方法（重要）：
-- 1. phpMyAdmin 左侧先点选你的业务库（如 shasha_lunarhaor）
-- 2. 再打开「SQL」粘贴本文件全部内容执行
-- 3. 不要写 USE settlement（你的账号没有那个库）
-- 4. 本脚本对「已存在的表/列」会自动跳过，可重复执行
-- ============================================================

SET NAMES utf8mb4;

-- ---------- 辅助：按需加列（已存在则跳过）----------
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
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- 一、系统设置表（若已有可跳过）
-- ============================================================
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('brand_name', '清账系统'),
('brand_logo', '/img/logo.png'),
('brand_theme_color', '#001A72'),
('settlement_rate_a', '0.8'),
('settlement_rate_b', '0.5'),
('storage_driver', 'auto');

-- ============================================================
-- 二、RBAC 核心表
-- ============================================================
CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    group_name VARCHAR(50) NOT NULL DEFAULT '其他',
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) DEFAULT NULL COMMENT '系统内置编码，自定义可空',
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=系统角色不可删',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_roles_name (name),
    UNIQUE KEY uk_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_data_scopes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    scope_type VARCHAR(32) NOT NULL DEFAULT 'self',
    staff_ids TEXT DEFAULT NULL COMMENT 'JSON 打手ID列表',
    customer_ids TEXT DEFAULT NULL COMMENT 'JSON 客户ID列表',
    business_ids TEXT DEFAULT NULL COMMENT 'JSON 业务类型ID列表',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_role_scope (role_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 三、毛照多图 + 荣誉
-- ============================================================
CREATE TABLE IF NOT EXISTS staff_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    photo_key VARCHAR(512) NOT NULL,
    uploaded_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    INDEX idx_staff_photos_staff (staff_id),
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_honors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    title VARCHAR(100) NOT NULL,
    remark VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    INDEX idx_staff_honors_staff (staff_id),
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_honor_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    honor_id INT UNSIGNED NOT NULL,
    image_key VARCHAR(512) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (honor_id) REFERENCES staff_honors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 四、给现有表加字段（已存在自动跳过）
-- ============================================================
CALL qz_add_column_if_missing('orders', 'staff_amount', "DECIMAL(12,2) DEFAULT NULL COMMENT '打手结算金额' AFTER amount");
CALL qz_add_column_if_missing('orders', 'rate_a', "DECIMAL(8,4) DEFAULT NULL COMMENT '创建时基础倍率快照' AFTER staff_amount");
CALL qz_add_column_if_missing('orders', 'rate_b', "DECIMAL(8,4) DEFAULT NULL COMMENT '创建时打手倍率快照' AFTER rate_a");
CALL qz_add_column_if_missing('orders', 'deleted_at', 'DATETIME DEFAULT NULL AFTER updated_at');
CALL qz_add_column_if_missing('orders', 'wechat_order_no', "VARCHAR(64) DEFAULT NULL COMMENT '微信订单编号' AFTER order_no");
CALL qz_add_column_if_missing('orders', 'screenshot_key', "TEXT DEFAULT NULL COMMENT '截图Key JSON' AFTER wechat_order_no");

CALL qz_add_column_if_missing('users', 'hired_at', "DATE DEFAULT NULL COMMENT '入职时间' AFTER role");
CALL qz_add_column_if_missing('users', 'examiner', "VARCHAR(100) DEFAULT NULL COMMENT '考核官' AFTER hired_at");
CALL qz_add_column_if_missing('users', 'deposit', "VARCHAR(50) DEFAULT NULL COMMENT '押金' AFTER examiner");
CALL qz_add_column_if_missing('users', 'photo_uploaded', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否上传毛照' AFTER deposit");
CALL qz_add_column_if_missing('users', 'photo_key', "VARCHAR(255) DEFAULT NULL COMMENT '毛照存储Key' AFTER photo_uploaded");
CALL qz_add_column_if_missing('users', 'pay_qr_key', "VARCHAR(512) DEFAULT NULL COMMENT '收款转账二维码存储Key' AFTER photo_key");
CALL qz_add_column_if_missing('users', 'deleted_at', 'DATETIME DEFAULT NULL AFTER updated_at');

CALL qz_add_column_if_missing('customers', 'balance', "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '预存余额' AFTER remark");
CALL qz_add_column_if_missing('customers', 'is_prepaid', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=预存客户' AFTER balance");

CALL qz_add_column_if_missing('withdrawals', 'deleted_at', 'DATETIME DEFAULT NULL AFTER updated_at');

-- 角色 ENUM 尽量包含 BOSS / EXAMINER（若已包含会报错则忽略；下面单独处理）
-- 用动态 SQL 更安全：仅当缺 BOSS/EXAMINER 时再改
DROP PROCEDURE IF EXISTS qz_ensure_role_enum;
DELIMITER $$
CREATE PROCEDURE qz_ensure_role_enum()
BEGIN
    DECLARE col_type TEXT;
    SELECT COLUMN_TYPE INTO col_type
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'
    LIMIT 1;

    IF col_type IS NOT NULL
       AND (col_type NOT LIKE '%BOSS%' OR col_type NOT LIKE '%EXAMINER%') THEN
        SET @sql = "ALTER TABLE users MODIFY COLUMN role ENUM('STAFF','CUSTOMER_SERVICE','EXAMINER','BOSS','ADMIN') NOT NULL DEFAULT 'STAFF'";
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;
CALL qz_ensure_role_enum();
DROP PROCEDURE IF EXISTS qz_ensure_role_enum;

-- ============================================================
-- 五、权限 / 角色种子
-- ============================================================
INSERT INTO permissions (code, name, group_name, sort_order) VALUES
('dashboard.view', '工作台', '工作台', 10),
('report.create', '报单', '报单', 20),
('order.view', '订单查看', '订单', 30),
('order.review', '订单审核', '订单', 31),
('order.delete', '删除订单', '订单', 32),
('withdrawal.view', '提现查看', '提现', 40),
('withdrawal.process', '提现处理', '提现', 41),
('registration.review', '注册审核', '注册', 50),
('staff.view', '打手查看', '打手', 60),
('staff.manage', '打手管理', '打手', 61),
('photo.view', '毛照查看', '毛照', 70),
('photo.download', '毛照下载', '毛照', 71),
('honor.view', '荣誉查看', '荣誉', 80),
('honor.manage', '荣誉管理', '荣誉', 81),
('customer.view', '客户查看', '客户', 90),
('customer.manage', '客户管理', '客户', 91),
('business.view', '业务类型查看', '业务', 100),
('business.manage', '业务类型管理', '业务', 101),
('stats.view', '数据统计', '统计', 110),
('board.view', '数据看板', '统计', 111),
('user.view', '用户查看', '用户', 120),
('user.manage', '用户管理', '用户', 121),
('user.delete', '删除用户', '用户', 122),
('role.view', '角色查看', '角色', 130),
('role.manage', '角色管理', '角色', 131),
('permission.manage', '权限管理', '角色', 132),
('rate.manage', '倍率管理', '结算', 140),
('settings.manage', '系统设置', '系统', 150),
('password.change', '修改密码', '账号', 160)
ON DUPLICATE KEY UPDATE name = VALUES(name), group_name = VALUES(group_name), sort_order = VALUES(sort_order);

INSERT INTO roles (code, name, description, is_system, status) VALUES
('BOSS', '老板', '全部权限与全部数据（原管理员已并入）', 1, 1),
('CUSTOMER_SERVICE', '客服', '报单/订单/提现/客户等', 1, 1),
('EXAMINER', '考官', '打手档案/毛照/荣誉/注册审核', 1, 1),
('STAFF', '打手', '仅本人数据', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), status = 1, deleted_at = NULL;

-- 原 ADMIN 用户并入老板
UPDATE users SET role = 'BOSS' WHERE role = 'ADMIN';

-- 若库里曾插入过 ADMIN 角色，则禁用并标记合并
UPDATE roles
SET status = 0, deleted_at = NOW(), name = '管理员(已合并到老板)'
WHERE code = 'ADMIN' AND (deleted_at IS NULL OR deleted_at IS NOT NULL);

-- 老板：全部权限
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'BOSS';

-- 客服默认权限
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.code IN (
    'dashboard.view', 'report.create',
    'order.view', 'order.review',
    'withdrawal.view', 'withdrawal.process',
    'registration.review',
    'staff.view', 'photo.view', 'honor.view',
    'customer.view', 'customer.manage',
    'business.view', 'business.manage',
    'password.change'
) WHERE r.code = 'CUSTOMER_SERVICE';

-- 考官默认权限
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.code IN (
    'dashboard.view', 'report.create',
    'registration.review',
    'staff.view', 'staff.manage',
    'photo.view', 'photo.download',
    'honor.view', 'honor.manage',
    'order.view', 'stats.view',
    'password.change'
) WHERE r.code = 'EXAMINER';

-- 打手默认权限
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.code IN (
    'dashboard.view', 'report.create',
    'order.view', 'withdrawal.view',
    'photo.view', 'honor.view', 'honor.manage',
    'password.change'
) WHERE r.code = 'STAFF';

-- 数据范围
INSERT INTO role_data_scopes (role_id, scope_type)
SELECT id, 'all' FROM roles WHERE code = 'BOSS'
ON DUPLICATE KEY UPDATE scope_type = VALUES(scope_type);

INSERT INTO role_data_scopes (role_id, scope_type)
SELECT id, 'assigned' FROM roles WHERE code IN ('CUSTOMER_SERVICE', 'EXAMINER')
ON DUPLICATE KEY UPDATE scope_type = VALUES(scope_type);

INSERT INTO role_data_scopes (role_id, scope_type)
SELECT id, 'self' FROM roles WHERE code = 'STAFF'
ON DUPLICATE KEY UPDATE scope_type = VALUES(scope_type);

-- 现有用户写入 user_roles（双角色基础）
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
JOIN roles r ON r.code = CASE WHEN u.role = 'ADMIN' THEN 'BOSS' ELSE u.role END
WHERE u.role IS NOT NULL AND u.role != '';

-- 旧单张毛照迁到 staff_photos
INSERT INTO staff_photos (staff_id, photo_key, uploaded_by, created_at)
SELECT u.id, u.photo_key, u.id, COALESCE(u.updated_at, u.created_at)
FROM users u
WHERE (u.role = 'STAFF' OR EXISTS (
        SELECT 1 FROM user_roles ur
        JOIN roles r ON r.id = ur.role_id AND r.code = 'STAFF'
        WHERE ur.user_id = u.id
      ))
  AND u.photo_key IS NOT NULL
  AND u.photo_key != ''
  AND NOT EXISTS (
      SELECT 1 FROM staff_photos sp
      WHERE sp.staff_id = u.id AND sp.photo_key = u.photo_key AND sp.deleted_at IS NULL
  );

-- 微信订单号唯一索引（已存在会跳过）
DROP PROCEDURE IF EXISTS qz_add_unique_wechat;
DELIMITER $$
CREATE PROCEDURE qz_add_unique_wechat()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND INDEX_NAME = 'idx_wechat_order_no'
    ) THEN
        -- 空字符串先改成 NULL，避免唯一冲突
        UPDATE orders SET wechat_order_no = NULL WHERE wechat_order_no = '';
        SET @sql = 'ALTER TABLE orders ADD UNIQUE INDEX idx_wechat_order_no (wechat_order_no)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;
CALL qz_add_unique_wechat();
DROP PROCEDURE IF EXISTS qz_add_unique_wechat;

-- 清理辅助过程
DROP PROCEDURE IF EXISTS qz_add_column_if_missing;

-- ============================================================
-- 完成。请重新登录后台验证：
-- - 老板可见：角色权限、结算倍率、系统设置
-- - 员工管理可给用户勾选多个角色，顶栏显示 客服&考官
-- ============================================================
