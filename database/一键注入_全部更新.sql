-- ============================================================
-- 清账系统｜一键注入 · 全部更新（怕漏就跑这一份）
-- ============================================================
-- 覆盖内容（可重复执行，已有表/列会自动跳过）：
--   ✓ system_settings（含默认倍率、品牌、app_version）
--   ✓ RBAC：permissions / roles / user_roles / role_permissions / role_data_scopes
--   ✓ 毛照多图 staff_photos、荣誉 staff_honors / staff_honor_images
--   ✓ users：入职/考核官/押金/毛照/收款二维码 / deleted_at / CLIENT 角色 / 会员字段
--   ✓ orders：微信单号、截图、staff_amount、rate、co_staff、paid_amount、dispatcher_id
--   ✓ customers：预存 / is_prepaid / user_id（关联门户顾客）
--   ✓ 额外收费 extra_fee_items、业务启用关系、订单快照字段
--   ✓ 顾客端：client_orders / 评价 / 罚款 / 会员档次与月年卡
--   ✓ 结算板块 settlement_boards（三角洲行动/暗区突围/无畏契约）
--   ✓ 活动系统：积分钱包/流水、快捷加分模板、抽奖奖品与记录、兑换
--   ✓ 权限种子、老板/客服/考官/打手角色
--
-- 使用方法：
-- 1. phpMyAdmin 左侧先点选你的业务库
-- 2. 打开「SQL」→ 全选粘贴本文件 → 执行
-- 3. 不要写 USE settlement
-- 4. 跑完重新登录后台
-- ============================================================

SET NAMES utf8mb4;

-- ---------- 辅助：按需加列 ----------
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

