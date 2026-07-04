<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

requireAdminLogin();
requirePermission('manage_admins');

$db = Database::getInstance();
$currentAdmin = getCurrentAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        redirectWithMessage('admins.php', 'حدث خطأ في التحقق من الطلب', 'danger');
    }

    $targetId = (int) ($_POST['admin_id'] ?? 0);

    if ($targetId === (int) $currentAdmin['id']) {
        redirectWithMessage('admins.php', 'لا يمكنك حذف حسابك الخاص', 'danger');
    }

    $stmt = $db->prepare('SELECT * FROM admins WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();

    if ($target) {
        if ($target['role'] === 'super_admin' && $currentAdmin['role'] !== 'super_admin') {
            redirectWithMessage('admins.php', 'ليس لديك صلاحية حذف حساب مدير عام', 'danger');
        }

        if ($target['role'] === 'super_admin') {
            $countStmt = $db->query("SELECT COUNT(*) as cnt FROM admins WHERE role = 'super_admin' AND is_active = 1");
            if ((int) $countStmt->fetch()['cnt'] <= 1) {
                redirectWithMessage('admins.php', 'لا يمكن حذف آخر مدير عام نشط في النظام', 'danger');
            }
        }

        $delStmt = $db->prepare('DELETE FROM admins WHERE id = ?');
        $delStmt->execute([$targetId]);
        logActivity('delete_admin', 'حذف المشرف: ' . $target['username']);
        redirectWithMessage('admins.php', 'تم حذف المشرف بنجاح');
    }
}

$admins = $db->query("
    SELECT * FROM admins ORDER BY 
        CASE role WHEN 'super_admin' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END, 
        created_at ASC
")->fetchAll();

$pageTitle = 'المشرفون';
$breadcrumb = 'الرئيسية / المشرفون';
require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">كل المشرفين (<?= count($admins) ?>)</span>
        <a href="admin-edit.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus"></i> إضافة مشرف</a>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th></th><th>اسم المستخدم</th><th>الاسم الكامل</th><th>الدور</th><th>الحالة</th><th>آخر دخول</th><th>إجراءات</th></tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                <tr>
                    <td>
                        <span class="sidebar-user-avatar" style="width:36px;height:36px;font-size:0.9rem;">
                            <?php if ($admin['avatar']): ?>
                                <img src="<?= UPLOAD_URL . e($admin['avatar']) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                            <?php else: ?>
                                <?= e(mb_substr($admin['full_name'] ?: $admin['username'], 0, 1)) ?>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td dir="ltr" style="text-align:right;">
                        <?= e($admin['username']) ?>
                        <?= (int) $admin['id'] === (int) $currentAdmin['id'] ? '<span class="badge badge-gold">أنت</span>' : '' ?>
                    </td>
                    <td><?= e($admin['full_name'] ?: '—') ?></td>
                    <td>
                        <?php $roleBadge = $admin['role'] === 'super_admin' ? 'badge-gold' : 'badge-silver'; ?>
                        <span class="badge <?= $roleBadge ?>"><?= e(roleLabel($admin['role'])) ?></span>
                    </td>
                    <td>
                        <?php if ($admin['is_active']): ?>
                            <span class="badge badge-success">نشط</span>
                        <?php else: ?>
                            <span class="badge badge-danger">معطل</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $admin['last_login'] ? formatArabicDate($admin['last_login']) : 'لم يسجل دخول بعد' ?></td>
                    <td>
                        <div class="action-icons">
                            <a href="admin-edit.php?id=<?= (int) $admin['id'] ?>" class="action-icon-btn edit" title="تعديل"><i class="fa-solid fa-pen"></i></a>
                            <?php if ((int) $admin['id'] !== (int) $currentAdmin['id']): ?>
                            <form method="post" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>">
                                <button type="button" class="action-icon-btn delete" title="حذف"
                                    data-confirm-delete="هل تريد حذف المشرف «<?= e($admin['username']) ?>»؟">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
