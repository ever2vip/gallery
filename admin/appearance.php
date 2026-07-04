<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

requireAdminLogin();
requirePermission('manage_settings');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'حدث خطأ في التحقق من الطلب';
    } else {
        $formType = $_POST['form_type'] ?? '';

        if ($formType === 'theme') {
            $theme = $_POST['accent_theme'] ?? 'gold';
            if (array_key_exists($theme, getAvailableAccentThemes())) {
                updateSetting('accent_theme', $theme);
                logActivity('update_appearance', 'تغيير ثيم الألوان إلى: ' . $theme);
                redirectWithMessage('appearance.php', 'تم تحديث ألوان الموقع بنجاح');
            }
        }

        if ($formType === 'logo') {
            if (!empty($_FILES['logo']['name'])) {
                $file = $_FILES['logo'];
                if ($file['error'] === UPLOAD_ERR_OK && $file['size'] <= MAX_FILE_SIZE) {
                    $imageInfo = @getimagesize($file['tmp_name']);
                    if ($imageInfo !== false && isValidImageExtension($file['name'])) {
                        $logoDir = UPLOAD_DIR . 'branding/';
                        if (!is_dir($logoDir)) mkdir($logoDir, 0755, true);
                        $newFilename = 'branding/' . generateUniqueFilename($file['name']);
                        if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newFilename)) {
                            updateSetting('site_logo', $newFilename);
                            logActivity('update_appearance', 'تحديث شعار الموقع');
                            redirectWithMessage('appearance.php', 'تم تحديث شعار الموقع بنجاح');
                        } else {
                            $errors[] = 'فشل رفع ملف الشعار';
                        }
                    } else {
                        $errors[] = 'ملف الشعار غير صالح';
                    }
                } else {
                    $errors[] = 'حجم ملف الشعار أكبر من الحد المسموح';
                }
            }
        }

        if ($formType === 'remove_logo') {
            updateSetting('site_logo', '');
            logActivity('update_appearance', 'إزالة شعار الموقع');
            redirectWithMessage('appearance.php', 'تم إزالة الشعار، سيتم استخدام الأيقونة الافتراضية');
        }
    }
}

$currentTheme = getSetting('accent_theme', 'gold');
$currentLogo = getSetting('site_logo', '');
$themes = getAvailableAccentThemes();

$pageTitle = 'المظهر والتصميم';
$breadcrumb = 'الرئيسية / المظهر';
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
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-image" style="color:var(--color-gold-light);margin-left:8px;"></i>شعار الموقع</span>
        </div>

        <div class="avatar-upload">
            <span class="avatar-preview" style="border-radius:14px;width:100px;height:100px;">
                <?php if ($currentLogo): ?>
                    <img src="<?= UPLOAD_URL . e($currentLogo) ?>" alt="الشعار الحالي">
                <?php else: ?>
                    <i class="fa-solid fa-camera-retro"></i>
                <?php endif; ?>
            </span>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <form method="post" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_type" value="logo">
                    <label class="btn btn-outline btn-sm" style="cursor:pointer;">
                        <i class="fa-solid fa-upload"></i> رفع شعار جديد
                        <input type="file" name="logo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                    </label>
                </form>
                <?php if ($currentLogo): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_type" value="remove_logo">
                    <button type="button" class="btn btn-outline btn-sm" style="color:#f27784;" data-confirm-delete="هل تريد إزالة شعار الموقع والعودة للأيقونة الافتراضية؟">
                        <i class="fa-solid fa-trash"></i> إزالة الشعار
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <p class="form-hint">يُفضّل استخدام صورة مربعة بخلفية شفافة (PNG) لأفضل مظهر</p>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-palette" style="color:var(--color-gold-light);margin-left:8px;"></i>لوحة الألوان</span>
        </div>
        <p class="form-hint" style="margin-bottom:18px;">اختر النمط اللوني المعتمد على الأسود مع لمسة من الألوان التالية</p>

        <form method="post" id="themeForm">
            <?= csrfField() ?>
            <input type="hidden" name="form_type" value="theme">
            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                <?php foreach ($themes as $key => $theme): ?>
                    <label style="text-align:center;cursor:pointer;">
                        <input type="radio" name="accent_theme" value="<?= e($key) ?>" onchange="this.form.submit()"
                               <?= $currentTheme === $key ? 'checked' : '' ?> style="display:none;">
                        <span class="color-swatch <?= $currentTheme === $key ? 'selected' : '' ?>"
                              style="background: <?= e($theme['swatch']) ?>;display:block;margin:0 auto 8px;"></span>
                        <span style="font-size:0.82rem;color:var(--color-silver-dim);"><?= e($theme['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-eye" style="color:var(--color-gold-light);margin-left:8px;"></i>معاينة</span>
    </div>
    <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;">
        <button type="button" class="btn btn-primary">زر أساسي</button>
        <button type="button" class="btn btn-outline">زر ثانوي</button>
        <span class="badge badge-gold"><i class="fa-solid fa-star"></i> شارة مميزة</span>
        <span class="gold-text-static" style="font-size:1.3rem;font-weight:800;">نص بلون التمييز</span>
    </div>
</div>

<style>.gold-text-static { background: var(--gradient-gold); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }</style>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