-- ---------- 辅助：按需改列类型（如截图字段扩成 TEXT）----------
DROP PROCEDURE IF EXISTS qz_modify_column_if_exists;
DELIMITER $$
CREATE PROCEDURE qz_modify_column_if_exists(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` MODIFY COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- 一、系统设置
-- ============================================================
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('brand_name', '清账系统'),
('brand_logo', 'https://wp-1301153132.cos.ap-chengdu.myqcloud.com/shasha/orders/logo.png'),
('brand_theme_color', '#001A72'),
('app_version', '3.0.0-beta'),
('settlement_rate_a', '0.8'),
('settlement_rate_b', '0.5'),
('storage_driver', 'auto');

-- ============================================================
-- 二、RBAC
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
-- 四、现有表加字段（已存在自动跳过）
-- ============================================================
CALL qz_add_column_if_missing('orders', 'staff_amount', "DECIMAL(12,2) DEFAULT NULL COMMENT '打手结算金额' AFTER amount");
CALL qz_add_column_if_missing('orders', 'rate_a', "DECIMAL(8,4) DEFAULT NULL COMMENT '创建时基础倍率快照' AFTER staff_amount");
CALL qz_add_column_if_missing('orders', 'rate_b', "DECIMAL(8,4) DEFAULT NULL COMMENT '创建时打手倍率快照' AFTER rate_a");
CALL qz_add_column_if_missing('orders', 'co_staff_id', "INT UNSIGNED NULL COMMENT '附加打手用户ID' AFTER staff_id");
CALL qz_add_column_if_missing('orders', 'deleted_at', 'DATETIME DEFAULT NULL AFTER updated_at');
CALL qz_add_column_if_missing('orders', 'wechat_order_no', "VARCHAR(64) DEFAULT NULL COMMENT '微信订单编号' AFTER order_no");
CALL qz_add_column_if_missing('orders', 'screenshot_key', "TEXT DEFAULT NULL COMMENT '截图Key JSON' AFTER wechat_order_no");
CALL qz_modify_column_if_exists('orders', 'screenshot_key', "TEXT DEFAULT NULL COMMENT '截图Key JSON'");

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

-- 角色 ENUM 含 BOSS / EXAMINER
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

-- 旧订单若无结算金额，按 0.8×0.5 补一版（仅 NULL）
UPDATE orders
SET staff_amount = ROUND(amount * 0.8 * 0.5, 2)
WHERE staff_amount IS NULL
  AND status IN ('APPROVED', 'SETTLED');

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
('password.change', '修改密码', '账号', 160),
('activity.manage', '活动管理', '活动', 170)
ON DUPLICATE KEY UPDATE name = VALUES(name), group_name = VALUES(group_name), sort_order = VALUES(sort_order);

INSERT INTO roles (code, name, description, is_system, status) VALUES
('BOSS', '老板', '全部权限与全部数据（原管理员已并入）', 1, 1),
('CUSTOMER_SERVICE', '客服', '报单/订单/提现/客户等', 1, 1),
('EXAMINER', '考官', '打手档案/毛照/荣誉/注册审核', 1, 1),
('STAFF', '打手', '仅本人数据', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), status = 1, deleted_at = NULL;

UPDATE users SET role = 'BOSS' WHERE role = 'ADMIN';

UPDATE roles
SET status = 0, deleted_at = NOW(), name = '管理员(已合并到老板)'
WHERE code = 'ADMIN';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'BOSS';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.code IN (
    'report.create',
    'order.view', 'order.review',
    'withdrawal.view', 'withdrawal.process',
    'registration.review',
    'staff.view', 'photo.view', 'honor.view',
    'customer.view', 'customer.manage',
    'business.view', 'business.manage',
    'activity.manage',
    'password.change'
) WHERE r.code = 'CUSTOMER_SERVICE';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.code IN (
    'report.create',
    'registration.review',
    'staff.view', 'staff.manage',
    'photo.view', 'photo.download',
    'honor.view', 'honor.manage',
    'order.view', 'stats.view',
    'password.change'
) WHERE r.code = 'EXAMINER';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.code IN (
    'report.create',
    'order.view', 'withdrawal.view',
    'photo.view', 'honor.view', 'honor.manage',
    'password.change'
) WHERE r.code = 'STAFF';

-- 财务工作台 / 看板：仅老板。清掉误配到打手/客服/考官的 dashboard.view
DELETE rp FROM role_permissions rp
INNER JOIN roles r ON r.id = rp.role_id
INNER JOIN permissions p ON p.id = rp.permission_id
WHERE p.code = 'dashboard.view' AND r.code IN ('STAFF', 'CUSTOMER_SERVICE', 'EXAMINER');

INSERT INTO role_data_scopes (role_id, scope_type)
SELECT id, 'all' FROM roles WHERE code = 'BOSS'
ON DUPLICATE KEY UPDATE scope_type = VALUES(scope_type);

INSERT INTO role_data_scopes (role_id, scope_type)
SELECT id, 'assigned' FROM roles WHERE code IN ('CUSTOMER_SERVICE', 'EXAMINER')
ON DUPLICATE KEY UPDATE scope_type = VALUES(scope_type);

INSERT INTO role_data_scopes (role_id, scope_type)
SELECT id, 'self' FROM roles WHERE code = 'STAFF'
ON DUPLICATE KEY UPDATE scope_type = VALUES(scope_type);

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

-- 微信订单号唯一索引
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
        UPDATE orders SET wechat_order_no = NULL WHERE wechat_order_no = '';
        SET @sql = 'ALTER TABLE orders ADD INDEX idx_wechat_order_no (wechat_order_no)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;
CALL qz_add_unique_wechat();
DROP PROCEDURE IF EXISTS qz_add_unique_wechat;

-- 附加打手索引
DROP PROCEDURE IF EXISTS qz_add_co_staff_index;
DELIMITER $$
CREATE PROCEDURE qz_add_co_staff_index()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = 'co_staff_id'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND INDEX_NAME = 'idx_co_staff_id'
    ) THEN
        SET @sql = 'ALTER TABLE orders ADD INDEX idx_co_staff_id (co_staff_id)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;
CALL qz_add_co_staff_index();
DROP PROCEDURE IF EXISTS qz_add_co_staff_index;

-- ============================================================
-- 十、额外收费
-- ============================================================
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

CALL qz_add_column_if_missing('orders', 'base_amount', "DECIMAL(12,2) DEFAULT NULL COMMENT '单价×数量（未加额外项目）' AFTER unit_price");
CALL qz_add_column_if_missing('orders', 'extra_fees_json', "TEXT DEFAULT NULL COMMENT '额外收费快照 JSON' AFTER amount");
CALL qz_add_column_if_missing('orders', 'extra_fees_rate', "DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT '额外上调比例合计' AFTER extra_fees_json");
CALL qz_add_column_if_missing('extra_fee_items', 'fee_type', "VARCHAR(16) NOT NULL DEFAULT 'percent' COMMENT 'percent=加百分比 fixed=直接加钱' AFTER name");
CALL qz_add_column_if_missing('extra_fee_items', 'fixed_amount', "DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '直接加价金额' AFTER rate");

INSERT INTO extra_fee_items (name, rate, sort_order, status, remark)
SELECT '包卡', 0.1000, 10, 1, '订单金额上调10%' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM extra_fee_items WHERE name = '包卡' LIMIT 1);
INSERT INTO extra_fee_items (name, rate, sort_order, status, remark)
SELECT '选图', 0.1000, 20, 1, '订单金额上调10%' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM extra_fee_items WHERE name = '选图' LIMIT 1);

-- ============================================================
-- 十一、顾客端 / 罚款 / 会员
-- ============================================================
CREATE TABLE IF NOT EXISTS client_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(32) NOT NULL,
    client_id INT UNSIGNED NOT NULL COMMENT '顾客 users.id',
    business_type_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED DEFAULT NULL COMMENT '当前主打手；待派时可预填顾客指定',
    co_staff_id INT UNSIGNED DEFAULT NULL COMMENT '双人附加打手；空=单人结算',
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
    status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE' COMMENT 'ACTIVE/REVOKED',
    revoked_by INT UNSIGNED DEFAULT NULL,
    revoked_at DATETIME DEFAULT NULL,
    revoke_note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fines_staff (staff_id),
    KEY idx_fines_status (status)
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

CALL qz_add_column_if_missing('users', 'accept_client_orders', "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否接顾客单' AFTER status");
CALL qz_add_column_if_missing('users', 'contact_wechat', "VARCHAR(100) DEFAULT NULL COMMENT '联系微信' AFTER accept_client_orders");
CALL qz_add_column_if_missing('users', 'growth_points', "INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员成长值' AFTER contact_wechat");
CALL qz_add_column_if_missing('users', 'membership_expire_at', "DATETIME DEFAULT NULL COMMENT '月卡年卡到期' AFTER growth_points");

DROP PROCEDURE IF EXISTS qz_ensure_client_role;
DELIMITER $$
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
END$$
DELIMITER ;
CALL qz_ensure_client_role();
DROP PROCEDURE IF EXISTS qz_ensure_client_role;

UPDATE users SET accept_client_orders = 0 WHERE role = 'CLIENT' AND IFNULL(accept_client_orders, 1) = 1;

CALL qz_add_column_if_missing('client_orders', 'base_amount', "DECIMAL(12,2) DEFAULT NULL COMMENT '加价前基础金额' AFTER amount");
CALL qz_add_column_if_missing('client_orders', 'extra_fees_json', "TEXT DEFAULT NULL COMMENT '额外收费快照JSON' AFTER base_amount");
CALL qz_add_column_if_missing('client_orders', 'extra_fees_rate', "DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT '额外收费百分比合计' AFTER extra_fees_json");
CALL qz_add_column_if_missing('client_orders', 'game_name', "VARCHAR(100) DEFAULT NULL COMMENT '游戏名' AFTER remark");
CALL qz_add_column_if_missing('client_orders', 'game_id', "VARCHAR(100) DEFAULT NULL COMMENT '游戏ID' AFTER game_name");
CALL qz_add_column_if_missing('client_orders', 'game_client', "VARCHAR(50) DEFAULT NULL COMMENT '客户端' AFTER game_id");
CALL qz_add_column_if_missing('client_orders', 'contact', "VARCHAR(100) DEFAULT NULL COMMENT '老板联系方式QQ/微信' AFTER game_client");
CALL qz_add_column_if_missing('client_orders', 'co_staff_id', "INT UNSIGNED DEFAULT NULL COMMENT '双人附加打手' AFTER staff_id");

CALL qz_add_column_if_missing('staff_fines', 'status', "VARCHAR(16) NOT NULL DEFAULT 'ACTIVE' COMMENT 'ACTIVE/REVOKED' AFTER created_by");
CALL qz_add_column_if_missing('staff_fines', 'revoked_by', "INT UNSIGNED DEFAULT NULL AFTER status");
CALL qz_add_column_if_missing('staff_fines', 'revoked_at', "DATETIME DEFAULT NULL AFTER revoked_by");
CALL qz_add_column_if_missing('staff_fines', 'revoke_note', "VARCHAR(255) DEFAULT NULL AFTER revoked_at");

INSERT INTO membership_tiers (name, min_points, sort_order, status)
SELECT '普通', 0, 0, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM membership_tiers WHERE name = '普通' LIMIT 1);
INSERT INTO membership_tiers (name, min_points, sort_order, status)
SELECT '银卡', 100, 10, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM membership_tiers WHERE name = '银卡' LIMIT 1);
INSERT INTO membership_tiers (name, min_points, sort_order, status)
SELECT '金卡', 500, 20, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM membership_tiers WHERE name = '金卡' LIMIT 1);
INSERT INTO membership_cards (name, card_type, duration_days, bonus_points, price, status)
SELECT '月卡', 'month', 30, 50, 30, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM membership_cards WHERE name = '月卡' LIMIT 1);
INSERT INTO membership_cards (name, card_type, duration_days, bonus_points, price, status)
SELECT '年卡', 'year', 365, 300, 298, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM membership_cards WHERE name = '年卡' LIMIT 1);

-- ============================================================
-- 十二、实付 / 派单客服 / 客户关联 / 板块
-- ============================================================
CALL qz_add_column_if_missing('orders', 'paid_amount', "DECIMAL(12,2) DEFAULT NULL COMMENT '实付金额；空=按amount' AFTER amount");
CALL qz_add_column_if_missing('orders', 'dispatcher_id', "INT UNSIGNED DEFAULT NULL COMMENT '派单客服 users.id' AFTER staff_id");
CALL qz_add_column_if_missing('business_types', 'board', "VARCHAR(64) DEFAULT NULL COMMENT '结算板块名' AFTER name");
CALL qz_add_column_if_missing('customers', 'user_id', "INT UNSIGNED DEFAULT NULL COMMENT '关联门户顾客 users.id' AFTER id");

DROP PROCEDURE IF EXISTS qz_add_customers_user_uk;
DELIMITER $$
CREATE PROCEDURE qz_add_customers_user_uk()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND INDEX_NAME = 'uk_customers_user_id'
    ) THEN
        SET @sql = 'ALTER TABLE customers ADD UNIQUE KEY uk_customers_user_id (user_id)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;
CALL qz_add_customers_user_uk();
DROP PROCEDURE IF EXISTS qz_add_customers_user_uk;

INSERT INTO customers (name, remark, user_id, balance, is_prepaid, status)
SELECT
    COALESCE(NULLIF(TRIM(u.nickname), ''), u.username),
    CONCAT('门户账号 ', u.username),
    u.id, 0, 0, IF(u.status = 1, 1, 0)
FROM users u
WHERE u.role = 'CLIENT'
  AND NOT EXISTS (SELECT 1 FROM customers c WHERE c.user_id = u.id);

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

INSERT IGNORE INTO settlement_boards (name, sort_order, status) VALUES
('三角洲行动', 10, 1),
('暗区突围', 20, 1),
('无畏契约', 30, 1);

UPDATE business_types SET board = '三角洲行动' WHERE board IN ('三角洲', '三角洲行动');
UPDATE business_types SET board = '暗区突围' WHERE board IN ('暗区', '暗区突围');
UPDATE business_types SET board = '无畏契约' WHERE board IN ('微契约', '无畏契约');
UPDATE settlement_boards SET name = '三角洲行动' WHERE name = '三角洲';
UPDATE settlement_boards SET name = '暗区突围' WHERE name = '暗区';
UPDATE settlement_boards SET name = '无畏契约' WHERE name = '微契约';

CALL qz_modify_column_if_exists('business_types', 'board', "VARCHAR(64) DEFAULT NULL COMMENT '结算板块名'");

-- 已拒绝订单允许同一微信单号重报：UNIQUE → 普通索引
DROP PROCEDURE IF EXISTS qz_relax_wechat_order_unique;
DELIMITER $$
CREATE PROCEDURE qz_relax_wechat_order_unique()
BEGIN
    DECLARE uniq_cnt INT DEFAULT 0;
    DECLARE idx_cnt INT DEFAULT 0;
    SELECT COUNT(*) INTO uniq_cnt FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'idx_wechat_order_no' AND NON_UNIQUE = 0;
    IF uniq_cnt > 0 THEN
        SET @sql = 'ALTER TABLE orders DROP INDEX idx_wechat_order_no';
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
    SELECT COUNT(*) INTO idx_cnt FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_wechat_order_no';
    IF idx_cnt = 0 THEN
        SET @sql = 'ALTER TABLE orders ADD INDEX idx_wechat_order_no (wechat_order_no)';
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;
CALL qz_relax_wechat_order_unique();
DROP PROCEDURE IF EXISTS qz_relax_wechat_order_unique;

-- 勾了打手角色但 legacy role 不是 STAFF 的账号纠正（老板/超管不降级）
UPDATE users u
SET u.role = 'STAFF'
WHERE u.deleted_at IS NULL
  AND u.role NOT IN ('STAFF', 'BOSS', 'ADMIN', 'CLIENT')
  AND EXISTS (
      SELECT 1 FROM user_roles ur
      JOIN roles r ON r.id = ur.role_id
      WHERE ur.user_id = u.id AND r.code = 'STAFF' AND r.deleted_at IS NULL AND r.status = 1
  )
  AND NOT EXISTS (
      SELECT 1 FROM user_roles ur2
      JOIN roles r2 ON r2.id = ur2.role_id
      WHERE ur2.user_id = u.id AND r2.code IN ('BOSS', 'ADMIN') AND r2.deleted_at IS NULL
  );

-- ============================================================
-- 十三、活动系统（多活动转盘 + 积分兑换；任务积分人工后台加）
-- ============================================================
CREATE TABLE IF NOT EXISTS activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '后台显示名',
    slug VARCHAR(64) DEFAULT NULL COMMENT '可选短链',
    title VARCHAR(120) NOT NULL DEFAULT '' COMMENT '前台标题',
    description VARCHAR(255) NOT NULL DEFAULT '' COMMENT '前台副标题',
    logo_url VARCHAR(512) DEFAULT NULL COMMENT '分享图，空则站点Logo',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=开启',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_activities_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_wallets (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    points INT NOT NULL DEFAULT 0 COMMENT '活动积分余额（全活动共用）',
    lottery_chances INT NOT NULL DEFAULT 0 COMMENT '遗留字段，次数已迁至 activity_user_chances',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_user_chances (
    user_id INT UNSIGNED NOT NULL,
    activity_id INT UNSIGNED NOT NULL,
    chances INT NOT NULL DEFAULT 0 COMMENT '该活动剩余抽奖次数',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, activity_id),
    KEY idx_auc_activity (activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_point_presets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '如：完成任务A',
    points INT NOT NULL DEFAULT 0 COMMENT '一次加多少分',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_point_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    delta INT NOT NULL COMMENT '正加负扣',
    balance_after INT NOT NULL,
    reason VARCHAR(255) NOT NULL DEFAULT '',
    preset_id INT UNSIGNED DEFAULT NULL,
    admin_id INT UNSIGNED DEFAULT NULL,
    ref_type VARCHAR(32) DEFAULT NULL COMMENT 'lottery/exchange/manual/chance',
    ref_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_apl_user (user_id, created_at),
    KEY idx_apl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_lottery_prizes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id INT UNSIGNED NOT NULL DEFAULT 1,
    name VARCHAR(100) NOT NULL,
    weight INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '权重，越大越易中',
    prize_type VARCHAR(16) NOT NULL DEFAULT 'empty' COMMENT 'empty/points/claim',
    points_value INT NOT NULL DEFAULT 0 COMMENT 'prize_type=points 时发放积分',
    stock INT DEFAULT NULL COMMENT 'NULL=不限库存',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_alp_activity (activity_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_lottery_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id INT UNSIGNED NOT NULL DEFAULT 1,
    user_id INT UNSIGNED NOT NULL,
    prize_id INT UNSIGNED DEFAULT NULL,
    prize_name VARCHAR(100) NOT NULL,
    prize_type VARCHAR(16) NOT NULL DEFAULT 'empty',
    points_awarded INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_all_user (user_id, created_at),
    KEY idx_all_activity (activity_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_exchange_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id INT UNSIGNED NOT NULL DEFAULT 1,
    name VARCHAR(100) NOT NULL,
    cost_points INT NOT NULL DEFAULT 0,
    stock INT DEFAULT NULL COMMENT 'NULL=不限',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    remark VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_aei_activity (activity_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_exchange_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id INT UNSIGNED NOT NULL DEFAULT 1,
    user_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED DEFAULT NULL,
    item_name VARCHAR(100) NOT NULL,
    cost_points INT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING/DONE/CANCELLED',
    admin_note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fulfilled_at DATETIME DEFAULT NULL,
    fulfilled_by INT UNSIGNED DEFAULT NULL,
    KEY idx_aeo_status (status, created_at),
    KEY idx_aeo_user (user_id, created_at),
    KEY idx_aeo_activity (activity_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 默认活动（兼容旧单活动数据）
INSERT INTO activities (id, name, title, description, status, sort_order)
SELECT 1, '默认抽奖',
       COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = 'activity_share_title' LIMIT 1), '活动抽奖 · 积分兑换'),
       COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = 'activity_share_desc' LIMIT 1), '登录参与抽奖，积分可兑换好礼'),
       CASE WHEN COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = 'activity_enabled' LIMIT 1), '1') = '1' THEN 1 ELSE 0 END,
       10
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM activities WHERE id = 1 LIMIT 1);

CALL qz_add_column_if_missing('activity_lottery_prizes', 'activity_id', "INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '所属活动' AFTER id");
CALL qz_add_column_if_missing('activity_lottery_logs', 'activity_id', "INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '所属活动' AFTER id");
CALL qz_add_column_if_missing('activity_exchange_items', 'activity_id', "INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '所属活动' AFTER id");
CALL qz_add_column_if_missing('activity_exchange_orders', 'activity_id', "INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '所属活动' AFTER id");

UPDATE activity_lottery_prizes SET activity_id = 1 WHERE activity_id IS NULL OR activity_id = 0;
UPDATE activity_lottery_logs SET activity_id = 1 WHERE activity_id IS NULL OR activity_id = 0;
UPDATE activity_exchange_items SET activity_id = 1 WHERE activity_id IS NULL OR activity_id = 0;
UPDATE activity_exchange_orders SET activity_id = 1 WHERE activity_id IS NULL OR activity_id = 0;

-- 旧钱包次数迁到默认活动
INSERT INTO activity_user_chances (user_id, activity_id, chances)
SELECT w.user_id, 1, w.lottery_chances
FROM activity_wallets w
WHERE w.lottery_chances > 0
  AND NOT EXISTS (
      SELECT 1 FROM activity_user_chances c
      WHERE c.user_id = w.user_id AND c.activity_id = 1
  );

INSERT INTO system_settings (setting_key, setting_value) VALUES
('activity_enabled', '1'),
('activity_share_title', '活动抽奖 · 积分兑换'),
('activity_share_desc', '登录参与抽奖，积分可兑换好礼'),
('activity_share_logo', '')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO activity_point_presets (name, points, sort_order, status)
SELECT '完成日常任务', 10, 10, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM activity_point_presets WHERE name = '完成日常任务' LIMIT 1);
INSERT INTO activity_point_presets (name, points, sort_order, status)
SELECT '分享活动', 5, 20, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM activity_point_presets WHERE name = '分享活动' LIMIT 1);

INSERT INTO activity_lottery_prizes (activity_id, name, weight, prize_type, points_value, sort_order, status)
SELECT 1, '谢谢参与', 50, 'empty', 0, 10, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM activity_lottery_prizes WHERE activity_id = 1 AND name = '谢谢参与' LIMIT 1);
INSERT INTO activity_lottery_prizes (activity_id, name, weight, prize_type, points_value, sort_order, status)
SELECT 1, '积分+10', 30, 'points', 10, 20, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM activity_lottery_prizes WHERE activity_id = 1 AND name = '积分+10' LIMIT 1);
INSERT INTO activity_lottery_prizes (activity_id, name, weight, prize_type, points_value, sort_order, status)
SELECT 1, '积分+50', 15, 'points', 50, 30, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM activity_lottery_prizes WHERE activity_id = 1 AND name = '积分+50' LIMIT 1);
INSERT INTO activity_lottery_prizes (activity_id, name, weight, prize_type, points_value, sort_order, status)
SELECT 1, '神秘礼品（人工发放）', 5, 'claim', 0, 40, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM activity_lottery_prizes WHERE activity_id = 1 AND name = '神秘礼品（人工发放）' LIMIT 1);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.code = 'activity.manage'
WHERE r.code IN ('BOSS', 'CUSTOMER_SERVICE');

-- 顾客端展示字段 / 动态
SET @qz_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'business_types' AND COLUMN_NAME = 'cover_url'
);
SET @qz_sql := IF(@qz_col = 0,
  'ALTER TABLE business_types ADD COLUMN cover_url VARCHAR(512) DEFAULT NULL COMMENT ''封面图URL'' AFTER remark',
  'SELECT 1');
PREPARE qz_stmt FROM @qz_sql; EXECUTE qz_stmt; DEALLOCATE PREPARE qz_stmt;

SET @qz_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'business_types' AND COLUMN_NAME = 'original_price'
);
SET @qz_sql := IF(@qz_col = 0,
  'ALTER TABLE business_types ADD COLUMN original_price DECIMAL(12,2) DEFAULT NULL COMMENT ''划线原价'' AFTER unit_price',
  'SELECT 1');
PREPARE qz_stmt FROM @qz_sql; EXECUTE qz_stmt; DEALLOCATE PREPARE qz_stmt;

SET @qz_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'business_types' AND COLUMN_NAME = 'badge_text'
);
SET @qz_sql := IF(@qz_col = 0,
  'ALTER TABLE business_types ADD COLUMN badge_text VARCHAR(32) DEFAULT NULL COMMENT ''角标如限时优惠'' AFTER cover_url',
  'SELECT 1');
PREPARE qz_stmt FROM @qz_sql; EXECUTE qz_stmt; DEALLOCATE PREPARE qz_stmt;

CREATE TABLE IF NOT EXISTS client_feeds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    cover_url VARCHAR(512) DEFAULT NULL,
    content TEXT DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO client_feeds (title, content, sort_order, status)
SELECT '为什么选择我们？', '三重检验 · 平均 15 选 1 · 售后 24 小时受理，服务到满意为止。', 10, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM client_feeds WHERE title = '为什么选择我们？' LIMIT 1);
INSERT INTO client_feeds (title, content, sort_order, status)
SELECT '下单流程引导', '① 选择游戏板块 ② 挑选陪单项目 ③ 填写信息下单并联系客服。', 20, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM client_feeds WHERE title = '下单流程引导' LIMIT 1);

INSERT INTO system_settings (setting_key, setting_value) VALUES
('customer_service_link', ''),
('customer_banner_title', '所有订单都附赠活动福利'),
('customer_banner_desc', '登录下单 · 积分抽奖等你来'),
('customer_order_rules', '1. 禁止未成年人下单。\n2. 下单后请按客服指引提供账号信息。\n3. 对服务不满意请联系客服处理。\n4. 谨防私下交易与诈骗。\n5. 俱乐部提供陪玩服务，不做兜底承诺。')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- 清理辅助过程
DROP PROCEDURE IF EXISTS qz_add_column_if_missing;
DROP PROCEDURE IF EXISTS qz_modify_column_if_exists;

-- ============================================================
-- 自检（跑完看结果：关键列为 1 表示已就绪）
-- ============================================================
SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_settings') AS has_system_settings,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions') AS has_permissions,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_orders') AS has_client_orders,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extra_fee_items') AS has_extra_fees,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settlement_boards') AS has_settlement_boards,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_wallets') AS has_activity,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'paid_amount') AS has_paid_amount,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'dispatcher_id') AS has_dispatcher_id,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'user_id') AS has_customer_user_id,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'co_staff_id') AS has_co_staff_id;

-- ============================================================
-- 完成。建议检查：上面自检关键列应为 1；然后重新登录后台。
-- ============================================================
