<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

requireAdminLogin();
requirePermission('manage_comments');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        redirectWithMessage('comments.php', 'حدث خطأ في التحقق من الطلب', 'danger');
    }

    $commentId = (int) ($_POST['comment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $stmt = $db->prepare('UPDATE comments SET is_approved = 1 WHERE id = ?');
        $stmt->execute([$commentId]);
        logActivity('approve_comment', 'الموافقة على تعليق رقم ' . $commentId);
        redirectWithMessage('comments.php', 'تم نشر التعليق بنجاح');
    }

    if ($action === 'unapprove') {
        $stmt = $db->prepare('UPDATE comments SET is_approved = 0 WHERE id = ?');
        $stmt->execute([$commentId]);
        redirectWithMessage('comments.php', 'تم إخفاء التعليق');
    }

    if ($action === 'delete') {
        $stmt = $db->prepare('DELETE FROM comments WHERE id = ?');
        $stmt->execute([$commentId]);
        logActivity('delete_comment', 'حذف تعليق رقم ' . $commentId);
        redirectWithMessage('comments.php', 'تم حذف التعليق نهائياً');
    }
}

$filter = $_GET['filter'] ?? 'pending';
$where = match ($filter) {
    'approved' => 'WHERE c.is_approved = 1',
    'all' => '',
    default => 'WHERE c.is_approved = 0',
};

$comments = $db->query("
    SELECT c.*, a.title as album_title, a.slug as album_slug
    FROM comments c
    JOIN albums a ON c.album_id = a.id
    $where
    ORDER BY c.created_at DESC
")->fetchAll();

$pendingCount = (int) $db->query('SELECT COUNT(*) as cnt FROM comments WHERE is_approved = 0')->fetch()['cnt'];

$pageTitle = 'التعليقات';
$breadcrumb = 'الرئيسية / التعليقات';
require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">إدارة التعليقات</span>
        <div class="tabs" style="border-bottom:none;margin-bottom:0;">
            <a href="?filter=pending" class="tab-btn <?= $filter === 'pending' ? 'active' : '' ?>">
                بانتظار الموافقة <?= $pendingCount > 0 ? '<span class="badge badge-gold">' . $pendingCount . '</span>' : '' ?>
            </a>
            <a href="?filter=approved" class="tab-btn <?= $filter === 'approved' ? 'active' : '' ?>">المنشورة</a>
            <a href="?filter=all" class="tab-btn <?= $filter === 'all' ? 'active' : '' ?>">الكل</a>
        </div>
    </div>

    <?php if (empty($comments)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-comments"></i>
            <h3>لا توجد تعليقات هنا</h3>
        </div>
    <?php else: ?>
        <?php foreach ($comments as $comment): ?>
            <div class="activity-item" style="align-items:flex-start;">
                <span class="activity-icon"><i class="fa-solid fa-comment"></i></span>
                <div style="flex:1;">
                    <div class="activity-text">
                        <strong><?= e($comment['name']) ?></strong>
                        <span style="color:var(--color-silver-muted);font-size:0.82rem;">
                            على ألبوم «<a href="<?= SITE_URL ?>/album.php?slug=<?= e($comment['album_slug']) ?>" target="_blank" style="color:var(--color-gold-light);"><?= e($comment['album_title']) ?></a>»
                        </span>
                        <?php if ($comment['is_approved']): ?>
                            <span class="badge badge-success">منشور</span>
                        <?php else: ?>
                            <span class="badge badge-gold">بانتظار الموافقة</span>
                        <?php endif; ?>
                    </div>
                    <p style="margin:8px 0;font-size:0.92rem;color:var(--color-silver);"><?= nl2br(e($comment['comment'])) ?></p>
                    <div class="activity-time" style="margin-bottom:8px;"><?= formatArabicDate($comment['created_at']) ?></div>

                    <div style="display:flex;gap:8px;">
                        <?php if (!$comment['is_approved']): ?>
                        <form method="post" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                            <button type="submit" class="btn btn-success btn-sm"><i class="fa-solid fa-check"></i> موافقة ونشر</button>
                        </form>
                        <?php else: ?>
                        <form method="post" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="unapprove">
                            <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye-slash"></i> إخفاء</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                            <button type="button" class="btn btn-danger btn-sm" data-confirm-delete="هل تريد حذف هذا التعليق نهائياً؟"><i class="fa-solid fa-trash"></i> حذف</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
