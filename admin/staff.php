<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/BalanceService.php';
require_once __DIR__ . '/../includes/helpers.php';

Auth::requirePage('staff');

$pdo = Database::getConnection();
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            UserService::createStaff($pdo, $_POST, $_FILES['photo'] ?? null);
            flash('success', '打手添加成功');
        } elseif ($action === 'update') {
            UserService::updateStaff($pdo, (int) $_POST['id'], $_POST, $_FILES['photo'] ?? null);
            flash('success', '打手信息已更新');
        } elseif ($action === 'reset_password') {
            UserService::resetStaffPassword($pdo, (int) $_POST['id'], $_POST['password'] ?? '');
            flash('success', '密码已重置');
        } elseif ($action === 'delete') {
            UserService::deleteStaff($pdo, (int) $_POST['id']);
            flash('success', '打手已禁用');
        }
        redirect('/admin/staff.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/staff.php');
    }
}

$staffList = UserService::getStaffList($pdo);

$currentPage = 'staff';
$pageTitle = '打手管理';
require __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>添加打手</h2>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>昵称</label>
                    <input type="text" name="nickname" class="form-control">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label>入职时间</label>
                    <input type="date" name="hired_at" class="form-control">
                </div>
                <div class="form-group">
                    <label>考核官</label>
                    <input type="text" name="examiner" class="form-control" placeholder="选填">
                </div>
                <div class="form-group">
                    <label>押金</label>
                    <input type="text" name="deposit" class="form-control" placeholder="如 100">
                </div>
                <div class="form-group">
                    <label>毛照</label>
                    <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <button type="submit" class="btn btn-primary">添加</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>打手列表 (<?= count($staffList) ?>)</h2></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>用户名</th><th>昵称</th><th>入职时间</th><th>考核官</th><th>押金</th><th>毛照</th><th>状态</th>
                        <th>累计收入</th><th>可提现</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($staffList as $s):
                    $stats = BalanceService::getBalanceSummary($pdo, (int) $s['id']);
                ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td><?= e($s['username']) ?></td>
                        <td><?= e($s['nickname']) ?></td>
                        <td><?= formatDate($s['hired_at'] ?? null) ?></td>
                        <td><?= e($s['examiner'] ?: '-') ?></td>
                        <td><?= e($s['deposit'] ?: '-') ?></td>
                        <td>
                            <?php if (!empty($s['photo_key'])): ?>
                                <a href="/admin/staff_photo.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm">查看</a>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">未上传</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $s['status'] ? 'active' : 'disabled' ?>"><?= $s['status'] ? '启用' : '禁用' ?></span></td>
                        <td class="money"><?= formatMoney($stats['total_income']) ?></td>
                        <td class="money"><?= formatMoney($stats['available_balance']) ?></td>
                        <td class="actions">
                            <a href="/admin/staff_detail.php?id=<?= $s['id'] ?>" class="btn btn-sm">详情</a>
                            <button type="button" class="btn btn-sm" onclick="editStaff(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">编辑</button>
                            <?php if ($s['status']): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('确认禁用该打手？')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">禁用</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">编辑打手</div>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:12px">
                    <label>昵称</label>
                    <input type="text" name="nickname" id="editNickname" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>入职时间</label>
                    <input type="date" name="hired_at" id="editHiredAt" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>考核官</label>
                    <input type="text" name="examiner" id="editExaminer" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label>押金</label>
                    <input type="text" name="deposit" id="editDeposit" class="form-control">
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" id="editStatus" class="form-control">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
                <p style="font-size:12px;color:var(--text-muted);margin-top:12px">上传/更换毛照请进入打手详情页</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="document.getElementById('editModal').classList.remove('show')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
        <form method="post" style="padding:0 20px 20px">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="resetId">
            <div class="form-group">
                <input type="password" name="password" class="form-control" placeholder="新密码（至少6位）" minlength="6">
            </div>
            <button type="submit" class="btn btn-sm btn-danger">重置密码</button>
        </form>
    </div>
</div>

<script>
function editStaff(s) {
    document.getElementById('editId').value = s.id;
    document.getElementById('resetId').value = s.id;
    document.getElementById('editNickname').value = s.nickname || '';
    document.getElementById('editHiredAt').value = s.hired_at || '';
    document.getElementById('editExaminer').value = s.examiner || '';
    document.getElementById('editDeposit').value = s.deposit || '';
    document.getElementById('editStatus').value = s.status;
    document.getElementById('editModal').classList.add('show');
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
