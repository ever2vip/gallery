<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

requireAdminLogin();
requirePermission('view_activity_log');

$db = Database::getInstance();

$itemsPerPage = 25;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $itemsPerPage;

$totalStmt = $db->query('SELECT COUNT(*) as cnt FROM activity_logs');
$totalLogs = (int) $totalStmt->fetch()['cnt'];
$totalPages = (int) ceil($totalLogs / $itemsPerPage);

$stmt = $db->prepare('
    SELECT al.*, a.username, a.full_name
    FROM activity_logs al
    LEFT JOIN admins a ON al.admin_id = a.id
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
');
$stmt->bindValue(1, $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$actionIcons = [
    'login' => 'fa-right-to-bracket',
    'logout' => 'fa-right-from-bracket',
    'create_album' => 'fa-square-plus',
    'update_album' => 'fa-pen',
    'delete_album' => 'fa-trash',
    'upload_photos' => 'fa-images',
    'delete_photo' => 'fa-image',
    'create_category' => 'fa-tags',
    'update_category' => 'fa-tags',
    'delete_category' => 'fa-tags',
    'create_admin' => 'fa-user-plus',
    'update_admin' => 'fa-user-pen',
    'delete_admin' => 'fa-user-minus',
    'update_profile' => 'fa-id-card',
    'change_password' => 'fa-key',
    'update_settings' => 'fa-gear',
    'update_appearance' => 'fa-palette',
];

$pageTitle = 'سجل النشاطات';
$breadcrumb = 'الرئيسية / سجل النشاطات';
require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">كل النشاطات (<?= number_format($totalLogs) ?>)</span>
    </div>

    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <h3>لا توجد نشاطات مسجلة بعد</h3>
        </div>
    <?php else: ?>
        <?php foreach ($logs as $log): ?>
            <div class="activity-item">
                <span class="activity-icon"><i class="fa-solid <?= e($actionIcons[$log['action']] ?? 'fa-circle-dot') ?>"></i></span>
                <div style="flex:1;">
                    <div class="activity-text">
                        <strong><?= e($log['full_name'] ?: $log['username'] ?: 'النظام') ?></strong>
                        — <?= e($log['details'] ?: $log['action']) ?>
                    </div>
                    <div class="activity-time">
                        <?= formatArabicDate($log['created_at']) ?> · <?= date('H:i', strtotime($log['created_at'])) ?>
                        <?php if ($log['ip_address']): ?> · <span dir="ltr"><?= e($log['ip_address']) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?><span class="active"><?= $i ?></span><?php else: ?><a href="?page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
