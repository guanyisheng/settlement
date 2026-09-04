<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('employees');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            UserService::createEmployee($pdo, $_POST);
            flash('success', '员工添加成功');
        } elseif ($action === 'update') {
            UserService::updateEmployee($pdo, (int) $_POST['id'], $_POST);
            flash('success', '员工信息已更新');
        } elseif ($action === 'reset_password') {
            UserService::resetEmployeePassword($pdo, (int) $_POST['id'], $_POST['password'] ?? '');
            flash('success', '密码已重置');
        } elseif ($action === 'delete') {
            UserService::deleteEmployee($pdo, (int) $_POST['id']);
            flash('success', '员工已禁用');
        }
        redirect('/admin/employees.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/employees.php');
    }
}

$employees = UserService::getEmployeeList($pdo);

$currentPage = 'employees';
$pageTitle = '员工管理';
require __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>添加员工（客服 / 考官）</h2>
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
                <div class="form-group">
                    <label>角色</label>
                    <select name="role" class="form-control" required>
                        <option value="CUSTOMER_SERVICE">客服</option>
                        <option value="EXAMINER">考官</option>
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <button type="submit" class="btn btn-primary">添加</button>
                </div>
            </div>
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
                    <tr>
                        <td><?= $emp['id'] ?></td>
                        <td><?= e($emp['username']) ?></td>
                        <td><?= e($emp['nickname']) ?></td>
                        <td><?= roleLabel($emp['role']) ?></td>
                        <td><span class="badge badge-<?= $emp['status'] ? 'active' : 'disabled' ?>"><?= $emp['status'] ? '启用' : '禁用' ?></span></td>
                        <td><?= formatDateTimeShort($emp['created_at']) ?></td>
                        <td class="actions">
                            <button type="button" class="btn btn-sm" onclick="editEmployee(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES) ?>)">编辑</button>
                            <?php if ($emp['status'] && (int) $emp['id'] !== Auth::id()): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('确认禁用该员工？')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">禁用</button>
                            </form>
                            <?php endif; ?>
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
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" id="editStatus" class="form-control">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
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
    document.getElementById('editModal').classList.add('show');
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
