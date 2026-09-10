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
require_once __DIR__ . '/../includes/ErrorCodes.php';

Auth::requireReport();

$pdo = Database::getConnection();
$customers = CustomerService::getAll($pdo, true);
$businessTypes = BusinessTypeService::getAll($pdo, true);
$coStaffEnabled = OrderService::hasCoStaffColumn($pdo);
$rates = SettlementService::rates();
$rateAPct = (float) $rates['rate_a'] * 100;
$rateDuoPct = (float) $rates['rate_b'] * 100;
$rateSoloPct = (float) ($rates['rate_solo'] ?? min(1, $rates['rate_b'] * 2)) * 100;
$postedCrewMode = (string) ($_POST['crew_mode'] ?? 'solo');
if (!in_array($postedCrewMode, ['solo', 'duo'], true)) {
    $postedCrewMode = 'solo';
}
$coStaffLabel = '';
if ($coStaffEnabled && !empty($_POST['co_staff_id'])) {
    require_once __DIR__ . '/../includes/UserService.php';
    $coRow = UserService::getStaffById($pdo, (int) $_POST['co_staff_id']);
    if ($coRow) {
        $coStaffLabel = trim(($coRow['nickname'] ?: $coRow['username']) . ' (@' . $coRow['username'] . ')');
        $postedCrewMode = 'duo';
    }
}

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
        $error = ErrorCodes::format($e, 'RPT');
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
            <?php renderAlertError($error); ?>
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

                <?php if ($coStaffEnabled): ?>
                <div class="form-group">
                    <label>接单方式 <span class="required-mark">*</span></label>
                    <div class="crew-mode-toggle" role="radiogroup" aria-label="接单方式">
                        <label class="crew-mode-option" for="crewModeSolo">
                            <input type="radio" name="crew_mode" value="solo" id="crewModeSolo"
                                <?= $postedCrewMode !== 'duo' ? 'checked' : '' ?>>
                            <span>一人接单</span>
                        </label>
                        <label class="crew-mode-option" for="crewModeDuo">
                            <input type="radio" name="crew_mode" value="duo" id="crewModeDuo"
                                <?= $postedCrewMode === 'duo' ? 'checked' : '' ?>>
                            <span>双人接单</span>
                        </label>
                    </div>
                    <p class="order-no-hint">默认一人；一人 ×<?= e((string) rtrim(rtrim(number_format($rateAPct, 2, '.', ''), '0'), '.')) ?>%×<?= e((string) rtrim(rtrim(number_format($rateSoloPct, 2, '.', ''), '0'), '.')) ?>%，双人每人 ×<?= e((string) rtrim(rtrim(number_format($rateAPct, 2, '.', ''), '0'), '.')) ?>%×<?= e((string) rtrim(rtrim(number_format($rateDuoPct, 2, '.', ''), '0'), '.')) ?>%</p>
                </div>

                <div class="form-group" id="coStaffGroup" style="<?= $postedCrewMode === 'duo' ? '' : 'display:none' ?>">
                    <label>附加打手 <span class="required-mark">*</span></label>
                    <input type="hidden" name="co_staff_id" id="coStaffId"
                           value="<?= e((string) ($_POST['co_staff_id'] ?? '')) ?>">
                    <div class="co-staff-picker">
                        <div class="co-staff-search-row">
                            <input type="text" id="coStaffSearch" class="form-control"
                                   placeholder="输入昵称或用户名，点搜索"
                                   value="<?= e($coStaffLabel) ?>" autocomplete="off"
                                   enterkeyhint="search">
                            <button type="button" class="btn btn-primary" id="coStaffSearchBtn">搜索</button>
                        </div>
                        <div class="co-staff-results" id="coStaffResults" hidden></div>
                        <div class="co-staff-selected" id="coStaffSelected" <?= $coStaffLabel === '' ? 'hidden' : '' ?>>
                            <span id="coStaffSelectedLabel"><?= e($coStaffLabel) ?></span>
                            <button type="button" class="co-staff-clear" id="coStaffClear">清除</button>
                        </div>
                    </div>
                    <p class="order-no-hint">选「双人接单」后先点搜索选中搭档，再提交。支持搜一个字。</p>
                </div>
                <?php else: ?>
                <input type="hidden" name="crew_mode" value="solo">
                <?php endif; ?>

                <div class="form-group">
                    <label>微信订单编号 <span class="required-mark">*</span></label>
                    <input type="text" name="wechat_order_no" class="form-control wechat-order-input"
                           required maxlength="64" inputmode="text"
                           placeholder="请填写微信支付订单编号"
                           value="<?= e($_POST['wechat_order_no'] ?? '') ?>">
                    <p class="order-no-hint">从微信账单或收款记录中复制订单编号；重复编号会提示谁已报单</p>
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
                <div class="label" style="margin-top:10px;font-size:12px" id="previewFormula">
                    <?= e(SettlementService::crewModeHint($postedCrewMode === 'duo')) ?>
                </div>
                <div class="amount" id="previewStaffAmount" style="font-size:22px;margin-top:4px">¥0.00</div>
                <div class="label" id="previewShareHint" style="margin-top:8px;font-size:12px;display:none">
                    你预计可得 <span id="previewMyShare">¥0.00</span>（另一半归附加打手）
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block-fixed">提交报单</button>
        </form>
    </main>

    <?php require __DIR__ . '/partials/nav.php'; ?>
