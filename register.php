<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/brand.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/UserService.php';
require_once __DIR__ . '/includes/UploadLimits.php';
require_once __DIR__ . '/includes/helpers.php';

Auth::startSession();

if (Auth::check()) {
    Auth::redirectHome();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = Database::getConnection();
        // photos[] 多图 或 兼容旧字段 photo
        $photoInput = null;
        if (!empty($_FILES['photos']) && is_array($_FILES['photos'])) {
            $photoInput = $_FILES['photos'];
        } elseif (!empty($_FILES['photo']) && is_array($_FILES['photo'])) {
            $photoInput = $_FILES['photo'];
        }
        UserService::registerStaff($pdo, $_POST, $photoInput);
        flash('success', '注册申请已提交，请等待审核通过后再登录');
        redirect('/login.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = brandTitle('打手注册');
$bodyClass = 'login-body';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="<?= brandThemeColor() ?>">
    <link rel="icon" href="<?= brandLogo() ?>" type="image/png">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/staff/assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<div class="app-shell">
    <div class="login-page">
        <div class="login-brand">
            <img src="<?= brandLogo() ?>" alt="<?= e(brandName()) ?>" class="brand-logo">
            <p class="login-tagline">打手注册</p>
        </div>
        <div class="login-card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" id="registerForm">
                <div class="form-group">
                    <label>用户名 <span class="required-mark">*</span></label>
                    <input type="text" name="username" id="regUsername" class="form-control" required autofocus
                           autocomplete="username" placeholder="仅英文/数字/下划线，如 zhangsan01"
                           pattern="[a-zA-Z0-9_]{3,50}"
                           title="3-50位字母、数字或下划线，不能用中文"
                           value="<?= e($_POST['username'] ?? '') ?>">
                    <p class="order-no-hint" id="usernameHint">输完后自动检测是否重复；不能用中文</p>
                </div>
                <div class="form-group">
                    <label>昵称 / 打手名</label>
                    <input type="text" name="nickname" class="form-control"
                           placeholder="选填，可用中文；默认同用户名"
                           value="<?= e($_POST['nickname'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>毛照（选填）</label>
                    <input type="file" name="photos[]" id="regPhotos" class="form-control file-input" multiple
                           accept="image/jpeg,image/png,image/webp">
                    <p class="order-no-hint">可不传。可多选；<?= e(UploadLimits::hint()) ?></p>
                    <p class="order-no-hint" id="regPhotoSize" style="display:none"></p>
                </div>
                <div class="form-group">
                    <label>密码 <span class="required-mark">*</span></label>
                    <input type="password" name="password" class="form-control" required
                           autocomplete="new-password" placeholder="至少6位" minlength="6">
                </div>
                <div class="form-group">
                    <label>确认密码 <span class="required-mark">*</span></label>
                    <input type="password" name="password_confirm" class="form-control" required
                           autocomplete="new-password" placeholder="再次输入密码" minlength="6">
                </div>
                <button type="submit" class="btn btn-primary">提交注册申请</button>
            </form>
            <p class="login-role-hint">提交成功后账号为「待审核」，老板/考官在后台「注册审核」通过后才能登录</p>
            <div class="auth-footer">
                已有账号？<a href="/login.php">去登录</a>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const maxBatch = <?= (int) UploadLimits::BATCH_MAX_BYTES ?>;
    const input = document.getElementById('regPhotos');
    const preview = document.getElementById('regPhotoSize');
    const form = document.getElementById('registerForm');
    const usernameInput = document.getElementById('regUsername');
    const usernameHint = document.getElementById('usernameHint');
    let usernameOk = true;
    let checkTimer = null;
    let lastChecked = '';

    function setUsernameHint(text, ok) {
        if (!usernameHint) return;
        usernameHint.textContent = text;
        usernameHint.style.color = ok === null ? 'var(--text-muted)' : (ok ? 'var(--success)' : 'var(--danger)');
    }

    async function checkUsername(force) {
        const username = (usernameInput?.value || '').trim();
        if (!username) {
            setUsernameHint('输完后自动检测是否重复；不能用中文', null);
            usernameOk = false;
            return false;
        }
        if (!/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
            setUsernameHint('须为 3–50 位英文、数字或下划线，不能用中文', false);
            usernameOk = false;
            return false;
        }
        if (!force && username === lastChecked) {
            return usernameOk;
        }
        setUsernameHint('检测中…', null);
        try {
            const res = await fetch('/check_username.php?username=' + encodeURIComponent(username), {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            lastChecked = username;
            usernameOk = !!data.available;
            setUsernameHint(data.message || (usernameOk ? '用户名可用' : '用户名不可用'), usernameOk);
            return usernameOk;
        } catch (e) {
            setUsernameHint('检测失败，提交时会再校验', null);
            usernameOk = true; // 网络失败不挡提交，后端仍会校验
            return true;
        }
    }

    usernameInput?.addEventListener('blur', function () {
        checkUsername(true);
    });
    usernameInput?.addEventListener('input', function () {
        usernameOk = false;
        lastChecked = '';
        clearTimeout(checkTimer);
        checkTimer = setTimeout(function () { checkUsername(true); }, 450);
        setUsernameHint('输完后自动检测是否重复；不能用中文', null);
    });

    function checkPhotos() {
        let total = 0;
        Array.from(input.files || []).forEach(f => total += f.size);
        if (!input.files || !input.files.length) {
            preview.style.display = 'none';
            return true;
        }
        const mb = (total / 1024 / 1024).toFixed(1);
        preview.style.display = 'block';
        preview.textContent = '已选 ' + input.files.length + ' 张，合计约 ' + mb + 'MB（上限 30MB）';
        preview.style.color = total > maxBatch ? 'var(--danger)' : 'var(--text-muted)';
        return total <= maxBatch;
    }
    input?.addEventListener('change', checkPhotos);
    form?.addEventListener('submit', async function (e) {
        if (!checkPhotos()) {
            e.preventDefault();
            alert('一次最多上传 30MB，请减少图片或压缩后再传');
            return;
        }
        e.preventDefault();
        const ok = await checkUsername(true);
        if (!ok) {
            alert(usernameHint?.textContent || '用户名不可用');
            usernameInput?.focus();
            return;
        }
        form.submit();
    });
})();
</script>
</body>
</html>
