<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/RoleService.php';
require_once __DIR__ . '/../includes/PermissionService.php';
require_once __DIR__ . '/../includes/BalanceService.php';
require_once __DIR__ . '/../includes/UploadLimits.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('users');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');
$rbac = PermissionService::isRbacReady($pdo);
$allRoles = $rbac ? RoleService::listRoles($pdo, false) : [];

$qs = static function () : string {
    $parts = [];
    if (!empty($_GET['q'])) {
        $parts[] = 'q=' . urlencode((string) $_GET['q']);
    }
    if (!empty($_GET['role_id'])) {
        $parts[] = 'role_id=' . (int) $_GET['role_id'];
    }
    return $parts === [] ? '' : ('?' . implode('&', $parts));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_staff') {
            UserService::createStaff($pdo, $_POST, $_FILES['photos'] ?? null);
            flash('success', '打手添加成功');
        } elseif ($action === 'create_employee') {
            if ($rbac && empty($_POST['role_ids'])) {
                throw new InvalidArgumentException('请至少勾选一个角色');
            }
            UserService::createEmployee($pdo, $_POST);
            flash('success', '员工添加成功');
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = UserService::getById($pdo, $id);
            if (!$row) {
                throw new RuntimeException('用户不存在');
            }
            if (UserService::userIsStaffLike($pdo, $row) && ($row['role'] ?? '') === 'STAFF') {
                UserService::deleteStaff($pdo, $id);
            } else {
                UserService::deleteEmployee($pdo, $id);
            }
            flash('success', '用户已删除/停用');
        } elseif ($action === 'reset_password') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = UserService::getById($pdo, $id);
            if (!$row) {
                throw new RuntimeException('用户不存在');
            }
            if (($row['role'] ?? '') === 'STAFF') {
                UserService::resetStaffPassword($pdo, $id, $_POST['password'] ?? '');
            } else {
                UserService::resetEmployeePassword($pdo, $id, $_POST['password'] ?? '');
            }
            flash('success', '密码已重置');
        }
        redirect('/admin/users.php' . $qs());
    } catch (Throwable $e) {
        flashError($e, 'USER');
        redirect('/admin/users.php' . $qs());
    }
}

$keyword = trim((string) ($_GET['q'] ?? ''));
$roleId = (int) ($_GET['role_id'] ?? 0);
$roleId = $roleId > 0 ? $roleId : null;
$users = UserService::getUnifiedUserList($pdo, $keyword, $roleId);

$userRoles = [];
$balances = [];
foreach ($users as $u) {
    $uid = (int) $u['id'];
    if ($rbac) {
        $userRoles[$uid] = PermissionService::getUserRoles($pdo, $uid);
    }
    $balances[$uid] = BalanceService::getBalanceSummary($pdo, $uid);
}

$currentPage = 'users';
$pageTitle = '用户中心';
require __DIR__ . '/partials/header.php';

$statusLabel = static function (int $status): string {
    return match ($status) {
        1 => '启用',
        0 => '禁用',
        2 => '待审核',
        default => (string) $status,
    };
};
?>

