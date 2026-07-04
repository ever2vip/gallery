<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/Album.php';

requireAdminLogin();
requirePermission('manage_albums');

$albumModel = new Album();
$db = Database::getInstance();

$albumId = (int) ($_GET['id'] ?? 0);
$album = $albumId ? $albumModel->getById($albumId) : null;
$isEdit = $album !== null;

if ($albumId && !$album) {
    redirectWithMessage('albums.php', 'الألبوم المطلوب غير موجود', 'danger');
}

$categories = $db->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'حدث خطأ في التحقق من الطلب، الرجاء المحاولة مرة أخرى';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        $removePassword = isset($_POST['remove_password']);

        if (empty($title)) {
            $errors[] = 'عنوان الألبوم مطلوب';
        }

        if (empty($errors)) {
            $data = [
                'title' => $title,
                'description' => $description,
                'category_id' => $categoryId ?: null,
                'is_published' => $isPublished,
                'is_featured' => $isFeatured,
            ];

            if (!empty($password)) {
                $data['password'] = $password;
            } elseif ($removePassword) {
                $data['password'] = '';
                $data['remove_password'] = true;
            }

            if ($isEdit) {
                $albumModel->update($albumId, $data);
                logActivity('update_album', 'تعديل الألبوم: ' . $title);
                redirectWithMessage('album-photos.php?id=' . $albumId, 'تم تحديث الألبوم بنجاح، يمكنك الآن إضافة الصور');
            } else {
                $newId = $albumModel->create($data);
                logActivity('create_album', 'إنشاء ألبوم جديد: ' . $title);
                redirectWithMessage('album-photos.php?id=' . $newId, 'تم إنشاء الألبوم بنجاح، الآن قم بإضافة الصور');
            }
        }
    }
}

$pageTitle = $isEdit ? 'تعديل ألبوم' : 'إنشاء ألبوم جديد';
$breadcrumb = 'الرئيسية / الألبومات / ' . $pageTitle;
require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div><?php foreach ($errors as $err) echo e($err) . '<br>'; ?></div>
    </div>
<?php endif; ?>

<form method="post">
    <?= csrfField() ?>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
        <div>
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="fa-solid fa-circle-info" style="color:var(--color-gold-light);margin-left:8px;"></i>معلومات الألبوم</span>
                </div>

                <div class="form-group">
                    <label class="form-label">عنوان الألبوم <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" required
                           value="<?= e($album['title'] ?? $_POST['title'] ?? '') ?>" placeholder="مثال: حفل زفاف أحمد وسارة">
                </div>

                <div class="form-group">
                    <label class="form-label">الوصف</label>
                    <textarea name="description" class="form-control" placeholder="وصف مختصر عن الألبوم..."><?= e($album['description'] ?? $_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">التصنيف</label>
                    <select name="category_id" class="form-control">
                        <option value="">بدون تصنيف</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= (($album['category_id'] ?? 0) == $cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="fa-solid fa-shield-halved" style="color:var(--color-gold-light);margin-left:8px;"></i>الحماية بكلمة مرور</span>
                </div>

                <?php if ($isEdit && $album['is_protected']): ?>
                    <div class="alert alert-info" style="margin-bottom:18px;">
                        <i class="fa-solid fa-lock"></i>
                        <span>هذا الألبوم محمي حالياً بكلمة مرور. اترك الحقل فارغاً للإبقاء عليها، أو أدخل كلمة جديدة لتغييرها.</span>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label"><?= ($isEdit && $album['is_protected']) ? 'تغيير كلمة المرور' : 'كلمة مرور الألبوم (اختياري)' ?></label>
                    <div class="password-input-wrapper" style="position:relative;">
                        <input type="password" name="password" class="form-control" placeholder="اتركه فارغاً لعدم الحماية" style="padding-left:46px;">
                        <button type="button" class="password-toggle" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--color-silver-muted);cursor:pointer;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <p class="form-hint">عند تعيين كلمة مرور، سيُطلب من الزوار إدخالها لمشاهدة محتوى الألبوم</p>
                </div>

                <?php if ($isEdit && $album['is_protected']): ?>
                <div class="checkbox-wrapper" style="margin-top:14px;">
                    <input type="checkbox" name="remove_password" id="removePassword">
                    <label for="removePassword">إزالة الحماية بكلمة المرور نهائياً عن هذا الألبوم</label>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">إعدادات النشر</span>
                </div>
                <div class="toggle-label-row">
                    <div class="toggle-label-text">
                        <strong>نشر الألبوم</strong>
                        <span>ظهور الألبوم للزوار</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_published" <?= (!$isEdit || $album['is_published']) ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-label-row">
                    <div class="toggle-label-text">
                        <strong>ألبوم مميز</strong>
                        <span>يظهر في قسم المميزة بالرئيسية</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_featured" <?= (!empty($album['is_featured'])) ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="card">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-check"></i> <?= $isEdit ? 'حفظ التعديلات' : 'إنشاء الألبوم والمتابعة لإضافة الصور' ?>
                </button>
                <a href="albums.php" class="btn btn-outline btn-block" style="margin-top:10px;">
                    <i class="fa-solid fa-xmark"></i> إلغاء
                </a>
            </div>

            <?php if ($isEdit): ?>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">رابط الألبوم</span>
                </div>
                <div style="display:flex;gap:8px;">
                    <input type="text" class="form-control" readonly value="<?= e(SITE_URL . '/album.php?slug=' . $album['slug']) ?>" id="albumLink">
                    <button type="button" class="btn btn-dark btn-icon" onclick="copyToClipboard(document.getElementById('albumLink').value, this)">
                        <i class="fa-solid fa-copy"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
