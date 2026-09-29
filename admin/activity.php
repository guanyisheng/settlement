<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ActivityService.php';
require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('activity');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');
$ready = ActivityService::isReady($pdo);
if ($ready) {
    ActivityService::ensureCampaignSchema($pdo);
}

$hasCampaigns = $ready && ActivityService::hasCampaigns($pdo);
$campaigns = $ready ? ActivityService::listActivities($pdo) : [];
$allowedTabs = ['campaigns', 'grant', 'orders', 'prizes', 'items', 'presets', 'rank', 'ledger'];
$tab = (string) ($_GET['tab'] ?? $_POST['_tab'] ?? ($hasCampaigns ? 'campaigns' : 'grant'));
if (!in_array($tab, $allowedTabs, true)) {
    $tab = $hasCampaigns ? 'campaigns' : 'grant';
}
$aid = (int) ($_GET['aid'] ?? $_POST['activity_id'] ?? $_POST['_aid'] ?? 0);
if ($aid <= 0) {
    $aid = $ready ? ActivityService::getDefaultActivityId($pdo) : 1;
}
$currentActivity = $ready ? ActivityService::getActivity($pdo, $aid) : null;
if (!$currentActivity && $campaigns !== []) {
    $aid = (int) $campaigns[0]['id'];
    $currentActivity = $campaigns[0];
}

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $nextTab = (string) ($_POST['_tab'] ?? $tab);
    if (!in_array($nextTab, $allowedTabs, true)) {
        $nextTab = 'grant';
    }
    try {
        $adminId = (int) Auth::id();
        if ($action === 'save_activity') {
            $id = (int) ($_POST['id'] ?? 0);
            $newId = ActivityService::saveActivity($pdo, $_POST + ['status' => isset($_POST['status']) ? 1 : 0], $id > 0 ? $id : null);
            flash('success', $id > 0 ? '活动已保存' : '活动已创建');
            $aid = $newId;
        } elseif ($action === 'grant_points') {
            $user = ActivityService::findUserByUsername($pdo, (string) ($_POST['username'] ?? ''));
            if (!$user) {
                throw new RuntimeException('找不到该用户名');
            }
            $delta = (int) ($_POST['points'] ?? 0);
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if ($reason === '') {
                $reason = $delta >= 0 ? '后台加积分' : '后台扣积分';
            }
            $after = ActivityService::adjustPoints($pdo, (int) $user['id'], $delta, $reason, $adminId);
            flash('success', '已更新 ' . $user['username'] . ' 积分，余额 ' . $after);
        } elseif ($action === 'grant_preset') {
            $user = ActivityService::findUserByUsername($pdo, (string) ($_POST['username'] ?? ''));
            if (!$user) {
                throw new RuntimeException('找不到该用户名');
            }
            $after = ActivityService::grantByPreset($pdo, (int) $user['id'], (int) ($_POST['preset_id'] ?? 0), $adminId);
            flash('success', '快捷加分成功，' . $user['username'] . ' 余额 ' . $after);
        } elseif ($action === 'grant_chance') {
            $user = ActivityService::findUserByUsername($pdo, (string) ($_POST['username'] ?? ''));
            if (!$user) {
                throw new RuntimeException('找不到该用户名');
            }
            $delta = (int) ($_POST['chances'] ?? 0);
            $chanceAid = (int) ($_POST['activity_id'] ?? $aid);
            $after = ActivityService::adjustLotteryChances($pdo, (int) $user['id'], $delta, $adminId, '', $chanceAid);
            flash('success', '已更新抽奖次数，' . $user['username'] . ' 剩余 ' . $after . ' 次');
        } elseif ($action === 'save_preset') {
            $id = (int) ($_POST['id'] ?? 0);
            ActivityService::savePreset($pdo, [
                'name'       => $_POST['name'] ?? '',
                'points'     => $_POST['points'] ?? 0,
                'sort_order' => $_POST['sort_order'] ?? 0,
                'status'     => isset($_POST['status']) ? 1 : 0,
            ], $id > 0 ? $id : null);
            flash('success', '快捷加分模板已保存');
        } elseif ($action === 'save_prize') {
            $id = (int) ($_POST['id'] ?? 0);
            ActivityService::savePrize($pdo, $_POST + [
                'status' => isset($_POST['status']) ? 1 : 0,
                'activity_id' => (int) ($_POST['activity_id'] ?? $aid),
            ], $id > 0 ? $id : null);
            flash('success', '奖品已保存');
        } elseif ($action === 'save_item') {
            $id = (int) ($_POST['id'] ?? 0);
            ActivityService::saveExchangeItem($pdo, $_POST + [
                'status' => isset($_POST['status']) ? 1 : 0,
                'activity_id' => (int) ($_POST['activity_id'] ?? $aid),
            ], $id > 0 ? $id : null);
            flash('success', '兑换物已保存');
        } elseif ($action === 'fulfill') {
            ActivityService::fulfillExchange(
                $pdo,
                (int) ($_POST['order_id'] ?? 0),
                $adminId,
                (string) ($_POST['admin_note'] ?? ''),
                false
            );
            flash('success', '已标记发放完成');
        } elseif ($action === 'cancel_exchange') {
            ActivityService::fulfillExchange(
                $pdo,
                (int) ($_POST['order_id'] ?? 0),
                $adminId,
                (string) ($_POST['admin_note'] ?? '取消'),
                true
            );
            flash('success', '已取消并退回积分');
        } else {
            throw new RuntimeException('未知操作');
        }
        $q = ['tab' => $nextTab, 'aid' => (int) ($_POST['activity_id'] ?? $_POST['_aid'] ?? $aid)];
        if (trim((string) ($_POST['username'] ?? '')) !== '' && str_starts_with($action, 'grant_')) {
            $q['q'] = trim((string) $_POST['username']);
        }
        redirect('/admin/activity.php?' . http_build_query($q));
    } catch (Throwable $e) {
        flashError($e, 'ACT');
        redirect('/admin/activity.php?' . http_build_query(['tab' => $nextTab, 'aid' => $aid]));
    }
}

