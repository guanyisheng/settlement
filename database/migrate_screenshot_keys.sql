-- 订单截图字段（支持多张，JSON 数组存储）
-- 若报错 Unknown column 'screenshot_key'，说明从未添加过该列，执行「步骤1」
-- 若报错 Duplicate column name，说明已有 VARCHAR 版，改执行「步骤2」


-- 步骤1：首次添加（大多数情况执行这句）
ALTER TABLE orders
    ADD COLUMN screenshot_key TEXT DEFAULT NULL COMMENT 'COS截图Key JSON数组' AFTER wechat_order_no;

-- 步骤2：若步骤1报 Duplicate column，改执行下面这句（把 VARCHAR 扩为 TEXT）
-- ALTER TABLE orders
--     MODIFY COLUMN screenshot_key TEXT DEFAULT NULL COMMENT 'COS截图Key JSON数组';
