<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/BusinessTypeService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('business_types');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            BusinessTypeService::create($pdo, $_POST);
            flash('success', '业务类型添加成功');
        } elseif ($action === 'update') {
            BusinessTypeService::update($pdo, (int) $_POST['id'], $_POST);
            flash('success', '业务类型已更新');
        }
        redirect('/admin/business_types.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/business_types.php');
    }
}

$businessTypes = BusinessTypeService::getAll($pdo);

$currentPage = 'business_types';
$pageTitle = '业务类型管理';
require __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2>添加业务类型</h2></div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>业务名称</label>
                    <input type="text" name="name" class="form-control" required placeholder="如：轮回（二挡）">
                </div>
                <div class="form-group">
                    <label>单价 (元)</label>
                    <input type="number" name="unit_price" class="form-control" required min="0" step="0.01" placeholder="444">
                </div>
                <div class="form-group">
                    <label>计价单位</label>
                    <input type="text" name="pricing_type" class="form-control" value="fixed" placeholder="fixed">
                </div>
                <div class="form-group">
                    <label>备注</label>
                    <input type="text" name="remark" class="form-control">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <button type="submit" class="btn btn-primary">添加</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>业务类型列表</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>ID</th><th>业务名称</th><th>单价</th><th>计价单位</th><th>备注</th><th>状态</th><th>操作</th></tr>
                </thead>
                <tbody>
                <?php foreach ($businessTypes as $bt): ?>
                    <tr>
                        <td><?= $bt['id'] ?></td>
                        <td><?= e($bt['name']) ?></td>
                        <td class="money"><?= formatMoney($bt['unit_price']) ?></td>
                        <td><?= e($bt['pricing_type']) ?></td>
                        <td><?= e($bt['remark'] ?: '-') ?></td>
                        <td><span class="badge badge-<?= $bt['status'] ? 'active' : 'disabled' ?>"><?= $bt['status'] ? '启用' : '禁用' ?></span></td>
                        <td><button type="button" class="btn btn-sm" onclick="editBT(<?= htmlspecialchars(json_encode($bt), ENT_QUOTES) ?>)">编辑</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">编辑业务类型</div>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:12px">
                    <label>业务名称</label>
                    <input type="text" name="name" id="editName" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>单价 (元)</label>
                    <input type="number" name="unit_price" id="editPrice" class="form-control" required min="0" step="0.01">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>计价单位</label>
                    <input type="text" name="pricing_type" id="editPricingType" class="form-control">
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
function editBT(bt) {
    document.getElementById('editId').value = bt.id;
    document.getElementById('editName').value = bt.name;
    document.getElementById('editPrice').value = bt.unit_price;
    document.getElementById('editPricingType').value = bt.pricing_type;
    document.getElementById('editRemark').value = bt.remark || '';
    document.getElementById('editStatus').value = bt.status;
    document.getElementById('editModal').classList.add('show');
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
