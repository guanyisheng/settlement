# Settlement / 清账系统

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

轻量级 **电竞/陪玩团队内部清账系统**：打手报单 → 审核 → 结算入账 → 提现申请 → 人工放款。  
无第三方支付对接，适合自营团队自建部署。

**PHP 8 + MySQL**，无框架，开箱即用，**后台可配置**品牌、结算系数、腾讯云 COS，无需改代码。

---

## 功能特性

### 打手端（移动端）
- 报单：客户/业务类型可搜索、微信订单号、多张截图、整数数量、接单时间
- 订单列表、预计到手金额（可配置结算系数）
- 提现申请与记录
- 修改密码

### 管理后台（PC）
| 模块 | 老板 | 客服 | 考官 |
|------|:----:|:----:|:----:|
| 工作台 / 订单 / 数据统计 | ✅ | | |
| 系统设置（品牌/COS/结算） | ✅ | | |
| 提现 / 注册审核 / 打手管理 | ✅ | ✅ | ✅ |
| 客户 / 业务类型 | ✅ | ✅ | |
| 员工管理 | ✅ | | |

- 打手档案：入职时间、考核官、押金、**毛照上传**
- 客户预存余额，审核通过自动扣款
- 打手结算：`订单金额 × 系数A × 系数B`（后台可调）

### 存储
- 腾讯云 COS（推荐）或本地 `uploads/`（开发/无 COS 时自动降级）

---

## 快速开始

### 环境要求
- PHP 8.0+（扩展：`pdo_mysql`, `curl`, `fileinfo`, `json`）
- MySQL 5.7+ / MariaDB 10.3+

### 1. 克隆项目

```bash
git clone https://github.com/YOUR_USER/settlement.git
cd settlement
```

### 2. 配置文件

```bash
cp config/database.example.php config/database.php
cp config/cos.example.php config/cos.php   # 可选，也可在后台【系统设置】填写 COS
```

编辑 `config/database.php` 填入数据库连接信息。

### 3. 初始化数据库

```bash
php install.php
```

### 4. 目录权限

```bash
chmod -R 755 uploads
```

### 5. 启动（开发）

```bash
php -S localhost:8080 router.php
```

访问：http://localhost:8080/login.php

| 角色 | 账号 | 密码 |
|------|------|------|
| 老板 | boss1 | boss123 |
| 管理员 | admin | admin123 |
| 客服 | cs1 | cs123 |
| 打手 | staff1 | staff123 |

**首次登录后请立即修改默认密码。**

### 6. 后台配置（推荐）

老板登录 → **系统设置**：
- 站点名称、Logo、主题色
- 结算系数 A / B
- COS SecretId、SecretKey、Bucket、Region
- 点击「测试 COS 连接」验证

---

## 目录结构

```
├── admin/              # 管理后台
├── staff/              # 打手移动端
├── includes/           # 业务逻辑
├── config/             # 配置文件（database/cos 不提交 git）
├── database/           # SQL 结构与迁移
├── uploads/            # 本地存储（不提交 git）
├── scripts/            # 诊断脚本
├── install.php         # 一键安装
└── router.php          # 内置服务器路由
```

---

## 生产部署

推荐使用 **Nginx + PHP-FPM**，网站根目录指向项目根目录。

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/settlement;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ ^/uploads/ {
        try_files $uri =404;
    }
}
```

---

## 数据库迁移（已有旧库）

按顺序在 phpMyAdmin 或命令行执行 `database/` 下迁移文件（若某步报 Duplicate column 可跳过）：

- `migrate_wechat_order_no.sql`
- `migrate_order_screenshot.sql` / `migrate_screenshot_keys.sql`
- `migrate_staff_profile.sql`
- `migrate_staff_photo_key.sql`
- `migrate_settlement_roles_customers.sql`
- `migrate_add_boss.sql`
- `migrate_system_settings.sql`

新安装直接 `php install.php` 即可。

---

## 环境变量（可选）

| 变量 | 说明 |
|------|------|
| `DB_HOST` `DB_PORT` `DB_NAME` `DB_USER` `DB_PASS` | 数据库 |
| `COS_SECRET_ID` `COS_SECRET_KEY` | 腾讯云密钥（优先于后台设置） |
| `STORAGE_DRIVER` | `auto` / `cos` / `local` |

---

## 诊断

```bash
php scripts/cos_diagnose.php
```

---

## 安全说明

- 密码 bcrypt 存储
- 提现使用事务 + 行锁
- 订单金额后端计算
- **切勿**将 `config/database.php`、`config/cos.php` 提交到公开仓库
- 生产环境使用 HTTPS

---

## 开源协议

[MIT License](LICENSE)

---

## 贡献

欢迎 Issue / Pull Request。
