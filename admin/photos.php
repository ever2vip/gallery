<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ImageHandler.php';

requireAdminLogin();
requirePermission('manage_albums');

$db = Database::getInstance();

// حذف صورة مباشرة من هذه الصفحة
if (isset($_GET['delete'])) {
    $photoId = (int) $_GET['delete'];
    $stmt = $db->prepare('SELECT * FROM photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();

    if ($photo) {
        $imageHandler = new ImageHandler();
        $imageHandler->deletePhotoFiles($photo['filename'], $photo['thumbnail'] ?? '');
        $delStmt = $db->prepare('DELETE FROM photos WHERE id = ?');
        $delStmt->execute([$photoId]);
        logActivity('delete_photo', 'حذف صورة من صفحة إدارة الصور');
        redirectWithMessage('photos.php', 'تم حذف الصورة بنجاح');
    }
}

$albumFilter = (int) ($_GET['album'] ?? 0);
$itemsPerPage = 24;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $itemsPerPage;

$where = $albumFilter ? 'WHERE p.album_id = ?' : '';
$params = $albumFilter ? [$albumFilter] : [];

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM photos p $where");
$countStmt->execute($params);
$totalPhotos = (int) $countStmt->fetch()['cnt'];
$totalPages = (int) ceil($totalPhotos / $itemsPerPage);

$sql = "
    SELECT p.*, a.title as album_title, a.slug as album_slug
    FROM photos p
    JOIN albums a ON p.album_id = a.id
    $where
    ORDER BY p.created_at DESC
    LIMIT $itemsPerPage OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$photos = $stmt->fetchAll();

$albums = $db->query('SELECT id, title FROM albums ORDER BY title ASC')->fetchAll();

$pageTitle = 'كل الصور';
$breadcrumb = 'الرئيسية / كل الصور';
require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">كل الصور (<?= number_format($totalPhotos) ?>)</span>
        <form method="get" style="display:flex;gap:8px;">
            <select name="album" class="form-control" onchange="this.form.submit()" style="max-width:220px;">
                <option value="">كل الألبومات</option>
                <?php foreach ($albums as $alb): ?>
                    <option value="<?= (int) $alb['id'] ?>" <?= $albumFilter === (int) $alb['id'] ? 'selected' : '' ?>><?= e($alb['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (empty($photos)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-image"></i>
            <h3>لا توجد صور</h3>
        </div>
    <?php else: ?>
        <div class="manage-photos-grid">
            <?php foreach ($photos as $photo): ?>
                <div class="manage-photo-item" title="<?= e($photo['album_title']) ?>">
                    <img src="<?= UPLOAD_URL . e($photo['thumbnail'] ?: $photo['filename']) ?>" alt="">
                    <?php if ($photo['is_cover']): ?>
                        <span class="cover-star-badge"><i class="fa-solid fa-star"></i></span>
                    <?php endif; ?>
                    <div class="manage-photo-overlay">
                        <div class="manage-photo-top">
                            <span style="color:#fff;font-size:0.72rem;background:rgba(0,0,0,0.55);padding:3px 8px;border-radius:20px;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= e($photo['album_title']) ?>
                            </span>
                        </div>
                        <div class="manage-photo-bottom">
                            <a href="album-photos.php?id=<?= (int) $photo['album_id'] ?>" class="action-icon-btn view btn-icon" title="فتح الألبوم" style="width:32px;height:32px;">
                                <i class="fa-solid fa-folder-open"></i>
                            </a>
                            <a href="?delete=<?= (int) $photo['id'] ?><?= $albumFilter ? '&album=' . $albumFilter : '' ?>" class="action-icon-btn delete btn-icon" title="حذف" style="width:32px;height:32px;"
                               data-confirm-delete="هل تريد حذف هذه الصورة نهائياً؟">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php $qs = $albumFilter ? '&album=' . $albumFilter : ''; ?>
            <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?><?= $qs ?>"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?><span class="active"><?= $i ?></span><?php else: ?><a href="?page=<?= $i ?><?= $qs ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?><?= $qs ?>"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
