<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/HonorService.php';
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
        if ($action === 'create') {
            HonorService::create(
                $pdo,
                $uid,
                $uid,
                $_POST['title'] ?? '',
                $_POST['remark'] ?? '',
                $_FILES['images'] ?? [],
                $user['username']
            );
            flash('success', '荣誉已添加');
        } elseif ($action === 'delete') {
            HonorService::softDelete($pdo, (int) ($_POST['id'] ?? 0), $uid, false);
            flash('success', '已删除');
        }
        redirect('/staff/honors.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$honors = [];
try {
    $honors = HonorService::listByStaff($pdo, $uid);
} catch (Throwable) {
    $honors = [];
}

$currentPage = 'honors';
$pageTitle = brandTitle('我的荣誉');
$bodyClass = 'has-nav';
require __DIR__ . '/partials/head.php';
?>
<div class="app-shell">
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="top-bar-info">
                <h1>我的荣誉</h1>
                <p class="subtitle">可多选图片 · <?= e(UploadLimits::hint()) ?></p>
            </div>
            <?php require __DIR__ . '/partials/user-chip.php'; ?>
        </div>
    </header>
    <main class="page-content">
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="form-section" id="honorForm">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>荣誉名称 <span class="required-mark">*</span></label>
                <input type="text" name="title" class="form-control" required placeholder="如：月度之星">
            </div>
            <div class="form-group">
                <label>备注</label>
                <input type="text" name="remark" class="form-control" placeholder="选填">
            </div>
            <div class="form-group">
                <label>荣誉图片（可多选） <span class="required-mark">*</span></label>
                <input type="file" name="images[]" id="honorImages" class="form-control file-input" multiple
                       accept="image/jpeg,image/png,image/webp" required>
                <p class="order-no-hint"><?= e(UploadLimits::hint()) ?></p>
                <p class="order-no-hint" id="honorSizePreview" style="display:none"></p>
            </div>
            <button type="submit" class="btn btn-primary">提交荣誉</button>
        </form>

        <div class="form-section" style="margin-top:16px">
            <div class="form-section-title">我的荣誉 (<?= count($honors) ?>)</div>
            <?php if ($honors === []): ?>
                <p style="color:var(--text-muted);padding:12px 0">暂无荣誉</p>
            <?php else: ?>
                <?php foreach ($honors as $h): ?>
                    <div class="order-card" style="margin-top:12px">
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
                        <form method="post" style="margin-top:10px" onsubmit="return confirm('删除这条荣誉？')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php require __DIR__ . '/partials/nav.php'; ?>
</div>
<script>
(function () {
    const maxBatch = <?= (int) UploadLimits::BATCH_MAX_BYTES ?>;
    const input = document.getElementById('honorImages');
    const preview = document.getElementById('honorSizePreview');
    const form = document.getElementById('honorForm');
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
