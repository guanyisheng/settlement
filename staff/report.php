<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/CustomerService.php';
require_once __DIR__ . '/../includes/BusinessTypeService.php';
require_once __DIR__ . '/../includes/OrderService.php';
require_once __DIR__ . '/../includes/SettlementService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireReport();

$pdo = Database::getConnection();
$customers = CustomerService::getAll($pdo, true);
$businessTypes = BusinessTypeService::getAll($pdo, true);

$error = '';
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 报单人：默认本人；老板代报时可传 staff_id（需有打手管理权限）
        $staffId = Auth::id();
        if (!empty($_POST['staff_id']) && Auth::can('staff.manage')) {
            $staffId = (int) $_POST['staff_id'];
        }
        $result = OrderService::create($pdo, $staffId, $_POST, $_FILES['screenshots'] ?? null);
        flash('success', '报单提交成功，微信订单号：' . $result['wechat_order_no']);
        redirect(Auth::canAccessAdmin() ? '/admin/orders.php' : '/staff/index.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$currentPage = 'report';
$pageTitle = brandTitle('我要报单');
$bodyClass = 'has-nav';

require __DIR__ . '/partials/head.php';
?>
<div class="app-shell">
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="top-bar-info">
                <h1>我要报单</h1>
                <p class="subtitle">填写信息，提交审核（带 <span class="required-mark">*</span> 为必填）</p>
            </div>
            <?php require __DIR__ . '/partials/user-chip.php'; ?>
        </div>
    </header>

    <main class="page-content">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="post" id="reportForm" enctype="multipart/form-data">
            <div class="form-section">
                <div class="form-section-title">报单信息</div>

                <div class="form-group">
                    <label>选择客户 <span class="required-mark">*</span></label>
                    <input type="text" id="customerSearch" class="form-control search-select-input"
                           placeholder="输入关键字搜索客户" autocomplete="off">
                    <input type="hidden" name="customer_id" id="customerId"
                           value="<?= e((string) ($_POST['customer_id'] ?? '')) ?>">
                    <select id="customerSelect" class="form-control search-select-native" size="6" required>
                        <option value="">请选择客户</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($_POST['customer_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>业务类型 <span class="required-mark">*</span></label>
                    <input type="text" id="businessTypeSearch" class="form-control search-select-input"
                           placeholder="输入关键字搜索业务类型" autocomplete="off">
                    <input type="hidden" name="business_type_id" id="businessTypeId"
                           value="<?= e((string) ($_POST['business_type_id'] ?? '')) ?>">
                    <select id="businessType" class="form-control search-select-native" size="6" required>
                        <option value="" data-price="0">请选择业务类型</option>
                        <?php foreach ($businessTypes as $bt): ?>
                            <option value="<?= $bt['id'] ?>" data-price="<?= $bt['unit_price'] ?>"
                                <?= (($_POST['business_type_id'] ?? '') == $bt['id']) ? 'selected' : '' ?>>
                                <?= e($bt['name']) ?> · <?= formatMoney($bt['unit_price']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>微信订单编号 <span class="required-mark">*</span></label>
                    <input type="text" name="wechat_order_no" class="form-control wechat-order-input"
                           required maxlength="64" inputmode="text"
                           placeholder="请填写微信支付订单编号"
                           value="<?= e($_POST['wechat_order_no'] ?? '') ?>">
                    <p class="order-no-hint">从微信账单或收款记录中复制订单编号</p>
                </div>

                <div class="form-group">
                    <label>订单截图 <span class="required-mark">*</span></label>
                    <input type="file" name="screenshots[]" id="screenshots" class="form-control file-input"
                           accept="image/jpeg,image/png,image/webp" capture="environment" multiple required>
                    <p class="order-no-hint">可上传多张微信订单详情截图，支持 JPG/PNG/WEBP，每张最大 5MB，最多 9 张</p>
                    <div class="screenshot-preview-grid" id="screenshotPreview"></div>
                </div>

                <div class="form-group">
                    <label>数量 <span class="required-mark">*</span></label>
                    <input type="number" name="quantity" id="quantity" class="form-control"
                           value="<?= e($_POST['quantity'] ?? '') ?>" min="1" step="1" inputmode="numeric"
                           required placeholder="请填写数量（整数）">
                </div>

                <div class="form-group">
                    <label>接单时间 <span class="required-mark">*</span></label>
                    <div class="form-row-2">
                        <div class="form-group nested">
                            <label>开始时间 <span class="required-mark">*</span></label>
                            <input type="datetime-local" name="start_time" class="form-control datetime-input" required
                                   value="<?= e($_POST['start_time'] ?? '') ?>">
                        </div>
                        <div class="form-group nested">
                            <label>结束时间 <span class="required-mark">*</span></label>
                            <input type="datetime-local" name="end_time" class="form-control datetime-input" required
                                   value="<?= e($_POST['end_time'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>备注（选填）</label>
                    <textarea name="remark" class="form-control" placeholder="如有特殊情况请说明"><?= e($_POST['remark'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="amount-preview">
                <div class="label">预计订单金额</div>
                <div class="amount" id="previewAmount">¥0.00</div>
                <div class="label" style="margin-top:10px;font-size:12px">预计到手 <?= SettlementService::formulaLabel() ?></div>
                <div class="amount" id="previewStaffAmount" style="font-size:22px;margin-top:4px">¥0.00</div>
            </div>

            <button type="submit" class="btn btn-primary btn-block-fixed">提交报单</button>
        </form>
    </main>

    <?php require __DIR__ . '/partials/nav.php'; ?>
</div>

<script>
function updateAmount() {
    const select = document.getElementById('businessType');
    const option = select.options[select.selectedIndex];
    const price = parseFloat(option.dataset.price || 0);
    const qty = parseInt(document.getElementById('quantity').value || 0, 10);
    const amount = (price * (qty > 0 ? qty : 0)).toFixed(2);
    document.getElementById('previewAmount').textContent = '¥' + Number(amount).toLocaleString('zh-CN', {minimumFractionDigits: 2});
    const staffAmount = (parseFloat(amount) * 0.8 * 0.5).toFixed(2);
    document.getElementById('previewStaffAmount').textContent = '¥' + Number(staffAmount).toLocaleString('zh-CN', {minimumFractionDigits: 2});
}
document.getElementById('businessType').addEventListener('change', updateAmount);
document.getElementById('businessType').addEventListener('searchselect:change', updateAmount);
document.getElementById('quantity').addEventListener('input', updateAmount);
updateAmount();

document.getElementById('screenshots')?.addEventListener('change', function(e) {
    const preview = document.getElementById('screenshotPreview');
    preview.innerHTML = '';
    const files = Array.from(e.target.files || []);
    if (!files.length) return;

    files.slice(0, 9).forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'screenshot-preview-item';
        const img = document.createElement('img');
        img.alt = '截图预览 ' + (index + 1);
        item.appendChild(img);
        preview.appendChild(item);

        const reader = new FileReader();
        reader.onload = ev => { img.src = ev.target.result; };
        reader.readAsDataURL(file);
    });
});
</script>
<script src="/staff/assets/js/search-select.js"></script>
<script>
initSearchSelect('customerSearch', 'customerSelect', 'customerId');
initSearchSelect('businessTypeSearch', 'businessType', 'businessTypeId');
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
