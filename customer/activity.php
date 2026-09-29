<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/boot.php';
require_once __DIR__ . '/../includes/ActivityService.php';
require_once __DIR__ . '/../includes/brand.php';

$demoMode = !empty($demoMode);
$ready = false;
$activityId = (int) ($_GET['id'] ?? $_POST['activity_id'] ?? 0);

$demoCampaigns = [
    [
        'id' => 1, 'name' => '周末转盘', 'title' => '幸运大转盘',
        'description' => '抽奖赢积分 · 排行榜前十有奖', 'status' => 1,
    ],
    [
        'id' => 2, 'name' => '新春抽奖', 'title' => '新春转转乐',
        'description' => '春节专属奖池', 'status' => 1,
    ],
];

if ($demoMode) {
    $ready = true;
    $campaigns = $demoCampaigns;
    if ($activityId <= 0 && count($campaigns) === 1) {
        $activityId = (int) $campaigns[0]['id'];
    }
} else {
    $ready = ActivityService::isReady($pdo);
    $campaigns = $ready ? ActivityService::listActivities($pdo, true) : [];
    if ($activityId <= 0 && count($campaigns) === 1) {
        $activityId = (int) $campaigns[0]['id'];
    } elseif ($activityId <= 0 && $campaigns !== []) {
        // 多活动：停留在选择页
        $activityId = 0;
    }
}

$showPicker = $activityId <= 0;
$activity = null;
$share = [
    'id' => 0, 'name' => '', 'title' => '活动抽奖', 'desc' => '', 'logo' => brandLogo(), 'enabled' => true,
];
$enabled = true;
$wallet = ['points' => 0, 'lottery_chances' => 0];
$prizes = $items = $board = [];
$myRank = ['rank' => null, 'points' => 0];
$myLedger = $myDraws = $myEx = [];

