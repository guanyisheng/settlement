-- 添加微信订单编号字段（打手手动填写）
ALTER TABLE orders
    ADD COLUMN wechat_order_no VARCHAR(64) NULL COMMENT '微信订单编号' AFTER order_no;

-- 已有数据：用系统订单号填充
UPDATE orders SET wechat_order_no = order_no WHERE wechat_order_no IS NULL OR wechat_order_no = '';

ALTER TABLE orders
    MODIFY COLUMN wechat_order_no VARCHAR(64) NOT NULL COMMENT '微信订单编号';

ALTER TABLE orders
    ADD UNIQUE INDEX idx_wechat_order_no (wechat_order_no);
