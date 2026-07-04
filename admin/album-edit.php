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
        $removeEmailProtection = isset($_POST['remove_email_protection']);

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

            // تحديد الـ ID الحالي للتعامل مع الإدخال
            $currentAlbumId = $albumId;

            if ($isEdit) {
                $albumModel->update($albumId, $data);
                logActivity('update_album', 'تعديل الألبوم: ' . $title);
            } else {
                $currentAlbumId = $albumModel->create($data);
                logActivity('create_album', 'إنشاء ألبوم جديد: ' . $title);
            }

            // --- معالجة حماية الألبوم بملف الإكسيل (CSV) ---
            if (isset($_FILES['allowed_emails_file']) && $_FILES['allowed_emails_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['allowed_emails_file']['tmp_name'];
                $fileName = $_FILES['allowed_emails_file']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileExtension === 'csv') {
                    // 1. تنظيف الإيميلات القديمة المرتبطة بهذا الألبوم
                    $db->prepare("DELETE FROM album_allowed_emails WHERE album_id = ?")->execute([$currentAlbumId]);

                    // 2. قراءة ملف الـ CSV وزرع الإيميلات
                    if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
                        $insert_stmt = $db->prepare("INSERT IGNORE INTO album_allowed_emails (album_id, email) VALUES (?, ?)");
                        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            $email = trim($row[0]);
                            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $insert_stmt->execute([$currentAlbumId, $email]);
                            }
                        }
                        fclose($handle);

                        // 3. تحديث حالة الألبوم ليكون محمياً
                        $db->prepare("UPDATE albums SET is_protected = 1 WHERE id = ?")->execute([$currentAlbumId]);
                    }
                } else {
                    $errors[] = 'الملف المرفوع غير صالح، يجب رفع ملف بتنسيق CSV فقط.';
                }
            }

            // خيار إزالة حماية الإيميلات نهائياً
            if ($isEdit && $removeEmailProtection) {
                $db->prepare("DELETE FROM album_allowed_emails WHERE album_id = ?")->execute([$albumId]);
                $db->prepare("UPDATE albums SET is_protected = 0 WHERE id = ?")->execute([$albumId]);
            }

            // توجيه المستخدم بعد نجاح العملية
            if (empty($errors)) {
                if ($isEdit) {
                    redirectWithMessage('album-photos.php?id=' . $albumId, 'تم تحديث الألبوم بنجاح، يمكنك الآن إدارة الصور');
                } else {
                    redirectWithMessage('album-photos.php?id=' . $currentAlbumId, 'تم إنشاء الألبوم بنجاح، الآن قم بإضافة الصور');
                }
            }
        }
    }
}

// التحقق من حالة حماية الإيميلات الحالية للألبوم في وضع التعديل
$hasEmailProtection = false;
if ($isEdit) {
    $checkEmails = $db->prepare("SELECT COUNT(*) FROM album_allowed_emails WHERE album_id = ?");
    $checkEmails->execute([$albumId]);
    $hasEmailProtection = (bool) $checkEmails->fetchColumn();
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

<form method="post" enctype="multipart/form-data">
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
                    <span class="card-title"><i class="fa-solid fa-file-excel" style="color:#1f7246;margin-left:8px;"></i>الحماية المتقدمة بقائمة الإيميلات</span>
                </div>

                <?php if ($isEdit && $hasEmailProtection): ?>
                    <div class="alert alert-success" style="margin-bottom:18px;">
                        <i class="fa-solid fa-user-shield"></i>
                        <span>هذا الألبوم محمي حالياً بواسطة قائمة بريدية خاصة. رفع ملف جديد سيستبدل القائمة القديمة.</span>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">رفع قائمة الإيميلات المسموحة (ملف إكسيل بصيغة CSV)</label>
                    <input type="file" name="allowed_emails_file" class="form-control" accept=".csv" style="padding: 10px;">
                    <p class="form-hint">يجب أن يحتوي الملف على عمود واحد يضم البريد الإلكتروني للأشخاص المصرح لهم فقط بدخول الألبوم.</p>
                </div>

                <?php if ($isEdit && $hasEmailProtection): ?>
                <div class="checkbox-wrapper" style="margin-top:14px;">
                    <input type="checkbox" name="remove_email_protection" id="removeEmailProtection">
                    <label for="removeEmailProtection" style="color: var(--color-danger, #dc3545); font-weight: bold;">إزالة الحماية بالقائمة البريدية نهائياً عن هذا الألبوم</label>
                </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="fa-solid fa-shield-halved" style="color:var(--color-gold-light);margin-left:8px;"></i>الحماية التقليدية بكلمة مرور</span>
                </div>

                <?php if ($isEdit && isset($album['password']) && !empty($album['password'])): ?>
                    <div class="alert alert-info" style="margin-bottom:18px;">
                        <i class="fa-solid fa-lock"></i>
                        <span>هذا الألبوم محمي حالياً بكلمة مرور. اترك الحقل فارغاً للإبقاء عليها، أو أدخل كلمة جديدة لتغييرها.</span>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label"><?= ($isEdit && !empty($album['password'])) ? 'تغيير كلمة المرور' : 'كلمة مرور الألبوم (اختياري)' ?></label>
                    <div class="password-input-wrapper" style="position:relative;">
                        <input type="password" name="password" class="form-control" placeholder="اتركه فارغاً لعدم الحماية" style="padding-left:46px;">
                        <button type="button" class="password-toggle" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--color-silver-muted);cursor:pointer;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <p class="form-hint">عند تعيين كلمة مرور، سيُطلب من الزوار إدخالها لمشاهدة محتوى الألبوم (تنويه: حماية الإيميلات تلغي حماية كلمة المرور تلقائياً)</p>
                </div>

                <?php if ($isEdit && !empty($album['password'])): ?>
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
