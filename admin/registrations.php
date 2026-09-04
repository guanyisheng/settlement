<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('registrations');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if ($action === 'approve') {
            UserService::approveRegistration($pdo, $id, Auth::id());
            flash('success', '已通过注册申请');
        } elseif ($action === 'reject') {
            UserService::rejectRegistration($pdo, $id, $_POST['reject_reason'] ?? '');
            flash('success', '已拒绝注册申请');
        }
        redirect('/admin/registrations.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/registrations.php');
    }
}

$pending = UserService::getPendingRegistrations($pdo);

$currentPage = 'registrations';
$pageTitle = '注册审核';
require __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>待审核注册 (<?= count($pending) ?>)</h2>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>用户名</th><th>昵称</th><th>毛照</th><th>申请时间</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($pending)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--text-muted)">暂无待审核注册</td></tr>
                <?php else: ?>
                    <?php foreach ($pending as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= e($u['username']) ?></td>
                        <td><?= e($u['nickname']) ?></td>
                        <td>
                            <?php if (!empty($u['photo_key'])): ?>
                                <a href="/admin/staff_photo.php?id=<?= $u['id'] ?>" target="_blank" class="btn btn-sm">查看</a>
                            <?php else: ?>
                                <span style="color:var(--danger)">未上传</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatDateTime($u['created_at']) ?></td>
                        <td class="actions">
                            <form method="post" style="display:inline" onsubmit="return confirm('确认通过该注册申请？')">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">通过</button>
                            </form>
                            <form method="post" style="display:inline" onsubmit="return confirm('确认拒绝该注册申请？')">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">拒绝</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
