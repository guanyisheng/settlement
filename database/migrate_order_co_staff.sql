-- 双人单：附加打手（同一微信订单号仅一条订单）
-- 执行前请选择业务库，勿 USE settlement

ALTER TABLE orders
    ADD COLUMN co_staff_id INT UNSIGNED NULL COMMENT '附加打手用户ID' AFTER staff_id;

ALTER TABLE orders
    ADD INDEX idx_co_staff_id (co_staff_id);

-- 可选外键（若环境已有 FK 风格可解开）
-- ALTER TABLE orders
--     ADD CONSTRAINT fk_orders_co_staff FOREIGN KEY (co_staff_id) REFERENCES users(id);