</div>

<script>
const SETTLEMENT_RATE_A = <?= json_encode((float) $rates['rate_a']) ?>;
const SETTLEMENT_RATE_SOLO = <?= json_encode((float) ($rates['rate_solo'] ?? min(1, $rates['rate_b'] * 2))) ?>;
const SETTLEMENT_RATE_DUO = <?= json_encode((float) $rates['rate_b']) ?>;

function formatMoneyYuan(n) {
    return '¥' + Number(n).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function pctLabel(rate) {
    const p = Math.round(rate * 10000) / 100;
    return String(p).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
}

function isDuoMode() {
    const duo = document.getElementById('crewModeDuo');
    return !!(duo && duo.checked);
}

function syncCrewModeUi() {
    const duo = isDuoMode();
    const group = document.getElementById('coStaffGroup');
    const search = document.getElementById('coStaffSearch');
    const hidden = document.getElementById('coStaffId');
    if (group) {
        group.style.display = duo ? '' : 'none';
        group.hidden = !duo;
    }
    // 不要 disabled：部分手机选双人后 change 不触发，输入框会一直灰掉搜不了
    if (search) {
        search.disabled = false;
        search.removeAttribute('disabled');
        search.readOnly = false;
    }
    if (!duo && hidden) {
        hidden.value = '';
        const selectedWrap = document.getElementById('coStaffSelected');
        const selectedLabel = document.getElementById('coStaffSelectedLabel');
        if (selectedWrap) selectedWrap.hidden = true;
        if (selectedLabel) selectedLabel.textContent = '';
        if (search) search.value = '';
        const results = document.getElementById('coStaffResults');
        if (results) {
            results.hidden = true;
            results.innerHTML = '';
        }
    }
    updateAmount();
    if (duo && search) {
        setTimeout(function () { try { search.focus(); } catch (e) {} }, 30);
    }
}

function updateAmount() {
    const select = document.getElementById('businessType');
    if (!select) return;
    const option = select.options[select.selectedIndex];
    const price = parseFloat((option && option.dataset.price) || 0);
    const qty = parseInt((document.getElementById('quantity') || {}).value || 0, 10);
    const amount = price * (qty > 0 ? qty : 0);
    const previewAmount = document.getElementById('previewAmount');
    if (previewAmount) previewAmount.textContent = formatMoneyYuan(amount);

    const duo = isDuoMode();
    const pool = duo
        ? amount * SETTLEMENT_RATE_A * SETTLEMENT_RATE_DUO * 2
        : amount * SETTLEMENT_RATE_A * SETTLEMENT_RATE_SOLO;
    const formula = document.getElementById('previewFormula');
    if (formula) {
        formula.textContent = duo
            ? ('双人接单 · 每人 ×' + pctLabel(SETTLEMENT_RATE_A) + '%×' + pctLabel(SETTLEMENT_RATE_DUO) + '%')
            : ('一人接单 · ×' + pctLabel(SETTLEMENT_RATE_A) + '%×' + pctLabel(SETTLEMENT_RATE_SOLO) + '%');
    }

    const hint = document.getElementById('previewShareHint');
    const staffEl = document.getElementById('previewStaffAmount');
    if (staffEl) staffEl.textContent = formatMoneyYuan(pool);
    if (duo) {
        const mine = Math.round(amount * SETTLEMENT_RATE_A * SETTLEMENT_RATE_DUO * 100) / 100;
        if (hint) {
            hint.style.display = 'block';
            const share = document.getElementById('previewMyShare');
            if (share) share.textContent = formatMoneyYuan(mine);
        }
    } else if (hint) {
        hint.style.display = 'none';
    }
}

document.getElementById('businessType')?.addEventListener('change', updateAmount);
document.getElementById('businessType')?.addEventListener('searchselect:change', updateAmount);
document.getElementById('quantity')?.addEventListener('input', updateAmount);

document.querySelectorAll('input[name="crew_mode"]').forEach(function (el) {
    el.addEventListener('change', syncCrewModeUi);
    el.addEventListener('click', syncCrewModeUi);
});
document.querySelectorAll('.crew-mode-option').forEach(function (el) {
    el.addEventListener('click', function () {
        setTimeout(syncCrewModeUi, 0);
    });
});
syncCrewModeUi();

document.getElementById('reportForm')?.addEventListener('submit', function (e) {
    if (!isDuoMode()) return;
    const coId = document.getElementById('coStaffId')?.value;
    if (!coId) {
        e.preventDefault();
        syncCrewModeUi();
        alert('双人接单请先搜索并点选附加打手');
        const search = document.getElementById('coStaffSearch');
        if (search) {
            search.scrollIntoView({ behavior: 'smooth', block: 'center' });
            search.focus();
        }
    }
});

document.getElementById('screenshots')?.addEventListener('change', function(e) {
    const preview = document.getElementById('screenshotPreview');
    if (!preview) return;
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

(function initCoStaffPicker() {
    const search = document.getElementById('coStaffSearch');
    const searchBtn = document.getElementById('coStaffSearchBtn');
    const hidden = document.getElementById('coStaffId');
    const results = document.getElementById('coStaffResults');
    const selectedWrap = document.getElementById('coStaffSelected');
    const selectedLabel = document.getElementById('coStaffSelectedLabel');
    const clearBtn = document.getElementById('coStaffClear');
    if (!search || !hidden || !results) return;

    let timer = null;
    let composing = false;

    function setCoStaff(id, label) {
        hidden.value = id ? String(id) : '';
        if (selectedLabel) selectedLabel.textContent = label || '';
        if (selectedWrap) selectedWrap.hidden = !id;
        search.value = id ? (label || '') : search.value;
        results.hidden = true;
        results.innerHTML = '';
        updateAmount();
    }

    async function runSearch() {
        const q = search.value.trim();
        results.innerHTML = '';
        if (q.length < 1) {
            const empty = document.createElement('div');
            empty.className = 'co-staff-empty';
            empty.textContent = '请输入昵称或用户名再搜索';
            results.appendChild(empty);
            results.hidden = false;
            return;
        }
        const loading = document.createElement('div');
        loading.className = 'co-staff-empty';
        loading.textContent = '搜索中…';
        results.appendChild(loading);
        results.hidden = false;
        try {
            const res = await fetch('/staff/api_staff_search.php?q=' + encodeURIComponent(q));
            const data = await res.json();
            const items = data.items || [];
            results.innerHTML = '';
            if (!items.length) {
                const empty = document.createElement('div');
                empty.className = 'co-staff-empty';
                empty.textContent = '未找到打手，可换一个字再试';
                results.appendChild(empty);
                results.hidden = false;
                return;
            }
            items.forEach(item => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'co-staff-item';
                btn.dataset.id = String(item.id);
                btn.dataset.label = item.label;
                btn.textContent = item.label;
                results.appendChild(btn);
            });
            results.hidden = false;
        } catch (err) {
            results.innerHTML = '';
            const empty = document.createElement('div');
            empty.className = 'co-staff-empty';
            empty.textContent = '搜索失败，请重试';
            results.appendChild(empty);
            results.hidden = false;
        }
    }

    clearBtn?.addEventListener('click', () => {
        setCoStaff('', '');
        search.value = '';
        search.focus();
    });

    searchBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        clearTimeout(timer);
        runSearch();
    });

    search.addEventListener('compositionstart', function () { composing = true; });
    search.addEventListener('compositionend', function () {
        composing = false;
        clearTimeout(timer);
        timer = setTimeout(runSearch, 120);
    });

    search.addEventListener('input', () => {
        if (composing) return;
        clearTimeout(timer);
        const q = search.value.trim();
        if (q.length < 1) {
            results.hidden = true;
            results.innerHTML = '';
            return;
        }
        timer = setTimeout(runSearch, 200);
    });

    search.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(timer);
            runSearch();
        }
    });

    results.addEventListener('click', (e) => {
        const btn = e.target.closest('.co-staff-item');
        if (!btn) return;
        setCoStaff(btn.dataset.id, btn.dataset.label);
    });
})();
</script>
<script src="/staff/assets/js/search-select.js"></script>
<script>
initSearchSelect('customerSearch', 'customerSelect', 'customerId');
initSearchSelect('businessTypeSearch', 'businessType', 'businessTypeId');
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