$share = $ready ? ActivityService::activityMeta($pdo, $aid) : ActivityService::shareMeta();
$presets = $ready ? ActivityService::listPresets($pdo) : [];
$prizes = $ready ? ActivityService::listPrizes($pdo, false, $aid) : [];
$items = $ready ? ActivityService::listExchangeItems($pdo, false, $aid) : [];
$ledger = $ready ? ActivityService::ledger($pdo, null, 80) : [];
$orders = $ready ? ActivityService::exchangeOrders($pdo, null, 50, $aid) : [];
$board = $ready ? ActivityService::leaderboard($pdo, 10) : [];
$lookupUser = null;
$lookupWallet = null;
$qUser = trim((string) ($_GET['q'] ?? ''));
if ($ready && $qUser !== '') {
    $lookupUser = ActivityService::findUserByUsername($pdo, $qUser);
    if ($lookupUser) {
        $lookupWallet = ActivityService::getWallet($pdo, (int) $lookupUser['id'], $aid);
    }
}

$pendingOrders = 0;
foreach ($orders as $o) {
    if (($o['status'] ?? '') === 'PENDING') {
        $pendingOrders++;
    }
}

$prizeTypeLabel = static fn(string $t): string => match ($t) {
    'points' => '积分',
    'claim' => '人工发放',
    default => '谢谢参与',
};

$tabs = [
    'campaigns' => '活动列表',
    'grant'     => '客服上分',
    'orders'    => '兑换单' . ($pendingOrders > 0 ? " ({$pendingOrders})" : ''),
    'prizes'    => '抽奖奖品',
    'items'     => '兑换物',
    'presets'   => '加分模板',
    'rank'      => '排行榜',
    'ledger'    => '积分流水',
];
if (!$ready) {
    unset($tabs['campaigns']);
}

