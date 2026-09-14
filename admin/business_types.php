<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/BusinessTypeService.php';
require_once __DIR__ . '/../includes/SettlementService.php';
require_once __DIR__ . '/../includes/ExtraFeeService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('business_types');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');
$extraReady = ExtraFeeService::isReady($pdo);

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
        } elseif ($action === 'create_extra_fee') {
            ExtraFeeService::createItem($pdo, $_POST);
            flash('success', '额外收费项目已添加');
        } elseif ($action === 'update_extra_fee') {
            ExtraFeeService::updateItem($pdo, (int) ($_POST['id'] ?? 0), $_POST);
            flash('success', '额外收费项目已更新');
        }
        $hash = in_array($action, ['create_extra_fee', 'update_extra_fee'], true) ? '#extra-fees'
            : ($action === 'save_rates' ? '#settlement' : '');
        redirect('/admin/business_types.php' . $hash);
    } catch (Throwable $e) {
        flashError($e, 'BIZ');
        redirect('/admin/business_types.php');
    }
}

$keyword = trim((string) ($_GET['q'] ?? ''));
$businessTypes = BusinessTypeService::getAll($pdo, false, $keyword);
$rates = SettlementService::rates();
$extraItems = $extraReady ? ExtraFeeService::getAllItems($pdo, false) : [];
$enabledByBt = [];
if ($extraReady) {
    foreach ($businessTypes as $bt) {
        $enabledByBt[(int) $bt['id']] = ExtraFeeService::getEnabledIdsForBusinessType($pdo, (int) $bt['id']);
    }
}

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
        <p>订单金额 = 单价×数量，再按勾选的额外项目：百分比上调 + 固定加价（可叠加）。</p>
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

