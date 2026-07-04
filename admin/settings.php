<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

requireAdminLogin();
requirePermission('manage_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        redirectWithMessage('settings.php', 'حدث خطأ في التحقق من الطلب', 'danger');
    }

    $textFields = ['site_title', 'site_description', 'footer_text', 'items_per_page', 'max_login_attempts', 'lockout_minutes'];
    foreach ($textFields as $field) {
        if (isset($_POST[$field])) {
            updateSetting($field, trim($_POST[$field]));
        }
    }

    $toggleFields = ['allow_comments', 'allow_downloads', 'watermark_enabled'];
    foreach ($toggleFields as $field) {
        updateSetting($field, isset($_POST[$field]) ? '1' : '0');
    }

    logActivity('update_settings', 'تحديث إعدادات الموقع العامة');
    redirectWithMessage('settings.php', 'تم حفظ الإعدادات بنجاح');
}

$pageTitle = 'إعدادات الموقع';
$breadcrumb = 'الرئيسية / الإعدادات';
require_once __DIR__ . '/includes/admin-header.php';
?>

<form method="post">
    <?= csrfField() ?>
    <div class="card">
        <div class="tabs">
            <button type="button" class="tab-btn active" data-tab="tabGeneral">عام</button>
            <button type="button" class="tab-btn" data-tab="tabFeatures">الميزات</button>
            <button type="button" class="tab-btn" data-tab="tabSecurity">الأمان</button>
        </div>

        <div id="tabGeneral" class="tab-content active">
            <div class="form-group">
                <label class="form-label">اسم الموقع</label>
                <input type="text" name="site_title" class="form-control" value="<?= e(getSetting('site_title')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">وصف الموقع</label>
                <textarea name="site_description" class="form-control"><?= e(getSetting('site_description')) ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">نص الفوتر</label>
                <input type="text" name="footer_text" class="form-control" value="<?= e(getSetting('footer_text')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">عدد الألبومات في الصفحة الواحدة</label>
                <input type="number" name="items_per_page" class="form-control" value="<?= e(getSetting('items_per_page', '12')) ?>" min="4" max="48">
            </div>
        </div>

        <div id="tabFeatures" class="tab-content">
            <div class="toggle-label-row">
                <div class="toggle-label-text"><strong>السماح بالتعليقات</strong><span>يمكن للزوار ترك تعليقات على الصور (يتطلب موافقة المشرف)</span></div>
                <label class="toggle-switch">
                    <input type="checkbox" name="allow_comments" <?= getSetting('allow_comments') === '1' ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="toggle-label-row">
                <div class="toggle-label-text"><strong>السماح بتحميل الصور</strong><span>إظهار خيار تحميل الصورة الأصلية للزوار</span></div>
                <label class="toggle-switch">
                    <input type="checkbox" name="allow_downloads" <?= getSetting('allow_downloads', '1') === '1' ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="toggle-label-row">
                <div class="toggle-label-text"><strong>العلامة المائية</strong><span>إضافة علامة مائية نصية تلقائياً على الصور الجديدة</span></div>
                <label class="toggle-switch">
                    <input type="checkbox" name="watermark_enabled" <?= getSetting('watermark_enabled') === '1' ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <div id="tabSecurity" class="tab-content">
            <div class="form-group">
                <label class="form-label">الحد الأقصى لمحاولات كلمة المرور الخاطئة</label>
                <input type="number" name="max_login_attempts" class="form-control" value="<?= e(getSetting('max_login_attempts', '5')) ?>" min="3" max="20">
                <p class="form-hint">يُطبّق على محاولات فتح الألبومات المحمية بكلمة مرور</p>
            </div>
            <div class="form-group">
                <label class="form-label">مدة الحظر بعد تجاوز المحاولات (بالدقائق)</label>
                <input type="number" name="lockout_minutes" class="form-control" value="<?= e(getSetting('lockout_minutes', '15')) ?>" min="1" max="1440">
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> حفظ كل الإعدادات</button>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