$currentPage = 'activity';
$pageTitle = '活动管理';
require __DIR__ . '/partials/header.php';

$editPresetId = (int) ($_GET['edit_preset'] ?? 0);
$editPrizeId = (int) ($_GET['edit_prize'] ?? 0);
$editItemId = (int) ($_GET['edit_item'] ?? 0);
$editCampaignId = (int) ($_GET['edit_campaign'] ?? 0);
$editPreset = null;
$editPrize = null;
$editItem = null;
$editCampaign = null;
foreach ($campaigns as $c) {
    if ((int) $c['id'] === $editCampaignId) {
        $editCampaign = $c;
        break;
    }
}
foreach ($presets as $p) {
    if ((int) $p['id'] === $editPresetId) {
        $editPreset = $p;
        break;
    }
}
foreach ($prizes as $p) {
    if ((int) $p['id'] === $editPrizeId) {
        $editPrize = $p;
        break;
    }
}
foreach ($items as $it) {
    if ((int) $it['id'] === $editItemId) {
        $editItem = $it;
        break;
    }
}
?>

<?php renderAlertError($error); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<?php if (!$ready): ?>
<div class="alert alert-error">请先执行 <code>database/一键注入_全部更新.sql</code> 安装活动表。</div>
<?php else: ?>

<div class="act-head">
    <div>
        <h1 class="act-title">活动管理</h1>
        <p class="act-sub">
            支持多个转盘活动；积分全站共用，抽奖次数与奖品按活动分开。
            入口 <a href="/customer/activity.php" target="_blank" rel="noopener">/customer/activity.php</a>
            <?php if ($currentActivity): ?>
                · 当前管理：<strong><?= e($currentActivity['name']) ?></strong>
                · 链接 <a href="/customer/activity.php?id=<?= (int) $aid ?>" target="_blank" rel="noopener">?id=<?= (int) $aid ?></a>
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($hasCampaigns && $tab !== 'campaigns' && $tab !== 'presets' && $tab !== 'rank' && $tab !== 'ledger'): ?>
<form method="get" class="act-aid-bar">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <?php if ($qUser !== ''): ?><input type="hidden" name="q" value="<?= e($qUser) ?>"><?php endif; ?>
    <label>管理活动</label>
    <select name="aid" class="form-control" onchange="this.form.submit()">
        <?php foreach ($campaigns as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $aid ? 'selected' : '' ?>>
                <?= e($c['name']) ?><?= (int) $c['status'] ? '' : '（已关）' ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>
<?php endif; ?>

<nav class="act-tabs" aria-label="活动分区">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="act-tab <?= $tab === $key ? 'is-active' : '' ?>"
           href="?tab=<?= e($key) ?>&aid=<?= (int) $aid ?><?= $qUser !== '' ? '&q=' . rawurlencode($qUser) : '' ?>">
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'campaigns'): ?>
<?php if (!$hasCampaigns): ?>
<div class="alert alert-error">多活动表未就绪，请执行 <code>database/一键注入_全部更新.sql</code> 后刷新本页。</div>
<?php else: ?>
<div class="card">
    <div class="card-header"><h2><?= $editCampaign ? '编辑活动文案' : '新建活动' ?></h2></div>
    <div class="card-body">
        <p class="act-hint">
            可开多个转盘活动。填名称与前台标题/副标题后点「<?= $editCampaign ? '保存活动' : '创建活动' ?>」。
            创建后到「抽奖奖品」给它单独配奖池；前台链接形如 <code>/customer/activity.php?id=2</code>。
        </p>
        <form method="post" class="act-edit-form">
            <input type="hidden" name="action" value="save_activity">
            <input type="hidden" name="_tab" value="campaigns">
            <?php if ($editCampaign): ?>
                <input type="hidden" name="id" value="<?= (int) $editCampaign['id'] ?>">
                <input type="hidden" name="activity_id" value="<?= (int) $editCampaign['id'] ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>后台名称 *</label>
                    <input name="name" class="form-control" required placeholder="如：周末转盘 / 新春抽奖"
                           value="<?= e((string) ($editCampaign['name'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label>前台标题</label>
                    <input name="title" class="form-control" placeholder="页面大标题 / 分享标题"
                           value="<?= e((string) ($editCampaign['title'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label>排序</label>
                    <input type="number" name="sort_order" class="form-control"
                           value="<?= (int) ($editCampaign['sort_order'] ?? 0) ?>">
                </div>
            </div>
            <div class="form-group">
                <label>前台副标题 / 描述</label>
                <input name="description" class="form-control" placeholder="一句话介绍活动"
                       value="<?= e((string) ($editCampaign['description'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>分享图 URL（空=站点 Logo）</label>
                <input type="text" name="logo_url" class="form-control" placeholder="https://..."
                       value="<?= e((string) ($editCampaign['logo_url'] ?? '')) ?>">
            </div>
            <label class="act-check">
                <input type="checkbox" name="status" value="1"
                    <?= !isset($editCampaign) || (int) ($editCampaign['status'] ?? 0) ? 'checked' : '' ?>> 开启前台展示
            </label>
            <div class="act-form-actions">
                <button type="submit" class="btn btn-primary"><?= $editCampaign ? '保存活动' : '创建活动' ?></button>
                <?php if ($editCampaign): ?>
                    <a class="btn" href="?tab=campaigns">取消，去新建另一个</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><h2>全部活动（<?= count($campaigns) ?>）</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>ID</th><th>名称</th><th>前台标题</th><th>状态</th><th>前台链接</th><th></th></tr>
                </thead>
                <tbody>
                <?php if ($campaigns === []): ?>
                    <tr><td colspan="6" class="act-empty">还没有活动，请在上方创建</td></tr>
                <?php endif; ?>
                <?php foreach ($campaigns as $c): ?>
                    <tr>
                        <td><?= (int) $c['id'] ?></td>
                        <td><?= e($c['name']) ?></td>
                        <td><?= e($c['title']) ?></td>
                        <td>
                            <?php if ((int) $c['status']): ?>
                                <span class="badge badge-active">开启</span>
                            <?php else: ?>
                                <span class="badge badge-disabled">关闭</span>
                            <?php endif; ?>
                        </td>
                        <td><code>/customer/activity.php?id=<?= (int) $c['id'] ?></code></td>
                        <td style="white-space:nowrap">
                            <a class="btn btn-sm" href="?tab=campaigns&edit_campaign=<?= (int) $c['id'] ?>">改文案</a>
                            <a class="btn btn-sm btn-primary" href="?tab=prizes&aid=<?= (int) $c['id'] ?>">管奖品</a>
                            <a class="btn btn-sm" href="/customer/activity.php?id=<?= (int) $c['id'] ?>" target="_blank" rel="noopener">打开</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'grant'): ?>
