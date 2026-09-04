-- 订单截图字段（首次添加，直接 TEXT 支持多张）
ALTER TABLE orders
    ADD COLUMN screenshot_key TEXT DEFAULT NULL COMMENT 'COS截图Key JSON数组' AFTER wechat_order_no;
