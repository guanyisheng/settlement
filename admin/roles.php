<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/RoleService.php';
require_once __DIR__ . '/../includes/PermissionService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('roles');

$pdo = Database::getConnection();
if (!PermissionService::isRbacReady($pdo)) {
    flash('error', '请先执行 database/migrate_rbac_v2.sql');
    redirect('/admin/index.php');
}

$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        Auth::requirePermission('role.manage');
        if ($action === 'create') {
            $id = RoleService::create($pdo, $_POST['name'] ?? '', $_POST['description'] ?? '');
            flash('success', '角色已创建，可在「员工管理」里勾选分配（含自定义角色）');
            redirect('/admin/role_edit.php?id=' . $id);
        }
        if ($action === 'delete') {
            RoleService::softDelete($pdo, (int) ($_POST['id'] ?? 0));
            flash('success', '角色已删除');
        }
        redirect('/admin/roles.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/roles.php');
    }
}

$roles = RoleService::listRoles($pdo, true);
$canManage = Auth::can('role.manage');

$currentPage = 'roles';
$pageTitle = '角色管理';
require __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<?php if ($canManage): ?>
<div class="card">
    <div class="card-header"><h2>创建角色</h2></div>
    <div class="card-body">
        <form method="post" class="form-row">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>角色名称</label>
                <input type="text" name="name" class="form-control" required placeholder="如：主管">
            </div>
            <div class="form-group">
                <label>说明</label>
                <input type="text" name="description" class="form-control" placeholder="可选">
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end">
                <button type="submit" class="btn btn-primary">创建并配置权限</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2>角色列表 (<?= count($roles) ?>)</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th><th>名称</th><th>编码</th><th>说明</th><th>用户数</th><th>状态</th><th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($roles as $r): ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td><?= e($r['name']) ?><?= (int) $r['is_system'] === 1 ? ' <span class="badge">系统</span>' : '' ?></td>
                        <td><?= e($r['code'] ?? '-') ?></td>
                        <td><?= e($r['description'] ?? '') ?></td>
                        <td><?= (int) $r['user_count'] ?></td>
                        <td><?= (int) $r['status'] === 1 ? '启用' : '禁用' ?></td>
                        <td>
                            <a class="btn btn-sm" href="/admin/role_edit.php?id=<?= (int) $r['id'] ?>">配置</a>
                            <?php if ($canManage && (int) $r['is_system'] !== 1): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('确定删除该角色？')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">删除</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
