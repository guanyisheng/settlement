-- 打手收款/转账二维码（提现时客服扫码打款用）
-- 先选中业务库再执行，不要 USE settlement;

ALTER TABLE users
  ADD COLUMN pay_qr_key VARCHAR(512) DEFAULT NULL COMMENT '收款转账二维码存储Key' AFTER photo_key;