<div class="card" id="extra-fees">
    <div class="card-header"><h2>额外收费项目</h2></div>
    <div class="card-body">
        <?php if (!$extraReady): ?>
            <div class="alert alert-error">
                尚未安装额外收费表。请在业务库执行
                <code>database/migrate_extra_fee_items.sql</code>
                ；若已装过百分比项目、要加「直接加钱」，再执行
                <code>database/migrate_extra_fee_fixed_amount.sql</code>。
                后刷新本页。
            </div>
        <?php else: ?>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:14px">
                报单勾选后：订单金额 = 基础金额 × (1 + 百分比合计) + 固定加价合计。
                例：¥100 勾「包卡+10%」和「加急+¥20」→ ¥130，再 × 结算倍率。
            </p>
            <form method="post" style="margin-bottom:20px" id="createExtraFeeForm">
                <input type="hidden" name="action" value="create_extra_fee">
                <div class="form-row">
                    <div class="form-group">
                        <label>项目名称</label>
                        <input type="text" name="name" class="form-control" required placeholder="如：包卡 / 加急">
                    </div>
                    <div class="form-group">
                        <label>计费方式</label>
                        <select name="fee_type" class="form-control js-fee-type" required>
                            <option value="percent">加百分比</option>
                            <option value="fixed">直接加钱</option>
                        </select>
                    </div>
                    <div class="form-group js-fee-percent">
                        <label>上调比例（%）</label>
                        <input type="number" name="rate_pct" class="form-control js-rate-pct" min="0.01" max="500" step="0.01" value="10">
                    </div>
                    <div class="form-group js-fee-fixed" hidden>
                        <label>加价金额（元）</label>
                        <input type="number" name="fixed_amount" class="form-control js-fixed-amount" min="0.01" step="0.01" placeholder="20">
                    </div>
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" name="sort_order" class="form-control" value="0" step="1">
                    </div>
                    <div class="form-group">
                        <label>备注</label>
                        <input type="text" name="remark" class="form-control" placeholder="可选">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end">
                        <button type="submit" class="btn btn-primary">添加项目</button>
                    </div>
                </div>
            </form>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>ID</th><th>名称</th><th>方式</th><th>加收</th><th>排序</th><th>状态</th><th>备注</th><th>操作</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($extraItems === []): ?>
                        <tr><td colspan="8" style="text-align:center;color:var(--text-muted)">暂无项目，请先添加</td></tr>
                    <?php else: ?>
                        <?php foreach ($extraItems as $fee): ?>
                            <?php
                            $norm = ExtraFeeService::normalizeItem($fee);
                            $feeJs = [
                                'id' => $norm['id'],
                                'name' => $norm['name'],
                                'fee_type' => $norm['fee_type'],
                                'rate_pct' => $norm['rate_pct'],
                                'fixed_amount' => $norm['fixed_amount'],
                                'sort_order' => (int) $fee['sort_order'],
                                'status' => (int) $fee['status'],
                                'remark' => (string) ($fee['remark'] ?? ''),
                            ];
                            ?>
                            <tr>
                                <td><?= (int) $fee['id'] ?></td>
                                <td><?= e($fee['name']) ?></td>
                                <td><?= $norm['fee_type'] === 'fixed' ? '直接加钱' : '加百分比' ?></td>
                                <td><?= e(ExtraFeeService::chargeLabel($fee)) ?></td>
                                <td><?= (int) $fee['sort_order'] ?></td>
                                <td><span class="badge badge-<?= $fee['status'] ? 'active' : 'disabled' ?>"><?= $fee['status'] ? '启用' : '禁用' ?></span></td>
                                <td><?= e($fee['remark'] ?: '-') ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm"
                                            onclick='editExtraFee(<?= json_encode($feeJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>)'>编辑</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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
            </div>
            <?php if ($extraReady && $extraItems !== []): ?>
            <div class="form-group" style="margin-top:12px">
                <label>本类型启用的额外收费（打手报单可选）</label>
                <div style="display:flex;flex-wrap:wrap;gap:10px 16px;margin-top:8px">
                    <?php foreach ($extraItems as $fee): ?>
                        <?php if (!(int) $fee['status']) continue; ?>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px">
                            <input type="checkbox" name="extra_fee_ids[]" value="<?= (int) $fee['id'] ?>">
                            <?= e($fee['name']) ?>（<?= e(ExtraFeeService::chargeLabel($fee)) ?>）
                        </label>
                    <?php endforeach; ?>
                </div>
                <p style="color:var(--text-muted);font-size:12px;margin-top:6px">不勾选则该业务类型报单不出现额外项目。</p>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary" style="margin-top:12px">添加</button>
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
                    <tr><th>ID</th><th>业务名称</th><th>单价</th><th>额外项目</th><th>备注</th><th>状态</th><th>操作</th></tr>
                </thead>
                <tbody>
                <?php if ($businessTypes === []): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--text-muted)"><?= $keyword !== '' ? '无匹配业务类型' : '暂无业务类型' ?></td></tr>
                <?php else: ?>
                <?php foreach ($businessTypes as $bt): ?>
                    <?php
                    $feeIds = $enabledByBt[(int) $bt['id']] ?? [];
                    $feeNames = [];
                    foreach ($extraItems as $fee) {
                        if (in_array((int) $fee['id'], $feeIds, true)) {
                            $feeNames[] = $fee['name'] . ExtraFeeService::chargeLabel($fee);
                        }
                    }
                    $btPayload = $bt;
                    $btPayload['extra_fee_ids'] = $feeIds;
                    ?>
                    <tr>
                        <td><?= $bt['id'] ?></td>
                        <td><?= e($bt['name']) ?></td>
                        <td class="money"><?= formatMoney($bt['unit_price']) ?></td>
                        <td style="font-size:12px;max-width:220px"><?= $feeNames !== [] ? e(implode('、', $feeNames)) : '<span style="color:var(--text-muted)">未启用</span>' ?></td>
                        <td><?= e($bt['remark'] ?: '-') ?></td>
                        <td><span class="badge badge-<?= $bt['status'] ? 'active' : 'disabled' ?>"><?= $bt['status'] ? '启用' : '禁用' ?></span></td>
                        <td><button type="button" class="btn btn-sm" onclick='editBT(<?= json_encode($btPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>)'>编辑</button></td>
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
                <div class="form-group" style="margin-bottom:12px">
                    <label>状态</label>
                    <select name="status" id="editStatus" class="form-control">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
                <?php if ($extraReady): ?>
                <div class="form-group">
                    <label>本类型启用的额外收费</label>
                    <div id="editExtraFees" style="display:flex;flex-wrap:wrap;gap:10px 16px;margin-top:8px">
                        <?php foreach ($extraItems as $fee): ?>
                            <?php if (!(int) $fee['status'] && true) { /* 禁用项也显示，便于保留历史勾选 */ } ?>
                            <label style="display:flex;align-items:center;gap:6px;font-size:13px<?= !(int) $fee['status'] ? ';opacity:.55' : '' ?>">
                                <input type="checkbox" name="extra_fee_ids[]" value="<?= (int) $fee['id'] ?>" class="edit-extra-fee"
                                       data-fee-id="<?= (int) $fee['id'] ?>">
                                <?= e($fee['name']) ?>（<?= e(ExtraFeeService::chargeLabel($fee)) ?>）
                                <?= !(int) $fee['status'] ? '·已禁用' : '' ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p style="color:var(--text-muted);font-size:12px;margin-top:6px">不勾选则打手报该业务时看不到额外项目。</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="document.getElementById('editModal').classList.remove('show')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editExtraModal">
    <div class="modal">
        <div class="modal-header">编辑额外收费项目</div>
        <form method="post">
            <input type="hidden" name="action" value="update_extra_fee">
            <input type="hidden" name="id" id="extraEditId">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:12px">
                    <label>项目名称</label>
                    <input type="text" name="name" id="extraEditName" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>计费方式</label>
                    <select name="fee_type" id="extraEditType" class="form-control js-fee-type">
                        <option value="percent">加百分比</option>
                        <option value="fixed">直接加钱</option>
                    </select>
                </div>
                <div class="form-group js-fee-percent" style="margin-bottom:12px">
                    <label>上调比例（%）</label>
                    <input type="number" name="rate_pct" id="extraEditRate" class="form-control js-rate-pct" min="0.01" max="500" step="0.01">
                </div>
                <div class="form-group js-fee-fixed" style="margin-bottom:12px" hidden>
                    <label>加价金额（元）</label>
                    <input type="number" name="fixed_amount" id="extraEditFixed" class="form-control js-fixed-amount" min="0.01" step="0.01">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>排序</label>
                    <input type="number" name="sort_order" id="extraEditSort" class="form-control" step="1">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>备注</label>
                    <input type="text" name="remark" id="extraEditRemark" class="form-control">
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" id="extraEditStatus" class="form-control">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="document.getElementById('editExtraModal').classList.remove('show')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function bindFeeTypeToggle(root) {
    if (!root) return;
    var typeEl = root.querySelector('.js-fee-type');
    if (!typeEl || typeEl.dataset.bound === '1') return;
    typeEl.dataset.bound = '1';
    function sync() {
        var isFixed = typeEl.value === 'fixed';
        root.querySelectorAll('.js-fee-percent').forEach(function (el) { el.hidden = isFixed; });
        root.querySelectorAll('.js-fee-fixed').forEach(function (el) { el.hidden = !isFixed; });
        var rate = root.querySelector('.js-rate-pct');
        var amt = root.querySelector('.js-fixed-amount');
        if (rate) rate.required = !isFixed;
        if (amt) amt.required = isFixed;
    }
    typeEl.addEventListener('change', sync);
    sync();
}
bindFeeTypeToggle(document.getElementById('createExtraFeeForm'));
bindFeeTypeToggle(document.getElementById('editExtraModal'));

