<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/SettingsService.php';
require_once __DIR__ . '/../includes/CosService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('settings');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');
$testResult = flash('cos_test');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    try {
        if ($action === 'test_cos') {
            if (SettingsService::shouldUseLocalStorage()) {
                throw new RuntimeException('当前为本地存储模式，请先将存储方式设为 COS 并填写密钥');
            }
            $cos = new CosService(SettingsService::getCosConfig());
            $key = rtrim(SettingsService::get('cos_prefix_orders'), '/') . '/_diagnose/' . date('YmdHis') . '_test.txt';
            $cos->putText($key, 'settings test ' . date('c'));
            flash('cos_test', 'COS 测试上传成功，Key: ' . $key);
        } else {
            $pairs = [
                'brand_name'        => trim($_POST['brand_name'] ?? ''),
                'brand_logo'        => trim($_POST['brand_logo'] ?? ''),
                'brand_theme_color' => trim($_POST['brand_theme_color'] ?? '#001A72'),
                'app_version'       => trim($_POST['app_version'] ?? '1.2.0'),
                'storage_driver'    => trim($_POST['storage_driver'] ?? 'auto'),
                'cos_secret_id'     => trim($_POST['cos_secret_id'] ?? ''),
                'cos_region'        => trim($_POST['cos_region'] ?? ''),
                'cos_bucket'        => trim($_POST['cos_bucket'] ?? ''),
                'cos_prefix_orders' => trim($_POST['cos_prefix_orders'] ?? ''),
                'cos_prefix_staff'  => trim($_POST['cos_prefix_staff'] ?? ''),
            ];
            if ($pairs['brand_name'] === '') {
                throw new InvalidArgumentException('站点名称不能为空');
            }
            if ($pairs['app_version'] === '') {
                $pairs['app_version'] = '1.2.0';
            }
            $newSecret = trim($_POST['cos_secret_key'] ?? '');
            if ($newSecret !== '') {
                $pairs['cos_secret_key'] = $newSecret;
            } else {
                $pairs['cos_secret_key'] = SettingsService::get('cos_secret_key');
            }
            SettingsService::setMany($pdo, $pairs);
            flash('success', '系统设置已保存');
        }
        redirect('/admin/settings.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/settings.php');
    }
}

$s = SettingsService::getAll();
$currentPage = 'settings';
$pageTitle = '系统设置';
require __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($testResult): ?><div class="alert alert-success"><?= e($testResult) ?></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="action" value="save">

    <div class="card">
        <div class="card-header"><h2>站点品牌</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>站点名称</label>
                    <input type="text" name="brand_name" class="form-control" required value="<?= e($s['brand_name']) ?>">
                </div>
                <div class="form-group">
                    <label>Logo 路径</label>
                    <input type="text" name="brand_logo" class="form-control" value="<?= e($s['brand_logo']) ?>" placeholder="/img/logo.png">
                </div>
                <div class="form-group">
                    <label>主题色</label>
                    <input type="color" name="brand_theme_color" class="form-control" value="<?= e($s['brand_theme_color']) ?>" style="height:40px;padding:4px">
                </div>
                <div class="form-group">
                    <label>系统版本号</label>
                    <input type="text" name="app_version" class="form-control" value="<?= e($s['app_version'] ?? '1.2.0') ?>" placeholder="如 1.2.0">
                    <p style="font-size:12px;color:var(--text-muted);margin-top:6px">显示在后台页脚，方便告知用户当前版本</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>结算系数</h2></div>
        <div class="card-body">
            <p style="font-size:13px;color:var(--text-muted);margin:0">
                默认结算倍率已移至
                <a href="/admin/business_types.php#settlement">业务类型 → 默认结算倍率</a>
                （客服可改）。特殊单在订单详情里手动调整。
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>文件存储 / 腾讯云 COS</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>存储方式</label>
                    <select name="storage_driver" class="form-control">
                        <option value="auto" <?= $s['storage_driver'] === 'auto' ? 'selected' : '' ?>>自动（无有效密钥时用本地）</option>
                        <option value="cos" <?= $s['storage_driver'] === 'cos' ? 'selected' : '' ?>>强制 COS</option>
                        <option value="local" <?= $s['storage_driver'] === 'local' ? 'selected' : '' ?>>强制本地 uploads/</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>COS SecretId</label>
                    <input type="text" name="cos_secret_id" class="form-control" value="<?= e($s['cos_secret_id']) ?>">
                </div>
                <div class="form-group">
                    <label>COS SecretKey</label>
                    <input type="password" name="cos_secret_key" class="form-control" placeholder="留空则不修改" autocomplete="new-password">
                    <?php if ($s['cos_secret_key'] !== ''): ?>
                        <p style="font-size:12px;color:var(--text-muted);margin-top:4px">已配置（<?= e(substr($s['cos_secret_key'], 0, 4)) ?>***）</p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Region</label>
                    <input type="text" name="cos_region" class="form-control" value="<?= e($s['cos_region']) ?>" placeholder="ap-chengdu">
                </div>
                <div class="form-group">
                    <label>Bucket</label>
                    <input type="text" name="cos_bucket" class="form-control" value="<?= e($s['cos_bucket']) ?>" placeholder="name-appid">
                </div>
                <div class="form-group">
                    <label>订单截图前缀</label>
                    <input type="text" name="cos_prefix_orders" class="form-control" value="<?= e($s['cos_prefix_orders']) ?>">
                </div>
                <div class="form-group">
                    <label>毛照前缀</label>
                    <input type="text" name="cos_prefix_staff" class="form-control" value="<?= e($s['cos_prefix_staff']) ?>">
                </div>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin-top:8px">
                当前实际模式：<strong><?= SettingsService::shouldUseLocalStorage() ? '本地存储' : 'COS 云存储' ?></strong>
            </p>
        </div>
    </div>

    <div style="display:flex;gap:12px;margin-bottom:24px">
        <button type="submit" class="btn btn-primary">保存设置</button>
    </div>
</form>

<form method="post" style="margin-bottom:24px">
    <input type="hidden" name="action" value="test_cos">
    <button type="submit" class="btn">测试 COS 连接</button>
</form>

<div class="card">
    <div class="card-header"><h2>说明</h2></div>
    <div class="card-body" style="font-size:13px;color:var(--text-muted);line-height:1.7">
        <p>客户、业务类型、打手、员工等请在对应菜单管理。数据库连接仍通过 <code>config/database.php</code> 或环境变量配置。</p>
        <p>开源部署后，建议在此页面完成品牌、结算、COS 配置，无需改代码。</p>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
