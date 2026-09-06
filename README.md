# Settlement / 清账系统

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

轻量级 **电竞/陪玩团队内部清账系统**：打手报单 → 审核结算 → 提现申请 → 人工扫码放款。  
无第三方支付对接，适合自营团队自建部署。

**PHP 8 + MySQL**，无框架。支持品牌配置、腾讯云 COS、**RBAC 多角色权限**、打手资料（毛照 / 荣誉 / 收款码）等。

操作说明见仓库内 [`使用手册.md`](使用手册.md)（面向客服 / 打手 / 老板的日常用法）。

---

## 功能特性

### 打手端（移动端）
- 自主注册（用户名实时查重）、登录；后台审核通过后启用
- 报单：客户 / 业务可搜索、微信订单号防重、多张截图、数量与接单时间
- 订单列表与预计到手（按报单时倍率快照）
- 提现申请与记录
- **修改信息**（原改密入口）：昵称、毛照多图、荣誉、收款转账二维码、可选改密
- 底部导航：订单 / 报单 / 毛照 / 荣誉 / 提现

### 管理后台（PC）
| 能力 | 说明 |
|------|------|
| 工作台 / 订单 / 数据统计 | 审核、趋势与多维统计 |
| 角色权限（RBAC） | 自定义角色、权限点、数据范围 |
| 业务类型 | 维护单价；**页顶配置默认结算倍率**（客服可改） |
| 订单审核结算 | 默认按倍率；特殊单可改本单倍率或直接填结算金额（改倍率会自动重算） |
| 提现管理 | 详情展示打手上传的收款码，扫码后确认放款 |
| 注册审核 / 打手 / 员工 | 打手与员工列表支持搜索；禁用账号沉底 |
| 客户管理 | 预存余额；审核通过可自动扣款 |
| 系统设置 | 品牌、**系统版本号**、COS / 存储方式 |
| 员工管理 | 多角色勾选（如客服&考官） |

内置角色：**老板、客服、考官、打手**（原「管理员」已并入老板）。一人可多角色，权限取并集。

### 结算公式

```
打手到手 = 订单金额 × 基础倍率 × 打手倍率
```

- 报单时写入 `rate_a` / `rate_b` / `staff_amount` 快照；改默认倍率**不重算历史单**
- 默认倍率在 **业务类型** 页配置；特殊单在订单详情处理
- 旧入口 `/admin/rates.php` 已重定向到业务类型页

### 上传限制
- 毛照 / 荣誉 / 注册毛照：可多选；单次合计 ≤ **30MB**，单张 ≤ 15MB（JPG/PNG/WEBP）
- 报单截图：最多 9 张，单张约 5MB
- 建议 Nginx / PHP `client_max_body_size` / `upload_max_filesize` ≥ 50MB

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

新装含最新表结构（RBAC、毛照/荣誉、结算快照等）。旧库见下方「数据库迁移」。

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

老板登录后：
1. **系统设置**：站点名称、Logo、主题色、版本号、COS
2. **业务类型**：业务单价 + 顶部默认结算倍率
3. **角色权限 / 员工管理**：按需分配多角色
4. 打手在端上「资料」上传收款二维码，便于提现扫码放款

---

## 目录结构

```
├── admin/              # 管理后台
├── staff/              # 打手移动端
├── includes/           # 业务逻辑（Auth / RBAC / Order / Settlement…）
├── config/             # 配置（database.php / cos.php 不提交）
├── database/           # SQL 结构与迁移
├── uploads/            # 本地存储（不提交）
├── scripts/            # 诊断 / 迁移脚本
├── 使用手册.md          # 三端操作说明（用户向）
├── install.php
└── router.php
```

---

## 生产部署

推荐 **Nginx + PHP-FPM**，网站根目录为项目根目录。上传相关建议：

```nginx
client_max_body_size 50m;
```

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/settlement;
    index index.php;
    client_max_body_size 50m;

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

PHP 侧建议 `upload_max_filesize` / `post_max_size` ≥ `50M`。

---

## 数据库迁移（已有旧库）

在 phpMyAdmin **先选中业务库**再执行（不要写死 `USE settlement`，生产库名可能不同）。

推荐一次执行：

- **`database/本次更新_总SQL.sql`**（可重复执行，已存在表/列会跳过）

或按需单独执行，例如：

| 脚本 | 说明 |
|------|------|
| `migrate_rbac_v2.sql` | RBAC、毛照多图、荣誉、倍率快照等 |
| `migrate_pay_qr.sql` | 打手收款二维码字段 `pay_qr_key`（提现扫码用） |
| `migrate_system_settings.sql` | 系统设置表 |
| `reset_total_income_to_zero.sql` | **可选运维**：将已通过订单结算金额清零（慎用，先备份） |

辅助：`php scripts/migrate_rbac.php`（若环境支持）。新安装直接 `php install.php`。

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
- **切勿**将 `config/database.php`、`config/cos.php`、密钥提交到公开仓库
- `.cursor/` 为本地编辑器配置，已 gitignore，勿提交
- 生产环境使用 HTTPS

---

## 开源协议

[MIT License](LICENSE)

---

## 贡献

欢迎 Issue / Pull Request。请从 `main` 拉取 `feature/…` 或 `fix/…` 分支开发，勿直接推 `main`。
