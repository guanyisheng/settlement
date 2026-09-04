-- 已有数据库升级：添加老板角色
-- 在 phpMyAdmin 中执行此脚本

ALTER TABLE users
    MODIFY COLUMN role ENUM('STAFF', 'CUSTOMER_SERVICE', 'BOSS', 'ADMIN') NOT NULL DEFAULT 'STAFF';

-- 可选：添加默认老板账号 (密码: boss123)
INSERT INTO users (username, password, nickname, role, status) VALUES
('boss1', '$2y$12$yLWb1cY2eKv/ZVxs0C39Luu4KURiamKikLFx1IpfaIi8vHNJS3FkW', '老板', 'BOSS', 1)
ON DUPLICATE KEY UPDATE nickname = VALUES(nickname);
