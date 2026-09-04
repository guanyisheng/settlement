-- 打手档案字段扩展
ALTER TABLE users
    ADD COLUMN hired_at DATE DEFAULT NULL COMMENT '入职时间' AFTER role,
    ADD COLUMN examiner VARCHAR(100) DEFAULT NULL COMMENT '考核官' AFTER hired_at,
    ADD COLUMN deposit VARCHAR(50) DEFAULT NULL COMMENT '押金' AFTER examiner,
    ADD COLUMN photo_uploaded TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否上传毛照' AFTER deposit;