<div class="card">
    <div class="card-header"><h2>查用户 · 上分 / 发抽奖次数</h2></div>
    <div class="card-body">
        <form method="get" class="act-lookup">
            <input type="hidden" name="tab" value="grant">
            <input type="hidden" name="aid" value="<?= (int) $aid ?>">
            <input type="text" name="q" class="form-control" placeholder="输入用户名查询"
                   value="<?= e($qUser) ?>" autofocus>
            <button type="submit" class="btn btn-primary">查询</button>
        </form>

        <?php if ($lookupUser && $lookupWallet): ?>
            <div class="act-user-card">
                <div class="act-user-main">
                    <strong><?= e($lookupUser['username']) ?></strong>
                    <span><?= e((string) ($lookupUser['nickname'] ?? '')) ?></span>
                    <span class="badge"><?= e((string) $lookupUser['role']) ?></span>
                </div>
                <div class="act-user-stats">
                    <div><em><?= (int) $lookupWallet['points'] ?></em><span>积分（共用）</span></div>
                    <div><em><?= (int) $lookupWallet['lottery_chances'] ?></em><span>本活动次数</span></div>
                </div>
            </div>

            <div class="act-grant-grid">
                <form method="post" class="act-grant-box">
                    <input type="hidden" name="action" value="grant_preset">
                    <input type="hidden" name="_tab" value="grant">
                    <input type="hidden" name="username" value="<?= e($lookupUser['username']) ?>">
                    <h3>快捷加分</h3>
                    <div class="form-group">
                        <label>模板</label>
                        <select name="preset_id" class="form-control" required>
                            <?php
                            $hasPreset = false;
                            foreach ($presets as $p):
                                if (!(int) $p['status']) {
                                    continue;
                                }
                                $hasPreset = true;
                                ?>
                                <option value="<?= (int) $p['id'] ?>">
                                    <?= e($p['name']) ?>（+<?= (int) $p['points'] ?>）
                                </option>
                            <?php endforeach; ?>
                            <?php if (!$hasPreset): ?>
                                <option value="" disabled>请先在「加分模板」里添加</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" <?= $hasPreset ? '' : 'disabled' ?>>一键加分</button>
                </form>

                <form method="post" class="act-grant-box">
                    <input type="hidden" name="action" value="grant_points">
                    <input type="hidden" name="_tab" value="grant">
                    <input type="hidden" name="username" value="<?= e($lookupUser['username']) ?>">
                    <h3>手动加减积分</h3>
                    <div class="form-group">
                        <label>积分（正加负扣）</label>
                        <input type="number" name="points" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>备注</label>
                        <input type="text" name="reason" class="form-control" placeholder="如：完成某某任务">
                    </div>
                    <button type="submit" class="btn btn-primary">提交</button>
                </form>

                <form method="post" class="act-grant-box">
                    <input type="hidden" name="action" value="grant_chance">
                    <input type="hidden" name="_tab" value="grant">
                    <input type="hidden" name="_aid" value="<?= (int) $aid ?>">
                    <input type="hidden" name="username" value="<?= e($lookupUser['username']) ?>">
                    <h3>抽奖次数</h3>
                    <div class="form-group">
                        <label>发放到哪个活动</label>
                        <select name="activity_id" class="form-control" required>
                            <?php foreach ($campaigns as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $aid ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($campaigns === []): ?>
                                <option value="1">默认活动</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>次数（正加负扣）</label>
                        <input type="number" name="chances" class="form-control" required value="1">
                    </div>
                    <button type="submit" class="btn btn-primary">发放次数</button>
                </form>
            </div>
        <?php elseif ($qUser !== ''): ?>
            <div class="alert alert-error" style="margin:0">未找到用户「<?= e($qUser) ?>」</div>
        <?php else: ?>
            <p class="act-hint">先查用户，再上分或发抽奖次数。任务积分与抽奖机会均由人工发放。</p>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($tab === 'orders'): ?>
<div class="card">
    <div class="card-header"><h2>兑换单 · 人工发放</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>时间</th><th>用户</th><th>物品</th><th>积分</th><th>状态</th><th style="min-width:220px">操作</th></tr>
                </thead>
                <tbody>
                <?php if ($orders === []): ?>
                    <tr><td colspan="6" class="act-empty">暂无兑换单</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= e(formatDateTimeShort($o['created_at'])) ?></td>
                        <td>
                            <?= e($o['username']) ?>
                            <?php if (!empty($o['nickname'])): ?>
                                <div class="act-muted"><?= e($o['nickname']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($o['item_name']) ?></td>
                        <td><?= (int) $o['cost_points'] ?></td>
                        <td>
                            <?php if ($o['status'] === 'PENDING'): ?>
                                <span class="badge badge-pending">待发放</span>
                            <?php elseif ($o['status'] === 'DONE'): ?>
                                <span class="badge badge-settled">已发放</span>
                            <?php else: ?>
                                <span class="badge badge-disabled">已取消</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($o['status'] === 'PENDING'): ?>
                                <div class="act-order-actions">
                                    <form method="post" class="act-inline-form">
                                        <input type="hidden" name="action" value="fulfill">
                                        <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                        <input type="hidden" name="_tab" value="orders">
                                        <input type="hidden" name="_aid" value="<?= (int) $aid ?>">
                                        <input type="text" name="admin_note" class="form-control" placeholder="备注（选填）">
                                        <button class="btn btn-sm btn-primary" type="submit">确认发放</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('取消并退回积分？')">
                                        <input type="hidden" name="action" value="cancel_exchange">
                                        <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                        <input type="hidden" name="_tab" value="orders">
                                        <input type="hidden" name="_aid" value="<?= (int) $aid ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">退分</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="act-muted"><?= e((string) ($o['admin_note'] ?? '—')) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($tab === 'prizes'): ?>
<div class="card">
    <div class="card-header">
        <h2><?= $editPrize ? '编辑奖品' : '添加奖品' ?></h2>
    </div>
    <div class="card-body">
        <p class="act-hint">权重越大越容易中。积分类中奖后自动到账；人工发放只记日志。</p>
        <form method="post" class="act-edit-form">
            <input type="hidden" name="action" value="save_prize">
            <input type="hidden" name="_tab" value="prizes">
            <input type="hidden" name="activity_id" value="<?= (int) $aid ?>">
            <input type="hidden" name="_aid" value="<?= (int) $aid ?>">
            <?php if ($editPrize): ?>
                <input type="hidden" name="id" value="<?= (int) $editPrize['id'] ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>名称</label>
                    <input name="name" class="form-control" required
                           value="<?= e((string) ($editPrize['name'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label>权重</label>
                    <input type="number" name="weight" class="form-control" min="1"
                           value="<?= (int) ($editPrize['weight'] ?? 10) ?>">
                </div>
                <div class="form-group">
                    <label>类型</label>
                    <select name="prize_type" class="form-control">
                        <?php
                        $curType = (string) ($editPrize['prize_type'] ?? 'empty');
                        foreach (['empty' => '谢谢参与', 'points' => '积分', 'claim' => '人工发放'] as $k => $lab):
                            ?>
                            <option value="<?= $k ?>" <?= $curType === $k ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>积分值</label>
                    <input type="number" name="points_value" class="form-control"
                           value="<?= (int) ($editPrize['points_value'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label>库存（空=不限）</label>
                    <input type="number" name="stock" class="form-control"
                           value="<?= isset($editPrize['stock']) && $editPrize['stock'] !== null ? (int) $editPrize['stock'] : '' ?>">
                </div>
                <div class="form-group">
                    <label>排序</label>
                    <input type="number" name="sort_order" class="form-control"
                           value="<?= (int) ($editPrize['sort_order'] ?? 0) ?>">
                </div>
            </div>
            <label class="act-check">
                <input type="checkbox" name="status" value="1"
                    <?= !isset($editPrize) || (int) ($editPrize['status'] ?? 0) ? 'checked' : '' ?>> 启用
            </label>
            <div class="act-form-actions">
                <button type="submit" class="btn btn-primary"><?= $editPrize ? '保存修改' : '添加奖品' ?></button>
                <?php if ($editPrize): ?>
                    <a class="btn" href="?tab=prizes&aid=<?= (int) $aid ?>">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>奖品列表</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>名称</th><th>权重</th><th>类型</th><th>积分</th><th>库存</th><th>排序</th><th>状态</th><th></th></tr>
                </thead>
                <tbody>
                <?php if ($prizes === []): ?>
                    <tr><td colspan="8" class="act-empty">暂无奖品</td></tr>
                <?php endif; ?>
                <?php foreach ($prizes as $p): ?>
                    <tr>
                        <td><?= e($p['name']) ?></td>
                        <td><?= (int) $p['weight'] ?></td>
                        <td><?= e($prizeTypeLabel((string) $p['prize_type'])) ?></td>
                        <td><?= (int) $p['points_value'] ?></td>
                        <td><?= $p['stock'] === null ? '不限' : (int) $p['stock'] ?></td>
                        <td><?= (int) $p['sort_order'] ?></td>
                        <td>
                            <?php if ((int) $p['status']): ?>
                                <span class="badge badge-active">启用</span>
                            <?php else: ?>
                                <span class="badge badge-disabled">停用</span>
                            <?php endif; ?>
                        </td>
                        <td><a class="btn btn-sm" href="?tab=prizes&aid=<?= (int) $aid ?>&edit_prize=<?= (int) $p['id'] ?>">编辑</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($tab === 'items'): ?>
<div class="card">
    <div class="card-header"><h2><?= $editItem ? '编辑兑换物' : '添加兑换物' ?></h2></div>
    <div class="card-body">
        <form method="post" class="act-edit-form">
            <input type="hidden" name="action" value="save_item">
            <input type="hidden" name="_tab" value="items">
            <input type="hidden" name="activity_id" value="<?= (int) $aid ?>">
            <input type="hidden" name="_aid" value="<?= (int) $aid ?>">
            <?php if ($editItem): ?>
                <input type="hidden" name="id" value="<?= (int) $editItem['id'] ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>名称</label>
                    <input name="name" class="form-control" required
                           value="<?= e((string) ($editItem['name'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label>所需积分</label>
                    <input type="number" name="cost_points" class="form-control" min="1"
                           value="<?= (int) ($editItem['cost_points'] ?? 100) ?>">
                </div>
                <div class="form-group">
                    <label>库存（空=不限）</label>
                    <input type="number" name="stock" class="form-control"
                           value="<?= isset($editItem['stock']) && $editItem['stock'] !== null ? (int) $editItem['stock'] : '' ?>">
                </div>
                <div class="form-group">
                    <label>排序</label>
                    <input type="number" name="sort_order" class="form-control"
                           value="<?= (int) ($editItem['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>备注</label>
                    <input name="remark" class="form-control"
                           value="<?= e((string) ($editItem['remark'] ?? '')) ?>">
                </div>
            </div>
            <label class="act-check">
                <input type="checkbox" name="status" value="1"
                    <?= !isset($editItem) || (int) ($editItem['status'] ?? 0) ? 'checked' : '' ?>> 启用
            </label>
            <div class="act-form-actions">
                <button type="submit" class="btn btn-primary"><?= $editItem ? '保存修改' : '添加兑换物' ?></button>
                <?php if ($editItem): ?>
                    <a class="btn" href="?tab=items&aid=<?= (int) $aid ?>">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>兑换物列表</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>名称</th><th>积分</th><th>库存</th><th>备注</th><th>排序</th><th>状态</th><th></th></tr>
                </thead>
                <tbody>
                <?php if ($items === []): ?>
                    <tr><td colspan="7" class="act-empty">暂无兑换物</td></tr>
                <?php endif; ?>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['name']) ?></td>
                        <td><?= (int) $it['cost_points'] ?></td>
                        <td><?= $it['stock'] === null ? '不限' : (int) $it['stock'] ?></td>
                        <td><?= e((string) ($it['remark'] ?? '')) ?></td>
                        <td><?= (int) $it['sort_order'] ?></td>
                        <td>
                            <?php if ((int) $it['status']): ?>
                                <span class="badge badge-active">启用</span>
                            <?php else: ?>
                                <span class="badge badge-disabled">停用</span>
                            <?php endif; ?>
                        </td>
                        <td><a class="btn btn-sm" href="?tab=items&aid=<?= (int) $aid ?>&edit_item=<?= (int) $it['id'] ?>">编辑</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($tab === 'presets'): ?>
<div class="card">
    <div class="card-header"><h2><?= $editPreset ? '编辑模板' : '添加快捷加分模板' ?></h2></div>
    <div class="card-body">
        <form method="post" class="act-edit-form">
            <input type="hidden" name="action" value="save_preset">
            <input type="hidden" name="_tab" value="presets">
            <?php if ($editPreset): ?>
                <input type="hidden" name="id" value="<?= (int) $editPreset['id'] ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>名称</label>
                    <input type="text" name="name" class="form-control" required
                           placeholder="如：完成日常任务"
                           value="<?= e((string) ($editPreset['name'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label>积分</label>
                    <input type="number" name="points" class="form-control" required
                           value="<?= (int) ($editPreset['points'] ?? 10) ?>">
                </div>
                <div class="form-group">
                    <label>排序</label>
                    <input type="number" name="sort_order" class="form-control"
                           value="<?= (int) ($editPreset['sort_order'] ?? 0) ?>">
                </div>
            </div>
            <label class="act-check">
                <input type="checkbox" name="status" value="1"
                    <?= !isset($editPreset) || (int) ($editPreset['status'] ?? 0) ? 'checked' : '' ?>> 启用
            </label>
            <div class="act-form-actions">
                <button type="submit" class="btn btn-primary"><?= $editPreset ? '保存修改' : '添加模板' ?></button>
                <?php if ($editPreset): ?>
                    <a class="btn" href="?tab=presets">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>模板列表</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>名称</th><th>积分</th><th>排序</th><th>状态</th><th></th></tr>
                </thead>
                <tbody>
                <?php if ($presets === []): ?>
                    <tr><td colspan="5" class="act-empty">暂无模板</td></tr>
                <?php endif; ?>
                <?php foreach ($presets as $p): ?>
                    <tr>
                        <td><?= e($p['name']) ?></td>
                        <td>+<?= (int) $p['points'] ?></td>
                        <td><?= (int) $p['sort_order'] ?></td>
                        <td>
                            <?php if ((int) $p['status']): ?>
                                <span class="badge badge-active">启用</span>
                            <?php else: ?>
                                <span class="badge badge-disabled">停用</span>
                            <?php endif; ?>
                        </td>
                        <td><a class="btn btn-sm" href="?tab=presets&edit_preset=<?= (int) $p['id'] ?>">编辑</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($tab === 'rank'): ?>
<div class="card">
    <div class="card-header"><h2>积分排行榜 Top 10</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>名次</th><th>用户名</th><th>昵称</th><th>积分</th></tr></thead>
                <tbody>
                <?php if ($board === []): ?>
                    <tr><td colspan="4" class="act-empty">暂无人上榜</td></tr>
                <?php endif; ?>
                <?php foreach ($board as $i => $row): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($row['username']) ?></td>
                        <td><?= e((string) ($row['nickname'] ?? '')) ?></td>
                        <td><strong><?= (int) $row['points'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($tab === 'ledger'): ?>
<div class="card">
    <div class="card-header"><h2>积分流水（最近 80 条）</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>时间</th><th>用户</th><th>变动</th><th>余额</th><th>说明</th><th>类型</th></tr>
                </thead>
                <tbody>
                <?php if ($ledger === []): ?>
                    <tr><td colspan="6" class="act-empty">暂无流水</td></tr>
                <?php endif; ?>
                <?php foreach ($ledger as $l): ?>
                    <tr>
                        <td><?= e(formatDateTimeShort($l['created_at'])) ?></td>
                        <td><?= e($l['username']) ?></td>
                        <td style="color:<?= (int) $l['delta'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                            <?= (int) $l['delta'] > 0 ? '+' : '' ?><?= (int) $l['delta'] ?>
                        </td>
                        <td><?= (int) $l['balance_after'] ?></td>
                        <td><?= e($l['reason']) ?></td>
                        <td><?= e((string) ($l['ref_type'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
