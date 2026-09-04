<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/CustomerService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('customers');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            CustomerService::create($pdo, $_POST);
            flash('success', '客户添加成功');
        } elseif ($action === 'update') {
            CustomerService::update($pdo, (int) $_POST['id'], $_POST);
            flash('success', '客户已更新');
        }
        redirect('/admin/customers.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/customers.php');
    }
}

$customers = CustomerService::getAll($pdo);

$currentPage = 'customers';
$pageTitle = '客户管理';
require __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2>添加客户</h2></div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>客户名称</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>备注</label>
                    <input type="text" name="remark" class="form-control">
                </div>
                <div class="form-group">
                    <label>预存余额</label>
                    <input type="number" name="balance" class="form-control" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>预存客户</label>
                    <select name="is_prepaid" class="form-control">
                        <option value="0">否（不扣余额）</option>
                        <option value="1">是（审核通过扣余额）</option>
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
    <div class="card-header"><h2>客户列表</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>名称</th><th>预存余额</th><th>预存客户</th><th>备注</th><th>状态</th><th>创建时间</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td><?= e($c['name']) ?></td>
                        <td class="money"><?= formatMoney($c['balance'] ?? 0) ?></td>
                        <td><?= !empty($c['is_prepaid']) ? '是' : '否' ?></td>
                        <td><?= e($c['remark'] ?: '-') ?></td>
                        <td><span class="badge badge-<?= $c['status'] ? 'active' : 'disabled' ?>"><?= $c['status'] ? '启用' : '禁用' ?></span></td>
                        <td><?= formatDateTimeShort($c['created_at']) ?></td>
                        <td><button type="button" class="btn btn-sm" onclick="editCustomer(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">编辑</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">编辑客户</div>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:12px">
                    <label>客户名称</label>
                    <input type="text" name="name" id="editName" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>预存余额</label>
                    <input type="number" name="balance" id="editBalance" class="form-control" min="0" step="0.01">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>预存客户</label>
                    <select name="is_prepaid" id="editPrepaid" class="form-control">
                        <option value="0">否（不扣余额）</option>
                        <option value="1">是（审核通过扣余额）</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>备注</label>
                    <input type="text" name="remark" id="editRemark" class="form-control">
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
    </div>
</div>

<script>
function editCustomer(c) {
    document.getElementById('editId').value = c.id;
    document.getElementById('editName').value = c.name;
    document.getElementById('editBalance').value = c.balance ?? 0;
    document.getElementById('editPrepaid').value = c.is_prepaid ? '1' : '0';
    document.getElementById('editRemark').value = c.remark || '';
    document.getElementById('editStatus').value = c.status;
    document.getElementById('editModal').classList.add('show');
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
