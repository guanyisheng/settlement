-- 毛照实际存储路径

ALTER TABLE users
    ADD COLUMN photo_key VARCHAR(255) DEFAULT NULL COMMENT '毛照存储Key' AFTER photo_uploaded;
