-- RBAC / 多角色 / 数据范围 / 毛照多图 / 荣誉 / 倍率快照 / 逻辑删除
-- 在「当前已选中的数据库」上执行（不要写 USE，避免连错库）
-- 新装可直接用更新后的 schema.sql

-- ========== 权限点 ==========
CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    group_name VARCHAR(50) NOT NULL DEFAULT '其他',
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 角色 ==========
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

-- scope_type: all | self | assigned | staff_ids | customer_ids | business_ids
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

-- ========== 毛照多图 ==========
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

-- ========== 荣誉 ==========
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

-- ========== 订单：倍率快照 + 软删 ==========
ALTER TABLE orders
    ADD COLUMN rate_a DECIMAL(8,4) DEFAULT NULL COMMENT '创建时基础倍率快照' AFTER staff_amount,
    ADD COLUMN rate_b DECIMAL(8,4) DEFAULT NULL COMMENT '创建时打手倍率快照' AFTER rate_a,
    ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER updated_at;

-- ========== 用户软删 ==========
ALTER TABLE users
    ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER updated_at;

-- ========== 提现软删（可选） ==========
ALTER TABLE withdrawals
    ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER updated_at;

-- ========== 权限种子 ==========
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

-- ========== 系统角色 ==========
INSERT INTO roles (code, name, description, is_system, status) VALUES
('BOSS', '老板', '全部权限与全部数据（等同原管理员）', 1, 1),
('CUSTOMER_SERVICE', '客服', '报单/订单/提现/客户等', 1, 1),
('EXAMINER', '考官', '打手档案/毛照/荣誉/注册审核', 1, 1),
('STAFF', '打手', '仅本人数据', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- 原 ADMIN 账号并入老板；禁用独立管理员角色
UPDATE users SET role = 'BOSS' WHERE role = 'ADMIN';
UPDATE roles SET status = 0, deleted_at = NOW(), name = '管理员(已合并到老板)'
WHERE code = 'ADMIN' AND deleted_at IS NULL;

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

-- 把现有 users.role 迁入 user_roles（ADMIN 已改写为 BOSS）
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
JOIN roles r ON r.code = CASE WHEN u.role = 'ADMIN' THEN 'BOSS' ELSE u.role END
WHERE u.role IS NOT NULL;

-- 旧单张毛照迁入 staff_photos
INSERT INTO staff_photos (staff_id, photo_key, uploaded_by, created_at)
SELECT u.id, u.photo_key, u.id, COALESCE(u.updated_at, u.created_at)
FROM users u
WHERE u.role = 'STAFF'
  AND u.photo_key IS NOT NULL
  AND u.photo_key != ''
  AND NOT EXISTS (
      SELECT 1 FROM staff_photos sp WHERE sp.staff_id = u.id AND sp.photo_key = u.photo_key AND sp.deleted_at IS NULL
  );
