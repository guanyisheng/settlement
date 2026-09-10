<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/PermissionCatalog.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/RoleService.php';
require_once __DIR__ . '/../includes/PermissionService.php';
require_once __DIR__ . '/../includes/StaffPhotoService.php';
require_once __DIR__ . '/../includes/HonorService.php';
require_once __DIR__ . '/../includes/BalanceService.php';
require_once __DIR__ . '/../includes/OrderService.php';
require_once __DIR__ . '/../includes/WithdrawalService.php';
require_once __DIR__ . '/../includes/UploadLimits.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/icons.php';

// Prefer users menu gate; fall back if catalog has no users entry
if (isset(PermissionCatalog::MENU_PERMISSIONS['users'])) {
    Auth::requirePage('users');
} else {
    Auth::requireAdminAccess();
    if (!Auth::canAccessPage('staff') && !Auth::canAccessPage('employees')) {
        flash('error', '无权限访问该页面');
        redirect(Auth::adminHomeUrl());
    }
}

$pdo = Database::getConnection();
// POST/GET 都认 id，避免表单提交丢查询串后落到错误用户
$userId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$detailUser = UserService::getById($pdo, $userId);

if ($userId <= 0 || !$detailUser || !empty($detailUser['deleted_at'])) {
    flash('error', '用户不存在');
    redirect('/admin/users.php');
}

$rbac = PermissionService::isRbacReady($pdo);
$isStaffLike = UserService::userIsStaffLike($pdo, $detailUser);
$error = flash('error');
$success = flash('success');