<?php renderAlertError($error); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2>添加用户</h2></div>
    <div class="card-body">
        <div class="tab-strip" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
            <button type="button" class="btn btn-sm btn-primary" id="tabStaffBtn" onclick="showCreateTab('staff')">添加打手</button>
            <button type="button" class="btn btn-sm" id="tabEmpBtn" onclick="showCreateTab('employee')">添加员工</button>
        </div>

        <form method="post" enctype="multipart/form-data" id="createStaffForm">
            <input type="hidden" name="action" value="create_staff">
            <div class="form-row">
                <div class="form-group"><label>用户名</label><input type="text" name="username" class="form-control" required></div>
                <div class="form-group"><label>昵称</label><input type="text" name="nickname" class="form-control"></div>
                <div class="form-group"><label>密码</label><input type="password" name="password" class="form-control" required minlength="6"></div>
                <div class="form-group"><label>入职时间</label><input type="date" name="hired_at" class="form-control"></div>
                <div class="form-group"><label>考核官</label><input type="text" name="examiner" class="form-control"></div>
                <div class="form-group"><label>押金</label><input type="text" name="deposit" class="form-control"></div>
                <div class="form-group">
                    <label>毛照（可多选）</label>
                    <input type="file" name="photos[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
                    <p style="font-size:12px;color:var(--text-muted);margin-top:6px"><?= e(UploadLimits::hint()) ?></p>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <button type="submit" class="btn btn-primary">添加打手</button>
                </div>
            </div>
        </form>

        <form method="post" id="createEmpForm" style="display:none">
            <input type="hidden" name="action" value="create_employee">
            <div class="form-row">
                <div class="form-group"><label>用户名</label><input type="text" name="username" class="form-control" required></div>
                <div class="form-group"><label>昵称</label><input type="text" name="nickname" class="form-control"></div>
                <div class="form-group"><label>密码</label><input type="password" name="password" class="form-control" required minlength="6"></div>
            </div>
            <?php if ($rbac && $allRoles !== []): ?>
            <div class="form-group">
                <label>角色（可多选）</label>
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px">
                    <?php foreach ($allRoles as $r): ?>
                        <?php if (in_array($r['code'] ?? '', ['STAFF', 'BOSS', 'ADMIN'], true)) continue; ?>
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--border);border-radius:6px">
                            <input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>"
                                <?= ($r['code'] ?? '') === 'CUSTOMER_SERVICE' ? 'checked' : '' ?>>
                            <span><?= e($r['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">添加员工</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
        <h2 style="margin:0">用户列表 (<?= count($users) ?>)</h2>
        <form method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <select name="role_id" class="form-control" style="width:auto;min-width:140px">
                <option value="">全部职位</option>
                <?php foreach ($allRoles as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= $roleId === (int) $r['id'] ? 'selected' : '' ?>>
                        <?= e($r['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="q" class="form-control" style="width:200px" placeholder="搜用户名/昵称/ID"
                   value="<?= e($keyword) ?>">
            <button type="submit" class="btn btn-sm btn-primary">筛选</button>
            <?php if ($keyword !== '' || $roleId): ?>
                <a href="/admin/users.php" class="btn btn-sm">清除</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th><th>用户名</th><th>名称</th><th>押金</th><th>角色</th>
                    <th>累计收入</th><th>可提现</th><th>注册时间</th><th>状态</th><th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($users === []): ?>
                    <tr><td colspan="10" style="text-align:center;color:var(--text-muted)">暂无用户</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $uid = (int) $u['id'];
                        $roles = $userRoles[$uid] ?? [];
                        $roleText = $roles !== []
                            ? implode('&', array_map(static fn($r) => $r['name'], $roles))
                            : roleLabel((string) ($u['role'] ?? ''));
                        $bal = $balances[$uid] ?? ['total_income' => 0, 'available_balance' => 0];
                        ?>
                        <tr>
                            <td><?= $uid ?></td>
                            <td><?= e($u['username']) ?></td>
                            <td><?= e($u['nickname'] ?: '-') ?></td>
                            <td><?= e((string) ($u['deposit'] ?? '-')) ?></td>
                            <td><?= e($roleText) ?></td>
                            <td class="money"><?= formatMoney($bal['total_income']) ?></td>
                            <td class="money"><?= formatMoney($bal['available_balance']) ?></td>
                            <td><?= formatDateTimeShort($u['created_at'] ?? null) ?></td>
                            <td><?= e($statusLabel((int) ($u['status'] ?? 0))) ?></td>
                            <td class="actions">
                                <a class="btn btn-sm" href="/admin/user_detail.php?id=<?= $uid ?>">详细</a>
                                <button type="button" class="btn btn-sm" onclick="openReset(<?= $uid ?>, '<?= e($u['username']) ?>')">重置密码</button>
                                <form method="post" style="display:inline" onsubmit="return confirm('确认删除/停用该用户？')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $uid ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">删除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="resetModal" class="modal" hidden>
    <div class="modal-dialog">
        <form method="post">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="resetUserId">
            <h3>重置密码 · <span id="resetUsername"></span></h3>
            <div class="form-group">
                <label>新密码</label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn" onclick="closeReset()">取消</button>
                <button type="submit" class="btn btn-primary">确认</button>
            </div>
        </form>
    </div>
</div>

<script>
function showCreateTab(which) {
    const staff = document.getElementById('createStaffForm');
    const emp = document.getElementById('createEmpForm');
    const b1 = document.getElementById('tabStaffBtn');
    const b2 = document.getElementById('tabEmpBtn');
    if (which === 'staff') {
        staff.style.display = '';
        emp.style.display = 'none';
        b1.classList.add('btn-primary');
        b2.classList.remove('btn-primary');
    } else {
        staff.style.display = 'none';
        emp.style.display = '';
        b2.classList.add('btn-primary');
        b1.classList.remove('btn-primary');
    }
}
function openReset(id, name) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetUsername').textContent = name;
    document.getElementById('resetModal').hidden = false;
}
function closeReset() {
    document.getElementById('resetModal').hidden = true;
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
