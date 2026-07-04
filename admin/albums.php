<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/Album.php';
require_once __DIR__ . '/../includes/ImageHandler.php';

requireAdminLogin();
requirePermission('manage_albums');

$albumModel = new Album();
$db = Database::getInstance();

// معالجة الإجراءات (نشر/إلغاء نشر/تمييز/حذف)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        redirectWithMessage('albums.php', 'حدث خطأ في التحقق من الطلب', 'danger');
    }

    $albumId = (int) ($_POST['album_id'] ?? 0);

    switch ($_POST['action']) {
        case 'toggle_published':
            $albumModel->togglePublished($albumId);
            logActivity('update_album', 'تغيير حالة نشر الألبوم #' . $albumId);
            redirectWithMessage('albums.php', 'تم تحديث حالة النشر بنجاح');
            break;

        case 'toggle_featured':
            $albumModel->toggleFeatured($albumId);
            logActivity('update_album', 'تغيير حالة التمييز للألبوم #' . $albumId);
            redirectWithMessage('albums.php', 'تم تحديث حالة التمييز بنجاح');
            break;

        case 'delete':
            requirePermission('delete_albums');
            $album = $albumModel->getById($albumId);
            if ($album) {
                $imageHandler = new ImageHandler();
                $imageHandler->deleteAlbumDirectory($albumId);
                $albumModel->delete($albumId);
                logActivity('delete_album', 'حذف الألبوم: ' . $album['title']);
                redirectWithMessage('albums.php', 'تم حذف الألبوم وكل صوره بنجاح');
            }
            break;
    }
}

$search = trim($_GET['search'] ?? '');
$filters = [];
if ($search) $filters['search'] = $search;

$albums = $albumModel->getAll($filters);
$pageTitle = 'إدارة الألبومات';
$breadcrumb = 'الرئيسية / الألبومات';
require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">كل الألبومات (<?= count($albums) ?>)</span>
        <div class="card-header-actions">
            <form method="get" style="display:flex;gap:8px;">
                <input type="text" name="search" class="form-control" placeholder="بحث..." value="<?= e($search) ?>" style="max-width:220px;">
                <button type="submit" class="btn btn-dark btn-sm"><i class="fa-solid fa-search"></i></button>
            </form>
            <a href="album-edit.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> ألبوم جديد</a>
        </div>
    </div>

    <?php if (empty($albums)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-folder-open"></i>
            <h3>لا توجد ألبومات</h3>
            <p>ابدأ بإنشاء أول ألبوم لك</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>الغلاف</th>
                        <th>العنوان</th>
                        <th>التصنيف</th>
                        <th>الصور</th>
                        <th>المشاهدات</th>
                        <th>الحماية</th>
                        <th>النشر</th>
                        <th>مميز</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($albums as $album): ?>
                    <tr>
                        <td>
                            <?php if ($album['cover_image']): ?>
                                <img src="<?= UPLOAD_URL . e($album['cover_image']) ?>" class="table-thumb" alt="">
                            <?php else: ?>
                                <div class="table-thumb" style="display:flex;align-items:center;justify-content:center;background:var(--color-black-elevated);color:var(--color-silver-muted);"><i class="fa-solid fa-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e($album['title']) ?></strong><br>
                            <small style="color:var(--color-silver-muted);"><?= formatArabicDate($album['created_at']) ?></small>
                        </td>
                        <td><?= $album['category_name'] ? '<span class="badge badge-silver">' . e($album['category_name']) . '</span>' : '—' ?></td>
                        <td><?= (int) $album['photos_count'] ?></td>
                        <td><?= (int) $album['views_count'] ?></td>
                        <td>
                            <?php if ($album['is_protected']): ?>
                                <span class="badge badge-gold"><i class="fa-solid fa-lock"></i> محمي</span>
                            <?php else: ?>
                                <span class="badge badge-silver">عام</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_published">
                                <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
                                <label class="toggle-switch">
                                    <input type="checkbox" onchange="this.form.submit()" <?= $album['is_published'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </form>
                        </td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_featured">
                                <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
                                <label class="toggle-switch">
                                    <input type="checkbox" onchange="this.form.submit()" <?= $album['is_featured'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </form>
                        </td>
                        <td>
                            <div class="action-icons">
                                <a href="<?= SITE_URL ?>/album.php?slug=<?= e($album['slug']) ?>" target="_blank" class="action-icon-btn view" title="عرض"><i class="fa-solid fa-eye"></i></a>
                                <a href="album-photos.php?id=<?= (int) $album['id'] ?>" class="action-icon-btn view" title="إدارة الصور"><i class="fa-solid fa-images"></i></a>
                                <a href="album-edit.php?id=<?= (int) $album['id'] ?>" class="action-icon-btn edit" title="تعديل"><i class="fa-solid fa-pen"></i></a>
                                <?php if (hasPermission('delete_albums')): ?>
                                <form method="post" style="display:inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
                                    <button type="button" class="action-icon-btn delete" title="حذف"
                                            data-confirm-delete="هل أنت متأكد من حذف ألبوم «<?= e($album['title']) ?>»؟ سيتم حذف كل الصور بداخله نهائياً.">
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
