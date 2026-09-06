<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/StaffPhotoService.php';
require_once __DIR__ . '/../includes/HonorService.php';
require_once __DIR__ . '/../includes/StaffPhotoStorage.php';
require_once __DIR__ . '/../includes/UploadLimits.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireStaff();

$pdo = Database::getConnection();
$uid = (int) Auth::id();
$error = '';
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    try {
        if ($action === 'delete_honor') {
            HonorService::softDelete($pdo, (int) ($_POST['honor_id'] ?? 0), $uid, false);
            flash('success', '荣誉已删除');
        } else {
            UserService::updateOwnProfile($pdo, $uid, $_POST, $_FILES);
            flash('success', '资料已保存');
        }
        redirect('/staff/password.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$userRow = UserService::getById($pdo, $uid) ?: Auth::user();
$photos = [];
$honors = [];
try {
    $photos = StaffPhotoService::listByStaff($pdo, $uid);
} catch (Throwable) {
    $photos = [];
}
try {
    $honors = HonorService::listByStaff($pdo, $uid);
} catch (Throwable) {
    $honors = [];
}
$payQrKey = $userRow['pay_qr_key'] ?? null;

$currentPage = 'account';
$pageTitle = brandTitle('修改信息');
$bodyClass = 'has-nav';

require __DIR__ . '/partials/head.php';
?>
<div class="app-shell">
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="top-bar-info">
                <h1>修改信息</h1>
                <p class="subtitle"><?= e($userRow['username'] ?? '') ?> · 资料 / 毛照 / 荣誉 / 收款码 / 密码</p>
            </div>
            <?php require __DIR__ . '/partials/user-chip.php'; ?>
        </div>
    </header>

    <main class="page-content">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="form-section" id="profileForm">
            <input type="hidden" name="action" value="save">
            <div class="form-section-title">基本信息</div>
            <div class="form-group">
                <label>用户名</label>
                <input type="text" class="form-control" value="<?= e($userRow['username'] ?? '') ?>" disabled>
            </div>
            <div class="form-group">
                <label>昵称 / 打手名 <span class="required-mark">*</span></label>
                <input type="text" name="nickname" class="form-control" required
                       value="<?= e($userRow['nickname'] ?? '') ?>" placeholder="显示名称">
            </div>

            <div class="form-section-title" style="margin-top:20px">毛照</div>
            <div class="form-group">
                <label>追加上传（可多选）</label>
                <input type="file" name="photos[]" id="profilePhotos" class="form-control file-input" multiple
                       accept="image/jpeg,image/png,image/webp">
                <p class="order-no-hint"><?= e(UploadLimits::hint()) ?></p>
                <p class="order-no-hint" id="photoSizePreview" style="display:none"></p>
            </div>
            <?php if ($photos !== []): ?>
                <div class="screenshot-preview-grid" style="margin-bottom:12px">
                    <?php foreach (array_slice($photos, 0, 6) as $p): ?>
                        <a href="/staff/media.php?type=photo&id=<?= (int) $p['id'] ?>" target="_blank">
                            <img src="/staff/media.php?type=photo&id=<?= (int) $p['id'] ?>" alt="毛照"
                                 style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px;border:1px solid var(--border)">
                        </a>
                    <?php endforeach; ?>
                </div>
                <p class="order-no-hint">管理/删除更多毛照可到底部导航「毛照」</p>
            <?php endif; ?>

            <div class="form-section-title" style="margin-top:20px">荣誉</div>
            <div class="form-group">
                <label>荣誉名称</label>
                <input type="text" name="honor_title" class="form-control" placeholder="选填；要新增荣誉时填写">
            </div>
            <div class="form-group">
                <label>备注</label>
                <input type="text" name="honor_remark" class="form-control" placeholder="选填">
            </div>
            <div class="form-group">
                <label>荣誉图片（可多选）</label>
                <input type="file" name="honor_images[]" id="honorImages" class="form-control file-input" multiple
                       accept="image/jpeg,image/png,image/webp">
                <p class="order-no-hint">与名称一起填才会新增一条荣誉；<?= e(UploadLimits::hint()) ?></p>
                <p class="order-no-hint" id="honorSizePreview" style="display:none"></p>
            </div>
            <?php if ($honors !== []): ?>
                <?php foreach ($honors as $h): ?>
                    <div class="order-card" style="margin-bottom:12px">
                        <div class="order-card-header">
                            <strong><?= e($h['title']) ?></strong>
                            <span style="font-size:12px;color:var(--text-muted)"><?= formatDateTimeShort($h['created_at']) ?></span>
                        </div>
                        <?php if (!empty($h['remark'])): ?>
                            <p style="font-size:13px;color:var(--text-muted);margin:8px 0"><?= e($h['remark']) ?></p>
                        <?php endif; ?>
                        <div class="screenshot-preview-grid">
                            <?php foreach ($h['images'] as $img): ?>
                                <a href="/staff/media.php?type=honor&id=<?= (int) $img['id'] ?>" target="_blank">
                                    <img src="/staff/media.php?type=honor&id=<?= (int) $img['id'] ?>" alt=""
                                         style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px">
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" form="deleteHonor<?= (int) $h['id'] ?>" class="btn btn-sm btn-danger" style="margin-top:10px"
                                onclick="return confirm('删除这条荣誉？')">删除</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="form-section-title" style="margin-top:20px">收款转账二维码</div>
            <p class="order-no-hint" style="margin-bottom:10px">提现时客服会扫你上传的码给你转账，请传微信/支付宝收款码</p>
            <?php if (!empty($payQrKey)): ?>
                <div style="margin-bottom:12px">
                    <img src="/staff/media.php?type=pay_qr" alt="收款码"
                         style="max-width:200px;width:100%;border-radius:12px;border:1px solid var(--border);background:#fff">
                    <p class="order-no-hint">已上传；重新选择图片可更换</p>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label><?= !empty($payQrKey) ? '更换二维码' : '上传二维码' ?></label>
                <input type="file" name="pay_qr" class="form-control file-input"
                       accept="image/jpeg,image/png,image/webp">
            </div>

            <div class="form-section-title" style="margin-top:20px">修改密码（选填）</div>
            <p class="order-no-hint" style="margin-bottom:10px">不改密码可全部留空</p>
            <div class="form-group">
                <label>当前密码</label>
                <input type="password" name="old_password" class="form-control"
                       autocomplete="current-password" placeholder="改密码时必填">
            </div>
            <div class="form-group">
                <label>新密码</label>
                <input type="password" name="new_password" class="form-control"
                       minlength="6" autocomplete="new-password" placeholder="至少6位">
            </div>
            <div class="form-group">
                <label>确认新密码</label>
                <input type="password" name="confirm_password" class="form-control"
                       minlength="6" autocomplete="new-password" placeholder="再次输入">
            </div>

            <button type="submit" class="btn btn-primary">保存信息</button>
        </form>

        <?php foreach ($honors as $h): ?>
            <form method="post" id="deleteHonor<?= (int) $h['id'] ?>" style="display:none">
                <input type="hidden" name="action" value="delete_honor">
                <input type="hidden" name="honor_id" value="<?= (int) $h['id'] ?>">
            </form>
        <?php endforeach; ?>
    </main>

    <?php require __DIR__ . '/partials/nav.php'; ?>
</div>
<script>
(function () {
    const maxBatch = <?= (int) UploadLimits::BATCH_MAX_BYTES ?>;
    const form = document.getElementById('profileForm');

    function bind(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        function check() {
            let total = 0;
            Array.from(input?.files || []).forEach(f => total += f.size);
            if (!input?.files?.length) {
                if (preview) preview.style.display = 'none';
                return true;
            }
            const mb = (total / 1024 / 1024).toFixed(1);
            if (preview) {
                preview.style.display = 'block';
                preview.textContent = '已选 ' + input.files.length + ' 张，合计约 ' + mb + 'MB（上限 30MB）';
                preview.style.color = total > maxBatch ? 'var(--danger)' : 'var(--text-muted)';
            }
            return total <= maxBatch;
        }
        input?.addEventListener('change', check);
        return check;
    }

    const checkPhotos = bind('profilePhotos', 'photoSizePreview');
    const checkHonors = bind('honorImages', 'honorSizePreview');
    form?.addEventListener('submit', function (e) {
        if (!checkPhotos() || !checkHonors()) {
            e.preventDefault();
            alert('一次最多上传 30MB，请减少图片或压缩后再传');
        }
    });
})();
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
