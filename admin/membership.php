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
        } elseif ($action === 'update_client') {
            MembershipService::updateClient($pdo, (int) ($_POST['user_id'] ?? 0), $_POST);
            flash('success', '顾客已更新（同步到客户管理）');
        } elseif ($action === 'backfill_links') {
            $n = MembershipService::backfillCustomerLinks($pdo);
            flash('success', "已同步 {$n} 个门户顾客到客户管理");
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin/membership.php');
}

$tiers = MembershipService::tiersAll($pdo);
$cards = MembershipService::cardsAll($pdo);
$activeCards = array_values(array_filter($cards, static fn($c) => (int) ($c['status'] ?? 0) === 1));
$clients = [];
try {
    $clients = $pdo->query(
        "SELECT id, username, nickname, growth_points, membership_expire_at, status
         FROM users WHERE role = 'CLIENT' ORDER BY id DESC LIMIT 200"
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
<div class="alert alert-error">请先执行 database/一键注入_全部更新.sql</div>
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
                <thead><tr><th>名称</th><th>最低成长值</th><th>排序</th><th>状态</th><th>保存</th></tr></thead>
                <tbody>
                <?php foreach ($tiers as $t): ?>
                    <?php $fid = 'tier-form-' . (int) $t['id']; ?>
                    <form method="post" id="<?= $fid ?>"></form>
                    <tr>
                        <td>
                            <input type="hidden" form="<?= $fid ?>" name="action" value="tier">
                            <input type="hidden" form="<?= $fid ?>" name="id" value="<?= (int) $t['id'] ?>">
                            <input form="<?= $fid ?>" name="name" class="form-control" value="<?= e($t['name']) ?>" required>
                        </td>
                        <td><input form="<?= $fid ?>" type="number" name="min_points" class="form-control" value="<?= (int) $t['min_points'] ?>" min="0"></td>
                        <td><input form="<?= $fid ?>" type="number" name="sort_order" class="form-control" value="<?= (int) $t['sort_order'] ?>"></td>
                        <td>
                            <select form="<?= $fid ?>" name="status" class="form-control">
                                <option value="1" <?= (int) $t['status'] === 1 ? 'selected' : '' ?>>启用</option>
                                <option value="0" <?= (int) $t['status'] === 0 ? 'selected' : '' ?>>停用</option>
                            </select>
                        </td>
                        <td><button form="<?= $fid ?>" class="btn btn-sm btn-primary">保存</button></td>
                    </tr>
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
                <thead><tr><th>名称</th><th>类型</th><th>天数</th><th>赠点</th><th>标价</th><th>状态</th><th>保存</th></tr></thead>
                <tbody>
                <?php foreach ($cards as $c): ?>
                    <?php $fid = 'card-form-' . (int) $c['id']; ?>
                    <form method="post" id="<?= $fid ?>"></form>
                    <tr>
                        <td>
                            <input type="hidden" form="<?= $fid ?>" name="action" value="card">
                            <input type="hidden" form="<?= $fid ?>" name="id" value="<?= (int) $c['id'] ?>">
                            <input form="<?= $fid ?>" name="name" class="form-control" value="<?= e($c['name']) ?>" required>
                        </td>
                        <td>
                            <select form="<?= $fid ?>" name="card_type" class="form-control">
                                <option value="month" <?= $c['card_type'] === 'month' ? 'selected' : '' ?>>月卡</option>
                                <option value="year" <?= $c['card_type'] === 'year' ? 'selected' : '' ?>>年卡</option>
                            </select>
                        </td>
                        <td><input form="<?= $fid ?>" type="number" name="duration_days" class="form-control" value="<?= (int) $c['duration_days'] ?>" min="1"></td>
                        <td><input form="<?= $fid ?>" type="number" name="bonus_points" class="form-control" value="<?= (int) $c['bonus_points'] ?>" min="0"></td>
                        <td><input form="<?= $fid ?>" type="number" name="price" class="form-control" value="<?= e((string) $c['price']) ?>" step="0.01"></td>
                        <td>
                            <select form="<?= $fid ?>" name="status" class="form-control">
                                <option value="1" <?= (int) $c['status'] === 1 ? 'selected' : '' ?>>启用</option>
                                <option value="0" <?= (int) $c['status'] === 0 ? 'selected' : '' ?>>停用</option>
                            </select>
                        </td>
                        <td><button form="<?= $fid ?>" class="btn btn-sm btn-primary">保存</button></td>
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
                    <?php foreach ($activeCards as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end"><button class="btn btn-primary">发卡</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
        <h2 style="margin:0">顾客账号（可编辑，与「客户管理」同一批人）</h2>
        <form method="post" style="margin:0">
            <input type="hidden" name="action" value="backfill_links">
            <button class="btn btn-sm" type="submit">同步到客户管理</button>
        </form>
    </div>
    <div class="card-body" style="padding:0">
        <p style="padding:12px 16px 0;color:var(--text-muted);font-size:13px;margin:0">
            改昵称/状态会同步到报单用的「客户管理」。预存余额请到
            <a href="/admin/customers.php">客户管理</a> 改。需先执行
            <code>database/一键注入_全部更新.sql</code>。
        </p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th><th>用户名</th><th>昵称</th><th>成长值</th><th>会员到期</th><th>状态</th><th>新密码</th><th>保存</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($clients as $u): ?>
                    <?php $fid = 'client-form-' . (int) $u['id']; ?>
                    <form method="post" id="<?= $fid ?>"></form>
                    <tr>
                        <td><?= (int) $u['id'] ?></td>
                        <td><?= e($u['username']) ?></td>
                        <td>
                            <input type="hidden" form="<?= $fid ?>" name="action" value="update_client">
                            <input type="hidden" form="<?= $fid ?>" name="user_id" value="<?= (int) $u['id'] ?>">
                            <input form="<?= $fid ?>" name="nickname" class="form-control" value="<?= e($u['nickname'] ?: $u['username']) ?>">
                        </td>
                        <td>
                            <input form="<?= $fid ?>" type="number" name="growth_points" class="form-control"
                                   value="<?= (int) ($u['growth_points'] ?? 0) ?>" min="0">
                        </td>
                        <td>
                            <input form="<?= $fid ?>" type="datetime-local" name="membership_expire_at" class="form-control"
                                   value="<?= e(!empty($u['membership_expire_at']) ? date('Y-m-d\TH:i', strtotime((string) $u['membership_expire_at'])) : '') ?>">
                        </td>
                        <td>
                            <select form="<?= $fid ?>" name="status" class="form-control">
                                <option value="1" <?= (int) ($u['status'] ?? 0) === 1 ? 'selected' : '' ?>>启用</option>
                                <option value="0" <?= (int) ($u['status'] ?? 0) === 0 ? 'selected' : '' ?>>禁用</option>
                            </select>
                        </td>
                        <td>
                            <input form="<?= $fid ?>" type="text" name="new_password" class="form-control" placeholder="不改留空" autocomplete="new-password">
                        </td>
                        <td><button form="<?= $fid ?>" class="btn btn-sm btn-primary">保存</button></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($clients === []): ?>
                    <tr><td colspan="8" style="text-align:center;color:var(--text-muted)">暂无顾客账号</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
