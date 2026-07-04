<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

requireAdminLogin();
$currentAdmin = getCurrentAdmin();
$db = Database::getInstance();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'حدث خطأ في التحقق من الطلب';
    } else {
        $formType = $_POST['form_type'] ?? '';

        if ($formType === 'profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $avatarFilename = $currentAdmin['avatar'];

            if (!empty($_FILES['avatar']['name'])) {
                $file = $_FILES['avatar'];
                if ($file['error'] === UPLOAD_ERR_OK && $file['size'] <= MAX_FILE_SIZE) {
                    $imageInfo = @getimagesize($file['tmp_name']);
                    if ($imageInfo !== false && isValidImageExtension($file['name'])) {
                        $avatarDir = UPLOAD_DIR . 'avatars/';
                        if (!is_dir($avatarDir)) {
                            mkdir($avatarDir, 0755, true);
                        }
                        $newFilename = 'avatars/' . generateUniqueFilename($file['name']);
                        if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newFilename)) {
                            $avatarFilename = $newFilename;
                        } else {
                            $errors[] = 'فشل رفع الصورة الشخصية';
                        }
                    } else {
                        $errors[] = 'ملف الصورة الشخصية غير صالح';
                    }
                } else {
                    $errors[] = 'حجم الصورة الشخصية أكبر من الحد المسموح (10 ميجابايت)';
                }
            }

            if (empty($errors)) {
                $stmt = $db->prepare('UPDATE admins SET full_name = ?, email = ?, avatar = ? WHERE id = ?');
                $stmt->execute([$fullName, $email, $avatarFilename, $currentAdmin['id']]);
                logActivity('update_profile', 'تحديث الملف الشخصي');
                redirectWithMessage('profile.php', 'تم تحديث ملفك الشخصي بنجاح');
            }
        }

        if ($formType === 'password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!password_verify($currentPassword, $currentAdmin['password'])) {
                $errors[] = 'كلمة المرور الحالية غير صحيحة';
            } elseif (strlen($newPassword) < 8) {
                $errors[] = 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'كلمة المرور الجديدة وتأكيدها غير متطابقين';
            }

            if (empty($errors)) {
                $stmt = $db->prepare('UPDATE admins SET password = ? WHERE id = ?');
                $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $currentAdmin['id']]);
                logActivity('change_password', 'تغيير كلمة المرور الخاصة');
                redirectWithMessage('profile.php', 'تم تغيير كلمة المرور بنجاح');
            }
        }
    }
}

$pageTitle = 'الملف الشخصي';
$breadcrumb = 'الرئيسية / الملف الشخصي';
require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div><?php foreach ($errors as $err) echo e($err) . '<br>'; ?></div>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fa-solid fa-id-card" style="color:var(--color-gold-light);margin-left:8px;"></i>المعلومات الشخصية</span></div>
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="form_type" value="profile">

            <div class="avatar-upload">
                <span class="avatar-preview" id="avatarPreview">
                    <?php if ($currentAdmin['avatar']): ?>
                        <img src="<?= UPLOAD_URL . e($currentAdmin['avatar']) ?>" alt="">
                    <?php else: ?>
                        <?= e(mb_substr($currentAdmin['full_name'] ?: $currentAdmin['username'], 0, 1)) ?>
                    <?php endif; ?>
                </span>
                <div>
                    <label class="btn btn-outline btn-sm" style="cursor:pointer;">
                        <i class="fa-solid fa-camera"></i> تغيير الصورة
                        <input type="file" name="avatar" accept="image/*" style="display:none;" data-preview-target="avatarPreview">
                    </label>
                    <p class="form-hint">JPG, PNG - حتى 10 ميجابايت</p>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">اسم المستخدم</label>
                <input type="text" class="form-control" value="<?= e($currentAdmin['username']) ?>" disabled dir="ltr">
                <p class="form-hint">لا يمكن تغيير اسم المستخدم</p>
            </div>
            <div class="form-group">
                <label class="form-label">الاسم الكامل</label>
                <input type="text" name="full_name" class="form-control" value="<?= e($currentAdmin['full_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">البريد الإلكتروني</label>
                <input type="email" name="email" class="form-control" dir="ltr" value="<?= e($currentAdmin['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> حفظ التعديلات</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fa-solid fa-key" style="color:var(--color-gold-light);margin-left:8px;"></i>تغيير كلمة المرور</span></div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="form_type" value="password">

            <div class="form-group">
                <label class="form-label">كلمة المرور الحالية</label>
                <div class="password-input-wrapper" style="position:relative;">
                    <input type="password" name="current_password" class="form-control" required style="padding-left:46px;">
                    <button type="button" class="password-toggle" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--color-silver-muted);cursor:pointer;"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور الجديدة</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
            </div>
            <div class="form-group">
                <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-lock"></i> تحديث كلمة المرور</button>
        </form>

        <div style="margin-top:20px;padding:16px;border-radius:14px;background:rgba(var(--gold-rgb),0.05);display:flex;gap:12px;">
            <i class="fa-solid fa-shield-halved" style="color:var(--color-gold-light);font-size:1.3rem;"></i>
            <div>
                <strong style="font-size:0.9rem;">نصيحة أمان</strong>
                <p style="font-size:0.82rem;color:var(--color-silver-muted);margin-top:4px;">استخدم كلمة مرور قوية وفريدة، ولا تشاركها مع أي شخص آخر.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
