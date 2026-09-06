<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/StaffPhotoService.php';
require_once __DIR__ . '/../includes/HonorService.php';
require_once __DIR__ . '/../includes/OrderService.php';
require_once __DIR__ . '/../includes/WithdrawalService.php';
require_once __DIR__ . '/../includes/UploadLimits.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/icons.php';

Auth::requirePage('staff');

$pdo = Database::getConnection();
$staffId = (int) ($_GET['id'] ?? 0);
$staff = UserService::getStaffById($pdo, $staffId);

if (!$staff) {
    flash('error', '打手不存在');
    redirect('/admin/staff.php');
}

$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_profile') {
            UserService::updateStaff($pdo, $staffId, $_POST, null);
            flash('success', '档案已更新');
        } elseif ($action === 'upload_photos') {
            StaffPhotoService::uploadMany(
                $pdo,
                $staffId,
                (int) Auth::id(),
                $_FILES['photos'] ?? [],
                $staff['username']
            );
            flash('success', '毛照上传成功');
        } elseif ($action === 'delete_photo') {
            StaffPhotoService::softDelete($pdo, (int) ($_POST['photo_id'] ?? 0), (int) Auth::id(), true);
            flash('success', '毛照已删除');
        } elseif ($action === 'add_honor') {
            HonorService::create(
                $pdo,
                $staffId,
                (int) Auth::id(),
                $_POST['title'] ?? '',
                $_POST['remark'] ?? '',
                $_FILES['images'] ?? [],
                $staff['username']
            );
            flash('success', '荣誉已添加');
        } elseif ($action === 'delete_honor') {
            HonorService::softDelete($pdo, (int) ($_POST['honor_id'] ?? 0), (int) Auth::id(), true);
            flash('success', '荣誉已删除');
        }
        redirect('/admin/staff_detail.php?id=' . $staffId);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/staff_detail.php?id=' . $staffId);
    }
}

$staff = UserService::getStaffById($pdo, $staffId);
$stats = UserService::getStaffStats($pdo, $staffId);
$orders = OrderService::search($pdo, ['staff_id' => $staffId]);
$withdrawals = WithdrawalService::search($pdo, ['staff_id' => $staffId]);

$photos = [];
$honors = [];
try {
    $photos = StaffPhotoService::listByStaff($pdo, $staffId);
} catch (Throwable) {
    $photos = [];
}
try {
    $honors = HonorService::listByStaff($pdo, $staffId);
} catch (Throwable) {
    $honors = [];
}

$currentPage = 'staff';
$pageTitle = '打手详情 - ' . $staff['nickname'];
require __DIR__ . '/partials/header.php';
?>

<a href="/admin/staff.php" class="btn btn-sm btn-back" style="margin-bottom:16px">
    <?= svgIcon('arrow-left', 'btn-icon') ?><span>返回列表</span>
