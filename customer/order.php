<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/BusinessTypeService.php';
require_once __DIR__ . '/../includes/ExtraFeeService.php';

$ready = ClientOrderService::isReady($pdo);
$btId = (int) ($_GET['bt'] ?? $_POST['business_type_id'] ?? 0);
$staffPref = (int) ($_GET['staff'] ?? $_POST['staff_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isClient) {
        flash('error', '下单请先登录顾客账号');
        redirect('/login.php?next=' . rawurlencode('/customer/order.php?bt=' . $btId . ($staffPref ? '&staff=' . $staffPref : '')));
    }
    try {
        $oid = ClientOrderService::create($pdo, $uid, $_POST);
        flash('success', '下单成功');
        redirect('/customer/order_detail.php?id=' . $oid);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/customer/order.php?bt=' . (int) ($_POST['business_type_id'] ?? 0) . '&staff=' . (int) ($_POST['staff_id'] ?? 0));
    }
}

$types = BusinessTypeService::getAll($pdo, true);
$staffList = $ready ? ClientOrderService::listAcceptingStaff($pdo) : [];
$extraFeeMap = ExtraFeeService::mapEnabledByBusinessType($pdo, $types);

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

$currentPage = 'home';
$pageTitle = '下单';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<p style="margin-bottom:12px"><a class="btn btn-sm" href="/customer/index.php">← 返回业务</a></p>

<div class="card">
    <div class="card-header"><h2>填写订单</h2></div>
    <div class="card-body">
        <p style="color:var(--text-muted);font-size:13px;margin-bottom:14px">
            指定打手优先；不选则进抢单池。列表仅含打手身份。
        </p>
        <?php if (!$isClient): ?>
            <div class="alert alert-error">提交订单需先 <a href="/login.php?next=<?= rawurlencode('/customer/order.php?bt=' . $btId) ?>">登录顾客账号</a></div>
        <?php endif; ?>

        <form method="post" id="orderForm">
            <div class="form-group" style="margin-bottom:14px">
                <label>游戏名 *</label>
                <input type="text" name="game_name" class="form-control" required value="<?= e($_POST['game_name'] ?? '') ?>" placeholder="游戏内昵称/角色名">
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label>游戏 ID *</label>
                <input type="text" name="game_id" class="form-control" required value="<?= e($_POST['game_id'] ?? '') ?>" placeholder="游戏账号 ID">
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label>客户端 *</label>
                <select name="game_client" class="form-control" required>
                    <?php
                    $clients = ['端游', '手游', 'Steam', 'Wegame', '其他'];
                    $curClient = (string) ($_POST['game_client'] ?? '');
                    ?>
                    <option value="">请选择</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= e($c) ?>" <?= $curClient === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label>联系方式 QQ/微信 *</label>
                <input type="text" name="contact" class="form-control" required value="<?= e($_POST['contact'] ?? '') ?>" placeholder="方便客服联系">
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label>业务类型</label>
                <select name="business_type_id" id="fieldBtId" class="form-control" required>
                    <option value="">请选择</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $btId ? 'selected' : '' ?>>
                            <?= e($t['name']) ?> · ¥<?= formatMoney($t['unit_price']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label>数量</label>
                <input type="number" name="quantity" id="fieldQty" class="form-control" min="1" value="1" required>
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label>指定打手（可空=抢单池）</label>
                <select name="staff_id" class="form-control">
                    <option value="0">不指定，进抢单池</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $staffPref ? 'selected' : '' ?>>
                            <?= e($s['nickname'] ?: $s['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="margin-top:6px;font-size:12px"><a href="/customer/staff.php">去打手广场挑选 →</a></p>
            </div>
            <div class="form-group" id="extrasWrap" style="display:none;margin-bottom:14px">
                <label>额外项目（指定地图、包卡等，可选）</label>
                <div class="extras-list" id="extrasList"></div>
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label>备注</label>
                <input type="text" name="remark" class="form-control" placeholder="选填">
            </div>
            <div class="total-bar">
                <span>应付合计</span>
                <strong id="sheetTotal">¥0.00</strong>
            </div>
            <?php if ($isClient): ?>
                <button type="submit" class="btn btn-primary">确认下单</button>
            <?php else: ?>
                <a class="btn btn-primary" href="/login.php?next=<?= rawurlencode('/customer/order.php?bt=' . $btId) ?>">登录后下单</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<script>
const PRODUCTS = <?= json_encode($productsJson, JSON_UNESCAPED_UNICODE) ?>;
function money(n){return '¥'+Number(n).toLocaleString('zh-CN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function renderExtras(){
  const btId=Number(document.getElementById('fieldBtId').value||0);
  const product=PRODUCTS.find(p=>p.id===btId);
  const wrap=document.getElementById('extrasWrap');
  const list=document.getElementById('extrasList');
  list.innerHTML='';
  if(!product||!product.fees.length){wrap.style.display='none';recalc();return;}
  wrap.style.display='';
  product.fees.forEach(fee=>{
    const row=document.createElement('label');
    row.className='extra-row';
    row.innerHTML='<input type="checkbox" name="extra_fee_ids[]" value="'+fee.id+'" data-rate="'+fee.rate+'" data-fixed="'+fee.fixed_amount+'"><span>'+fee.name+' · '+fee.label+'</span>';
    list.appendChild(row);
  });
  recalc();
}
function recalc(){
  const btId=Number(document.getElementById('fieldBtId').value||0);
  const product=PRODUCTS.find(p=>p.id===btId);
  if(!product){document.getElementById('sheetTotal').textContent='¥0.00';return;}
  const qty=Math.max(1,Number(document.getElementById('fieldQty').value||1));
  let rate=0,fixed=0;
  document.querySelectorAll('#extrasList input:checked').forEach(el=>{rate+=Number(el.dataset.rate||0);fixed+=Number(el.dataset.fixed||0);});
  document.getElementById('sheetTotal').textContent=money(Math.round((product.price*qty*(1+rate)+fixed)*100)/100);
}
document.getElementById('fieldBtId').addEventListener('change',renderExtras);
document.getElementById('fieldQty').addEventListener('input',recalc);
document.getElementById('extrasList').addEventListener('change',recalc);
renderExtras();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
