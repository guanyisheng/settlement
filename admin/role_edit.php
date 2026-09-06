<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/RoleService.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/CustomerService.php';
require_once __DIR__ . '/../includes/BusinessTypeService.php';
require_once __DIR__ . '/../includes/PermissionService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('roles');

$pdo = Database::getConnection();
$id = (int) ($_GET['id'] ?? 0);
$role = RoleService::getById($pdo, $id);
if (!$role) {
    flash('error', '角色不存在');
    redirect('/admin/roles.php');
}

$error = flash('error');
$success = flash('success');
$canManage = Auth::can('role.manage') || Auth::can('permission.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    $action = $_POST['action'] ?? 'save';
    try {
        if ($action === 'save') {
            RoleService::update(
                $pdo,
                $id,
                $_POST['name'] ?? $role['name'],
                $_POST['description'] ?? '',
                (int) ($_POST['status'] ?? 1)
            );
            $permIds = array_map('intval', $_POST['permission_ids'] ?? []);
            RoleService::setPermissions($pdo, $id, $permIds);
            RoleService::setScope(
                $pdo,
                $id,
                $_POST['scope_type'] ?? 'self',
                $_POST['staff_ids'] ?? [],
                $_POST['customer_ids'] ?? [],
                $_POST['business_ids'] ?? []
            );
            flash('success', '角色已保存');
        }
        redirect('/admin/role_edit.php?id=' . $id);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/role_edit.php?id=' . $id);
    }
}

$allPerms = RoleService::allPermissions($pdo);
$selectedPerms = RoleService::getPermissionIds($pdo, $id);
$scope = RoleService::getScope($pdo, $id);
$staffList = UserService::getStaffList($pdo);
$customers = CustomerService::getAll($pdo, true);
$businessTypes = BusinessTypeService::getAll($pdo, true);

$grouped = [];
foreach ($allPerms as $p) {
    $grouped[$p['group_name']][] = $p;
}

$currentPage = 'roles';
$pageTitle = '配置角色：' . $role['name'];
require __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="action" value="save">

    <div class="card">
        <div class="card-header"><h2>基本信息</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>角色名称</label>
                    <input type="text" name="name" class="form-control"
                           value="<?= e($role['name']) ?>"
                           <?= (int) $role['is_system'] === 1 ? 'readonly' : '' ?> required>
                </div>
                <div class="form-group">
                    <label>说明</label>
                    <input type="text" name="description" class="form-control" value="<?= e($role['description'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" class="form-control">
                        <option value="1" <?= (int) $role['status'] === 1 ? 'selected' : '' ?>>启用</option>
                        <option value="0" <?= (int) $role['status'] === 0 ? 'selected' : '' ?>>禁用</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>功能权限</h2></div>
        <div class="card-body">
            <?php foreach ($grouped as $group => $perms): ?>
                <div style="margin-bottom:16px">
                    <strong><?= e($group) ?></strong>
                    <div class="form-row" style="margin-top:8px;flex-wrap:wrap;gap:8px 16px">
                        <?php foreach ($perms as $p): ?>
                            <label style="display:flex;align-items:center;gap:6px;min-width:160px">
                                <input type="checkbox" name="permission_ids[]" value="<?= (int) $p['id'] ?>"
                                    <?= in_array((int) $p['id'], $selectedPerms, true) ? 'checked' : '' ?>
                                    <?= $canManage ? '' : 'disabled' ?>>
                                <?= e($p['name']) ?>
                                <span style="color:var(--text-muted);font-size:12px"><?= e($p['code']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>数据范围</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label>范围类型</label>
                <select name="scope_type" class="form-control" id="scopeType">
                    <?php
                    $types = [
                        'all' => '全部数据',
                        'self' => '仅本人',
                        'assigned' => '自己负责 / 指定范围（下方勾选）',
                    ];
                    foreach ($types as $k => $label):
                    ?>
                        <option value="<?= $k ?>" <?= ($scope['scope_type'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>指定打手</label>
                    <select name="staff_ids[]" class="form-control" multiple size="8">
                        <?php foreach ($staffList as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"
                                <?= in_array((int) $s['id'], array_map('intval', $scope['staff_ids']), true) ? 'selected' : '' ?>>
                                <?= e($s['nickname'] ?: $s['username']) ?> (#<?= (int) $s['id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:1">
                    <label>指定客户</label>
                    <select name="customer_ids[]" class="form-control" multiple size="8">
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= in_array((int) $c['id'], array_map('intval', $scope['customer_ids']), true) ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:1">
                    <label>指定业务</label>
                    <select name="business_ids[]" class="form-control" multiple size="8">
                        <?php foreach ($businessTypes as $b): ?>
                            <option value="<?= (int) $b['id'] ?>"
                                <?= in_array((int) $b['id'], array_map('intval', $scope['business_ids']), true) ? 'selected' : '' ?>>
                                <?= e($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p style="color:var(--text-muted);font-size:13px;margin-top:8px">
                按住 Ctrl/Command 多选。多角色用户的权限与数据范围为各角色合集；勾选「全部数据」的角色会使范围变为全部。
            </p>
        </div>
    </div>

    <div style="margin:16px 0;display:flex;gap:12px">
        <?php if ($canManage): ?>
            <button type="submit" class="btn btn-primary">保存</button>
        <?php endif; ?>
        <a href="/admin/roles.php" class="btn">返回</a>
    </div>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
