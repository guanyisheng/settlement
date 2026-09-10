-- 线上已有库：把系统版本号升到 3.0.0-beta（可在后台「系统设置」再改）
INSERT INTO system_settings (setting_key, setting_value) VALUES ('app_version', '3.0.0-beta')
ON DUPLICATE KEY UPDATE setting_value = '3.0.0-beta';
