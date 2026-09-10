-- 修复：已勾选「打手」但 users.role 不是 STAFF 的账号（如考官+打手）
-- 选中业务库后执行。不改表结构，只纠正 role 字段，方便列表/附加打手搜索。
-- 若同时有老板身份，保持 BOSS，不降级为 STAFF。

UPDATE users u
SET u.role = 'STAFF'
WHERE u.deleted_at IS NULL
  AND u.role NOT IN ('STAFF', 'BOSS', 'ADMIN')
  AND EXISTS (
      SELECT 1
      FROM user_roles ur
      JOIN roles r ON r.id = ur.role_id
      WHERE ur.user_id = u.id
        AND r.code = 'STAFF'
        AND r.deleted_at IS NULL
        AND r.status = 1
  )
  AND NOT EXISTS (
      SELECT 1
      FROM user_roles ur2
      JOIN roles r2 ON r2.id = ur2.role_id
      WHERE ur2.user_id = u.id
        AND r2.code IN ('BOSS', 'ADMIN')
        AND r2.deleted_at IS NULL
  );

SELECT u.id, u.username, u.nickname, u.role,
       (SELECT GROUP_CONCAT(r.name SEPARATOR ',')
          FROM user_roles ur JOIN roles r ON r.id = ur.role_id
         WHERE ur.user_id = u.id) AS roles
FROM users u
WHERE u.deleted_at IS NULL
  AND EXISTS (
      SELECT 1 FROM user_roles ur
      JOIN roles r ON r.id = ur.role_id
      WHERE ur.user_id = u.id AND r.code = 'STAFF'
  )
ORDER BY u.id DESC
LIMIT 50;
