<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/MembershipService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('membership');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'tier') {
            $id = (int) ($_POST['id'] ?? 0);
            MembershipService::saveTier($pdo, $_POST, $id > 0 ? $id : null);
            flash('success', '档次已保存');
        } elseif ($action === 'card') {
            $id = (int) ($_POST['id'] ?? 0);
            MembershipService::saveCard($pdo, $_POST, $id > 0 ? $id : null);
            flash('success', '卡种已保存');
        } elseif ($action === 'grant') {
            MembershipService::grantCard($pdo, (int) ($_POST['user_id'] ?? 0), (int) ($_POST['card_id'] ?? 0));
            flash('success', '已发卡');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin/membership.php');
}

$tiers = MembershipService::tiers($pdo);
$cards = MembershipService::cards($pdo);
$clients = [];
try {
    $clients = $pdo->query(
        "SELECT id, username, nickname, growth_points, membership_expire_at FROM users WHERE role = 'CLIENT' AND status = 1 ORDER BY id DESC LIMIT 200"
    )->fetchAll();
} catch (PDOException) {
}

$currentPage = 'membership';
$pageTitle = '会员管理';
require __DIR__ . '/partials/header.php';
?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!MembershipService::isReady($pdo)): ?>
<div class="alert alert-error">请先执行 database/migrate_customer_portal.sql</div>
<?php else: ?>

<div class="card">
    <div class="card-header"><h2>会员档次（消费 1 元 ≈ +1 成长值）</h2></div>
    <div class="card-body">
        <form method="post" class="form-row" style="margin-bottom:16px">
            <input type="hidden" name="action" value="tier">
            <div class="form-group"><label>名称</label><input name="name" class="form-control" required></div>
            <div class="form-group"><label>最低成长值</label><input type="number" name="min_points" class="form-control" value="0" min="0"></div>
            <div class="form-group"><label>排序</label><input type="number" name="sort_order" class="form-control" value="0"></div>
            <div class="form-group" style="display:flex;align-items:flex-end"><button class="btn btn-primary">新增档次</button></div>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>名称</th><th>最低成长值</th><th>排序</th></tr></thead>
                <tbody>
                <?php foreach ($tiers as $t): ?>
                    <tr><td><?= e($t['name']) ?></td><td><?= (int) $t['min_points'] ?></td><td><?= (int) $t['sort_order'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>月卡 / 年卡</h2></div>
    <div class="card-body">
        <form method="post" class="form-row" style="margin-bottom:16px">
            <input type="hidden" name="action" value="card">
            <div class="form-group"><label>名称</label><input name="name" class="form-control" required></div>
            <div class="form-group"><label>类型</label>
                <select name="card_type" class="form-control"><option value="month">月卡</option><option value="year">年卡</option></select>
            </div>
            <div class="form-group"><label>天数</label><input type="number" name="duration_days" class="form-control" value="30" min="1"></div>
            <div class="form-group"><label>赠送成长值</label><input type="number" name="bonus_points" class="form-control" value="0" min="0"></div>
            <div class="form-group"><label>标价</label><input type="number" name="price" class="form-control" value="0" step="0.01"></div>
            <div class="form-group" style="display:flex;align-items:flex-end"><button class="btn btn-primary">新增卡种</button></div>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>名称</th><th>类型</th><th>天数</th><th>赠点</th><th>标价</th></tr></thead>
                <tbody>
                <?php foreach ($cards as $c): ?>
                    <tr>
                        <td><?= e($c['name']) ?></td>
                        <td><?= $c['card_type'] === 'year' ? '年卡' : '月卡' ?></td>
                        <td><?= (int) $c['duration_days'] ?></td>
                        <td><?= (int) $c['bonus_points'] ?></td>
                        <td class="money"><?= formatMoney($c['price']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>给顾客发卡</h2></div>
    <div class="card-body">
        <form method="post" class="form-row">
            <input type="hidden" name="action" value="grant">
            <div class="form-group">
                <label>顾客</label>
                <select name="user_id" class="form-control" required>
                    <option value="">请选择</option>
                    <?php foreach ($clients as $u): ?>
                        <option value="<?= (int) $u['id'] ?>">
                            <?= e($u['nickname'] ?: $u['username']) ?> · 成长值<?= (int) ($u['growth_points'] ?? 0) ?>
                            <?php if (!empty($u['membership_expire_at'])): ?> · 到期<?= e($u['membership_expire_at']) ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>卡种</label>
                <select name="card_id" class="form-control" required>
                    <?php foreach ($cards as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end"><button class="btn btn-primary">发卡</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