</a>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">累计订单</div>
        <div class="value primary"><?= $stats['order_count'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label">累计收入</div>
        <div class="value success"><?= formatMoney($stats['total_income']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">累计提现</div>
        <div class="value"><?= formatMoney($stats['paid_withdrawals']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">可提现余额</div>
        <div class="value danger"><?= formatMoney($stats['available_balance']) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>档案信息</h2></div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-row">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" class="form-control" value="<?= e($staff['username']) ?>" disabled>
                </div>
                <div class="form-group">
                    <label>昵称</label>
                    <input type="text" name="nickname" class="form-control" value="<?= e($staff['nickname']) ?>">
                </div>
                <div class="form-group">
                    <label>入职时间</label>
                    <input type="date" name="hired_at" class="form-control" value="<?= e($staff['hired_at'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>考核官</label>
                    <input type="text" name="examiner" class="form-control" value="<?= e($staff['examiner'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>押金</label>
                    <input type="text" name="deposit" class="form-control" value="<?= e($staff['deposit'] ?? '') ?>" placeholder="如 100">
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" class="form-control">
                        <option value="1" <?= $staff['status'] ? 'selected' : '' ?>>启用</option>
                        <option value="0" <?= !$staff['status'] ? 'selected' : '' ?>>禁用</option>
                    </select>
                </div>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin:8px 0 16px">注册时间：<?= formatDateTime($staff['created_at']) ?></p>
            <button type="submit" class="btn btn-primary">保存档案</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>毛照 (<?= count($photos) ?>)</h2></div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" id="adminPhotoForm" style="margin-bottom:20px">
            <input type="hidden" name="action" value="upload_photos">
            <div class="form-group">
                <label>多选上传毛照</label>
                <input type="file" name="photos[]" id="adminPhotosInput" class="form-control" multiple
                       accept="image/jpeg,image/png,image/webp" required>
                <p style="font-size:12px;color:var(--text-muted);margin-top:6px"><?= e(UploadLimits::hint()) ?></p>
                <p style="font-size:12px;margin-top:4px;display:none" id="adminPhotoSize"></p>
            </div>
            <button type="submit" class="btn btn-primary">上传毛照</button>
        </form>
        <?php if ($photos === []): ?>
            <p style="color:var(--text-muted)">暂无毛照</p>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px">
                <?php foreach ($photos as $p): ?>
                    <div>
                        <a href="/admin/staff_photo.php?photo_id=<?= (int) $p['id'] ?>" target="_blank">
                            <img src="/admin/staff_photo.php?photo_id=<?= (int) $p['id'] ?>" alt="毛照"
                                 style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
                        </a>
                        <p style="font-size:11px;color:var(--text-muted);margin:4px 0"><?= formatDateTimeShort($p['created_at']) ?></p>
                        <form method="post" onsubmit="return confirm('删除这张毛照？')">
                            <input type="hidden" name="action" value="delete_photo">
                            <input type="hidden" name="photo_id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" style="width:100%">删除</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>荣誉 (<?= count($honors) ?>)</h2></div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" id="adminHonorForm" style="margin-bottom:20px">
            <input type="hidden" name="action" value="add_honor">
            <div class="form-row">
                <div class="form-group">
                    <label>荣誉名称</label>
                    <input type="text" name="title" class="form-control" required placeholder="如：月度之星">
                </div>
                <div class="form-group">
                    <label>备注</label>
                    <input type="text" name="remark" class="form-control" placeholder="选填">
                </div>
                <div class="form-group">
                    <label>荣誉图片（可多选）</label>
                    <input type="file" name="images[]" id="adminHonorImages" class="form-control" multiple
                           accept="image/jpeg,image/png,image/webp" required>
                </div>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px"><?= e(UploadLimits::hint()) ?></p>
            <p style="font-size:12px;margin:-8px 0 12px;display:none" id="adminHonorSize"></p>
            <button type="submit" class="btn btn-primary">添加荣誉</button>
        </form>
        <?php if ($honors === []): ?>
            <p style="color:var(--text-muted)">暂无荣誉</p>
        <?php else: ?>
            <?php foreach ($honors as $h): ?>
                <div style="border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                        <div>
                            <strong><?= e($h['title']) ?></strong>
                            <span style="font-size:12px;color:var(--text-muted);margin-left:8px"><?= formatDateTimeShort($h['created_at']) ?></span>
                            <?php if (!empty($h['remark'])): ?>
                                <p style="font-size:13px;color:var(--text-muted);margin:6px 0 0"><?= e($h['remark']) ?></p>
                            <?php endif; ?>
                        </div>
                        <form method="post" onsubmit="return confirm('删除这条荣誉？')">
                            <input type="hidden" name="action" value="delete_honor">
                            <input type="hidden" name="honor_id" value="<?= (int) $h['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    </div>
                    <?php if (!empty($h['images'])): ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;margin-top:10px">
                            <?php foreach ($h['images'] as $img): ?>
                                <a href="/admin/staff_photo.php?honor_image_id=<?= (int) $img['id'] ?>" target="_blank">
                                    <img src="/admin/staff_photo.php?honor_image_id=<?= (int) $img['id'] ?>" alt=""
                                         style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>订单记录</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead><tr><th>微信订单号</th><th>客户</th><th>业务</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($orders, 0, 20) as $o): ?>
                    <tr>
                        <td><?= e($o['wechat_order_no'] ?? $o['order_no']) ?></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td><?= e($o['business_type_name']) ?></td>
                        <td class="money"><?= formatMoney($o['amount']) ?></td>
                        <td><?= orderStatusLabel($o['status']) ?></td>
                        <td><?= formatDateTimeShort($o['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--text-muted)">暂无订单</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>提现记录</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead><tr><th>单号</th><th>金额</th><th>状态</th><th>申请时间</th></tr></thead>
                <tbody>
                <?php foreach ($withdrawals as $w): ?>
                    <tr>
                        <td><?= e($w['withdrawal_no']) ?></td>
                        <td class="money"><?= formatMoney($w['amount']) ?></td>
                        <td><?= withdrawalStatusLabel($w['status']) ?></td>
                        <td><?= formatDateTimeShort($w['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($withdrawals)): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted)">暂无提现</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const maxBatch = <?= (int) UploadLimits::BATCH_MAX_BYTES ?>;
    function bind(inputId, previewId, formId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const form = document.getElementById(formId);
        if (!input || !form) return;
        function check() {
            let total = 0;
            Array.from(input.files || []).forEach(f => total += f.size);
            if (!input.files || !input.files.length) {
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
        input.addEventListener('change', check);
        form.addEventListener('submit', function (e) {
            if (!check()) {
                e.preventDefault();
                alert('一次最多上传 30MB，请减少图片或压缩后再传');
            }
        });
    }
    bind('adminPhotosInput', 'adminPhotoSize', 'adminPhotoForm');
    bind('adminHonorImages', 'adminHonorSize', 'adminHonorForm');
})();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
