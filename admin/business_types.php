<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/BusinessTypeService.php';
require_once __DIR__ . '/../includes/SettlementService.php';
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
        } elseif ($action === 'save_rates') {
            $a = (float) ($_POST['rate_a_pct'] ?? 0) / 100;
            $b = (float) ($_POST['rate_b_pct'] ?? 0) / 100;
            SettlementService::setRates($pdo, $a, $b);
            flash('success', '默认结算倍率已更新。新报单按此计算；特殊单请在订单详情里手动改。');
        }
        redirect('/admin/business_types.php' . ($action === 'save_rates' ? '#settlement' : ''));
    } catch (Throwable $e) {
        flashError($e, 'BIZ');
        redirect('/admin/business_types.php');
    }
}

$keyword = trim((string) ($_GET['q'] ?? ''));
$businessTypes = BusinessTypeService::getAll($pdo, false, $keyword);
$rates = SettlementService::rates();
$example = SettlementService::calcStaffAmount(100);

$currentPage = 'business_types';
$pageTitle = '业务类型管理';
require __DIR__ . '/partials/header.php';
?>

<?php renderAlertError($error); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card" id="settlement">
    <div class="card-header"><h2>默认结算倍率</h2></div>
    <div class="card-body">
        <p>公式：<strong>订单金额 × 基础倍率 × 打手倍率</strong></p>
        <p>示例（一人接单）：订单 ¥100 → 打手结算 <?= formatMoney(SettlementService::calcByCrewMode(100, false)['staff_amount']) ?>（<?= e(SettlementService::crewModeHint(false)) ?>）</p>
        <p>示例（双人接单）：同一单总额同上，每人约 <?= formatMoney(SettlementService::calcByCrewMode(100, true)['staff_amount'] / 2) ?>（<?= e(SettlementService::crewModeHint(true)) ?>）</p>
        <p style="color:var(--text-muted);font-size:13px;margin-top:12px">
            「打手倍率」按<strong>双人每人半份</strong>配置（默认 50%）。一人接单自动按半份×2（默认 100%）结算加钱；双人单总额与一人相同再平分。特殊单请到<strong>订单管理</strong>手动改。只影响新报单。
        </p>
        <form method="post" style="margin-top:20px;max-width:480px">
            <input type="hidden" name="action" value="save_rates">
            <div class="form-row">
                <div class="form-group">
                    <label>基础倍率（%）</label>
                    <input type="number" name="rate_a_pct" class="form-control" step="0.01" min="0.01" max="100"
                           value="<?= e((string) round($rates['rate_a'] * 100, 2)) ?>" required>
                </div>
                <div class="form-group">
                    <label>打手倍率 / 双人半份（%）</label>
                    <input type="number" name="rate_b_pct" class="form-control" step="0.01" min="0.01" max="100"
                           value="<?= e((string) round($rates['rate_b'] * 100, 2)) ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"
                    onclick="return confirm('确认修改默认倍率？历史订单不会重算。')">保存默认倍率</button>
        </form>
    </div>
</div>

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
    <div class="card-header" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
        <h2 style="margin:0">业务类型列表 (<?= count($businessTypes) ?>)</h2>
        <form method="get" style="display:flex;gap:8px;align-items:center">
            <input type="search" name="q" class="form-control" style="width:220px"
                   placeholder="搜索业务名/备注/ID" value="<?= e($keyword) ?>">
            <button type="submit" class="btn btn-sm btn-primary">搜索</button>
            <?php if ($keyword !== ''): ?><a href="/admin/business_types.php" class="btn btn-sm">清除</a><?php endif; ?>
        </form>
    </div>
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
