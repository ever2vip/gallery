<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/Album.php';
require_once __DIR__ . '/../includes/Photo.php';
require_once __DIR__ . '/../includes/ImageHandler.php';

requireAdminLogin();
requirePermission('manage_albums');

$albumModel = new Album();
$photoModel = new Photo();

$albumId = (int) ($_GET['id'] ?? 0);
$album = $albumModel->getById($albumId);

if (!$album) {
    redirectWithMessage('albums.php', 'الألبوم المطلوب غير موجود', 'danger');
}

$uploadErrors = [];
$uploadSuccess = 0;

// معالجة رفع الصور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $uploadErrors[] = 'حدث خطأ في التحقق من الطلب';
    } else {
        $imageHandler = new ImageHandler();
        $files = $_FILES['photos'];
        $fileCount = count($files['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];

            $result = $imageHandler->handleUpload($file, $albumId);
            if ($result) {
                $photoModel->create($albumId, $result);
                $uploadSuccess++;

                if (getSetting('watermark_enabled') === '1') {
                    $imageHandler->addWatermark(UPLOAD_DIR . $result['filename'], getSetting('site_title', SITE_NAME));
                }
            } else {
                $uploadErrors = array_merge($uploadErrors, $imageHandler->getErrors());
            }
        }

        // تعيين أول صورة كغلاف تلقائياً إن لم يوجد غلاف
        if ($uploadSuccess > 0 && empty($album['cover_image'])) {
            $photos = $photoModel->getByAlbum($albumId);
            if (!empty($photos)) {
                $photoModel->setCover($photos[0]['id'], $albumId);
            }
        }

        if ($uploadSuccess > 0) {
            logActivity('upload_photos', "رفع {$uploadSuccess} صورة إلى ألبوم: {$album['title']}");
            redirectWithMessage('album-photos.php?id=' . $albumId, "تم رفع {$uploadSuccess} صورة بنجاح" . (!empty($uploadErrors) ? ' (مع بعض الأخطاء)' : ''));
        }
    }
}

// معالجة حذف صورة واحدة
if (isset($_GET['delete_photo'])) {
    $photoId = (int) $_GET['delete_photo'];
    $photo = $photoModel->getById($photoId);

    if ($photo && $photo['album_id'] == $albumId) {
        $imageHandler = new ImageHandler();
        $imageHandler->deletePhotoFiles($photo['filename'], $photo['thumbnail'] ?? '');
        $photoModel->delete($photoId);
        logActivity('delete_photo', 'حذف صورة من ألبوم: ' . $album['title']);
        redirectWithMessage('album-photos.php?id=' . $albumId, 'تم حذف الصورة بنجاح');
    }
}

// معالجة تعيين صورة كغلاف
if (isset($_GET['set_cover'])) {
    $photoId = (int) $_GET['set_cover'];
    $photo = $photoModel->getById($photoId);

    if ($photo && $photo['album_id'] == $albumId) {
        $photoModel->setCover($photoId, $albumId);
        redirectWithMessage('album-photos.php?id=' . $albumId, 'تم تعيين صورة الغلاف بنجاح');
    }
}

$photos = $photoModel->getByAlbum($albumId);

$pageTitle = 'إدارة صور: ' . $album['title'];
$breadcrumb = 'الرئيسية / الألبومات / إدارة الصور';
require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-images" style="color:var(--color-gold-light);margin-left:8px;"></i><?= e($album['title']) ?></span>
        <div class="card-header-actions">
            <span class="badge badge-gold"><?= count($photos) ?> صورة</span>
            <a href="album-edit.php?id=<?= $albumId ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-pen"></i> تعديل بيانات الألبوم</a>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" id="uploadForm">
        <?= csrfField() ?>
        <div class="upload-dropzone">
            <div class="upload-dropzone-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
            <h3>اسحب الصور هنا أو اضغط للاختيار</h3>
            <p>يمكنك رفع عدة صور دفعة واحدة (JPG, PNG, GIF, WEBP - حتى 10 ميجابايت لكل صورة)</p>
            <p id="selectedFilesCount" style="color:var(--color-gold-light);font-weight:600;margin-top:10px;"></p>
        </div>
        <input type="file" name="photos[]" id="photoFileInput" multiple accept="image/*" style="display:none;">

        <div class="upload-preview-grid"></div>

        <button type="submit" class="btn btn-primary btn-block" style="margin-top:20px;">
            <i class="fa-solid fa-upload"></i> رفع الصور المختارة
        </button>
    </form>

    <?php if (!empty($uploadErrors)): ?>
        <div class="alert alert-danger" style="margin-top:18px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?php foreach ($uploadErrors as $err) echo e($err) . '<br>'; ?></div>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">صور الألبوم</span>
        <span style="font-size:0.82rem;color:var(--color-silver-muted);"><i class="fa-solid fa-hand-pointer"></i> اسحب الصور لإعادة ترتيبها</span>
    </div>

    <?php if (empty($photos)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-image"></i>
            <h3>لا توجد صور بعد</h3>
            <p>ابدأ برفع الصور باستخدام النموذج أعلاه</p>
        </div>
    <?php else: ?>
        <div class="manage-photos-grid" data-sortable>
            <?php foreach ($photos as $photo): ?>
                <div class="manage-photo-item" data-photo-id="<?= (int) $photo['id'] ?>">
                    <img src="<?= UPLOAD_URL . e($photo['thumbnail'] ?: $photo['filename']) ?>" alt="">
                    <?php if ($photo['is_cover']): ?>
                        <span class="cover-star-badge" title="صورة الغلاف"><i class="fa-solid fa-star"></i></span>
                    <?php endif; ?>
                    <div class="manage-photo-overlay">
                        <div class="manage-photo-top">
                            <span style="color:#fff;font-size:0.75rem;background:rgba(0,0,0,0.5);padding:3px 8px;border-radius:20px;">
                                <?= formatFileSize((int) $photo['file_size']) ?>
                            </span>
                        </div>
                        <div class="manage-photo-bottom">
                            <?php if (!$photo['is_cover']): ?>
                            <a href="?id=<?= $albumId ?>&set_cover=<?= (int) $photo['id'] ?>" class="action-icon-btn edit btn-icon" title="تعيين كغلاف" style="width:32px;height:32px;">
                                <i class="fa-solid fa-star"></i>
                            </a>
                            <?php endif; ?>
                            <a href="?id=<?= $albumId ?>&delete_photo=<?= (int) $photo['id'] ?>" class="action-icon-btn delete btn-icon" title="حذف" style="width:32px;height:32px;"
                               data-confirm-delete="هل تريد حذف هذه الصورة نهائياً؟">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div style="text-align:center;margin-top:20px;">
    <a href="albums.php" class="btn btn-outline"><i class="fa-solid fa-arrow-right"></i> العودة لقائمة الألبومات</a>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