$assignableRoles = [];
$canEditRoles = Auth::isBoss() || Auth::can('user.manage') || Auth::can('role.manage');
if ($rbac) {
    // 老板可分配全部角色（含打手/老板）；其他管理员只能分配员工向角色，但保留对方已有角色以免保存时被抹掉
    if (Auth::isBoss()) {
        $assignableRoles = RoleService::listRoles($pdo, false);
    } else {
        $assignableRoles = RoleService::listAssignableForEmployees($pdo);
        $seen = array_flip(array_map(static fn($r) => (int) $r['id'], $assignableRoles));
        foreach (PermissionService::getUserRoles($pdo, $userId) as $r) {
            $rid = (int) ($r['id'] ?? 0);
            if ($rid > 0 && !isset($seen[$rid])) {
                $assignableRoles[] = $r;
                $seen[$rid] = true;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_profile') {
            $payload = [
                'nickname' => $_POST['nickname'] ?? '',
                'status'   => $_POST['status'] ?? ($detailUser['status'] ?? 1),
            ];
            if (!$rbac && isset($_POST['role'])) {
                $payload['role'] = (string) $_POST['role'];
            }
            // 仅当本次提交带了角色勾选时才改角色；避免只改昵称时因漏传 checkbox 保存失败
            if ($rbac && $canEditRoles && isset($_POST['role_ids']) && is_array($_POST['role_ids'])) {
                if ($_POST['role_ids'] === []) {
                    throw new InvalidArgumentException('请至少勾选一个角色');
                }
                $payload['role_ids'] = $_POST['role_ids'];
            }

            if ($isStaffLike || ($detailUser['role'] ?? '') === 'STAFF') {
                UserService::updateStaff($pdo, $userId, array_merge($_POST, $payload), null);
                if ($rbac && isset($payload['role_ids'])) {
                    RoleService::setUserRoles($pdo, $userId, $payload['role_ids']);
                } elseif (!$rbac && isset($payload['role'])) {
                    UserService::updateEmployee($pdo, $userId, $payload);
                }
            } else {
                UserService::updateEmployee($pdo, $userId, $payload);
            }
            flash('success', '档案已更新：' . ($detailUser['nickname'] ?: $detailUser['username']) . ' (#' . $userId . ')');
        } elseif ($action === 'reset_password') {
            $password = (string) ($_POST['password'] ?? '');
            UserService::resetEmployeePassword($pdo, $userId, $password);
            flash('success', '密码已重置');
        } elseif ($isStaffLike && $action === 'upload_photos') {
            StaffPhotoService::uploadMany(
                $pdo,
                $userId,
                (int) Auth::id(),
                $_FILES['photos'] ?? [],
                $detailUser['username']
            );
            flash('success', '毛照上传成功');
        } elseif ($isStaffLike && $action === 'delete_photo') {
            StaffPhotoService::softDelete($pdo, (int) ($_POST['photo_id'] ?? 0), (int) Auth::id(), true);
            flash('success', '毛照已删除');
        } elseif ($isStaffLike && $action === 'add_honor') {
            HonorService::create(
                $pdo,
                $userId,
                (int) Auth::id(),
                $_POST['title'] ?? '',
                $_POST['remark'] ?? '',
                $_FILES['images'] ?? [],
                $detailUser['username']
            );
            flash('success', '荣誉已添加');
        } elseif ($isStaffLike && $action === 'delete_honor') {
            HonorService::softDelete($pdo, (int) ($_POST['honor_id'] ?? 0), (int) Auth::id(), true);
            flash('success', '荣誉已删除');
        } else {
            throw new InvalidArgumentException('无效操作');
        }
        redirect('/admin/user_detail.php?id=' . $userId);
    } catch (Throwable $e) {
        flashError($e, 'USER');
        redirect('/admin/user_detail.php?id=' . $userId);
    }
}

$detailUser = UserService::getById($pdo, $userId);
if (!$detailUser) {
    flash('error', '用户不存在');
    redirect('/admin/users.php');
}
$isStaffLike = UserService::userIsStaffLike($pdo, $detailUser);

$stats = UserService::getStaffStats($pdo, $userId);
$balance = BalanceService::getBalanceSummary($pdo, $userId);
$orders = OrderService::search($pdo, ['staff_id' => $userId]);
$withdrawals = WithdrawalService::search($pdo, ['staff_id' => $userId]);

$photos = [];
$honors = [];
if ($isStaffLike) {
    try {
        $photos = StaffPhotoService::listByStaff($pdo, $userId);
    } catch (Throwable) {
        $photos = [];
    }
    try {
        $honors = HonorService::listByStaff($pdo, $userId);
    } catch (Throwable) {
        $honors = [];
    }
}

$detailUserRoles = [];
$roleIds = [];
if ($rbac) {
    $detailUserRoles = PermissionService::getUserRoles($pdo, $userId);
    $roleIds = array_map(static fn($r) => (int) $r['id'], $detailUserRoles);
}

$roleText = $detailUserRoles !== []
    ? implode('&', array_map(static fn($r) => $r['name'], $detailUserRoles))
    : roleLabel(($detailUser['role'] ?? '') === 'ADMIN' ? 'BOSS' : (string) ($detailUser['role'] ?? ''));

$currentPage = 'users';
$pageTitle = '用户详情';
require __DIR__ . '/partials/header.php';

$detailAction = '/admin/user_detail.php?id=' . $userId;
$subjectName = trim((string) ($detailUser['nickname'] ?? '')) !== ''
    ? (string) $detailUser['nickname']
    : (string) $detailUser['username'];
$selfId = (int) Auth::id();
?>

<a href="/admin/users.php" class="btn btn-sm btn-back" style="margin-bottom:16px">
    <?= svgIcon('arrow-left', 'btn-icon') ?><span>返回用户列表</span>
</a>

<div class="alert" style="background:rgba(56,139,253,0.12);border:1px solid rgba(56,139,253,0.35);color:var(--text);margin-bottom:16px">
    正在查看 / 编辑用户：
    <strong><?= e($subjectName) ?></strong>
    （@<?= e((string) $detailUser['username']) ?> · ID <?= (int) $userId ?>）
    <?php if ($userId === $selfId): ?>
        <span style="color:var(--warning,#d29922)">· 这是你自己的账号</span>
    <?php else: ?>
        <span style="color:var(--text-muted)">· 右上角是当前登录账号，不是本档案</span>
    <?php endif; ?>
</div>

<?php renderAlertError($error); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">累计订单</div>
        <div class="value primary"><?= (int) ($stats['order_count'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">累计收入</div>
        <div class="value success"><?= formatMoney($balance['total_income']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">累计提现</div>
        <div class="value"><?= formatMoney($balance['paid_withdrawals']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">可提现余额</div>
        <div class="value danger"><?= formatMoney($balance['available_balance']) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>档案信息 · <?= e($subjectName) ?></h2>
        <span style="font-size:13px;color:var(--text-muted)"><?= e($roleText) ?><?= $isStaffLike ? ' · 打手向' : '' ?></span>
    </div>
    <div class="card-body">
        <?php if ($isStaffLike): ?>
            <form method="post" action="<?= e($detailAction) ?>">
                <input type="hidden" name="id" value="<?= (int) $userId ?>">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-row">
                    <div class="form-group">
                        <label>用户名（登录账号，不可改）</label>
                        <input type="text" class="form-control" value="<?= e($detailUser['username']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>昵称</label>
                        <input type="text" name="nickname" class="form-control" value="<?= e($detailUser['nickname'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>入职时间</label>
                        <input type="date" name="hired_at" class="form-control" value="<?= e($detailUser['hired_at'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>考核官</label>
                        <input type="text" name="examiner" class="form-control" value="<?= e($detailUser['examiner'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>押金</label>
                        <input type="text" name="deposit" class="form-control" value="<?= e((string) ($detailUser['deposit'] ?? '')) ?>" placeholder="如 100">
                    </div>
                    <div class="form-group">
                        <label>状态</label>
                        <select name="status" class="form-control">
                            <option value="1" <?= (int) ($detailUser['status'] ?? 1) === 1 ? 'selected' : '' ?>>启用</option>
                            <option value="0" <?= (int) ($detailUser['status'] ?? 1) === 0 ? 'selected' : '' ?>>禁用</option>
                            <option value="2" <?= (int) ($detailUser['status'] ?? 1) === 2 ? 'selected' : '' ?>>待审核</option>
                        </select>
                    </div>
                </div>
                <p style="font-size:12px;color:var(--text-muted);margin:8px 0 16px">注册时间：<?= formatDateTime($detailUser['created_at'] ?? null) ?></p>
                <?php if ($rbac && $assignableRoles !== [] && $canEditRoles): ?>
                    <div class="form-group" style="margin-top:12px">
                        <label>角色（可多选）</label>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px">
                            <?php foreach ($assignableRoles as $r): ?>
                                <?php $rid = (int) $r['id']; ?>
                                <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--border);border-radius:6px">
                                    <input type="checkbox" name="role_ids[]" value="<?= $rid ?>"
                                        <?= in_array($rid, $roleIds, true) ? 'checked' : '' ?>>
                                    <span><?= e($r['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">保存档案</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e($detailAction) ?>">
                <input type="hidden" name="id" value="<?= (int) $userId ?>">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-row">
                    <div class="form-group">
                        <label>用户名（登录账号，不可改）</label>
                        <input type="text" class="form-control" value="<?= e($detailUser['username']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>昵称</label>
                        <input type="text" name="nickname" class="form-control" value="<?= e($detailUser['nickname'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>状态</label>
                        <select name="status" class="form-control">
                            <option value="1" <?= (int) ($detailUser['status'] ?? 1) === 1 ? 'selected' : '' ?>>启用</option>
                            <option value="0" <?= (int) ($detailUser['status'] ?? 1) === 0 ? 'selected' : '' ?>>禁用</option>
                        </select>
                    </div>
                </div>
                <?php if ($rbac && $assignableRoles !== [] && $canEditRoles): ?>
                    <div class="form-group" style="margin-top:12px">
                        <label>角色（可多选）</label>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px">
                            <?php foreach ($assignableRoles as $r): ?>
                                <?php $rid = (int) $r['id']; ?>
                                <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--border);border-radius:6px">
                                    <input type="checkbox" name="role_ids[]" value="<?= $rid ?>"
                                        <?= in_array($rid, $roleIds, true) ? 'checked' : '' ?>>
                                    <span><?= e($r['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif (!$rbac && $canEditRoles): ?>
                    <div class="form-group" style="margin-top:12px">
                        <label>角色</label>
                        <select name="role" class="form-control">
                            <option value="CUSTOMER_SERVICE" <?= ($detailUser['role'] ?? '') === 'CUSTOMER_SERVICE' ? 'selected' : '' ?>>客服</option>
                            <option value="EXAMINER" <?= ($detailUser['role'] ?? '') === 'EXAMINER' ? 'selected' : '' ?>>考官</option>
                            <?php if (Auth::isBoss()): ?>
                                <option value="BOSS" <?= in_array($detailUser['role'] ?? '', ['BOSS', 'ADMIN'], true) ? 'selected' : '' ?>>老板</option>
                                <option value="STAFF" <?= ($detailUser['role'] ?? '') === 'STAFF' ? 'selected' : '' ?>>打手</option>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <p style="font-size:12px;color:var(--text-muted);margin:8px 0 16px">注册时间：<?= formatDateTime($detailUser['created_at'] ?? null) ?></p>
                <button type="submit" class="btn btn-primary">保存档案</button>
            </form>
        <?php endif; ?>

        <form method="post" action="<?= e($detailAction) ?>" style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
            <input type="hidden" name="id" value="<?= (int) $userId ?>">
            <input type="hidden" name="action" value="reset_password">
            <div class="form-row">
                <div class="form-group">
                    <label>重置密码</label>
                    <input type="password" name="password" class="form-control" placeholder="新密码（至少6位）" minlength="6" required>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('确认重置该用户密码？')">重置密码</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($isStaffLike): ?>
<div class="card">
    <div class="card-header"><h2>毛照 (<?= count($photos) ?>)</h2></div>
    <div class="card-body">
        <form method="post" action="<?= e($detailAction) ?>" enctype="multipart/form-data" id="adminPhotoForm" style="margin-bottom:20px">
            <input type="hidden" name="id" value="<?= (int) $userId ?>">
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
                        <form method="post" action="<?= e($detailAction) ?>" onsubmit="return confirm('删除这张毛照？')">
                            <input type="hidden" name="id" value="<?= (int) $userId ?>">
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
        <form method="post" action="<?= e($detailAction) ?>" enctype="multipart/form-data" id="adminHonorForm" style="margin-bottom:20px">
            <input type="hidden" name="id" value="<?= (int) $userId ?>">
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
                        <form method="post" action="<?= e($detailAction) ?>" onsubmit="return confirm('删除这条荣誉？')">
                            <input type="hidden" name="id" value="<?= (int) $userId ?>">
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
<?php endif; ?>

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

<?php if ($isStaffLike): ?>
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
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