function editBT(bt) {
    document.getElementById('editId').value = bt.id;
    document.getElementById('editName').value = bt.name;
    document.getElementById('editPrice').value = bt.unit_price;
    document.getElementById('editPricingType').value = bt.pricing_type;
    document.getElementById('editRemark').value = bt.remark || '';
    document.getElementById('editStatus').value = bt.status;
    var enabled = (bt.extra_fee_ids || []).map(String);
    document.querySelectorAll('.edit-extra-fee').forEach(function (el) {
        el.checked = enabled.indexOf(String(el.getAttribute('data-fee-id'))) !== -1;
    });
    document.getElementById('editModal').classList.add('show');
}
function editExtraFee(fee) {
    document.getElementById('extraEditId').value = fee.id;
    document.getElementById('extraEditName').value = fee.name;
    document.getElementById('extraEditType').value = fee.fee_type === 'fixed' ? 'fixed' : 'percent';
    document.getElementById('extraEditRate').value = fee.rate_pct || '';
    document.getElementById('extraEditFixed').value = fee.fixed_amount || '';
    document.getElementById('extraEditSort').value = fee.sort_order;
    document.getElementById('extraEditRemark').value = fee.remark || '';
    document.getElementById('extraEditStatus').value = fee.status;
    bindFeeTypeToggle(document.getElementById('editExtraModal'));
    document.getElementById('extraEditType').dispatchEvent(new Event('change'));
    document.getElementById('editExtraModal').classList.add('show');
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
