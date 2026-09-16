<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ClientOrderService.php';
require_once __DIR__ . '/../includes/BusinessTypeService.php';
require_once __DIR__ . '/../includes/MembershipService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requireClient();

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');
$uid = (int) Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            ClientOrderService::create($pdo, $uid, $_POST);
            flash('success', '下单成功');
        } elseif ($action === 'cancel') {
            ClientOrderService::cancel($pdo, (int) ($_POST['id'] ?? 0), $uid);
            flash('success', '已取消');
        } elseif ($action === 'review') {
            ClientOrderService::addReview(
                $pdo,
                (int) ($_POST['id'] ?? 0),
                $uid,
                (int) ($_POST['score'] ?? 5),
                (string) ($_POST['content'] ?? '')
            );
            flash('success', '评价成功');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/customer/index.php');
}

$types = BusinessTypeService::getAll($pdo, true);
$staffList = ClientOrderService::listAcceptingStaff($pdo);
$orders = ClientOrderService::isReady($pdo) ? ClientOrderService::listForClient($pdo, $uid) : [];
$userRow = $pdo->prepare('SELECT growth_points, membership_expire_at FROM users WHERE id = ?');
$userRow->execute([$uid]);
$me = $userRow->fetch() ?: ['growth_points' => 0, 'membership_expire_at' => null];
$points = (int) ($me['growth_points'] ?? 0);
$tier = MembershipService::tierForPoints($pdo, $points);

$user = Auth::user();
$currentPage = 'home';
$pageTitle = brandTitle('顾客中心');
$bodyClass = 'has-nav';
require __DIR__ . '/partials/head.php';
?>
<div class="app-shell">
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="top-bar-info">
                <h1>顾客中心</h1>
                <p class="subtitle"><?= e($user['nickname'] ?? '') ?> · <?= e($tier['name'] ?? '普通') ?> · 成长值 <?= $points ?></p>
            </div>
            <a href="/logout.php" class="btn btn-sm">退出</a>
        </div>
    </header>

    <main class="page-content">
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if (!ClientOrderService::isReady($pdo)): ?>
            <div class="alert alert-error">请先执行 database/migrate_customer_portal.sql</div>
        <?php else: ?>

        <div class="section-title">下单</div>
        <form method="post" class="card" style="padding:16px;margin-bottom:20px">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>业务类型</label>
                <select name="business_type_id" class="form-control" required>
                    <option value="">请选择</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?> · ¥<?= formatMoney($t['unit_price']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>指定打手（可空=进抢单池）</label>
                <select name="staff_id" class="form-control">
                    <option value="0">不指定，进抢单池</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= e($s['nickname'] ?: $s['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>数量</label>
                <input type="number" name="quantity" class="form-control" min="1" value="1">
            </div>
            <div class="form-group">
                <label>备注</label>
                <input type="text" name="remark" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">提交订单</button>
        </form>

        <div class="section-title">我的订单</div>
        <?php if ($orders === []): ?>
            <div class="empty-state"><p>暂无订单</p></div>
        <?php else: ?>
            <div class="order-list">
            <?php foreach ($orders as $o): ?>
                <div class="order-card" style="padding:12px;margin-bottom:10px;background:#fff;border-radius:8px">
                    <div><strong><?= e($o['order_no']) ?></strong> · <?= e(ClientOrderService::statusLabel((string) $o['status'])) ?></div>
                    <div><?= e($o['business_type_name']) ?> ×<?= (int) $o['quantity'] ?> · ¥<?= formatMoney($o['amount']) ?></div>
                    <div>打手：<?= e($o['staff_name'] ?: '待接') ?></div>
                    <?php if (in_array($o['status'], ['WAITING', 'POOL'], true)): ?>
                        <form method="post" style="margin-top:8px" onsubmit="return confirm('确认取消？')">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                            <button class="btn btn-sm">取消</button>
                        </form>
                    <?php elseif ($o['status'] === 'DONE' && !ClientOrderService::getReview($pdo, (int) $o['id'])): ?>
                        <form method="post" style="margin-top:8px">
                            <input type="hidden" name="action" value="review">
                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                            <select name="score" class="form-control" style="margin-bottom:6px">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?= $i ?>"><?= $i ?> 星</option>
                                <?php endfor; ?>
                            </select>
                            <input type="text" name="content" class="form-control" placeholder="评价（选填）" style="margin-bottom:6px">
                            <button class="btn btn-sm btn-primary">提交评价</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
