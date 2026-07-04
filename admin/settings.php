<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

requireAdminLogin();
requirePermission('manage_settings');

$db = Database::getInstance();
$errors = [];
$successMessage = '';

// 1. معالجة حفظ الإعدادات عند إرسال الفورم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'حدث خطأ في التحقق من الطلب، الرجاء المحاولة مرة أخرى';
    } else {
        // مصفوفة الحقول النصية العادية المراد تحديثها
        $settingsToUpdate = [
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 
            'smtp_encryption', 'smtp_from_email', 'smtp_from_name',
            'about_text_ar', 'about_text_en', 'contact_email', 'site_title'
        ];

        foreach ($settingsToUpdate as $key) {
            if (isset($_POST[$key])) {
                $value = trim($_POST[$key]);
                // تحديث قاعدة البيانات (يفترض وجود جدول site_settings بحقول setting_key و setting_value)
                $stmt = $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
        }

        // --- معالجة رفع الخط المخصص ---
        if (isset($_FILES['custom_font']) && $_FILES['custom_font']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['custom_font']['tmp_name'];
            $fileName = $_FILES['custom_font']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // الامتدادات المسموحة للخطوط
            $allowedExtensions = ['ttf', 'woff', 'woff2'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $fontsDir = __DIR__ . '/../assets/fonts/';
                
                // إنشاء المجلد إذا لم يكن موجوداً
                if (!is_dir($fontsDir)) {
                    mkdir($fontsDir, 0755, true);
                }

                $newFontName = 'site_font_' . time() . '.' . $fileExtension;
                $destPath = $fontsDir . $newFontName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    // تحديث اسم الخط في الإعدادات
                    $stmt = $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'custom_font_family'");
                    $stmt->execute([$newFontName]);
                    logActivity('update_settings', 'تم رفع خط مخصص للموقع: ' . $fileName);
                } else {
                    $errors[] = 'حدث خطأ أثناء نقل ملف الخط إلى الخادم.';
                }
            } else {
                $errors[] = 'نوع الملف غير مدعوم. يرجى رفع خط بصيغة TTF أو WOFF أو WOFF2 فقط.';
            }
        }

        if (empty($errors)) {
            logActivity('update_settings', 'تحديث إعدادات الموقع العامة والـ SMTP');
            $successMessage = 'تم حفظ جميع الإعدادات وتحديثها بنجاح!';
        }
    }
}

// 2. جلب الإعدادات الحالية لعرضها في الفراغات
$settingsData = $db->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'إعدادات السكربت المتقدمة';
$breadcrumb = 'الرئيسية / الإعدادات';
require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div><?php foreach ($errors as $err) echo e($err) . '<br>'; ?></div>
    </div>
<?php endif; ?>

<?php if ($successMessage): ?>
    <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <span><?= e($successMessage) ?></span>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?= csrfField() ?>
    
    <div style="display:grid;grid-template-columns: 1fr 1fr; gap:24px; margin-bottom: 24px;">
        
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa-solid fa-server" style="color:var(--color-gold-light);margin-left:8px;"></i>إعدادات خادم البريد (SMTP)</span>
            </div>
            <div class="form-group">
                <label class="form-label">خادم SMTP (Host)</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= e($settingsData['smtp_host'] ?? '') ?>" placeholder="mail.yourdomain.com">
            </div>
            <div class="form-group">
                <label class="form-label">منفذ SMTP (Port)</label>
                <input type="text" name="smtp_port" class="form-control" value="<?= e($settingsData['smtp_port'] ?? '587') ?>" placeholder="587 أو 465">
            </div>
            <div class="form-group">
                <label class="form-label">اسم المستخدم (Username)</label>
                <input type="email" name="smtp_username" class="form-control" value="<?= e($settingsData['smtp_username'] ?? '') ?>" placeholder="info@yourdomain.com">
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور (Password)</label>
                <input type="password" name="smtp_password" class="form-control" value="<?= e($settingsData['smtp_password'] ?? '') ?>" placeholder="••••••••">
            </div>
            <div class="form-group">
                <label class="form-label">التشفير (Encryption)</label>
                <select name="smtp_encryption" class="form-control">
                    <option value="tls" <?= ($settingsData['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS (موصى به)</option>
                    <option value="ssl" <?= ($settingsData['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="none" <?= ($settingsData['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>بدون تشفير</option>
                </select>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">بريد المرسل الافتراضي</label>
                    <input type="email" name="smtp_from_email" class="form-control" value="<?= e($settingsData['smtp_from_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">اسم المرسل الافتراضي</label>
                    <input type="text" name="smtp_from_name" class="form-control" value="<?= e($settingsData['smtp_from_name'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa-solid fa-font" style="color:var(--color-gold-light);margin-left:8px;"></i>المظهر والخط المخصص</span>
            </div>
            <div class="form-group">
                <label class="form-label">عنوان الموقع الافتراضي</label>
                <input type="text" name="site_title" class="form-control" value="<?= e($settingsData['site_title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">رفع خط مخصص لشعار الموقع (Font)</label>
                <input type="file" name="custom_font" class="form-control" accept=".ttf,.woff,.woff2" style="padding:10px;">
                <p class="form-hint">ارفع ملف خط بصيغة TTF أو WOFF لتغيير طريقة عرض اسم المعرض في الهيدر بشكل مميز.</p>
                <?php if (!empty($settingsData['custom_font_family']) && $settingsData['custom_font_family'] !== 'Default'): ?>
                    <div class="alert alert-info py-1 px-2 mt-2 small" style="display:inline-block;">
                        <i class="fa-solid fa-circle-info"></i> هناك خط مخصص مفعل حالياً: <code><?= e($settingsData['custom_font_family']) ?></code>
                    </div>
                <?php endif; ?>
            </div>
            <hr style="border-color:#eee; margin:20px 0;">
            <div class="card-header" style="padding:0; margin-bottom:12px;">
                <span class="card-title"><i class="fa-solid fa-envelope-open-text" style="color:var(--color-gold-light);margin-left:8px;"></i>بريد تواصل معنا</span>
            </div>
            <div class="form-group">
                <label class="form-label">البريد الإلكتروني لاستقبال رسائل الزوار</label>
                <input type="email" name="contact_email" class="form-control" value="<?= e($settingsData['contact_email'] ?? '') ?>" placeholder="admin@domain.com">
                <p class="form-hint">هذا هو البريد الذي ستصل إليه رسائل نموذج "اتصل بنا".</p>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-file-pen" style="color:var(--color-gold-light);margin-left:8px;"></i>إدارة محتوى صفحة (من نحن - About Us)</span>
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px;">
            <div class="form-group">
                <label class="form-label"><strong style="color:#0d6efd;">النص باللغة العربية:</strong></label>
                <textarea name="about_text_ar" class="form-control" rows="8" placeholder="اكتب معلومات عن معرض الصور هنا..."><?= e($settingsData['about_text_ar'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label"><strong style="color:#20c997;">Content in English:</strong></label>
                <textarea name="about_text_en" class="form-control" rows="8" placeholder="Write about your gallery in English here..."><?= e($settingsData['about_text_en'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div style="text-align: left;">
        <button type="submit" class="btn btn-primary" style="padding: 12px 40px; font-size: 1rem;">
            <i class="fa-solid fa-floppy-disk"></i> حفظ كافة الإعدادات المتقدمة
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
