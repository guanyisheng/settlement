<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/BusinessTypeService.php';
require_once __DIR__ . '/../includes/ExtraFeeService.php';
require_once __DIR__ . '/../includes/SettingsService.php';

$btId = (int) ($_GET['bt'] ?? $_POST['business_type_id'] ?? 0);
$staffPref = (int) ($_GET['staff'] ?? $_POST['staff_id'] ?? 0);
$isCheckout = isset($_GET['checkout']) || isset($_POST['checkout']) || ($_SERVER['REQUEST_METHOD'] === 'POST');

if (!empty($demoMode)) {
    require_once __DIR__ . '/partials/demo_data.php';
    $ready = true;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        flash('error', '演示模式无法真实下单，请连接数据库后再试');
        redirect('/customer/order.php?bt=' . $btId . '&checkout=1');
    }
    $types = customerDemoProducts();
    $product = $btId > 0 ? customerDemoProduct($btId) : null;
    $staffList = [];
    $extraFeeMap = [];
    $rules = "未成年禁止下单！下单后请立即联系客服确认。";
} else {
    $ready = ClientOrderService::isReady($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$isClient) {
            flash('error', '下单请先登录顾客账号');
            redirect('/login.php?next=' . rawurlencode('/customer/order.php?bt=' . $btId . '&checkout=1'));
        }
        $nick = trim((string) ($_POST['game_name'] ?? ''));
        $gameId = trim((string) ($_POST['game_id'] ?? ''));
        if ($nick === '' && $gameId !== '') {
            $_POST['game_name'] = $gameId;
        }
        $contactName = trim((string) ($_POST['contact_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        if ($contactName !== '' || $phone !== '') {
            $_POST['contact'] = trim($contactName . ($contactName && $phone ? ' / ' : '') . $phone);
        }
        try {
            $oid = ClientOrderService::create($pdo, $uid, $_POST);
            flash('success', '下单成功');
            redirect('/customer/order_detail.php?id=' . $oid);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/customer/order.php?bt=' . (int) ($_POST['business_type_id'] ?? 0) . '&checkout=1&staff=' . (int) ($_POST['staff_id'] ?? 0));
        }
    }

    $types = BusinessTypeService::getAll($pdo, true);
    $product = $btId > 0 ? BusinessTypeService::getById($pdo, $btId) : null;
    if ($product && (int) ($product['status'] ?? 1) !== 1) {
        $product = null;
    }
    $staffList = $ready ? ClientOrderService::listAcceptingStaff($pdo) : [];
    $extraFeeMap = ExtraFeeService::mapEnabledByBusinessType($pdo, $types);
    $rules = SettingsService::get(
        'customer_order_rules',
        "未成年禁止下单！下单后请立即联系客服确认。"
    );
}

$productsJson = [];
foreach ($types as $t) {
    $id = (int) $t['id'];
    $fees = [];
    foreach ($extraFeeMap[$id] ?? [] as $fee) {
        $fees[] = [
            'id' => (int) $fee['id'],
            'name' => (string) $fee['name'],
            'rate' => (float) ($fee['rate'] ?? 0),
            'fixed_amount' => (float) ($fee['fixed_amount'] ?? 0),
            'label' => ExtraFeeService::chargeLabel($fee),
        ];
    }
    $productsJson[] = [
        'id' => $id,
        'name' => (string) $t['name'],
        'price' => (float) $t['unit_price'],
        'fees' => $fees,
    ];
}

$price = $product ? (float) $product['unit_price'] : 0;
$orig = $product && isset($product['original_price']) && $product['original_price'] !== null && $product['original_price'] !== ''
    ? (float) $product['original_price']
    : ($price > 0 ? round($price * 1.3, 2) : 0);
$cover = $product ? trim((string) ($product['cover_url'] ?? '')) : '';
$rulesShort = trim(preg_split('/\r\n|\n|\r/', $rules)[0] ?? $rules);

$rating = ['avg' => 5.0, 'count' => 0];
$orderCount = 17820;
if (empty($demoMode) && $ready) {
    try {
        $row = $pdo->query(
            'SELECT COUNT(*) AS cnt, COALESCE(AVG(score),0) AS avg_score FROM client_order_reviews'
        )->fetch(PDO::FETCH_ASSOC);
        $rating = [
            'avg' => round((float) ($row['avg_score'] ?? 0), 1) ?: 5.0,
            'count' => (int) ($row['cnt'] ?? 0),
        ];
        $orderCount = (int) $pdo->query("SELECT COUNT(*) FROM client_orders WHERE status IN ('DONE','DOING','ACCEPTED')")->fetchColumn();
        $orderCount = max($orderCount, $rating['count'] * 3);
    } catch (Throwable) {
    }
}

$fmt = static function (float $n): string {
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
};

$servers = ['端游', '手游'];
$curServer = (string) ($_POST['game_client'] ?? '');
$qtyPref = max(1, (int) ($_POST['quantity'] ?? $_GET['qty'] ?? 1));

// ========== 提交订单页 ==========
if ($isCheckout) {
    $pageTitle = '提交订单';
    $appTab = 'home';
    $hideTabbar = true;
    $hideFab = true;
    require __DIR__ . '/partials/app_head.php';
    ?>

<header class="app-topbar so-topbar">
    <a class="app-topbar-back" href="/customer/order.php?bt=<?= (int) $btId ?>" aria-label="返回">‹</a>
    <div class="app-topbar-center">提交订单</div>
    <span style="width:36px"></span>
</header>

<?php if ($error): ?><div class="app-alert app-alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if (!$product): ?>
    <div class="app-empty">未找到该陪单<br><a href="/customer/index.php" style="color:var(--app-purple)">返回约单</a></div>
    <?php require __DIR__ . '/partials/app_foot.php'; return; ?>
<?php endif; ?>

<form method="post" id="orderForm" class="so-page">
    <input type="hidden" name="checkout" value="1">
    <input type="hidden" name="business_type_id" id="fieldBtId" value="<?= (int) $product['id'] ?>">
    <input type="hidden" name="quantity" id="fieldQty" value="<?= (int) $qtyPref ?>">

    <div class="so-product-card">
        <div class="so-thumb<?= $cover !== '' ? ' has-img' : '' ?>"
             <?php if ($cover !== ''): ?>style="background-image:url('<?= e($cover) ?>')"<?php endif; ?>>
            <?php if ($cover === ''): ?>
                <span><?= e(mb_substr((string) $product['name'], 0, 6)) ?></span>
            <?php endif; ?>
        </div>
        <div class="so-product-meta">
            <div class="so-product-name"><?= e($product['name']) ?></div>
            <div class="so-product-price">¥<?= number_format($price, 2) ?></div>
        </div>
        <div class="so-product-qty">x<span id="qtyLabel"><?= (int) $qtyPref ?></span></div>
    </div>

    <div class="so-form-card">
        <div class="so-row">
            <label class="so-label">联系人</label>
            <input type="text" name="contact_name" class="so-input" required
                   placeholder="请输入联系人" value="<?= e($_POST['contact_name'] ?? '') ?>">
        </div>
        <div class="so-row">
            <label class="so-label">游戏ID</label>
            <input type="text" name="game_id" class="so-input" required
                   placeholder="请务必输入正确游戏ID" value="<?= e($_POST['game_id'] ?? '') ?>">
        </div>
        <div class="so-row">
            <label class="so-label">游戏昵称</label>
            <input type="text" name="game_name" class="so-input" required
                   placeholder="请务必输入正确游戏昵称" value="<?= e($_POST['game_name'] ?? '') ?>">
        </div>
        <div class="so-row so-row-radio">
            <span class="so-label">客户端</span>
            <div class="so-radios" id="serverList">
                <?php foreach ($servers as $s): ?>
                    <label class="so-radio <?= $curServer === $s ? 'active' : '' ?>">
                        <input type="radio" name="game_client" value="<?= e($s) ?>" <?= $curServer === $s ? 'checked' : '' ?> required>
                        <i></i><?= e($s) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="so-row so-row-top">
            <label class="so-label">指定打手</label>
            <div class="so-field">
                <select name="staff_id" class="so-input so-select">
                    <option value="0">不指定（由客服安排）</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $staffPref ? 'selected' : '' ?>>
                            <?= e($s['nickname'] ?: $s['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="so-hint">尽可能安排你指定的打手；若安排不了，客服会为你调配其他打手</p>
            </div>
        </div>
        <div class="so-row">
            <label class="so-label">输入手机号</label>
            <input type="tel" name="phone" class="so-input" required
                   placeholder="输入添加微信的手机号" value="<?= e($_POST['phone'] ?? '') ?>">
        </div>
        <div class="so-row so-row-top">
            <label class="so-label">备注</label>
            <textarea name="remark" class="so-input so-textarea" rows="2"
                      placeholder="请输入备注，可备注指定的打手（选填）"><?= e($_POST['remark'] ?? '') ?></textarea>
        </div>
        <div class="so-row" id="extrasWrap" style="display:none">
            <span class="so-label">额外项目</span>
            <div id="extrasList" class="so-extras"></div>
        </div>
        <div class="so-row">
            <span class="so-label">商品金额</span>
            <span class="so-amount" id="sheetTotalNum">¥<?= number_format($price * $qtyPref, 2) ?></span>
        </div>
        <div class="so-row so-row-note">
            <span class="so-label">下单说明</span>
            <div class="so-note-text"><?= e($rulesShort ?: '未成年禁止消费！') ?></div>
        </div>
    </div>
</form>

<div class="so-footer">
    <div class="so-footer-left">
        <span class="so-footer-count">共 <strong id="footerQty"><?= (int) $qtyPref ?></strong> 件</span>
        <span class="so-footer-total">合计 <strong id="footerTotal">¥<?= number_format($price * $qtyPref, 2) ?></strong></span>
    </div>
    <?php if ($isClient || !empty($demoMode)): ?>
        <button type="submit" form="orderForm" class="so-submit-btn"><?= !empty($demoMode) ? '演示提交' : '提交订单' ?></button>
    <?php else: ?>
        <a class="so-submit-btn" href="/login.php?next=<?= rawurlencode('/customer/order.php?bt=' . $btId . '&checkout=1') ?>">登录后提交</a>
    <?php endif; ?>
</div>

<script>
const PRODUCTS = <?= json_encode($productsJson, JSON_UNESCAPED_UNICODE) ?>;
const UNIT = <?= json_encode($price) ?>;

function money(n){
  return '¥' + Number(n).toLocaleString('zh-CN',{minimumFractionDigits:2,maximumFractionDigits:2});
}
function renderExtras(){
  const btId = Number(document.getElementById('fieldBtId').value||0);
  const product = PRODUCTS.find(p=>p.id===btId);
  const wrap = document.getElementById('extrasWrap');
  const list = document.getElementById('extrasList');
  list.innerHTML = '';
  if(!product || !product.fees.length){ wrap.style.display='none'; recalc(); return; }
  wrap.style.display = '';
  product.fees.forEach(fee=>{
    const row = document.createElement('label');
    row.className = 'so-extra-row';
    row.innerHTML = '<input type="checkbox" name="extra_fee_ids[]" value="'+fee.id+'" data-rate="'+fee.rate+'" data-fixed="'+fee.fixed_amount+'"><span>'+fee.name+' · '+fee.label+'</span>';
    list.appendChild(row);
  });
  recalc();
}
function recalc(){
  const product = PRODUCTS.find(p=>p.id===Number(document.getElementById('fieldBtId').value||0));
  const base = product ? product.price : UNIT;
  const qty = Math.max(1, Number(document.getElementById('fieldQty').value||1));
  let rate=0, fixed=0;
  document.querySelectorAll('#extrasList input:checked').forEach(el=>{
    rate += Number(el.dataset.rate||0);
    fixed += Number(el.dataset.fixed||0);
  });
  const total = Math.round((base*qty*(1+rate)+fixed)*100)/100;
  document.getElementById('sheetTotalNum').textContent = money(total);
  document.getElementById('footerTotal').textContent = money(total);
  document.getElementById('footerQty').textContent = String(qty);
  document.getElementById('qtyLabel').textContent = String(qty);
}
document.getElementById('extrasList').addEventListener('change', recalc);
document.getElementById('serverList').addEventListener('change', (e)=>{
  document.querySelectorAll('.so-radio').forEach(l=>l.classList.remove('active'));
  const lab = e.target.closest('.so-radio');
  if (lab) lab.classList.add('active');
});
renderExtras();
</script>
<?php require __DIR__ . '/partials/app_foot.php';
    return;
}

// ========== 陪单详情 ==========
$pageTitle = $product ? ((string) $product['name']) : '陪单详情';
$appTab = 'home';
$hideTabbar = true;
$hideFab = true;
require __DIR__ . '/partials/app_head.php';
?>

<header class="app-topbar">
    <a class="app-topbar-back" href="/customer/index.php" aria-label="返回">‹</a>
    <div class="app-topbar-center">陪单详情</div>
    <span style="width:36px"></span>
</header>

<?php if ($error): ?><div class="app-alert app-alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if (!$product): ?>
    <div class="app-empty">未找到该陪单项目<br><a href="/customer/index.php" style="color:var(--app-purple)">返回约单</a></div>
    <?php require __DIR__ . '/partials/app_foot.php'; return; ?>
<?php endif; ?>

<div class="app-detail-hero<?= $cover !== '' ? ' has-img' : '' ?>"
     <?php if ($cover !== ''): ?>style="background-image:url('<?= e($cover) ?>')"<?php endif; ?>>
    <div class="app-detail-hero-text">
        <h2><?= e(brandName()) ?></h2>
        <p>每位陪陪都经过筛选 · 技术与情绪价值<br>三重检验 · 售后 24 小时受理</p>
    </div>
</div>

<div class="app-price-bar">
    <span class="app-price-bar-tag"><?= e(trim((string) ($product['board'] ?? '')) ?: '体验单') ?></span>
    <div class="app-price-bar-nums">
        <?php if ($orig > $price): ?>
            <div>原价: <s><?= e($fmt($orig)) ?></s></div>
        <?php endif; ?>
        <div>
            <span class="now"><?= e($fmt($price)) ?></span>
            <span class="app-coin">★</span>
            <span>/单</span>
        </div>
    </div>
</div>

<div class="app-detail-panel">
    <h3><?= e($product['name']) ?></h3>
    <div class="app-detail-desc">
        <?= e(trim((string) ($product['remark'] ?? '')) ?: '下单后请按客服指引完成，服务到满意为止。') ?>
    </div>
    <div style="margin-top:10px;font-size:12px;color:var(--app-muted)">可接游戏区服</div>
    <span class="app-tag">🎮 <?= e(trim((string) ($product['board'] ?? '')) ?: '端游/手游') ?></span>
</div>

<div class="app-rules">
    <ol>
        <?php foreach (preg_split('/\r\n|\n|\r/', $rules) as $line): ?>
            <?php $line = trim($line); if ($line === '') continue; ?>
            <li><?= e(preg_replace('/^\d+[\.、)\s]*/u', '', $line) ?? $line) ?></li>
        <?php endforeach; ?>
    </ol>
</div>

<div class="app-detail-panel" style="margin-bottom:8px">
    <h3>评价</h3>
</div>
<div class="app-rating" style="margin-bottom:90px">
    <div class="app-rating-score">
        <div class="label">综合评分</div>
        <div class="num"><?= number_format($rating['avg'], 1) ?></div>
        <div class="app-stars"><?= str_repeat('★', max(1, min(5, (int) round($rating['avg'])))) ?></div>
        <div class="app-rating-tags">
            <span>技术水平</span><span>服务态度</span><span>响应速度</span>
        </div>
    </div>
    <div class="app-rating-side">
        <div class="num"><?= (int) $orderCount ?></div>
        <div class="label">累计接单</div>
    </div>
</div>

<div class="app-sticky-order">
    <div class="app-sticky-order-inner">
        <a class="app-chat-btn" href="<?= e($csLink) ?>" <?= str_starts_with((string) $csLink, 'http') ? 'target="_blank" rel="noopener"' : '' ?> aria-label="联系客服">💬</a>
        <a class="app-order-btn" style="display:flex;align-items:center;justify-content:center"
           href="/customer/order.php?bt=<?= (int) $product['id'] ?>&checkout=1<?= $staffPref ? '&staff=' . $staffPref : '' ?>">下单</a>
    </div>
</div>

<?php require __DIR__ . '/partials/app_foot.php'; ?>