if (!$showPicker) {
    if ($demoMode) {
        foreach ($demoCampaigns as $c) {
            if ((int) $c['id'] === $activityId) {
                $activity = $c;
                break;
            }
        }
        $share = [
            'id' => $activityId,
            'name' => (string) ($activity['name'] ?? ''),
            'title' => (string) ($activity['title'] ?? '幸运大转盘'),
            'desc' => (string) ($activity['description'] ?? ''),
            'logo' => brandLogo(),
            'enabled' => true,
        ];
        $wallet = ['points' => 128, 'lottery_chances' => 3];
        $prizes = [
            ['id' => 1, 'name' => '谢谢参与', 'prize_type' => 'empty', 'points_value' => 0, 'weight' => 40],
            ['id' => 2, 'name' => '积分+10', 'prize_type' => 'points', 'points_value' => 10, 'weight' => 30],
            ['id' => 3, 'name' => '积分+50', 'prize_type' => 'points', 'points_value' => 50, 'weight' => 15],
            ['id' => 4, 'name' => '积分+5', 'prize_type' => 'points', 'points_value' => 5, 'weight' => 20],
            ['id' => 5, 'name' => '神秘礼品', 'prize_type' => 'claim', 'points_value' => 0, 'weight' => 5],
            ['id' => 6, 'name' => '积分+100', 'prize_type' => 'points', 'points_value' => 100, 'weight' => 3],
        ];
        $items = [
            ['id' => 1, 'name' => '周边钥匙扣', 'cost_points' => 80, 'remark' => '人工邮寄'],
            ['id' => 2, 'name' => '体验单优惠券', 'cost_points' => 50, 'remark' => ''],
            ['id' => 3, 'name' => '神秘大礼', 'cost_points' => 300, 'remark' => '限量'],
            ['id' => 4, 'name' => '积分红包封面', 'cost_points' => 30, 'remark' => ''],
        ];
        $board = [
            ['user_id' => 1, 'username' => 'alpha', 'nickname' => '锦鲤本鲤', 'points' => 520],
            ['user_id' => 2, 'username' => 'beta', 'nickname' => '欧皇驾到', 'points' => 480],
            ['user_id' => 3, 'username' => 'gamma', 'nickname' => '冲榜选手', 'points' => 360],
            ['user_id' => 4, 'username' => 'delta', 'nickname' => '摸鱼达人', 'points' => 210],
            ['user_id' => 5, 'username' => 'echo', 'nickname' => '小透明', 'points' => 128],
        ];
        $myRank = ['rank' => 5, 'points' => 128];
    } else {
        $activity = ActivityService::getActivity($pdo, $activityId);
        $share = ActivityService::activityMeta($pdo, $activityId);
        $enabled = $share['enabled'];

        if ($ready && $enabled && $loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST'
            && (string) ($_POST['action'] ?? '') === 'draw'
            && (
                (string) ($_POST['ajax'] ?? '') === '1'
                || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            )
        ) {
            header('Content-Type: application/json; charset=utf-8');
            try {
                $drawAid = (int) ($_POST['activity_id'] ?? $activityId);
                $prizesLive = ActivityService::listPrizes($pdo, true, $drawAid);
                $result = ActivityService::drawLottery($pdo, $uid, $drawAid);
                $index = 0;
                foreach ($prizesLive as $i => $p) {
                    if ((int) $p['id'] === (int) ($result['prize_id'] ?? 0)) {
                        $index = $i;
                        break;
                    }
                }
                $walletNow = ActivityService::getWallet($pdo, $uid, $drawAid);
                echo json_encode([
                    'ok' => true,
                    'prize_id' => $result['prize_id'],
                    'prize_name' => $result['prize_name'],
                    'prize_type' => $result['prize_type'],
                    'points_awarded' => $result['points_awarded'],
                    'index' => $index,
                    'chances' => $walletNow['lottery_chances'],
                    'points' => $walletNow['points'],
                ], JSON_UNESCAPED_UNICODE);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        if ($ready && $enabled && $loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['action'] ?? '');
            try {
                if ($action === 'draw') {
                    $result = ActivityService::drawLottery($pdo, $uid, $activityId);
                    $msg = '抽中：' . $result['prize_name'];
                    if ($result['points_awarded'] > 0) {
                        $msg .= '，已自动到账 +' . $result['points_awarded'] . ' 积分';
                    }
                    flash('success', $msg);
                } elseif ($action === 'exchange') {
                    ActivityService::requestExchange($pdo, $uid, (int) ($_POST['item_id'] ?? 0));
                    flash('success', '兑换已提交，请等待人工发放');
                } else {
                    throw new RuntimeException('未知操作');
                }
            } catch (Throwable $e) {
                flash('error', $e->getMessage());
            }
            redirect('/customer/activity.php?id=' . $activityId);
        }

        $wallet = ($ready && $loggedIn) ? ActivityService::getWallet($pdo, $uid, $activityId) : ['points' => 0, 'lottery_chances' => 0];
        $prizes = $ready ? ActivityService::listPrizes($pdo, true, $activityId) : [];
        $items = $ready ? ActivityService::listExchangeItems($pdo, true, $activityId) : [];
        $board = $ready ? ActivityService::leaderboard($pdo, 10) : [];
        $myRank = ($ready && $loggedIn) ? ActivityService::myRank($pdo, $uid) : ['rank' => null, 'points' => 0];
        $myLedger = ($ready && $loggedIn) ? ActivityService::ledger($pdo, $uid, 15) : [];
        $myDraws = ($ready && $loggedIn) ? ActivityService::userLotteryLogs($pdo, $uid, 10, $activityId) : [];
        $myEx = ($ready && $loggedIn) ? ActivityService::userExchangeOrders($pdo, $uid, 10, $activityId) : [];
    }
}

// 演示抽奖 AJAX
if ($demoMode && !$showPicker && $_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'draw') {
    header('Content-Type: application/json; charset=utf-8');
    $total = 0;
    foreach ($prizes as $p) {
        $total += max(1, (int) $p['weight']);
    }
    $r = random_int(1, max(1, $total));
    $acc = 0;
    $hit = $prizes[0];
    $index = 0;
    foreach ($prizes as $i => $p) {
        $acc += max(1, (int) $p['weight']);
        if ($r <= $acc) {
            $hit = $p;
            $index = $i;
            break;
        }
    }
    $pts = ($hit['prize_type'] === 'empty') ? 0 : max(0, (int) $hit['points_value']);
    echo json_encode([
        'ok' => true,
        'prize_id' => (int) $hit['id'],
        'prize_name' => (string) $hit['name'],
        'prize_type' => (string) $hit['prize_type'],
        'points_awarded' => $pts,
        'index' => $index,
        'chances' => max(0, (int) $wallet['lottery_chances'] - 1),
        'points' => (int) $wallet['points'] + $pts,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$displayName = static function (array $row): string {
    $nick = trim((string) ($row['nickname'] ?? ''));
    $user = (string) ($row['username'] ?? '');
    if ($nick !== '') {
        return $nick;
    }
    if ($user === '') {
        return '用户';
    }
    $len = mb_strlen($user);
    if ($len <= 2) {
        return mb_substr($user, 0, 1) . '*';
    }
    return mb_substr($user, 0, 1) . str_repeat('*', min(4, $len - 2)) . mb_substr($user, -1);
};

$prizesJson = [];
foreach ($prizes as $p) {
    $prizesJson[] = [
        'id' => (int) $p['id'],
        'name' => (string) $p['name'],
        'prize_type' => (string) ($p['prize_type'] ?? 'empty'),
        'points_value' => (int) ($p['points_value'] ?? 0),
    ];
}

$canDraw = ($loggedIn || $demoMode) && (int) $wallet['lottery_chances'] > 0 && $prizesJson !== [];
$assetV = rawurlencode(assetBuildId());
$ogTitle = $showPicker ? '活动抽奖' : $share['title'];
$ogDesc = $showPicker ? '选择活动参与抽奖' : $share['desc'];
$ogImage = $share['logo'] ?: brandLogo();
$ogUrl = absoluteUrl($showPicker ? '/customer/activity.php' : ('/customer/activity.php?id=' . $activityId));
if ($ogImage !== '' && !preg_match('#^https?://#i', $ogImage)) {
    $ogImage = absoluteUrl($ogImage);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#c41e3a">
    <title><?= e($ogTitle) ?></title>
    <meta name="description" content="<?= e($ogDesc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDesc) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:url" content="<?= e($ogUrl) ?>">
    <meta itemprop="name" content="<?= e($ogTitle) ?>">
    <meta itemprop="description" content="<?= e($ogDesc) ?>">
    <meta itemprop="image" content="<?= e($ogImage) ?>">
    <link rel="icon" href="<?= brandLogo() ?>" type="image/png">
    <link rel="stylesheet" href="/customer/assets/css/activity-h5.css?v=<?= $assetV ?>">
</head>
<body>
<?php if ($showPicker): ?>
<div class="h5-page">
    <a class="h5-nav-back" href="/customer/index.php" aria-label="返回">‹</a>
    <header class="h5-hero">
        <h1>活动抽奖</h1>
        <p>选择一个活动参与</p>
    </header>
    <?php if ($error): ?><div class="h5-alert h5-alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="h5-alert h5-alert-error">请先执行 database/一键注入_全部更新.sql</div>
    <?php elseif ($campaigns === []): ?>
        <div class="h5-alert h5-alert-error">暂无开启中的活动</div>
    <?php else: ?>
        <div class="h5-pick-list">
            <?php foreach ($campaigns as $c): ?>
                <a class="h5-pick-card" href="/customer/activity.php?id=<?= (int) $c['id'] ?>">
                    <div class="h5-pick-title"><?= e($c['title'] ?: $c['name']) ?></div>
                    <?php if (!empty($c['description'])): ?>
                        <div class="h5-pick-desc"><?= e($c['description']) ?></div>
                    <?php endif; ?>
                    <div class="h5-pick-go">进入转盘 ›</div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="h5-page" id="h5Lottery"
     data-prizes="<?= e(json_encode($prizesJson, JSON_UNESCAPED_UNICODE)) ?>"
     data-draw-url="/customer/activity.php?id=<?= (int) $activityId ?>"
     data-activity-id="<?= (int) $activityId ?>"
     data-can-draw="<?= $canDraw ? '1' : '0' ?>">

    <a class="h5-nav-back" href="<?= count($campaigns) > 1 ? '/customer/activity.php' : '/customer/index.php' ?>" aria-label="返回">‹</a>

    <header class="h5-hero">
        <h1><?= e($share['title']) ?></h1>
        <p><?= e($share['desc']) ?></p>
        <div class="h5-stats">
            <span class="h5-chip" id="chipPoints">积分 <strong><?= $loggedIn || $demoMode ? (int) $wallet['points'] : '—' ?></strong></span>
            <span class="h5-chip" id="chipChances">抽奖 <strong><?= $loggedIn || $demoMode ? (int) $wallet['lottery_chances'] : '—' ?></strong> 次</span>
        </div>
    </header>

    <?php if ($error): ?><div class="h5-alert h5-alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="h5-alert h5-alert-ok"><?= e($success) ?></div><?php endif; ?>
    <?php if ($demoMode): ?><div class="h5-alert h5-alert-ok">演示模式：转盘可转，兑换不落库</div><?php endif; ?>

    <?php if (!$ready): ?>
        <div class="h5-alert h5-alert-error">请先执行 database/一键注入_全部更新.sql</div>
    <?php elseif (!$activity || !$enabled): ?>
        <div class="h5-alert h5-alert-error">活动不存在或未开启</div>
    <?php else: ?>

        <?php if (!$loggedIn && !$demoMode): ?>
            <div class="h5-login">
                <div>登录后即可转动大转盘</div>
                <a href="/login.php?next=<?= rawurlencode('/customer/activity.php?id=' . $activityId) ?>">登录</a>
                <a href="/customer/register.php" style="background:rgba(255,255,255,.2);color:#fff">注册</a>
            </div>
        <?php endif; ?>

        <div class="h5-wheel-wrap">
            <div class="h5-pointer" aria-hidden="true"></div>
            <div class="h5-wheel-outer">
                <div class="h5-wheel-disk" id="wheelDisk">
                    <?php if ($prizesJson === []): ?>
                        <div class="h5-empty" style="padding-top:40%">暂无奖品</div>
                    <?php else: ?>
                        <canvas class="h5-wheel-canvas" id="wheelCanvas"></canvas>
                    <?php endif; ?>
                </div>
            </div>
            <button type="button" class="h5-go" id="wheelGo" <?= $canDraw ? '' : 'disabled' ?>>GO</button>
        </div>
        <p class="h5-hint">抽中积分自动到账 · 次数由客服按本活动发放 · 点击 GO 转动</p>

        <section class="h5-panel" id="exchange">
            <h2>积分兑换 <em>我的积分 <?= $loggedIn || $demoMode ? (int) $wallet['points'] : '—' ?></em></h2>
            <?php if ($items === []): ?>
                <div class="h5-empty">暂无可兑换物品</div>
            <?php else: ?>
                <div class="h5-ex-grid">
                    <?php foreach ($items as $it): ?>
                        <div class="h5-ex-card">
                            <div class="n"><?= e($it['name']) ?></div>
                            <div class="m"><?= e((string) ($it['remark'] ?? '')) ?></div>
                            <div class="f">
                                <span class="cost"><?= (int) $it['cost_points'] ?> 分</span>
                                <?php if ($loggedIn && !$demoMode): ?>
                                    <form method="post" onsubmit="return confirm('确认兑换？')">
                                        <input type="hidden" name="action" value="exchange">
                                        <input type="hidden" name="activity_id" value="<?= (int) $activityId ?>">
                                        <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                                        <button type="submit" class="h5-btn"
                                            <?= (int) $wallet['points'] < (int) $it['cost_points'] ? 'disabled' : '' ?>>兑换</button>
                                    </form>
                                <?php elseif ($demoMode): ?>
                                    <button type="button" class="h5-btn" disabled>演示</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="h5-panel" id="rank">
            <h2>积分排行榜 Top10
                <?php if ($loggedIn || $demoMode): ?>
                    <em><?= $myRank['rank'] !== null ? ('我的第 ' . (int) $myRank['rank'] . ' 名') : '暂未上榜' ?></em>
                <?php endif; ?>
            </h2>
            <table class="h5-rank-table">
                <thead><tr><th>名次</th><th>玩家</th><th style="text-align:right">积分</th></tr></thead>
                <tbody>
                <?php if ($board === []): ?>
                    <tr><td colspan="3" class="h5-empty">暂无人上榜</td></tr>
                <?php endif; ?>
                <?php foreach ($board as $i => $row): ?>
                    <?php
                    $rank = $i + 1;
                    $isMe = ($loggedIn || $demoMode) && (int) $row['user_id'] === ($demoMode ? 5 : $uid);
                    ?>
                    <tr class="<?= $isMe ? 'h5-rank-me' : '' ?>">
                        <td>
                            <?php if ($rank <= 3): ?>
                                <span class="h5-medal h5-medal-<?= $rank ?>"><?= $rank ?></span>
                            <?php else: ?>
                                <?= $rank ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e($displayName($row)) ?><?= $isMe ? '（我）' : '' ?></td>
                        <td style="text-align:right;font-weight:800"><?= (int) $row['points'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php if ($loggedIn && !$demoMode): ?>
        <details class="h5-mine">
            <summary>我的记录（流水 / 抽奖 / 兑换）</summary>
            <div class="inner">
                <h3 style="font-size:13px;margin:0 0 6px">积分流水</h3>
                <table>
                    <thead><tr><th>时间</th><th>变动</th><th>余额</th><th>说明</th></tr></thead>
                    <tbody>
                    <?php foreach ($myLedger as $l): ?>
                        <tr>
                            <td><?= e($l['created_at']) ?></td>
                            <td><?= (int) $l['delta'] > 0 ? '+' : '' ?><?= (int) $l['delta'] ?></td>
                            <td><?= (int) $l['balance_after'] ?></td>
                            <td><?= e($l['reason']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($myLedger === []): ?><tr><td colspan="4">暂无</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <h3 style="font-size:13px;margin:12px 0 6px">本活动抽奖记录</h3>
                <table>
                    <thead><tr><th>时间</th><th>奖品</th><th>积分</th></tr></thead>
                    <tbody>
                    <?php foreach ($myDraws as $d): ?>
                        <tr>
                            <td><?= e($d['created_at']) ?></td>
                            <td><?= e($d['prize_name']) ?></td>
                            <td><?= (int) $d['points_awarded'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($myDraws === []): ?><tr><td colspan="3">暂无</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </details>
        <?php endif; ?>

    <?php endif; ?>
</div>

<div class="h5-modal" id="h5ResultModal" hidden>
    <div class="h5-modal-box">
        <h3>恭喜获得</h3>
        <div class="prize" id="h5ResultPrize">—</div>
        <p id="h5ResultSub"></p>
        <button type="button" class="h5-btn" id="h5ResultClose" style="padding:10px 28px;font-size:14px">好的</button>
    </div>
</div>

<script src="/customer/assets/js/lottery-wheel.js?v=<?= $assetV ?>"></script>
<?php endif; ?>
</body>
</html>
