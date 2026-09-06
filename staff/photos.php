<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/StaffPhotoService.php';
require_once __DIR__ . '/../includes/StaffPhotoStorage.php';
require_once __DIR__ . '/../includes/UploadLimits.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireStaff();

$pdo = Database::getConnection();
$uid = (int) Auth::id();
$user = Auth::user();
$error = '';
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'upload') {
            StaffPhotoService::uploadMany($pdo, $uid, $uid, $_FILES['photos'] ?? [], $user['username']);
            flash('success', '毛照上传成功');
        } elseif ($action === 'delete') {
            StaffPhotoService::softDelete($pdo, (int) ($_POST['id'] ?? 0), $uid, false);
            flash('success', '已删除');
        }
        redirect('/staff/photos.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$photos = [];
try {
    $photos = StaffPhotoService::listByStaff($pdo, $uid);
} catch (Throwable) {
    $photos = [];
}

$storage = new StaffPhotoStorage();
$currentPage = 'photos';
$pageTitle = brandTitle('我的毛照');
$bodyClass = 'has-nav';
require __DIR__ . '/partials/head.php';
?>
<div class="app-shell">
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="top-bar-info">
                <h1>我的毛照</h1>
                <p class="subtitle">可多选上传 · <?= e(UploadLimits::hint()) ?></p>
            </div>
            <?php require __DIR__ . '/partials/user-chip.php'; ?>
        </div>
    </header>
    <main class="page-content">
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="form-section" id="photoUploadForm">
            <input type="hidden" name="action" value="upload">
            <div class="form-group">
                <label>选择图片（可多选）</label>
                <input type="file" name="photos[]" id="photosInput" class="form-control file-input" multiple
                       accept="image/jpeg,image/png,image/webp" required>
                <p class="order-no-hint" id="sizeHint"><?= e(UploadLimits::hint()) ?></p>
                <p class="order-no-hint" id="sizePreview" style="display:none"></p>
            </div>
            <button type="submit" class="btn btn-primary">上传</button>
        </form>

        <div class="form-section" style="margin-top:16px">
            <div class="form-section-title">已上传 (<?= count($photos) ?>)</div>
            <?php if ($photos === []): ?>
                <p style="color:var(--text-muted);padding:12px 0">暂无毛照</p>
            <?php else: ?>
                <div class="screenshot-preview-grid" style="margin-top:12px">
                    <?php foreach ($photos as $p): ?>
                        <div style="position:relative">
                            <a href="/staff/media.php?type=photo&id=<?= (int) $p['id'] ?>" target="_blank">
                                <img src="/staff/media.php?type=photo&id=<?= (int) $p['id'] ?>" alt="毛照"
                                     style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px;border:1px solid var(--border)">
                            </a>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:4px"><?= formatDateTimeShort($p['created_at']) ?></p>
                            <form method="post" onsubmit="return confirm('删除这张毛照？')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" style="width:100%">删除</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php require __DIR__ . '/partials/nav.php'; ?>
</div>
<script>
(function () {
    const maxBatch = <?= (int) UploadLimits::BATCH_MAX_BYTES ?>;
    const input = document.getElementById('photosInput');
    const preview = document.getElementById('sizePreview');
    const form = document.getElementById('photoUploadForm');
    function check() {
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
    input?.addEventListener('change', check);
    form?.addEventListener('submit', function (e) {
        if (!check()) {
            e.preventDefault();
            alert('一次最多上传 30MB，请减少图片或压缩后再传');
        }
    });
})();
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
