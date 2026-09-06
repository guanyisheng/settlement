<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/RoleService.php';
require_once __DIR__ . '/../includes/PermissionService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('employees');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');
$rbac = PermissionService::isRbacReady($pdo);
$assignableRoles = $rbac ? RoleService::listAssignableForEmployees($pdo) : [];

$systemRoles = [];
$customRoles = [];
foreach ($assignableRoles as $r) {
    if ((int) ($r['is_system'] ?? 0) === 1) {
        $systemRoles[] = $r;
    } else {
        $customRoles[] = $r;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            if ($rbac && empty($_POST['role_ids'])) {
                throw new InvalidArgumentException('请至少勾选一个角色');
            }
            UserService::createEmployee($pdo, $_POST);
            flash('success', '员工添加成功');
        } elseif ($action === 'update') {
            if ($rbac && empty($_POST['role_ids'])) {
                throw new InvalidArgumentException('请至少勾选一个角色');
            }
            UserService::updateEmployee($pdo, (int) $_POST['id'], $_POST);
            flash('success', '员工信息已更新');
        } elseif ($action === 'reset_password') {
            UserService::resetEmployeePassword($pdo, (int) $_POST['id'], $_POST['password'] ?? '');
            flash('success', '密码已重置');
        }
        redirect('/admin/employees.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/employees.php');
    }
}

$employees = UserService::getEmployeeList($pdo);
$employeeRoles = [];
if ($rbac) {
    foreach ($employees as $emp) {
        $employeeRoles[(int) $emp['id']] = PermissionService::getUserRoles($pdo, (int) $emp['id']);
    }
}

$currentPage = 'employees';
$pageTitle = '员工管理';
require __DIR__ . '/partials/header.php';

$renderRoleChecks = static function (array $roles, string $nameAttr = 'role_ids[]', bool $defaultCs = false, array $checkedIds = []): void {
    foreach ($roles as $r) {
        $id = (int) $r['id'];
        $checked = in_array($id, $checkedIds, true)
            || ($defaultCs && ($r['code'] ?? '') === 'CUSTOMER_SERVICE' && $checkedIds === []);
        ?>
        <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;background:var(--card,#1c2128);border:1px solid var(--border,#30363d);border-radius:6px">
            <input type="checkbox" name="<?= e($nameAttr) ?>" value="<?= $id ?>" <?= $checked ? 'checked' : '' ?>
                   class="<?= str_contains($nameAttr, 'edit') ? 'edit-role-cb' : '' ?>"
                   data-role-id="<?= $id ?>">
            <span><?= e($r['name']) ?></span>
            <?php if ((int) ($r['is_system'] ?? 0) !== 1): ?>
                <span style="color:var(--text-muted);font-size:11px">自定义</span>
            <?php endif; ?>
        </label>
        <?php
    }
};
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>添加员工</h2>
    </div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>昵称</label>
                    <input type="text" name="nickname" class="form-control">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
            </div>
            <div class="form-group">
                <label>角色（可多选，权限为合集）</label>
                <?php if ($rbac && $assignableRoles !== []): ?>
                    <?php if ($systemRoles !== []): ?>
                        <div style="margin-top:8px;margin-bottom:4px;color:var(--text-muted);font-size:12px">系统角色</div>
                        <div style="display:flex;flex-wrap:wrap;gap:10px">
                            <?php $renderRoleChecks($systemRoles, 'role_ids[]', true); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($customRoles !== []): ?>
                        <div style="margin-top:14px;margin-bottom:4px;color:var(--text-muted);font-size:12px">自定义角色（在「角色权限」里新建后会出现在这里）</div>
                        <div style="display:flex;flex-wrap:wrap;gap:10px">
                            <?php $renderRoleChecks($customRoles, 'role_ids[]', false); ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);font-size:12px;margin-top:10px">
                            暂无自定义角色。可到 <a href="/admin/roles.php">角色权限</a> 新建（例如「财务」），保存后回到本页即可勾选。
                        </p>
                    <?php endif; ?>
                    <p style="color:var(--text-muted);font-size:12px;margin-top:10px">例：勾选「客服」+「财务」→ 登录后显示 客服&财务，菜单为两边权限之和。</p>
                <?php elseif ($rbac): ?>
                    <p class="alert alert-error">没有可分配的角色，请先在「角色权限」创建角色。</p>
                <?php else: ?>
                    <select name="role" class="form-control" required>
                        <option value="CUSTOMER_SERVICE">客服</option>
                        <option value="EXAMINER">考官</option>
                    </select>
                    <p style="color:var(--warning,#d29922);font-size:12px;margin-top:8px">尚未完成 RBAC 迁移，暂仅支持单角色。请执行 database/本次更新_总SQL.sql</p>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary">添加</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>员工列表 (<?= count($employees) ?>)</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>用户名</th><th>昵称</th><th>角色</th><th>状态</th><th>注册时间</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--text-muted)">暂无员工</td></tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                    <?php
                        $rid = (int) $emp['id'];
                        $roles = $employeeRoles[$rid] ?? [];
                        $roleText = $roles !== []
                            ? implode('&', array_map(static fn($r) => $r['name'], $roles))
                            : roleLabel(($emp['role'] ?? '') === 'ADMIN' ? 'BOSS' : ($emp['role'] ?? ''));
                        $roleIds = array_map(static fn($r) => (int) $r['id'], $roles);
                    ?>
                    <tr>
                        <td><?= $rid ?></td>
                        <td><?= e($emp['username']) ?></td>
                        <td><?= e($emp['nickname']) ?></td>
                        <td><?= e($roleText) ?></td>
                        <td><span class="badge badge-<?= $emp['status'] ? 'active' : 'disabled' ?>"><?= $emp['status'] ? '启用' : '禁用' ?></span></td>
                        <td><?= formatDateTimeShort($emp['created_at']) ?></td>
                        <td class="actions">
                            <button type="button" class="btn btn-sm"
                                onclick='editEmployee(<?= json_encode([
                                    'id' => $rid,
                                    'nickname' => $emp['nickname'],
                                    'status' => (int) $emp['status'],
                                    'role_ids' => $roleIds,
                                ], JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">编辑员工</div>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:12px">
                    <label>昵称</label>
                    <input type="text" name="nickname" id="editNickname" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>状态</label>
                    <select name="status" id="editStatus" class="form-control">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
                <?php if ($rbac && $assignableRoles !== []): ?>
                <div class="form-group">
                    <label>角色（可多选，含自定义角色）</label>
                    <div id="editRoles" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px">
                        <?php foreach ($assignableRoles as $r): ?>
                            <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--border,#30363d);border-radius:6px">
                                <input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>"
                                       class="edit-role-cb" data-role-id="<?= (int) $r['id'] ?>">
                                <?= e($r['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="document.getElementById('editModal').classList.remove('show')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
        <form method="post" style="padding:0 20px 20px">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="resetId">
            <div class="form-group">
                <input type="password" name="password" class="form-control" placeholder="新密码（至少6位）" minlength="6">
            </div>
            <button type="submit" class="btn btn-sm btn-danger">重置密码</button>
        </form>
    </div>
</div>

<script>
function editEmployee(e) {
    document.getElementById('editId').value = e.id;
    document.getElementById('resetId').value = e.id;
    document.getElementById('editNickname').value = e.nickname;
    document.getElementById('editStatus').value = e.status;
    const ids = (e.role_ids || []).map(String);
    document.querySelectorAll('.edit-role-cb').forEach(function (cb) {
        cb.checked = ids.indexOf(String(cb.getAttribute('data-role-id'))) !== -1;
    });
    document.getElementById('editModal').classList.add('show');
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
