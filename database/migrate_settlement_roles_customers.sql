-- 结算金额、考官角色、客户预存余额
-- 若某步报 Duplicate column，跳过该步继续

USE settlement;

-- 1. 考官角色（若已含 EXAMINER 会报错，可忽略）
ALTER TABLE users
    MODIFY COLUMN role ENUM('STAFF', 'CUSTOMER_SERVICE', 'EXAMINER', 'BOSS', 'ADMIN') NOT NULL DEFAULT 'STAFF';

-- 2. 打手结算金额
ALTER TABLE orders
    ADD COLUMN staff_amount DECIMAL(12,2) DEFAULT NULL COMMENT '打手结算金额' AFTER amount;

UPDATE orders
SET staff_amount = ROUND(amount * 0.8 * 0.5, 2)
WHERE staff_amount IS NULL;

-- 3. 客户预存
ALTER TABLE customers
    ADD COLUMN balance DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '预存余额' AFTER remark;

ALTER TABLE customers
    ADD COLUMN is_prepaid TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=预存客户，审核扣余额' AFTER balance;
