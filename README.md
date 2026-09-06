# Settlement / 清账系统

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

轻量级 **电竞/陪玩团队内部清账系统**：打手报单 → 审核 → 结算入账 → 提现申请 → 人工放款。  
无第三方支付对接，适合自营团队自建部署。

**PHP 8 + MySQL**，无框架，开箱即用。后台可配置品牌、结算倍率、腾讯云 COS；支持 **可配置 RBAC 角色权限** 与数据范围，无需改代码。

---

## 功能特性

### 打手端（移动端）
- 自主注册与登录，后台审核通过后启用
- 报单：客户/业务类型可搜索、微信订单号（防重复）、多张截图、整数数量、接单时间
- 订单列表、预计到手金额（按报单时倍率快照计算）
- 提现申请与记录
- 修改密码

### 管理后台（PC）
| 能力 | 说明 |
|------|------|
| 工作台 / 订单 / 数据统计 | 审核、趋势与多维度统计 |
| 角色权限（RBAC） | 自定义角色、权限点勾选、数据范围（全部/本人/指定打手·客户·业务） |
| 结算倍率 | 独立「结算倍率」页；公式：订单金额 × 基础倍率 × 打手倍率；历史订单不重算 |
| 系统设置 | 品牌名 / Logo / 主题色、COS、存储方式 |
| 提现 / 注册审核 / 打手管理 | 按角色权限控制 |
| 客户 / 业务类型 | 预存余额客户、审核通过自动扣款 |
| 员工管理 | 多角色分配（含自定义角色）、逻辑删除 |

内置系统角色（可在此基础上增删改权限）：**老板、客服、考官、打手**（原「管理员」已合并进老板）。

### 打手档案
- 入职时间、考核官、押金
- **毛照多图**上传 / 查看 / 下载（权限可控）
- **荣誉记录**（标题 + 多图）

### 订单与结算
- 报单时写入倍率快照（`rate_a` / `rate_b`），之后改倍率不影响旧单
- 订单 / 用户支持逻辑删除
- 客户预存余额，审核通过自动扣款

### 存储
- 腾讯云 COS（推荐）或本地 `uploads/`（密钥无效时自动降级）

---

## 快速开始

### 环境要求
- PHP 8.0+（扩展：`pdo_mysql`, `curl`, `fileinfo`, `json`）
- MySQL 5.7+ / MariaDB 10.3+

### 1. 克隆项目

```bash
git clone https://github.com/guanyisheng/settlement.git
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

新装会带上最新表结构（含 RBAC）。若已有旧库，见下方「数据库迁移」。

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
| 客服 | cs1 | cs123 |
| 打手 | staff1 | staff123 |

**首次登录后请立即修改默认密码。**

### 6. 后台配置（推荐）

老板登录后依次配置：
1. **系统设置**：站点名称、Logo、主题色、COS
2. **结算倍率**：基础倍率 / 打手倍率
3. **角色权限**：按需调整各角色权限与数据范围
4. **员工管理**：为账号勾选角色

---

## 目录结构

```
├── admin/              # 管理后台（含 roles / rates / employees 等）
├── staff/              # 打手移动端
├── includes/           # 业务逻辑（Auth / RBAC / Order / Settlement…）
├── config/             # 配置文件（database/cos 不提交 git）
├── database/           # SQL 结构与迁移
├── uploads/            # 本地存储（不提交 git）
├── scripts/            # 诊断 / 迁移辅助脚本
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

按顺序在 phpMyAdmin 或命令行执行（若某步报 Duplicate column 可跳过）：

- `migrate_wechat_order_no.sql`
- `migrate_order_screenshot.sql` / `migrate_screenshot_keys.sql`
- `migrate_staff_profile.sql`
- `migrate_staff_photo_key.sql`
- `migrate_settlement_roles_customers.sql`
- `migrate_add_boss.sql`
- `migrate_system_settings.sql`
- **`migrate_rbac_v2.sql`**（多角色 RBAC、毛照多图、荣誉、倍率快照、逻辑删除）

也可一次执行汇总脚本：`database/本次更新_总SQL.sql`（可重复执行，已存在的表/列会跳过）。

辅助：`php scripts/migrate_rbac.php`（若环境支持）。

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
- 订单金额与结算金额后端计算，报单写入倍率快照
- 后台按权限点与数据范围鉴权（RBAC 未就绪时回退旧角色矩阵）
- **切勿**将 `config/database.php`、`config/cos.php` 提交到公开仓库
- 生产环境使用 HTTPS

---

## 开源协议

[MIT License](LICENSE)

---

## 贡献

欢迎 Issue / Pull Request.
