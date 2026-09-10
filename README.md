# Settlement / 清账系统

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Release](https://img.shields.io/badge/release-3.0.0--beta-orange.svg)](https://github.com/guanyisheng/settlement/releases/tag/v3.0.0-beta)

轻量级 **电竞 / 陪玩团队内部清账系统**：打手报单 → 审核结算 → 提现申请 → 人工扫码放款。  
无第三方支付对接，适合自营团队自建部署。

**当前版本：`3.0.0-beta`**（PHP 8 + MySQL，无框架）。支持品牌配置、腾讯云 COS、**RBAC 多角色**、一人/双人接单、统一用户中心、打手资料（毛照 / 荣誉 / 收款码）等。

- 日常操作：[`使用手册.md`](使用手册.md)
- 正式预发布说明：[v3.0.0-beta Release](https://github.com/guanyisheng/settlement/releases/tag/v3.0.0-beta)

---

## 3.0.0-beta 亮点

| 模块 | 内容 |
|------|------|
| 一人 / 双人接单 | 报单可选接单方式；双人须搜索并点选附加打手；同微信订单号只报一次 |
| 结算倍率 | 业务类型页可配 **基础 / 一人 / 双人每人** 三档默认倍率 |
| 统一用户中心 | `用户管理` 合并打手与员工；详情页编辑指定用户（不再串成登录者） |
| 数据统计 | 快捷日期芯片 + 自定义起止；抽成 / 流水等指标 |
| 登录体验 | 可选「记住我」约 30 天；多角色入口按权限分流 |
| 收款码 | 按用户 ID 读取，多角色账号提现详情不再 404 |
| 附加打手搜索 | 支持 RBAC「打手」角色（含考官+打手）；输入框可见可点，可点「搜索」 |

---

## 功能特性

### 打手端（移动端）

- 自主注册（用户名实时查重）、登录；后台审核通过后启用
- **报单**：客户 / 业务可搜索；**一人或双人接单**；微信订单号防重；多张截图；数量与接单时间
- 双人单：搜索昵称/用户名 → 点选搭档 → 提交；预计到手按一人/双人规则预览
- 订单列表与预计到手（按报单时倍率快照）
- 提现申请与记录（展示自己上传的收款码便于后台扫码）
- **资料 / 修改信息**：昵称、毛照多图、荣誉、收款转账二维码、可选改密
- 底部导航：订单 / 报单 / 毛照 / 荣誉 / 提现

### 管理后台（PC，兼适配窄屏）

| 能力 | 说明 |
|------|------|
| 工作台 / 订单 | 审核、详情、本单倍率或金额调整 |
| 数据统计 | 区间筛选、流水 / 提现 / 抽成等 |
| 角色权限（RBAC） | 自定义角色、权限点、数据范围 |
| **用户管理** | 打手 + 员工统一列表；详情改资料 / 角色 / 状态 / 密码 |
| 业务类型 | 维护单价；页顶配置 **基础 / 一人 / 双人每人** 默认倍率 |
| 提现管理 | 展示收款码，扫码后确认放款 |
| 注册审核 | 新打手启用 |
| 客户管理 | 预存余额；审核通过可自动扣款 |
| 系统设置 | 品牌、**系统版本号**、COS / 存储 |
| 角色权限 / 员工 | 一人可多角色（如客服 & 考官） |

内置角色：**老板、客服、考官、打手**（原「管理员」已并入老板）。一人可多角色，权限取并集。

### 结算公式（3.0）

订单金额一般为「业务单价 × 数量」。打手侧份额：

```
一人接单：到手 ≈ 订单金额 × 基础倍率 × 一人倍率
双人接单：每人 ≈ 订单金额 × 基础倍率 × 双人每人倍率
          （双人池合计约为两人份额之和）
```

默认示例（可在后台改）：基础 `0.8`（80%）、一人 `1.0`（100%）、双人每人 `0.5`（50%）。

- 报单时写入 `rate_a` / `rate_b` / `staff_amount` 等快照；改默认倍率**不重算历史单**
- 特殊单可在订单详情改本单倍率或直接填结算金额
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
git checkout v3.0.0-beta   # 可选：固定到本预发布标签
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

新装含最新表结构（RBAC、一人/双人、毛照/荣誉、结算快照等）。旧库见下方「升级到 3.0.0-beta」。

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

1. **系统设置**：站点名称、Logo、主题色、版本号（默认 `3.0.0-beta`）、COS  
2. **业务类型**：业务单价 + 顶部 **基础 / 一人 / 双人每人** 倍率  
3. **角色权限 / 用户管理**：按需分配多角色  
4. 打手在端上「资料」上传收款二维码，便于提现扫码放款  

---

## 目录结构

```
├── admin/              # 管理后台（含 users / user_detail / statistics…）
├── staff/              # 打手移动端（报单含一人/双人）
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

推荐 **Nginx + PHP-FPM**，网站根目录为项目根目录。也可直接把项目文件夹覆盖上传到服务器（团队常用方式）。

上传相关建议：

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
覆盖代码后请对打手端 **强刷缓存**（尤其 `staff/assets/css/style.css`），再测双人附加打手搜索框。

---

## 升级到 3.0.0-beta（已有旧库）

在 phpMyAdmin **先选中业务库**再执行（不要写死 `USE settlement`，生产库名可能不同）。

### 推荐

1. 备份数据库  
2. 覆盖代码到 `main` / `v3.0.0-beta`  
3. 按需执行：

| 脚本 | 说明 |
|------|------|
| `database/一键注入_全部更新.sql` | 一键尽量对齐新结构（可重复执行） |
| 或 `database/本次更新_总SQL.sql` | 另一份汇总迁移 |

### 3.0 相关补丁（若一键脚本未覆盖）

| 脚本 | 说明 |
|------|------|
| `migrate_order_co_staff.sql` | 订单附加打手字段 |
| `migrate_settlement_rate_solo.sql` | 一人接单倍率配置项 |
| `migrate_fix_staff_like_role.sql` | 多角色打手可被搜到（修正 legacy `role`） |
| `migrate_app_version_3_0_0_beta.sql` | 系统版本号改为 `3.0.0-beta` |

### 历史迁移（按缺口补）

| 脚本 | 说明 |
|------|------|
| `migrate_rbac_v2.sql` | RBAC、毛照多图、荣誉、倍率快照等 |
| `migrate_pay_qr.sql` | 收款二维码 `pay_qr_key` |
| `migrate_system_settings.sql` | 系统设置表 |
| `reset_total_income_to_zero.sql` | **可选运维**：已通过订单结算清零（慎用，先备份） |

辅助：`php scripts/migrate_rbac.php`（若环境支持）。全新安装直接 `php install.php`。

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

- 密码 bcrypt 存储；可选「记住我」会话
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
预发布标签：[`v3.0.0-beta`](https://github.com/guanyisheng/settlement/releases/tag/v3.0.0-beta)。
